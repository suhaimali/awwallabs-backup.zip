<?php

namespace App\Services;

use App\Models\LabTest;
use App\Models\Patient;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateItem;

/**
 * Builds the rows shown in the result-entry grid.
 *
 * Row kinds:
 *  - panel:   title row of an expanded panel (carries the interpretation note)
 *  - heading: sub-heading inside a panel, e.g. "Differential Count"
 *  - param:   a result to enter
 */
class ReportBuilder
{
    /** Rows for the tests booked at the front desk (panels expand, single tests stay single). */
    public function forTestNames(array $names, ?Patient $patient): array
    {
        $rows = [];
        $seenPanels = [];

        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $candidates = LabTest::with(['parameter', 'referenceIntervals'])
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
                ->orderByRaw('report_template_id IS NULL')
                ->get();

            $panelId = $candidates->firstWhere('report_template_id', '!=', null)?->report_template_id
                ?? ReportTemplate::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])->value('id');

            if ($panelId) {
                if (!isset($seenPanels[$panelId]) && ($panel = ReportTemplate::find($panelId))) {
                    $seenPanels[$panelId] = true;
                    array_push($rows, ...$this->forTemplate($panel, $patient));
                }
                continue;
            }

            $labTest = $candidates->first(fn ($t) => $t->parameter !== null) ?? $candidates->first();
            $rows[] = $labTest ? $this->paramRow($labTest, $patient) : $this->bareRow($name);
        }

        return $rows;
    }

    public function forTemplate(ReportTemplate $panel, ?Patient $patient): array
    {
        $panel->loadMissing(['items' => fn ($q) => $q->orderBy('sort_order')]);
        $labTests = LabTest::with(['parameter', 'referenceIntervals'])
            ->whereIn('id', $panel->items->pluck('lab_test_id')->filter()->unique())
            ->get()->keyBy('id');

        $department = $panel->department ?: $panel->name;
        $rows = [[
            'kind' => 'panel',
            'panel' => $panel->name,
            'panel_id' => $panel->id,
            'category' => $department,
            'interpretation' => $panel->interpretation,
        ]];

        $heading = null;
        foreach ($panel->items as $item) {
            if ($item->item_type === 'heading') {
                $heading = $item->name;
                $rows[] = ['kind' => 'heading', 'panel' => $panel->name, 'panel_id' => $panel->id, 'name' => $item->name];
                continue;
            }

            $row = $this->itemRow($item, $labTests->get($item->lab_test_id), $patient);
            $row['panel'] = $panel->name;
            $row['panel_id'] = $panel->id;
            $row['category'] = $department;
            $row['heading'] = $heading ?? ($item->subcategory ?: null);
            $rows[] = $row;
        }

        return $rows;
    }

    public function forLabTests(array $ids, ?Patient $patient): array
    {
        return LabTest::with(['parameter', 'referenceIntervals'])->whereIn('id', $ids)->get()
            ->sortBy(fn ($t) => array_search($t->id, $ids))
            ->map(fn ($t) => $this->paramRow($t, $patient))
            ->values()->all();
    }

    /**
     * Ranges/config for rows already on screen (after the patient changes, or when editing a saved report).
     *
     * @param array<int, array{lab_test_id: ?int, template_id: ?int}> $items
     */
    public function resolve(array $items, ?Patient $patient): array
    {
        $labTests = LabTest::with(['parameter', 'referenceIntervals'])
            ->whereIn('id', collect($items)->pluck('lab_test_id')->filter()->unique())
            ->get()->keyBy('id');
        $templateItems = ReportTemplateItem::whereIn('report_template_id', collect($items)->pluck('template_id')->filter()->unique())
            ->whereNotNull('lab_test_id')->get()
            ->keyBy(fn ($i) => $i->report_template_id . ':' . $i->lab_test_id);

        return collect($items)->map(function ($item) use ($labTests, $templateItems, $patient) {
            $labTest = $labTests->get($item['lab_test_id'] ?? 0);
            $templateItem = $templateItems->get(($item['template_id'] ?? 0) . ':' . ($item['lab_test_id'] ?? 0));

            return $templateItem
                ? $this->itemRow($templateItem, $labTest, $patient)
                : ($labTest ? $this->paramRow($labTest, $patient) : null);
        })->values()->all();
    }

    /** Search catalogue for the "Add test or panel" box. */
    public function catalog(): array
    {
        $panels = ReportTemplate::where('is_active', true)->withCount(['items as params_count' => fn ($q) => $q->where('item_type', 'parameter')])
            ->orderBy('name')->get(['id', 'name', 'department'])
            ->map(fn ($p) => ['t' => 'panel', 'id' => $p->id, 'n' => $p->name, 'c' => $p->params_count, 'd' => $p->department]);
        $tests = LabTest::orderBy('name')->get(['id', 'name'])
            ->map(fn ($t) => ['t' => 'test', 'id' => $t->id, 'n' => $t->name]);

        return $panels->concat($tests)->values()->all();
    }

    private function itemRow(ReportTemplateItem $item, ?LabTest $labTest, ?Patient $patient): array
    {
        $row = $labTest ? $this->paramRow($labTest, $patient) : $this->bareRow($item->name);

        $row['name'] = $item->name ?: $row['name'];
        $row['unit'] = $item->unit ?: $row['unit'];
        // A patient-specific interval wins; otherwise the panel's own reference text.
        if (empty($row['range_specific'])) {
            $row['reference'] = $item->normal_value ?: ($item->biological_reference ?: $row['reference']);
        }
        $row['code'] = $item->code ? strtoupper($item->code) : null;
        $row['default_value'] = $item->default_value;
        $row['options'] = self::parseOptions($item->options);
        $row['formula'] = $item->formula;
        $row['decimals'] = $item->decimals;
        $row['template_item_id'] = $item->id;

        return $row;
    }

    private function paramRow(LabTest $labTest, ?Patient $patient): array
    {
        $parameter = $labTest->parameter;
        $range = ReferenceRangeService::resolve($labTest, $patient);
        $hasInterval = $labTest->referenceIntervals->isNotEmpty();

        return [
            'kind' => 'param',
            'panel' => null,
            'panel_id' => null,
            'category' => null,
            'heading' => null,
            'lab_test_id' => $labTest->id,
            'name' => $labTest->name,
            'unit' => $parameter?->unit,
            'reference' => $range['text'] ?? ($parameter?->biological_reference),
            'range_specific' => $hasInterval && !empty($range['text']),
            'min' => self::num($range['min'] ?? null),
            'max' => self::num($range['max'] ?? null),
            'critical_low' => self::num($parameter?->critical_low),
            'critical_high' => self::num($parameter?->critical_high),
            'immunoassay' => (bool) ($parameter?->is_immunoassay),
            'code' => null,
            'default_value' => null,
            'options' => [],
            'formula' => null,
            'decimals' => null,
        ];
    }

    private function bareRow(string $name): array
    {
        return [
            'kind' => 'param', 'panel' => null, 'panel_id' => null, 'category' => null, 'heading' => null,
            'lab_test_id' => null, 'name' => $name, 'unit' => null, 'reference' => null, 'range_specific' => false,
            'min' => null, 'max' => null, 'critical_low' => null, 'critical_high' => null, 'immunoassay' => false,
            'code' => null, 'default_value' => null, 'options' => [], 'formula' => null, 'decimals' => null,
        ];
    }

    /** "Nil, Trace, +*, ++*" -> values; a trailing * marks an abnormal choice. */
    public static function parseOptions(?string $options): array
    {
        if (!$options) {
            return [];
        }

        return collect(preg_split('/[,|\n]/', $options))
            ->map(fn ($o) => trim($o))
            ->filter(fn ($o) => $o !== '' && $o !== '*')
            ->map(fn ($o) => str_ends_with($o, '*')
                ? ['value' => rtrim(substr($o, 0, -1)), 'abnormal' => true]
                : ['value' => $o, 'abnormal' => false])
            ->values()->all();
    }

    private static function num(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
