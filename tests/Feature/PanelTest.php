<?php

namespace Tests\Feature;

use App\Models\LabTest;
use App\Models\Patient;
use App\Models\ReportTemplate;
use App\Models\TestReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use RefreshDatabase;

    private function femalePatient(): Patient
    {
        return Patient::create(['first_name' => 'Rani', 'last_name' => 'P', 'gender' => 'Female', 'age' => 30, 'age_type' => 'Years', 'status' => 'Active']);
    }

    public function test_builder_creates_parameters_ranges_and_billing_link(): void
    {
        $user = User::factory()->create();
        $billing = LabTest::create(['name' => 'Mini Lipid', 'price' => 400]);

        $this->actingAs($user)->postJson('/templates', [
            'name' => 'Mini Lipid', 'department' => 'BIOCHEMISTRY', 'billing_test_ids' => [$billing->id],
            'items' => [
                ['item_type' => 'parameter', 'name' => 'Total Cholesterol', 'code' => 'TC', 'unit' => 'mg/dL', 'ranges' => ['male_max' => 200, 'female_max' => 200]],
                ['item_type' => 'parameter', 'name' => 'HDL', 'code' => 'HDL', 'unit' => 'mg/dL', 'ranges' => ['male_min' => 40, 'female_min' => 50]],
                ['item_type' => 'heading', 'name' => 'Ratios'],
                ['item_type' => 'parameter', 'name' => 'TC/HDL', 'code' => 'RATIO', 'formula' => '{TC} / {HDL}', 'decimals' => 2],
            ],
        ])->assertOk();

        $panel = ReportTemplate::where('name', 'Mini Lipid')->firstOrFail();
        $this->assertEquals($panel->id, $billing->fresh()->report_template_id);
        $hdl = LabTest::where('name', 'HDL')->firstOrFail();
        $this->assertEquals(50, $hdl->parameter->female_min);
        $this->assertSame('> 50', $hdl->parameter->female_reference);

        // Booking the billing test expands into the panel, with patient-specific ranges.
        $patient = $this->femalePatient();
        $rows = $this->actingAs($user)->postJson('/report-builder/rows', ['patient_id' => $patient->id, 'tests' => ['Mini Lipid']])->assertOk()->json('rows');
        $this->assertSame(['panel', 'param', 'param', 'heading', 'param'], array_column($rows, 'kind'));
        $this->assertEquals(50, $rows[2]['min']);
        $this->assertSame('Ratios', $rows[4]['heading']);

        // The calculated value is filled in on save when left blank.
        $this->actingAs($user)->postJson('/reports', [
            'patient_id' => $patient->id, 'doctor_name' => 'Self',
            'sample_received_on' => now()->toDateTimeString(), 'report_released_on' => now()->toDateTimeString(),
            'test_name' => ['Total Cholesterol', 'HDL', 'TC/HDL'],
            'lab_test_id' => [$rows[1]['lab_test_id'], $rows[2]['lab_test_id'], $rows[4]['lab_test_id']],
            'report_template_id' => [$panel->id, $panel->id, $panel->id],
            'panel_name' => ['Mini Lipid', 'Mini Lipid', 'Mini Lipid'],
            'observed_value' => ['240', '45', ''],
            'panel_notes' => ['Mini Lipid' => 'Fasting sample'],
        ])->assertOk();

        $report = TestReport::with('items')->firstOrFail();
        $this->assertSame('5.33', $report->items->firstWhere('name', 'TC/HDL')->observed_value);
        $this->assertSame('↑', $report->items->firstWhere('name', 'Total Cholesterol')->flag);
        $this->assertSame('↓', $report->items->firstWhere('name', 'HDL')->flag, 'female HDL 45 is below 50');
        $this->assertSame('Mini Lipid', $report->items->first()->panel_name);
        $this->assertSame(['Mini Lipid' => 'Fasting sample'], $report->panel_notes);
    }

    public function test_formula_and_code_validation(): void
    {
        $user = User::factory()->create();
        $base = ['name' => 'Bad Panel', 'items' => [['item_type' => 'parameter', 'name' => 'A', 'code' => 'A']]];

        $bad = $base; $bad['items'][] = ['item_type' => 'parameter', 'name' => 'B', 'code' => 'B', 'formula' => '{A} + {MISSING}'];
        $this->actingAs($user)->postJson('/templates', $bad)->assertStatus(422)->assertJsonValidationErrors('items.1.formula');

        $bad = $base; $bad['items'][] = ['item_type' => 'parameter', 'name' => 'B', 'code' => 'B', 'formula' => '{A} ^ 2'];
        $this->actingAs($user)->postJson('/templates', $bad)->assertStatus(422)->assertJsonValidationErrors('items.1.formula');

        $bad = $base; $bad['items'][] = ['item_type' => 'parameter', 'name' => 'B', 'code' => 'a'];
        $this->actingAs($user)->postJson('/templates', $bad)->assertStatus(422)->assertJsonValidationErrors('items.1.code');

        $this->actingAs($user)->postJson('/templates', ['name' => 'Empty', 'items' => []])->assertStatus(422);
    }

    public function test_standard_panel_installs_next_to_an_imported_one_with_the_same_name(): void
    {
        $user = User::factory()->create();
        ReportTemplate::create(['name' => 'Lipid Profile']);
        $this->actingAs($user)->postJson('/templates/install-standard', ['panels' => ['Lipid Profile']])
            ->assertOk()->assertJsonPath('created.0', 'Lipid Profile (Standard)');
        $this->assertEquals(7, ReportTemplate::where('name', 'Lipid Profile (Standard)')->first()->items()->count());
    }

    public function test_standard_library_install_and_duplicate(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/templates/install-standard', ['panels' => ['Complete Blood Count (CBC)']])->assertOk()->assertJsonPath('created.0', 'Complete Blood Count (CBC)');
        $this->actingAs($user)->postJson('/templates/install-standard', ['panels' => ['Complete Blood Count (CBC)']])->assertOk()->assertJsonPath('skipped.0', 'Complete Blood Count (CBC)');

        $cbc = ReportTemplate::where('name', 'Complete Blood Count (CBC)')->firstOrFail();
        $this->assertEquals(14, $cbc->items()->where('item_type', 'parameter')->count());
        $this->assertNotNull(LabTest::where('report_template_id', $cbc->id)->first(), 'billing test created');

        $this->actingAs($user)->postJson("/templates/{$cbc->id}/duplicate")->assertOk();
        $this->assertTrue(ReportTemplate::where('name', 'Complete Blood Count (CBC) (Copy)')->exists());
        $this->actingAs($user)->get('/templates')->assertOk()->assertSee('Complete Blood Count (CBC) (Copy)');
    }
}
