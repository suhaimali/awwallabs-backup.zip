<?php

namespace Tests\Feature;

use App\Models\LabTest;
use App\Models\Product;
use App\Models\ReportSignature;
use App\Models\TestReport;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MasterDataAndSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_lab_test_crud_and_quick_parameter(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/lab-tests/store', ['name' => 'Urea'])->assertOk();
        $this->assertEquals(0, LabTest::where('name', 'Urea')->value('price'));

        $this->actingAs($user)->postJson('/tests/quick-store', ['name' => 'Glucose', 'price' => 80, 'unit' => 'mg/dL', 'male_min' => 70, 'male_max' => 110])->assertOk();
        $this->actingAs($user)->postJson('/tests/quick-store', ['name' => 'Bad', 'male_min' => 'abc'])->assertStatus(422);

        $glucose = LabTest::where('name', 'Glucose')->firstOrFail();
        $this->actingAs($user)->putJson('/tests/quick-update/' . $glucose->id, ['name' => 'Glucose (F)', 'unit' => 'mg/dl'])->assertOk();
        $this->assertSame('mg/dl', $glucose->parameter()->value('unit'));

        $this->actingAs($user)->postJson('/test-parameters', ['lab_test_id' => $glucose->id, 'male_min' => 70, 'male_max' => 100, 'female_min' => 70, 'female_max' => 100])->assertOk();
        $this->actingAs($user)->getJson('/test-parameters/' . $glucose->id . '/intervals')->assertOk()->assertJsonCount(2);
        $this->actingAs($user)->postJson('/test-parameters/' . $glucose->id . '/intervals', ['gender' => 'Male', 'age_min' => 0, 'age_max' => 12, 'min_value' => 60, 'max_value' => 100])->assertOk();

        $this->actingAs($user)->deleteJson('/lab-tests/' . $glucose->id)->assertOk();
    }

    public function test_categories_units_and_templates(): void
    {
        $user = User::factory()->create();
        $cat = $this->actingAs($user)->postJson('/categories', ['name' => 'Biochemistry'])->assertOk()->json('category');
        $this->actingAs($user)->postJson('/categories', ['name' => 'Biochemistry'])->assertStatus(422);
        $this->actingAs($user)->postJson('/sub-categories', ['category_id' => $cat['id'], 'name' => 'LFT'])->assertOk();
        $this->actingAs($user)->postJson('/units', ['name' => 'mg/dL'])->assertOk();
        $this->actingAs($user)->postJson('/result-templates', ['name' => 'Negative'])->assertOk();
        $this->actingAs($user)->postJson('/reference-templates', ['name' => '< 5'])->assertOk();
        $this->actingAs($user)->postJson('/flag-templates', ['name' => 'H'])->assertOk();

        $this->actingAs($user)->postJson('/templates', ['name' => 'LFT Panel', 'items' => [
            ['item_type' => 'parameter', 'name' => 'SGOT'], ['item_type' => 'parameter', 'name' => 'SGPT'],
        ]])->assertOk();
        $this->actingAs($user)->getJson('/templates/1')->assertOk()->assertJsonCount(2, 'items');
    }

    public function test_purchase_updates_stock_and_validates_items(): void
    {
        $user = User::factory()->create();
        $product = Product::create(['name' => 'Tubes', 'unit' => 'pcs', 'unit_price' => 5, 'stock_quantity' => 0]);

        $this->actingAs($user)->postJson('/purchases/store', [
            'purchase_date' => now()->toDateString(),
            'items' => json_encode([['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 5]]),
        ])->assertOk();
        $this->assertEquals(10, $product->fresh()->stock_quantity);

        $this->actingAs($user)->postJson('/purchases/store', [
            'purchase_date' => now()->toDateString(),
            'items' => json_encode([['product_id' => 999, 'quantity' => 1, 'unit_price' => 5]]),
        ])->assertStatus(422);
    }

    public function test_signature_pin_is_enforced_on_reports(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/report-signatures', [
            'name' => 'Dr. Lab', 'pin' => '4321',
            'signature_image' => UploadedFile::fake()->image('sig.png', 200, 80),
        ])->assertRedirect();
        $signature = ReportSignature::firstOrFail();
        $this->actingAs($user)->get('/report-signatures/' . $signature->id . '/image')->assertOk()->assertHeader('Content-Type', 'image/png');

        $patient = Patient::create(['first_name' => 'A', 'last_name' => 'B', 'gender' => 'Male', 'age' => 3, 'age_type' => 'Years', 'status' => 'Active']);
        $payload = [
            'patient_id' => $patient->id, 'doctor_name' => 'Self', 'sample_received_on' => now()->toDateTimeString(),
            'report_released_on' => now()->toDateTimeString(), 'report_signature_id' => $signature->id,
            'test_name' => ['X'], 'observed_value' => ['1'],
        ];
        $this->actingAs($user)->postJson('/reports', $payload + ['signature_pin' => '0000'])->assertStatus(422);
        $this->actingAs($user)->postJson('/reports', $payload + ['signature_pin' => '4321'])->assertOk();
        $this->assertEquals($signature->id, TestReport::firstOrFail()->report_signature_id);
    }

    public function test_backup_file_names_cannot_escape_the_backup_folder(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/backups/download/..%2F..%2F.env')->assertNotFound();
        $this->actingAs($user)->delete('/backups/delete/..%2F.env')->assertNotFound();
        $this->actingAs($user)->post('/backups/restore/notsql.txt')->assertNotFound();
    }

    public function test_vital_signs_crud(): void
    {
        $user = User::factory()->create();
        $patient = Patient::create(['first_name' => 'A', 'last_name' => 'B', 'gender' => 'Male', 'age' => 3, 'age_type' => 'Years', 'status' => 'Active']);
        $this->actingAs($user)->postJson('/vital-signs/store', ['patient_id' => $patient->id, 'weight' => 70, 'height' => 175, 'blood_pressure' => '120/80'])->assertOk();
        $this->actingAs($user)->getJson('/vital-signs/1')->assertOk()->assertJsonPath('bmi', '22.9');
        $this->actingAs($user)->postJson('/vital-signs/update/1', ['patient_id' => $patient->id, 'pulse' => 80])->assertOk();
        $this->actingAs($user)->deleteJson('/vital-signs/1')->assertOk();
    }
}
