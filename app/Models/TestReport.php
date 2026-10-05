<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasPublicToken;

class TestReport extends Model
{
    use SoftDeletes, HasPublicToken;

    protected $fillable = [
        'patient_id',
        'doctor_name',
        'sample_received_on',
        'report_released_on',
        'barcode',
        'status',
        'notes',
        'panel_notes',
        'report_signature_id',
    ];

    protected $hidden = ['public_token'];

    protected $appends = ['public_url'];

    protected $casts = [
        'sample_received_on' => 'datetime',
        'report_released_on' => 'datetime',
        'panel_notes' => 'array',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function items()
    {
        return $this->hasMany(TestReportItem::class)->orderBy('sort_order');
    }

    public function audits()
    {
        return $this->hasMany(TestReportAudit::class);
    }

    public function signature()
    {
        return $this->belongsTo(ReportSignature::class, 'report_signature_id');
    }

    public function publicRouteName(): string
    {
        return 'public.report';
    }
}
