<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\ReportTemplate;
use App\Services\ReportBuilder;
use Illuminate\Http\Request;

/**
 * Supplies result-entry rows: expand a panel, add single tests,
 * or re-resolve ranges when the patient changes / a saved report is edited.
 */
class ReportBuilderController extends Controller
{
    public function rows(Request $request, ReportBuilder $builder)
    {
        $data = $request->validate([
            'patient_id' => 'nullable|integer|exists:patients,id',
            'template_id' => 'nullable|integer|exists:report_templates,id',
            'tests' => 'nullable|array',
            'tests.*' => 'string|max:255',
            'lab_test_ids' => 'nullable|array',
            'lab_test_ids.*' => 'integer',
            'resolve' => 'nullable|array',
            'resolve.*.lab_test_id' => 'nullable|integer',
            'resolve.*.template_id' => 'nullable|integer',
        ]);

        $patient = !empty($data['patient_id']) ? Patient::find($data['patient_id']) : null;

        if (!empty($data['template_id'])) {
            return response()->json(['rows' => $builder->forTemplate(ReportTemplate::findOrFail($data['template_id']), $patient)]);
        }
        if (!empty($data['tests'])) {
            return response()->json(['rows' => $builder->forTestNames($data['tests'], $patient)]);
        }
        if (!empty($data['lab_test_ids'])) {
            return response()->json(['rows' => $builder->forLabTests($data['lab_test_ids'], $patient)]);
        }
        if (!empty($data['resolve'])) {
            return response()->json(['meta' => $builder->resolve($data['resolve'], $patient)]);
        }

        return response()->json(['rows' => []]);
    }
}
