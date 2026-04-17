<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->string('room_type', 10)->default('dm')->after('room_id');
        });

        // Back-fill existing rows: team rooms have room_id matching a team record
        DB::statement("
            UPDATE chat_rooms
            SET room_type = 'team'
            WHERE room_id IN (SELECT id FROM teams)
        ");

        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->index('room_type');
        });
    }

    public function down(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->dropIndex(['room_type']);
            $table->dropColumn('room_type');
        });
    }
};
