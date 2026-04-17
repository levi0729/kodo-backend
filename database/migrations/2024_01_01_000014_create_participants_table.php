<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->increments('id');
            $table->string('entity_type', 20);
            $table->unsignedInteger('entity_id');
            $table->unsignedInteger('user_id');
            $table->string('role', 20)->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['entity_type', 'entity_id', 'user_id'], 'uk_participants');
            $table->foreign('user_id', 'fk_participants_user')->references('id')->on('users')->onDelete('cascade');

            $table->index(['entity_type', 'entity_id'], 'idx_participants_entity');
            $table->index('user_id', 'idx_participants_user');
        });

        DB::statement("ALTER TABLE participants ADD CONSTRAINT chk_participants_entity_type CHECK (entity_type IN ('project','team'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
