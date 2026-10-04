<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'confirmed', 'completed', 'canceled'])->default('pending')->change();
        });

        DB::table('orders')->where('status', 'confirmed')->update(['status' => 'completed']);

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'completed', 'canceled'])->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'confirmed', 'completed', 'canceled'])->default('pending')->change();
        });

        DB::table('orders')->where('status', 'completed')->update(['status' => 'confirmed']);

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'confirmed', 'canceled'])->default('pending')->change();
        });
    }
};