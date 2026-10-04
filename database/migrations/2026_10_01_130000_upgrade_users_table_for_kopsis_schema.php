<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable();
            $table->enum('role', ['admin', 'kasir', 'pelanggan'])->default('admin');
        });

        DB::table('users')->orderBy('id')->get(['id'])->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'username' => 'legacy_'.$user->id,
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->renameColumn('id', 'user_id');
            $table->renameColumn('name', 'full_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->autoIncrement()->change();
            $table->string('username', 50)->nullable(false)->unique()->change();
            $table->string('full_name', 100)->change();
            $table->timestamp('created_at')->useCurrent()->change();
            $table->dropColumn(['email', 'email_verified_at', 'remember_token', 'updated_at']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->dropUnique('users_username_unique');
            $table->renameColumn('user_id', 'id');
            $table->renameColumn('full_name', 'name');
        });

        DB::table('users')->get(['id', 'username'])->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'email' => $user->username.'@legacy.invalid',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'role']);
        });
    }
};
