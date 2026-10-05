<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestReportItem extends Model
{
    protected $fillable = [
        'test_report_id',
        'lab_test_id',
        'report_template_id',
        'category',
        'subcategory',
        'panel_name',
        'name',
        'observed_value',
        'unit',
        'normal_value',
        'biological_reference',
        'flag',
        'sort_order',
    ];

    public function report()
    {
        return $this->belongsTo(TestReport::class, 'test_report_id');
    }

    public function labTest()
    {
        return $this->belongsTo(LabTest::class);
    }
}
