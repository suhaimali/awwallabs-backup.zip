<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns report templates into proper test panels:
 * billing test -> panel -> ordered parameters / sub-headings, with
 * calculated parameters, default values, result pick-lists and interpretation notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('report_templates', 'department')) Schema::table('report_templates', function (Blueprint $table) {
            $table->string('department')->nullable()->after('name');
            $table->text('interpretation')->nullable()->after('description');
            $table->boolean('is_active')->default(true)->after('interpretation');
        });

        if (!Schema::hasColumn('report_template_items', 'item_type')) Schema::table('report_template_items', function (Blueprint $table) {
            $table->string('item_type', 20)->default('parameter')->after('report_template_id');
            $table->string('code', 30)->nullable()->after('name');
            $table->string('default_value')->nullable()->after('biological_reference');
            $table->string('options', 500)->nullable()->after('default_value');
            $table->string('formula')->nullable()->after('options');
            $table->unsignedTinyInteger('decimals')->nullable()->after('formula');
            $table->text('normal_value')->nullable()->change();
        });

        // A billing test (what the front desk books) expands into a panel at result entry.
        if (!Schema::hasColumn('lab_tests', 'report_template_id')) Schema::table('lab_tests', function (Blueprint $table) {
            $table->foreignId('report_template_id')->nullable()
                ->constrained('report_templates')->nullOnDelete();
        });

        if (!Schema::hasColumn('test_report_items', 'panel_name')) Schema::table('test_report_items', function (Blueprint $table) {
            $table->string('panel_name')->nullable()->after('subcategory');
            $table->unsignedBigInteger('report_template_id')->nullable()->after('lab_test_id');
        });

        if (!Schema::hasColumn('test_reports', 'panel_notes')) Schema::table('test_reports', function (Blueprint $table) {
            $table->longText('panel_notes')->nullable()->after('notes');
        });

        $this->decodeHtmlEntities();
        $this->linkBillingTestsToPanels();
        $this->fillDepartments();
    }

    /** The CSV import stored text like "&lt;151", which printed literally on reports. */
    private function decodeHtmlEntities(): void
    {
        $columns = [
            'report_template_items' => ['name', 'unit', 'normal_value', 'biological_reference'],
            'test_parameters' => ['unit', 'male_reference', 'female_reference', 'biological_reference'],
            'reference_intervals' => ['reference_text'],
            'lab_tests' => ['name', 'description'],
            'reference_templates' => ['name'],
            'test_report_items' => ['normal_value', 'biological_reference'],
        ];

        foreach ($columns as $table => $cols) {
            foreach ($cols as $col) {
                DB::table($table)->select('id', $col)
                    ->where(fn ($q) => $q->where($col, 'like', '%&lt;%')->orWhere($col, 'like', '%&gt;%')->orWhere($col, 'like', '%&amp;%')->orWhere($col, 'like', '%&#%'))
                    ->orderBy('id')
                    ->chunkById(500, function ($rows) use ($table, $col) {
                        foreach ($rows as $row) {
                            $decoded = html_entity_decode((string) $row->{$col}, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            if ($table === 'reference_templates' && DB::table($table)->where('name', $decoded)->where('id', '!=', $row->id)->exists()) {
                                continue; // keep the unique name constraint intact
                            }
                            DB::table($table)->where('id', $row->id)->update([$col => $decoded]);
                        }
                    });
            }
        }
    }

    /** Link billing tests to the panel that has the same name (the old, implicit link). */
    private function linkBillingTestsToPanels(): void
    {
        $panels = DB::table('report_templates')->get(['id', 'name']);
        foreach ($panels as $panel) {
            DB::table('lab_tests')
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($panel->name))])
                ->where(fn ($q) => $q->whereNull('description')->orWhere('description', 'not like', 'Part of %'))
                ->whereNull('report_template_id')
                ->update(['report_template_id' => $panel->id]);
        }
    }

    private function fillDepartments(): void
    {
        $panels = DB::table('report_templates')->whereNull('department')->get(['id']);
        foreach ($panels as $panel) {
            $category = DB::table('report_template_items')
                ->where('report_template_id', $panel->id)
                ->whereNotNull('category')->where('category', '!=', '')->where('category', '!=', 'General')
                ->select('category', DB::raw('COUNT(*) c'))->groupBy('category')->orderByDesc('c')
                ->value('category');
            if ($category) {
                DB::table('report_templates')->where('id', $panel->id)->update(['department' => $category]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('test_reports', fn (Blueprint $t) => $t->dropColumn('panel_notes'));
        Schema::table('test_report_items', fn (Blueprint $t) => $t->dropColumn(['panel_name', 'report_template_id']));
        Schema::table('lab_tests', fn (Blueprint $t) => $t->dropConstrainedForeignId('report_template_id'));
        Schema::table('report_template_items', fn (Blueprint $t) => $t->dropColumn(['item_type', 'code', 'default_value', 'options', 'formula', 'decimals']));
        Schema::table('report_templates', fn (Blueprint $t) => $t->dropColumn(['department', 'interpretation', 'is_active']));
    }
};
