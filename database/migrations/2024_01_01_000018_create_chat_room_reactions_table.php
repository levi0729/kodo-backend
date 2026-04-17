<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_room_reactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('chat_room_id');
            $table->unsignedInteger('user_id');
            $table->string('emoji', 50);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['chat_room_id', 'user_id', 'emoji'], 'uk_chat_room_reactions');

            $table->foreign('chat_room_id', 'fk_chat_room_reactions_room')
                ->references('id')->on('chat_rooms')->onDelete('cascade');
            $table->foreign('user_id', 'fk_chat_room_reactions_user')
                ->references('id')->on('users')->onDelete('cascade');

            $table->index('chat_room_id', 'idx_chat_room_reactions_room');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_room_reactions');
    }
};
