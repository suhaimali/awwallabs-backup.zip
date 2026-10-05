<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The CSV import saved the word "NULL" as text (e.g. 648 template rows showed "NULL"
 * as sub-category). Turn those into real empty values.
 */
return new class extends Migration
{
    private array $columns = [
        'report_template_items' => ['category', 'subcategory', 'unit', 'normal_value', 'biological_reference', 'default_value', 'options', 'formula', 'code'],
        'report_templates' => ['department', 'description', 'interpretation'],
        'test_parameters' => ['unit', 'male_reference', 'female_reference', 'biological_reference'],
        'reference_intervals' => ['reference_text', 'age_type'],
        'lab_tests' => ['description'],
        'test_report_items' => ['subcategory', 'unit', 'normal_value', 'biological_reference', 'flag', 'panel_name'],
        'categories' => ['description'],
        'sub_categories' => ['description'],
        'patients' => ['phone', 'email', 'address', 'reference_dr'],
        'doctors' => ['qualification', 'phone', 'email'],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $cols) {
            foreach ($cols as $col) {
                if (!Schema::hasColumn($table, $col)) {
                    continue;
                }
                $replacement = ($table === 'report_template_items' && $col === 'category') ? 'General' : null;
                DB::table($table)->whereRaw("UPPER(TRIM(`$col`)) IN ('NULL', 'UNDEFINED')")->update([$col => $replacement]);
            }
        }
    }

    public function down(): void
    {
        // Data clean-up only; nothing to restore.
    }
};
