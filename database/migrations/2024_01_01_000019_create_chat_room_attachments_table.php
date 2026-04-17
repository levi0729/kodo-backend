<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_room_attachments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('chat_room_id');
            $table->unsignedInteger('uploaded_by');
            $table->string('file_name');
            $table->string('file_type', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('file_url', 500);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('chat_room_id', 'fk_chat_room_attachments_room')
                ->references('id')->on('chat_rooms')->onDelete('cascade');
            $table->foreign('uploaded_by', 'fk_chat_room_attachments_user')
                ->references('id')->on('users')->onDelete('cascade');

            $table->index('chat_room_id', 'idx_chat_room_attachments_room');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_room_attachments');
    }
};
