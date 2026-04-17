<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('code', 6);
            $table->string('method', 10);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id', 'fk_verification_codes_user')->references('id')->on('users')->onDelete('cascade');

            $table->index(['user_id', 'created_at'], 'idx_verification_codes_user');
        });

        DB::statement("ALTER TABLE verification_codes ADD CONSTRAINT chk_verification_codes_method CHECK (method IN ('email','sms'))");

        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('device_token', 255)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id', 'fk_trusted_devices_user')->references('id')->on('users')->onDelete('cascade');

            $table->index('user_id', 'idx_trusted_devices_user');
            $table->index('device_token', 'idx_trusted_devices_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
        Schema::dropIfExists('verification_codes');
    }
};
