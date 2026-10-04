<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Discount extends Model
{
    public $timestamps = false;

    protected $fillable = ['business_id', 'min_spend', 'discount_amount'];

    protected function casts(): array
    {
        return [
            'min_spend' => 'decimal:2',
            'discount_amount' => 'decimal:2',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'business_id', 'business_id');
    }
}