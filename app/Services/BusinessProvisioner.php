<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Category;
use App\Models\CooperativeSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BusinessProvisioner
{
    public function provision(User $admin): Business
    {
        return DB::transaction(function () use ($admin): Business {
            $business = Business::firstOrCreate(
                ['owner_admin_id' => $admin->user_id],
                ['business_name' => trim($admin->full_name).' Business'],
            );

            if ($admin->business_id !== $business->business_id) {
                $admin->forceFill(['business_id' => $business->business_id])->saveQuietly();
            }

            foreach ([
                ['Makanan', 'Makanan dan hidangan'],
                ['Minuman', 'Minuman dingin dan kemasan'],
                ['Snack', 'Camilan dan makanan ringan'],
                ['Alat Tulis', 'Alat tulis dan perlengkapan'],
            ] as [$name, $description]) {
                $category = Category::firstOrCreate(
                    ['business_id' => $business->business_id, 'category_name' => $name],
                    ['description' => $description, 'is_default' => true],
                );
                if (! $category->is_default) {
                    $category->update(['is_default' => true]);
                }
            }

            CooperativeSetting::firstOrCreate(
                ['business_id' => $business->business_id],
                ['cooperative_name' => 'Alz Point'],
            );

            return $business;
        });
    }
}