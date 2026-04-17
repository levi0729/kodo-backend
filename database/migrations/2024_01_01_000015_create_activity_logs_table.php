<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('action', 100);
            $table->string('target_type', 50)->nullable();
            $table->unsignedInteger('target_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id', 'fk_activity_logs_user')->references('id')->on('users')->onDelete('cascade');

            $table->index(['user_id', 'created_at'], 'idx_activity_logs_user');
            $table->index(['target_type', 'target_id'], 'idx_activity_logs_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
