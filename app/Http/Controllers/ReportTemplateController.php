<?php

namespace App\Http\Controllers;

use App\Models\LabTest;
use App\Models\ReportTemplate;
use App\Models\Unit;
use App\Services\FormulaService;
use App\Services\StandardPanelLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Test panels (report templates): the ordered list of parameters and sub-headings
 * a billed test expands into at result entry.
 */
class ReportTemplateController extends Controller
{
    public function index()
    {
        $templates = ReportTemplate::withCount([
                'items as params_count' => fn ($q) => $q->where('item_type', 'parameter'),
                'items as calc_count' => fn ($q) => $q->whereNotNull('formula')->where('formula', '!=', ''),
            ])
            ->with('billingTests:id,name,price,report_template_id')
            ->orderBy('name')->get();

        $catalog = LabTest::with('parameter:id,lab_test_id,unit,male_reference,female_reference,biological_reference,male_min,male_max,female_min,female_max')
            ->orderBy('name')->get(['id', 'name', 'price', 'report_template_id'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'n' => $t->name,
                'p' => (float) $t->price,
                'u' => $t->parameter?->unit,
                'r' => $t->parameter?->male_reference ?: $t->parameter?->biological_reference,
                'fr' => $t->parameter?->female_reference,
                'panel' => $t->report_template_id,
                'mm' => $t->parameter?->male_min, 'mx' => $t->parameter?->male_max,
                'fm' => $t->parameter?->female_min, 'fx' => $t->parameter?->female_max,
            ])->values();

        $departments = ReportTemplate::whereNotNull('department')->distinct()->orderBy('department')->pluck('department')
            ->merge(\App\Models\Category::orderBy('name')->pluck('name'))->unique()->values();
        $units = Unit::orderBy('name')->pluck('name');
        $standardPanels = StandardPanelLibrary::names();
        $installedPanels = StandardPanelLibrary::installed();

        return view('templates', compact('templates', 'catalog', 'departments', 'units', 'standardPanels', 'installedPanels'));
    }

    public function show($id)
    {
        $template = ReportTemplate::with(['items', 'billingTests:id,name,price,report_template_id'])->findOrFail($id);

        return response()->json($template);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $template = DB::transaction(fn () => $this->save(new ReportTemplate(), $data));

        return response()->json(['success' => 'Panel created successfully!', 'id' => $template->id]);
    }

    public function update(Request $request, $id)
    {
        $template = ReportTemplate::findOrFail($id);
        $data = $this->validated($request, (int) $id);
        DB::transaction(fn () => $this->save($template, $data));

        return response()->json(['success' => 'Panel updated successfully!', 'id' => $template->id]);
    }

    public function destroy($id)
    {
        $template = ReportTemplate::findOrFail($id);
        $template->delete(); // billing tests are unlinked by the foreign key (set null)

        return response()->json(['success' => 'Panel deleted successfully!']);
    }

    public function duplicate($id)
    {
        $source = ReportTemplate::with('items')->findOrFail($id);

        $copy = DB::transaction(function () use ($source) {
            $name = $source->name . ' (Copy)';
            for ($n = 2; ReportTemplate::where('name', $name)->exists(); $n++) {
                $name = $source->name . " (Copy {$n})";
            }
            $copy = $source->replicate(['items_count']);
            $copy->name = $name;
            $copy->save();
            $copy->items()->createMany($source->items->map(fn ($i) => collect($i->getAttributes())
                ->except(['id', 'report_template_id', 'created_at', 'updated_at'])->all())->all());

            return $copy;
        });

        return response()->json(['success' => 'Panel duplicated.', 'id' => $copy->id]);
    }

    public function installStandard(Request $request, StandardPanelLibrary $library)
    {
        $request->validate(['panels' => 'nullable|array', 'panels.*' => 'string']);
        $result = $library->install($request->input('panels'));

        return response()->json([
            'success' => count($result['created'])
                ? 'Installed: ' . implode(', ', $result['created']) . '. Please review reference ranges against your analyser / kit inserts and set prices.'
                : 'All selected standard panels are already installed.',
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ]);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:report_templates,name' . ($id ? ',' . $id : ''),
            'department' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'interpretation' => 'nullable|string|max:5000',
            'is_active' => 'nullable|boolean',
            'billing_test_ids' => 'nullable|array',
            'billing_test_ids.*' => 'integer|exists:lab_tests,id',
            'items' => 'required|array|min:1',
            'items.*.item_type' => 'required|in:parameter,heading',
            'items.*.name' => 'required|string|max:255',
            'items.*.lab_test_id' => 'nullable|integer|exists:lab_tests,id',
            'items.*.code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/'],
            'items.*.unit' => 'nullable|string|max:100',
            'items.*.normal_value' => 'nullable|string|max:1000',
            'items.*.default_value' => 'nullable|string|max:255',
            'items.*.options' => 'nullable|string|max:500',
            'items.*.formula' => 'nullable|string|max:255',
            'items.*.decimals' => 'nullable|integer|min:0|max:6',
            'items.*.ranges' => 'nullable|array',
            'items.*.ranges.male_min' => 'nullable|numeric',
            'items.*.ranges.male_max' => 'nullable|numeric',
            'items.*.ranges.female_min' => 'nullable|numeric',
            'items.*.ranges.female_max' => 'nullable|numeric',
        ], [
            'items.required' => 'Add at least one parameter to the panel.',
            'items.*.code.regex' => 'Codes start with a letter and use only letters, digits and _ (e.g. HB, RBC_COUNT).',
        ]);

        // Codes unique within the panel; formulas valid and only referencing codes in this panel.
        $errors = [];
        $codes = [];
        foreach ($data['items'] as $i => $item) {
            if ($item['item_type'] !== 'parameter' || empty($item['code'])) {
                continue;
            }
            $code = strtoupper($item['code']);
            if (isset($codes[$code])) {
                $errors["items.$i.code"] = "Code {$code} is used twice in this panel.";
            }
            $codes[$code] = $i;
        }
        foreach ($data['items'] as $i => $item) {
            if ($item['item_type'] !== 'parameter' || empty($item['formula'])) {
                continue;
            }
            try {
                FormulaService::validate($item['formula']);
                foreach (FormulaService::references($item['formula']) as $ref) {
                    if (!isset($codes[$ref])) {
                        $errors["items.$i.formula"] = "\"{$item['name']}\" uses {{$ref}}, but no parameter in this panel has the code {$ref}.";
                    } elseif (!empty($item['code']) && strtoupper($item['code']) === $ref) {
                        $errors["items.$i.formula"] = "\"{$item['name']}\" cannot use its own value in its formula.";
                    }
                }
            } catch (\InvalidArgumentException $e) {
                $errors["items.$i.formula"] = "\"{$item['name']}\": " . $e->getMessage();
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    private function save(ReportTemplate $template, array $data): ReportTemplate
    {
        $template->fill([
            'name' => trim($data['name']),
            'department' => $data['department'] ?? null,
            'description' => $data['description'] ?? null,
            'interpretation' => $data['interpretation'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ])->save();

        // Parameters typed into the panel that don't exist yet become real parameters,
        // so they can carry reference ranges and be flagged.
        foreach ($data['items'] as $i => $item) {
            if ($item['item_type'] !== 'parameter') {
                continue;
            }
            if (empty($item['lab_test_id'])) {
                $labTest = LabTest::create([
                    'name' => trim($item['name']),
                    'price' => 0,
                    'description' => 'Part of ' . trim($data['name']),
                    'payment_method' => 'Cash',
                ]);
                $data['items'][$i]['lab_test_id'] = $labTest->id;
            }
            $this->saveRanges((int) $data['items'][$i]['lab_test_id'], $item);
        }

        $template->items()->delete();
        $template->items()->createMany(collect($data['items'])->values()->map(fn ($item, $i) => [
            'item_type' => $item['item_type'],
            'lab_test_id' => $item['item_type'] === 'parameter' ? ($item['lab_test_id'] ?? null) : null,
            'category' => $data['department'] ?? 'General',
            'name' => trim($item['name']),
            'code' => !empty($item['code']) ? strtoupper($item['code']) : null,
            'unit' => $item['unit'] ?? null,
            'normal_value' => $item['normal_value'] ?? null,
            'default_value' => $item['default_value'] ?? null,
            'options' => $item['options'] ?? null,
            'formula' => $item['formula'] ?? null,
            'decimals' => $item['decimals'] ?? null,
            'sort_order' => $i,
        ])->all());

        // Which billing tests expand into this panel
        if (array_key_exists('billing_test_ids', $data)) {
            LabTest::where('report_template_id', $template->id)
                ->whereNotIn('id', $data['billing_test_ids'] ?: [0])
                ->update(['report_template_id' => null]);
            if (!empty($data['billing_test_ids'])) {
                LabTest::whereIn('id', $data['billing_test_ids'])->update(['report_template_id' => $template->id]);
            }
        }

        return $template;
    }

    /** Ranges edited inside the panel builder go to the parameter master (used for flags). */
    private function saveRanges(int $labTestId, array $item): void
    {
        $parameter = \App\Models\TestParameter::firstOrNew(['lab_test_id' => $labTestId]);
        $text = fn ($min, $max) => ($min !== null && $max !== null) ? "{$min} - {$max}"
            : ($max !== null ? "< {$max}" : ($min !== null ? "> {$min}" : null));
        $num = fn ($v) => ($v === null || $v === '') ? null : (float) $v + 0;

        if (empty($parameter->unit) && !empty($item['unit'])) {
            $parameter->unit = $item['unit'];
        }

        if (isset($item['ranges']) && is_array($item['ranges'])) {
            foreach (['male', 'female'] as $sex) {
                $oldAuto = $text($num($parameter->{"{$sex}_min"}), $num($parameter->{"{$sex}_max"}));
                foreach (['min', 'max'] as $end) {
                    if (array_key_exists("{$sex}_{$end}", $item['ranges'])) {
                        $parameter->{"{$sex}_{$end}"} = $num($item['ranges']["{$sex}_{$end}"]);
                    }
                }
                // Refresh the printed range only if it was generated from the numbers (keep custom wording).
                $current = $parameter->{"{$sex}_reference"};
                if ($current === null || $current === '' || $current === $oldAuto) {
                    $parameter->{"{$sex}_reference"} = $text($num($parameter->{"{$sex}_min"}), $num($parameter->{"{$sex}_max"}));
                }
            }
        }

        if (!$parameter->exists || $parameter->isDirty()) {
            $parameter->save();
            \App\Services\ReferenceRangeService::syncDefaultIntervals($parameter);
        }
    }
}
