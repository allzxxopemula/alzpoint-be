<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->increments('id');
            $table->decimal('min_spend', 12, 2);
            $table->decimal('discount_amount', 12, 2);
            $table->unsignedInteger('business_id');
            $table->foreign('business_id')->references('business_id')->on('businesses')->cascadeOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)->default(0)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
        });

        Schema::dropIfExists('discounts');
    }
};