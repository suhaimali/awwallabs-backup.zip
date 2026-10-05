<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\TestReport;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowAndQrTest extends TestCase
{
    use RefreshDatabase;

    private function visitPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'patient_mode' => 'new',
            'patient' => ['first_name' => 'Meera', 'last_name' => 'S', 'gender' => 'Female', 'age' => 28, 'age_type' => 'Years', 'phone' => '9000000001'],
            'doctor_name' => 'Dr. Rao',
            'tests' => [['name' => 'Hemoglobin', 'price' => 150, 'discount' => 0], ['name' => 'TSH', 'price' => 400, 'discount' => 50]],
            'advance_paid' => 200,
            'payment_method' => 'UPI',
            'vitals' => ['temperature' => 98.6, 'pulse' => 72, 'blood_pressure' => '120/80', 'weight' => 60, 'height' => 160],
        ], $overrides);
    }

    public function test_full_flow_from_onboarding_to_report_with_qr(): void
    {
        $user = User::factory()->create();
        $hb = LabTest::create(['name' => 'Hemoglobin', 'price' => 150]);
        $hb->parameter()->create(['unit' => 'g/dL', 'female_min' => 12, 'female_max' => 15, 'male_min' => 13, 'male_max' => 17]);

        // 1. Register visit
        $res = $this->actingAs($user)->postJson('/visits', $this->visitPayload())->assertOk();
        $patient = Patient::firstOrFail();
        $res->assertJsonPath('links.results', route('reports', ['create_for' => $patient->id]));

        $this->assertSame('Dr. Rao', $patient->reference_dr);
        $this->assertEquals(2, Appointment::where('status', 'Pending')->count());
        $this->assertEquals(500, Payment::firstOrFail()->net_amount);
        $this->assertSame('Partial', Payment::firstOrFail()->payment_status);
        $this->assertEquals(23.4, VitalSign::firstOrFail()->bmi);

        // 2. Result entry page is pre-filled with the pending tests
        $this->actingAs($user)->get('/reports?create_for=' . $patient->id)
            ->assertOk()->assertSee('"tests":["Hemoglobin","TSH"]', false);

        // 3. Save the report; one value left blank for later
        $res = $this->actingAs($user)->postJson('/reports', [
            'patient_id' => $patient->id, 'doctor_name' => 'Dr. Rao',
            'sample_received_on' => now()->format('Y-m-d\TH:i'), 'report_released_on' => now()->format('Y-m-d\TH:i'),
            'test_name' => ['Hemoglobin', 'TSH'], 'observed_value' => ['11.2', ''],
            'test_category' => ['Haematology', 'Hormones'],
        ])->assertOk();

        $report = TestReport::firstOrFail();
        $res->assertJsonPath('id', $report->id);
        $this->assertSame('↓', $report->items()->where('name', 'Hemoglobin')->value('flag'));
        $this->assertNull($report->items()->where('name', 'TSH')->value('observed_value'));
        $this->assertEquals(0, Appointment::where('status', 'Pending')->count(), 'booked tests marked complete');

        // 4. Report JSON exposes the URL the QR code points to
        $json = $this->actingAs($user)->getJson('/reports/' . $report->id)->assertOk()->json();
        $this->assertStringContainsString('/r/' . $report->public_token, $json['public_url']);
        $this->assertArrayNotHasKey('public_token', $json);

        // 5. Anyone scanning the QR (not logged in) sees the report
        auth()->logout();
        $this->get($json['public_url'])->assertOk()->assertSee('MEERA S')->assertSee('11.2')->assertSee('Hemoglobin');
        $this->get($patient->public_url)->assertOk()->assertSee('TSH')->assertSee($report->public_url, false);
    }

    public function test_returning_patient_visit_is_billed_separately(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/visits', $this->visitPayload())->assertOk();
        $patient = Patient::firstOrFail();

        $this->actingAs($user)->getJson('/visits/patient-search?q=meera')->assertOk()->assertJsonPath('0.id', $patient->id);
        $this->actingAs($user)->getJson('/visits/patient-search?q=9000000001')->assertOk()->assertJsonCount(1);

        $this->actingAs($user)->postJson('/visits', [
            'patient_mode' => 'existing', 'existing_patient_id' => $patient->id,
            'tests' => [['name' => 'Vitamin D', 'price' => 1200]], 'advance_paid' => 1200, 'payment_method' => 'Cash',
        ])->assertOk();

        $this->assertEquals(1, Patient::count());
        $this->assertEquals(2, Payment::count());
        $this->assertSame('Paid', Payment::latest('id')->first()->payment_status);
        $this->assertEquals(300, $patient->fresh()->balance, 'only the first visit has a balance');
        $this->assertEquals(1, VitalSign::count(), 'no empty vitals row for the second visit');
    }

    public function test_visit_validation_errors(): void
    {
        $user = User::factory()->create();
        $noTests = $this->visitPayload();
        $noTests['tests'] = [];
        $this->actingAs($user)->postJson('/visits', $noTests)
            ->assertStatus(422)->assertJsonValidationErrors('tests');
        $this->actingAs($user)->postJson('/visits', $this->visitPayload(['patient' => ['first_name' => '']]))
            ->assertStatus(422)->assertJsonValidationErrors('patient.first_name');
        $this->actingAs($user)->postJson('/visits', $this->visitPayload(['vitals' => ['blood_pressure' => 'high']]))
            ->assertStatus(422)->assertJsonValidationErrors('vitals.blood_pressure');
    }

    public function test_public_pages_reject_bad_tokens(): void
    {
        $this->get('/r/' . str_repeat('a', 40))->assertNotFound();
        $this->get('/p/short')->assertNotFound();
        $this->get('/r/' . str_repeat('a', 39) . '%27')->assertNotFound();
    }
}
