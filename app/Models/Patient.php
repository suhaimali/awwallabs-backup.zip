<?php

namespace App\Models;

use App\Models\Concerns\HasPublicToken;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasPublicToken;

    protected $fillable = [
        'patient_id',
        'first_name',
        'last_name',
        'gender',
        'age',
        'age_type',
        'phone',
        'address',
        'email',
        'reference_dr',
        'status',
        'total_amount',
        'discount',
        'advance_paid',
        'balance',
        'payment_method',
    ];

    protected $hidden = ['public_token'];

    protected $appends = ['public_url'];

    protected $casts = [
        'total_amount' => 'float',
        'discount' => 'float',
        'advance_paid' => 'float',
        'balance' => 'float',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function vitalSigns()
    {
        return $this->hasMany(VitalSign::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function testReports()
    {
        return $this->hasMany(TestReport::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function publicRouteName(): string
    {
        return 'public.patient';
    }
}
