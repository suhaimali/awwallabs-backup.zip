<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'patient_id',
        'payment_id',
        'doctor_name',
        'test_name',
        'test_price',
        'discount',
        'advance_paid',
        'balance',
        'appointment_date',
        'appointment_time',
        'status',
        'reason',
        'total_amount',
        'notes',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
