<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->increments('business_id');
            $table->unsignedInteger('owner_admin_id')->nullable()->unique();
            $table->string('business_name', 120)->default('Alz Point');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('owner_admin_id')->references('user_id')->on('users')->nullOnDelete();
        });

        foreach (['users', 'categories', 'products', 'orders', 'cooperative_settings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('business_id')->nullable()->index();
            });
        }

        $primaryBusinessId = null;
        $admins = DB::table('users')->where('role', 'admin')->orderBy('user_id')->get();

        foreach ($admins as $admin) {
            $businessId = DB::table('businesses')->insertGetId([
                'owner_admin_id' => $admin->user_id,
                'business_name' => trim($admin->full_name).' - Usaha',
                'created_at' => now(),
            ]);

            $primaryBusinessId ??= $businessId;
            DB::table('users')->where('user_id', $admin->user_id)->update(['business_id' => $businessId]);
        }

        if ($primaryBusinessId === null) {
            $hasLegacyData = collect(['users', 'categories', 'products', 'orders', 'cooperative_settings'])
                ->contains(fn (string $tableName) => DB::table($tableName)->exists());

            if ($hasLegacyData) {
                $primaryBusinessId = DB::table('businesses')->insertGetId([
                    'owner_admin_id' => null,
                    'business_name' => 'Alz Point - Data Lama',
                    'created_at' => now(),
                ]);
            }
        }

        if ($primaryBusinessId !== null) {
            foreach (['users', 'categories', 'products', 'orders', 'cooperative_settings'] as $tableName) {
                DB::table($tableName)->whereNull('business_id')->update(['business_id' => $primaryBusinessId]);
            }
        }

        foreach (DB::table('businesses')->get() as $business) {
            foreach ([
                ['Makanan', 'Makanan dan hidangan'],
                ['Minuman', 'Minuman dingin dan kemasan'],
                ['Snack', 'Camilan dan makanan ringan'],
                ['Alat Tulis', 'Alat tulis dan perlengkapan'],
            ] as [$categoryName, $description]) {
                $categoryExists = DB::table('categories')
                    ->where('business_id', $business->business_id)
                    ->where('category_name', $categoryName)
                    ->exists();

                if (! $categoryExists) {
                    DB::table('categories')->insert([
                        'business_id' => $business->business_id,
                        'category_name' => $categoryName,
                        'description' => $description,
                    ]);
                }
            }

            if (! DB::table('cooperative_settings')->where('business_id', $business->business_id)->exists()) {
                DB::table('cooperative_settings')->insert([
                    'business_id' => $business->business_id,
                    'cooperative_name' => 'Alz Point',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('business_id')->references('business_id')->on('businesses')->nullOnDelete();
        });

        foreach (['categories', 'products', 'orders', 'cooperative_settings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('business_id')->references('business_id')->on('businesses')->cascadeOnDelete();
            });
        }

        Schema::table('cooperative_settings', function (Blueprint $table) {
            $table->unique('business_id');
        });
    }

    public function down(): void
    {
        Schema::table('cooperative_settings', function (Blueprint $table) {
            $table->dropUnique(['business_id']);
            $table->dropForeign(['business_id']);
            $table->dropIndex(['business_id']);
            $table->dropColumn('business_id');
        });

        foreach (['orders', 'products', 'categories', 'users'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['business_id']);
                $table->dropIndex(['business_id']);
                $table->dropColumn('business_id');
            });
        }

        Schema::dropIfExists('businesses');
    }
};