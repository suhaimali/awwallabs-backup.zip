<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\PurchaseReceipt;
use App\Models\TestReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(): void
    {
        $test = LabTest::create(['name' => 'Hemoglobin', 'price' => 150]);
        $test->parameter()->create(['unit' => 'g/dL', 'male_min' => 13, 'male_max' => 17, 'female_min' => 12, 'female_max' => 15]);

        $patient = Patient::create([
            'patient_id' => '2026-0001', 'first_name' => 'Asha', 'last_name' => 'K', 'gender' => 'Female',
            'age' => 30, 'age_type' => 'Years', 'status' => 'Active',
        ]);
        $payment = Payment::create([
            'patient_id' => $patient->id, 'total_amount' => 150, 'discount' => 0, 'advance_paid' => 0,
            'net_amount' => 150, 'balance_due' => 150, 'payment_status' => 'Unpaid', 'payment_method' => 'UPI',
            'bill_date' => now()->toDateString(),
        ]);
        Appointment::create([
            'patient_id' => $patient->id, 'payment_id' => $payment->id, 'doctor_name' => 'Self',
            'test_name' => 'Hemoglobin', 'test_price' => 150, 'appointment_date' => now()->toDateString(),
            'appointment_time' => '10:00', 'status' => 'Pending',
        ]);
        $report = TestReport::create([
            'patient_id' => $patient->id, 'doctor_name' => 'Self', 'sample_received_on' => now(),
            'report_released_on' => now(), 'status' => 'Completed',
        ]);
        $report->items()->create(['name' => 'Hemoglobin', 'observed_value' => '11', 'category' => 'Haematology']);

        $product = Product::create(['name' => 'Gloves', 'unit' => 'box', 'unit_price' => 100, 'stock_quantity' => 1]);
        $receipt = PurchaseReceipt::create(['receipt_no' => 'PR-1', 'purchase_date' => now()->toDateString(), 'total_amount' => 200]);
        PurchaseItem::create(['purchase_receipt_id' => $receipt->id, 'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100, 'total_price' => 200]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk();
        $this->get('/')->assertOk();
    }

    public function test_every_authenticated_page_renders_with_data(): void
    {
        $this->seedData();
        $user = User::factory()->create();

        $pages = [
            '/dashboard', '/patients', '/vital-signs', '/appointments', '/lab-tests', '/test-parameters',
            '/categories', '/sub-categories', '/master-data', '/templates', '/reports', '/report-signatures',
            '/payments', '/income-report', '/daily-collection', '/products', '/purchases', '/backups',
            '/visits/new', '/reports?create_for=1',
        ];

        foreach ($pages as $page) {
            $this->actingAs($user)->get($page)->assertOk();
        }
    }

    public function test_purchases_page_lists_receipt_items(): void
    {
        $this->seedData();

        $this->actingAs(User::factory()->create())
            ->get('/purchases')
            ->assertOk()
            ->assertSee('1 items (2 qty)', false);
    }

    public function test_finance_pages_unlock_with_password(): void
    {
        $this->seedData();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/income-report/unlock', ['password' => 'wrong'])->assertStatus(422);
        $this->actingAs($user)->postJson('/income-report/unlock', ['password' => 'test-pass'])->assertOk();
        $this->actingAs($user)->get('/income-report')->assertOk()->assertSee('150');

        $this->actingAs($user)->postJson('/daily-collection/unlock', ['password' => 'test-pass'])->assertOk();
        $this->actingAs($user)->get('/daily-collection')->assertOk()->assertSee('UPI');
    }
}
