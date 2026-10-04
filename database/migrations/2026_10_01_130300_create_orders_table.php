<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('order_id');
            $table->string('order_number', 30)->unique();
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('cashier_id')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('status', ['pending', 'confirmed', 'canceled'])->default('pending');
            $table->enum('payment_method', ['tunai', 'qris', 'transfer']);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('customer_id')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('cashier_id')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
