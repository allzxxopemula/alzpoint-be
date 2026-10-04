<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    protected $primaryKey = 'business_id';

    public $timestamps = false;

    protected $fillable = ['owner_admin_id', 'business_name'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_admin_id', 'user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'business_id', 'business_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'business_id', 'business_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'business_id', 'business_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'business_id', 'business_id');
    }
}