<?php

namespace App\Services;

use App\Models\LabTest;
use App\Models\ReportTemplate;
use App\Models\TestParameter;
use Illuminate\Support\Facades\DB;

/**
 * Ready-made, clean panels a lab can install with one click.
 * Ranges are common adult reference intervals — each lab must verify them against
 * its own analyser and kit inserts (the UI says so after installing).
 */
class StandardPanelLibrary
{
    public static function names(): array
    {
        return array_keys(self::panels());
    }

    /** Library panels that are already installed (under their own or the "(Standard)" name). */
    public static function installed(): array
    {
        return array_values(array_filter(self::names(), fn ($n) => ReportTemplate::where('name', $n . ' (Standard)')->exists()
            || ReportTemplate::where('name', $n)->whereHas('items', fn ($q) => $q->whereNotNull('code'))->exists()));
    }

    /** @return array{created: string[], skipped: string[]} */
    public function install(?array $only = null): array
    {
        $created = [];
        $skipped = [];

        foreach (self::panels() as $name => $panel) {
            if ($only && !in_array($name, $only, true)) {
                continue;
            }
            // A lab's own (imported) panel may already use this name: install alongside it.
            if (in_array($name, self::installed(), true)) {
                $skipped[] = $name;
                continue;
            }
            $installName = ReportTemplate::where('name', $name)->exists() ? $name . ' (Standard)' : $name;

            DB::transaction(function () use ($installName, $panel) {
                $name = $installName;
                $template = ReportTemplate::create([
                    'name' => $name,
                    'department' => $panel['department'],
                    'description' => $panel['description'] ?? null,
                    'interpretation' => $panel['interpretation'] ?? null,
                    'is_active' => true,
                ]);

                LabTest::create([
                    'name' => $name,
                    'price' => 0,
                    'description' => $panel['description'] ?? null,
                    'payment_method' => 'Cash',
                    'report_template_id' => $template->id,
                ]);

                foreach ($panel['items'] as $i => $item) {
                    if (isset($item['heading'])) {
                        $template->items()->create(['item_type' => 'heading', 'name' => $item['heading'], 'category' => $panel['department'], 'sort_order' => $i]);
                        continue;
                    }

                    [$mMin, $mMax, $mText] = $item['male'] ?? $item['range'] ?? [null, null, null];
                    [$fMin, $fMax, $fText] = $item['female'] ?? $item['range'] ?? [$mMin, $mMax, $mText];

                    $labTest = LabTest::create([
                        'name' => $item['name'],
                        'price' => 0,
                        'description' => 'Part of ' . $name,
                        'payment_method' => 'Cash',
                    ]);
                    $parameter = TestParameter::create([
                        'lab_test_id' => $labTest->id,
                        'unit' => $item['unit'] ?? null,
                        'male_reference' => $mText,
                        'female_reference' => $fText,
                        'biological_reference' => $mText === $fText ? $mText : null,
                        'male_min' => $mMin,
                        'male_max' => $mMax,
                        'female_min' => $fMin,
                        'female_max' => $fMax,
                        'critical_low' => $item['critical'][0] ?? null,
                        'critical_high' => $item['critical'][1] ?? null,
                        'is_immunoassay' => false,
                    ]);
                    ReferenceRangeService::syncDefaultIntervals($parameter);

                    $template->items()->create([
                        'item_type' => 'parameter',
                        'lab_test_id' => $labTest->id,
                        'category' => $panel['department'],
                        'name' => $item['name'],
                        'code' => $item['code'] ?? null,
                        'unit' => $item['unit'] ?? null,
                        'default_value' => $item['default'] ?? null,
                        'options' => $item['options'] ?? null,
                        'formula' => $item['formula'] ?? null,
                        'decimals' => $item['decimals'] ?? null,
                        'sort_order' => $i,
                    ]);
                }
            });

            $created[] = $installName;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    private static function panels(): array
    {
        $r = fn ($min, $max, $text = null) => [$min, $max, $text ?? self::rangeText($min, $max)];

        return [
            'Complete Blood Count (CBC)' => [
                'department' => 'HAEMATOLOGY',
                'description' => 'Haemogram with RBC indices and differential count',
                'interpretation' => "RBC indices (MCV, MCH, MCHC) are calculated from Hb, PCV and RBC count.\nPlease correlate clinically.",
                'items' => [
                    ['name' => 'Haemoglobin', 'code' => 'HB', 'unit' => 'g/dL', 'male' => $r(13.0, 17.0), 'female' => $r(12.0, 15.0), 'critical' => [7, 20], 'decimals' => 1],
                    ['name' => 'Total RBC Count', 'code' => 'RBC', 'unit' => 'million/cumm', 'male' => $r(4.5, 5.5), 'female' => $r(3.8, 4.8), 'decimals' => 2],
                    ['name' => 'Packed Cell Volume (PCV)', 'code' => 'PCV', 'unit' => '%', 'male' => $r(40, 50), 'female' => $r(36, 46), 'decimals' => 1],
                    ['name' => 'MCV', 'code' => 'MCV', 'unit' => 'fL', 'range' => $r(83, 101), 'formula' => '{PCV} / {RBC} * 10', 'decimals' => 1],
                    ['name' => 'MCH', 'code' => 'MCH', 'unit' => 'pg', 'range' => $r(27, 32), 'formula' => '{HB} / {RBC} * 10', 'decimals' => 1],
                    ['name' => 'MCHC', 'code' => 'MCHC', 'unit' => 'g/dL', 'range' => $r(31.5, 34.5), 'formula' => '{HB} / {PCV} * 100', 'decimals' => 1],
                    ['name' => 'RDW-CV', 'code' => 'RDW', 'unit' => '%', 'range' => $r(11.6, 14.0), 'decimals' => 1],
                    ['name' => 'Total WBC Count', 'code' => 'WBC', 'unit' => 'cells/cumm', 'range' => $r(4000, 11000), 'critical' => [2000, 30000], 'decimals' => 0],
                    ['heading' => 'Differential Count'],
                    ['name' => 'Neutrophils', 'code' => 'NEUT', 'unit' => '%', 'range' => $r(40, 80), 'decimals' => 0],
                    ['name' => 'Lymphocytes', 'code' => 'LYMPH', 'unit' => '%', 'range' => $r(20, 40), 'decimals' => 0],
                    ['name' => 'Eosinophils', 'code' => 'EOS', 'unit' => '%', 'range' => $r(1, 6), 'decimals' => 0],
                    ['name' => 'Monocytes', 'code' => 'MONO', 'unit' => '%', 'range' => $r(2, 10), 'decimals' => 0],
                    ['name' => 'Basophils', 'code' => 'BASO', 'unit' => '%', 'range' => $r(0, 2), 'decimals' => 0],
                    ['heading' => 'Platelets'],
                    ['name' => 'Platelet Count', 'code' => 'PLT', 'unit' => 'lakhs/cumm', 'range' => $r(1.5, 4.1), 'critical' => [0.5, 10], 'decimals' => 2],
                ],
            ],
            'Liver Function Test (LFT)' => [
                'department' => 'BIOCHEMISTRY',
                'description' => 'Bilirubin, liver enzymes and proteins',
                'interpretation' => 'Indirect bilirubin, globulin and A/G ratio are calculated values.',
                'items' => [
                    ['heading' => 'Bilirubin'],
                    ['name' => 'Bilirubin Total', 'code' => 'TBIL', 'unit' => 'mg/dL', 'range' => $r(0.3, 1.2), 'decimals' => 2],
                    ['name' => 'Bilirubin Direct', 'code' => 'DBIL', 'unit' => 'mg/dL', 'range' => $r(0.0, 0.3), 'decimals' => 2],
                    ['name' => 'Bilirubin Indirect', 'code' => 'IBIL', 'unit' => 'mg/dL', 'range' => $r(0.2, 0.9), 'formula' => '{TBIL} - {DBIL}', 'decimals' => 2],
                    ['heading' => 'Enzymes'],
                    ['name' => 'SGOT (AST)', 'code' => 'AST', 'unit' => 'U/L', 'range' => [null, 40, '< 40'], 'decimals' => 0],
                    ['name' => 'SGPT (ALT)', 'code' => 'ALT', 'unit' => 'U/L', 'range' => [null, 41, '< 41'], 'decimals' => 0],
                    ['name' => 'Alkaline Phosphatase (ALP)', 'code' => 'ALP', 'unit' => 'U/L', 'range' => $r(44, 147), 'decimals' => 0],
                    ['heading' => 'Proteins'],
                    ['name' => 'Total Protein', 'code' => 'TP', 'unit' => 'g/dL', 'range' => $r(6.4, 8.3), 'decimals' => 1],
                    ['name' => 'Albumin', 'code' => 'ALB', 'unit' => 'g/dL', 'range' => $r(3.5, 5.2), 'decimals' => 1],
                    ['name' => 'Globulin', 'code' => 'GLOB', 'unit' => 'g/dL', 'range' => $r(2.0, 3.5), 'formula' => '{TP} - {ALB}', 'decimals' => 1],
                    ['name' => 'A/G Ratio', 'code' => 'AGR', 'unit' => '', 'range' => $r(1.1, 2.2), 'formula' => '{ALB} / ({TP} - {ALB})', 'decimals' => 2],
                ],
            ],
            'Kidney Function Test (KFT)' => [
                'department' => 'BIOCHEMISTRY',
                'description' => 'Renal function with electrolytes',
                'interpretation' => 'BUN is calculated as Blood Urea / 2.14.',
                'items' => [
                    ['name' => 'Blood Urea', 'code' => 'UREA', 'unit' => 'mg/dL', 'range' => $r(17, 43), 'decimals' => 0],
                    ['name' => 'Blood Urea Nitrogen (BUN)', 'code' => 'BUN', 'unit' => 'mg/dL', 'range' => $r(8, 20), 'formula' => '{UREA} / 2.14', 'decimals' => 1],
                    ['name' => 'Serum Creatinine', 'code' => 'CREA', 'unit' => 'mg/dL', 'male' => $r(0.7, 1.3), 'female' => $r(0.6, 1.1), 'decimals' => 2],
                    ['name' => 'Uric Acid', 'code' => 'UA', 'unit' => 'mg/dL', 'male' => $r(3.5, 7.2), 'female' => $r(2.6, 6.0), 'decimals' => 1],
                    ['heading' => 'Electrolytes'],
                    ['name' => 'Sodium', 'code' => 'NA', 'unit' => 'mmol/L', 'range' => $r(136, 145), 'critical' => [120, 160], 'decimals' => 0],
                    ['name' => 'Potassium', 'code' => 'K', 'unit' => 'mmol/L', 'range' => $r(3.5, 5.1), 'critical' => [2.8, 6.2], 'decimals' => 1],
                    ['name' => 'Chloride', 'code' => 'CL', 'unit' => 'mmol/L', 'range' => $r(98, 107), 'decimals' => 0],
                ],
            ],
            'Lipid Profile' => [
                'department' => 'BIOCHEMISTRY',
                'description' => 'Fasting lipid profile',
                'interpretation' => "LDL is calculated by the Friedewald formula (not valid when triglycerides exceed 400 mg/dL).\nVLDL = Triglycerides / 5. Fasting of 10–12 hours is recommended.",
                'items' => [
                    ['name' => 'Total Cholesterol', 'code' => 'TC', 'unit' => 'mg/dL', 'range' => [null, 200, "Desirable: < 200\nBorderline: 200 - 239\nHigh: >= 240"], 'decimals' => 0],
                    ['name' => 'Triglycerides', 'code' => 'TG', 'unit' => 'mg/dL', 'range' => [null, 150, "Normal: < 150\nBorderline: 150 - 199\nHigh: 200 - 499\nVery high: >= 500"], 'decimals' => 0],
                    ['name' => 'HDL Cholesterol', 'code' => 'HDL', 'unit' => 'mg/dL', 'male' => [40, null, '> 40'], 'female' => [50, null, '> 50'], 'decimals' => 0],
                    ['name' => 'LDL Cholesterol', 'code' => 'LDL', 'unit' => 'mg/dL', 'range' => [null, 129, "Optimal: < 100\nNear optimal: 100 - 129\nBorderline: 130 - 159\nHigh: >= 160"], 'formula' => '{TC} - {HDL} - {TG} / 5', 'decimals' => 0],
                    ['name' => 'VLDL Cholesterol', 'code' => 'VLDL', 'unit' => 'mg/dL', 'range' => $r(5, 40), 'formula' => '{TG} / 5', 'decimals' => 0],
                    ['name' => 'Total Cholesterol / HDL Ratio', 'code' => 'TCHDL', 'unit' => '', 'range' => [null, 5, '< 5.0'], 'formula' => '{TC} / {HDL}', 'decimals' => 2],
                    ['name' => 'LDL / HDL Ratio', 'code' => 'LDLHDL', 'unit' => '', 'range' => [null, 3.5, '< 3.5'], 'formula' => '{LDL} / {HDL}', 'decimals' => 2],
                ],
            ],
            'Thyroid Profile (T3, T4, TSH)' => [
                'department' => 'IMMUNOASSAY',
                'description' => 'Total T3, total T4 and TSH',
                'items' => [
                    ['name' => 'Total T3', 'code' => 'T3', 'unit' => 'ng/mL', 'range' => $r(0.8, 2.0), 'decimals' => 2],
                    ['name' => 'Total T4', 'code' => 'T4', 'unit' => 'µg/dL', 'range' => $r(5.1, 14.1), 'decimals' => 2],
                    ['name' => 'TSH', 'code' => 'TSH', 'unit' => 'µIU/mL', 'range' => $r(0.27, 4.2), 'decimals' => 2],
                ],
            ],
            'Urine Routine Examination' => [
                'department' => 'CLINICAL PATHOLOGY',
                'description' => 'Physical, chemical and microscopic examination of urine',
                'items' => [
                    ['heading' => 'Physical Examination'],
                    ['name' => 'Colour', 'default' => 'Pale Yellow', 'options' => 'Pale Yellow, Straw, Yellow, Dark Yellow*, Red*, Colourless', 'range' => [null, null, 'Pale Yellow']],
                    ['name' => 'Appearance', 'default' => 'Clear', 'options' => 'Clear, Slightly Turbid*, Turbid*', 'range' => [null, null, 'Clear']],
                    ['name' => 'Specific Gravity', 'range' => [1.005, 1.030, '1.005 - 1.030'], 'decimals' => 3],
                    ['name' => 'pH', 'range' => [4.6, 8.0, '4.6 - 8.0'], 'decimals' => 1],
                    ['heading' => 'Chemical Examination'],
                    ['name' => 'Protein', 'default' => 'Nil', 'options' => 'Nil, Trace*, +*, ++*, +++*', 'range' => [null, null, 'Nil']],
                    ['name' => 'Glucose', 'default' => 'Nil', 'options' => 'Nil, Trace*, +*, ++*, +++*', 'range' => [null, null, 'Nil']],
                    ['name' => 'Ketone Bodies', 'default' => 'Negative', 'options' => 'Negative, Positive*', 'range' => [null, null, 'Negative']],
                    ['name' => 'Bile Salts', 'default' => 'Absent', 'options' => 'Absent, Present*', 'range' => [null, null, 'Absent']],
                    ['name' => 'Bile Pigments', 'default' => 'Absent', 'options' => 'Absent, Present*', 'range' => [null, null, 'Absent']],
                    ['name' => 'Urobilinogen', 'default' => 'Normal', 'options' => 'Normal, Increased*', 'range' => [null, null, 'Normal']],
                    ['heading' => 'Microscopic Examination'],
                    ['name' => 'Pus Cells', 'unit' => '/hpf', 'default' => '2-4', 'range' => [null, null, '0 - 5']],
                    ['name' => 'Epithelial Cells', 'unit' => '/hpf', 'default' => 'Few', 'options' => 'Nil, Few, Moderate*, Plenty*', 'range' => [null, null, 'Few']],
                    ['name' => 'RBCs', 'unit' => '/hpf', 'default' => 'Nil', 'range' => [null, null, 'Nil']],
                    ['name' => 'Casts', 'default' => 'Nil', 'options' => 'Nil, Hyaline*, Granular*', 'range' => [null, null, 'Nil']],
                    ['name' => 'Crystals', 'default' => 'Nil', 'options' => 'Nil, Calcium Oxalate*, Uric Acid*, Amorphous*', 'range' => [null, null, 'Nil']],
                    ['name' => 'Bacteria', 'default' => 'Nil', 'options' => 'Nil, Present*', 'range' => [null, null, 'Nil']],
                ],
            ],
        ];
    }

    private static function rangeText($min, $max): string
    {
        return rtrim(rtrim(number_format((float) $min, 3, '.', ''), '0'), '.') . ' - ' . rtrim(rtrim(number_format((float) $max, 3, '.', ''), '0'), '.');
    }
}
