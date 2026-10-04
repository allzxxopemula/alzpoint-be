<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CooperativeSetting extends Model
{
    protected $fillable = ['business_id', 'cooperative_name', 'address', 'phone', 'email', 'receipt_footer'];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id', 'business_id');
    }
}