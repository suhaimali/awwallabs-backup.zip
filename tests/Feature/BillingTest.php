<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function patientPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Ravi', 'last_name' => 'M', 'gender' => 'Male', 'age' => 41, 'age_type' => 'Years',
            'phone' => '9876543210', 'status' => 'Active', 'payment_method' => 'UPI',
            'test_name' => ['CBC', 'Lipid Profile'], 'test_price' => [300, 700], 'test_discount' => [0, 100],
            'advance_paid' => 500,
        ], $overrides);
    }

    public function test_add_patient_creates_one_bill_linked_to_its_tests(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/patients/store', $this->patientPayload())->assertOk();

        $patient = Patient::firstOrFail();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}$/', $patient->patient_id);
        $this->assertEquals(1000, $patient->total_amount);
        $this->assertEquals(400, $patient->balance);
        $this->assertNotNull($patient->public_token);

        $payment = Payment::firstOrFail();
        $this->assertSame('Partial', $payment->payment_status);
        $this->assertSame('UPI', $payment->payment_method);
        $this->assertEquals(900, $payment->net_amount);
        $this->assertEquals(2, Appointment::where('payment_id', $payment->id)->count());
    }

    public function test_patient_numbers_do_not_collide(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/patients/store', $this->patientPayload(['patient_id' => '2026-0001', 'test_name' => []]))->assertOk();
        // The form's pre-filled number is stale: the server must pick the next free one.
        $this->actingAs($user)->postJson('/patients/store', $this->patientPayload(['patient_id' => '2026-0001', 'test_name' => []]))->assertOk();

        $this->assertEquals(2, Patient::distinct()->count('patient_id'));
    }

    public function test_editing_a_patient_does_not_touch_other_visits_bills(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/patients/store', $this->patientPayload())->assertOk();
        $patient = Patient::firstOrFail();

        // Second visit booked from the appointments page.
        $this->actingAs($user)->postJson('/appointments/store', [
            'patient_id' => $patient->id, 'test_name' => 'TSH', 'test_price' => 400, 'discount' => 0,
            'advance_paid' => 400, 'appointment_date' => now()->toDateString(), 'appointment_time' => '09:30',
            'status' => 'Pending', 'payment_method' => 'Cash',
        ])->assertOk();

        $this->assertEquals(2, Payment::count());
        $tshBill = Payment::latest('id')->first();
        $this->assertSame('Paid', $tshBill->payment_status);
        $this->assertEquals(400, $tshBill->net_amount);

        // Edit the patient: drop the Lipid Profile, keep everything else, pay in full.
        $apps = $patient->appointments()->orderBy('id')->get();
        $this->actingAs($user)->postJson('/patients/update/' . $patient->id, $this->patientPayload([
            'patient_id' => $patient->patient_id,
            'appointment_id' => [$apps[0]->id, $apps[2]->id],
            'test_name' => ['CBC', 'TSH'], 'test_price' => [300, 400], 'test_discount' => [0, 0],
            'advance_paid' => 700,
        ]))->assertOk();

        $patient->refresh();
        $this->assertEquals(700, $patient->total_amount);
        $this->assertEquals(0, $patient->balance);

        $firstBill = Payment::oldest('id')->first();
        $this->assertEquals(300, $firstBill->net_amount, 'first bill only holds CBC now');
        $this->assertSame('Paid', $firstBill->payment_status);
        $this->assertEquals(400, $tshBill->fresh()->net_amount, 'TSH bill keeps its own total');
        $this->assertEquals(2, Payment::count());
    }

    public function test_removing_all_tests_of_a_bill_removes_the_bill(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/patients/store', $this->patientPayload())->assertOk();
        $patient = Patient::firstOrFail();

        $this->actingAs($user)->postJson('/patients/update/' . $patient->id, $this->patientPayload([
            'patient_id' => $patient->patient_id, 'test_name' => [], 'advance_paid' => 0,
        ]))->assertOk();

        $this->assertEquals(0, Payment::count());
        $this->assertEquals(0, $patient->fresh()->total_amount);
    }

    public function test_deleting_an_appointment_updates_its_bill(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/patients/store', $this->patientPayload(['advance_paid' => 0]))->assertOk();
        $appointment = Appointment::where('test_name', 'Lipid Profile')->firstOrFail();

        $this->actingAs($user)->deleteJson('/appointments/' . $appointment->id)->assertOk();

        $this->assertEquals(300, Payment::firstOrFail()->net_amount);
        $this->assertEquals(300, Patient::firstOrFail()->balance);
    }

    public function test_updating_an_appointment_recalculates_its_bill_only(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/patients/store', $this->patientPayload(['advance_paid' => 0]))->assertOk();
        $appointment = Appointment::where('test_name', 'CBC')->firstOrFail();

        $this->actingAs($user)->postJson('/appointments/update/' . $appointment->id, [
            'patient_id' => $appointment->patient_id, 'doctor_name' => 'Self', 'test_name' => 'CBC',
            'test_price' => 350, 'discount' => 50, 'advance_paid' => 300,
            'appointment_date' => now()->toDateString(), 'appointment_time' => '11:00', 'status' => 'Pending',
        ])->assertOk();

        $bill = Payment::firstOrFail();
        $this->assertEquals(1050, $bill->total_amount, 'other test on the bill is kept');
        $this->assertEquals(900, $bill->net_amount);
        $this->assertEquals(300, $bill->advance_paid);
    }

    public function test_payments_page_accepts_methods_offered_by_forms(): void
    {
        $user = User::factory()->create();
        $patient = Patient::create(['first_name' => 'A', 'last_name' => 'B', 'gender' => 'Male', 'age' => 3, 'age_type' => 'Years', 'status' => 'Active']);

        foreach (['UPI', 'Net Banking', 'Card', 'Cash'] as $method) {
            $this->actingAs($user)->postJson('/payments', [
                'patient_id' => $patient->id, 'total_amount' => 100, 'discount' => 0, 'advance_paid' => 100,
                'payment_status' => 'Paid', 'payment_method' => $method, 'bill_date' => now()->toDateString(),
            ])->assertOk();
        }
        $this->assertEquals(4, Payment::count());
    }

    public function test_settling_a_linked_bill_clears_the_patient_balance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/patients/store', $this->patientPayload(['advance_paid' => 0]))->assertOk();
        $payment = Payment::firstOrFail();

        $this->actingAs($user)->putJson('/payments/' . $payment->id, [
            'patient_id' => $payment->patient_id, 'total_amount' => 1000, 'discount' => 100, 'advance_paid' => 900,
            'payment_status' => 'Paid', 'payment_method' => 'Cash', 'bill_date' => now()->toDateString(),
        ])->assertOk();

        $this->assertSame('Paid', $payment->fresh()->payment_status);
        $this->assertEquals(0, Patient::firstOrFail()->balance);
    }

    public function test_bills_linked_to_tests_cannot_be_deleted_directly(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/patients/store', $this->patientPayload())->assertOk();

        $this->actingAs($user)->deleteJson('/payments/' . Payment::firstOrFail()->id)->assertStatus(409);
        $this->assertEquals(1, Payment::count());
    }

    public function test_payment_status_follows_the_amounts(): void
    {
        $user = User::factory()->create();
        $patient = Patient::create(['first_name' => 'A', 'last_name' => 'B', 'gender' => 'Male', 'age' => 3, 'age_type' => 'Years', 'status' => 'Active']);
        $base = ['patient_id' => $patient->id, 'total_amount' => 500, 'discount' => 0, 'payment_method' => 'Cash', 'bill_date' => now()->toDateString()];

        // Picking "Paid" while money is still owed is corrected to Partial.
        $this->actingAs($user)->postJson('/payments', $base + ['advance_paid' => 200, 'payment_status' => 'Paid'])->assertOk();
        $this->assertSame('Partial', Payment::latest('id')->first()->payment_status);

        $this->actingAs($user)->postJson('/payments', $base + ['advance_paid' => 0])->assertOk();
        $this->assertSame('Unpaid', Payment::latest('id')->first()->payment_status);

        $this->actingAs($user)->postJson('/payments', $base + ['advance_paid' => 600])
            ->assertStatus(422)->assertJsonValidationErrors('advance_paid');

        $this->actingAs($user)->postJson('/payments', $base + ['advance_paid' => 500, 'payment_status' => 'Refunded'])->assertOk();
        $this->assertSame('Refunded', Payment::latest('id')->first()->payment_status);

        // Receive the remaining balance in one click
        $partial = Payment::where('payment_status', 'Partial')->firstOrFail();
        $this->actingAs($user)->postJson('/payments/' . $partial->id . '/settle', ['payment_method' => 'UPI'])->assertOk();
        $partial->refresh();
        $this->assertSame('Paid', $partial->payment_status);
        $this->assertEquals(0, $partial->balance_due);
        $this->assertSame('UPI', $partial->payment_method);
    }

    public function test_settling_a_test_bill_updates_patient_balance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/patients/store', $this->patientPayload(['advance_paid' => 100]))->assertOk();

        $this->actingAs($user)->postJson('/payments/' . Payment::firstOrFail()->id . '/settle')->assertOk();

        $this->assertSame('Paid', Payment::firstOrFail()->payment_status);
        $this->assertEquals(0, Patient::firstOrFail()->balance);
    }
}
