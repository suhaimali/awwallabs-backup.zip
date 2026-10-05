<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportTemplate extends Model
{
    protected $fillable = ['name', 'department', 'description', 'interpretation', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function items()
    {
        return $this->hasMany(ReportTemplateItem::class, 'report_template_id')->orderBy('sort_order');
    }

    /** Billing tests that expand into this panel when results are entered. */
    public function billingTests()
    {
        return $this->hasMany(LabTest::class, 'report_template_id');
    }
}
