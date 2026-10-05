<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReceipt extends Model
{
    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(PurchaseItem::class, 'purchase_receipt_id');
    }
}
