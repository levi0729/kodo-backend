<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chat rooms (direct messages)
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('room_id');
            $table->unsignedInteger('sender_id');
            $table->unsignedInteger('receiver_id');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->foreign('sender_id', 'fk_chatrooms_sender')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('receiver_id', 'fk_chatrooms_receiver')->references('id')->on('users')->onDelete('cascade');

            $table->index('room_id', 'idx_chatrooms_room');
            $table->index('sender_id', 'idx_chatrooms_sender');
            $table->index('receiver_id', 'idx_chatrooms_receiver');
        });

        DB::unprepared('
            CREATE TRIGGER trg_chat_rooms_updated_at
                BEFORE UPDATE ON chat_rooms
                FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
        ');

        // Conversations (group chats - future use)
        Schema::create('conversations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('conversation_type', 10)->default('direct');
            $table->string('name', 255)->nullable();
            $table->string('icon_url', 500)->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->foreign('created_by', 'fk_conversations_creator')->references('id')->on('users')->onDelete('set null');
        });

        DB::statement("ALTER TABLE conversations ADD CONSTRAINT chk_conversations_type CHECK (conversation_type IN ('direct','group'))");

        DB::unprepared('
            CREATE TRIGGER trg_conversations_updated_at
                BEFORE UPDATE ON conversations
                FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
        ');

        // Conversation participants
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('conversation_id');
            $table->unsignedInteger('user_id');
            $table->string('role', 10)->default('member');
            $table->boolean('is_muted')->default(false);
            $table->timestamp('last_read_at')->nullable();
            $table->unsignedInteger('last_read_message_id')->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();

            $table->unique(['conversation_id', 'user_id'], 'uk_conv_participants');
            $table->foreign('conversation_id', 'fk_conv_participants_conv')->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('user_id', 'fk_conv_participants_user')->references('id')->on('users')->onDelete('cascade');

            $table->index('user_id', 'idx_conv_participants_user');
        });

        DB::statement("ALTER TABLE conversation_participants ADD CONSTRAINT chk_conv_participants_role CHECK (role IN ('admin','member'))");

        // Messages (channel messages)
        Schema::create('messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('channel_id')->nullable();
            $table->unsignedInteger('conversation_id')->nullable();
            $table->unsignedInteger('parent_message_id')->nullable();
            $table->integer('thread_reply_count')->default(0);
            $table->timestamp('thread_last_reply_at')->nullable();
            $table->unsignedInteger('sender_id');
            $table->text('content')->nullable();
            $table->string('content_type', 20)->default('text');
            $table->jsonb('formatted_content')->nullable();
            $table->boolean('has_attachments')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_announcement')->default(false);
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('channel_id', 'fk_messages_channel')->references('id')->on('channels')->onDelete('cascade');
            $table->foreign('conversation_id', 'fk_messages_conversation')->references('id')->on('conversations')->onDelete('cascade');
            $table->foreign('parent_message_id', 'fk_messages_parent')->references('id')->on('messages')->onDelete('cascade');
            $table->foreign('sender_id', 'fk_messages_sender')->references('id')->on('users');

            $table->index(['channel_id', 'created_at'], 'idx_messages_channel');
            $table->index(['conversation_id', 'created_at'], 'idx_messages_conversation');
            $table->index('parent_message_id', 'idx_messages_thread');
            $table->index('sender_id', 'idx_messages_sender');
        });

        DB::statement("ALTER TABLE messages ADD CONSTRAINT chk_messages_content_type CHECK (content_type IN ('text','rich_text','code','system','mixed'))");

        // Full-text search index (PostgreSQL GIN)
        DB::statement("CREATE INDEX idx_messages_content_fts ON messages USING GIN (to_tsvector('hungarian', COALESCE(content, '')))");

        // Message attachments
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('message_id');
            $table->string('file_name', 255);
            $table->string('file_type', 100)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('file_url', 500);
            $table->string('thumbnail_url', 500)->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->unsignedInteger('uploaded_by');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('message_id', 'fk_attachments_message')->references('id')->on('messages')->onDelete('cascade');
            $table->foreign('uploaded_by', 'fk_attachments_uploader')->references('id')->on('users');

            $table->index('message_id', 'idx_attachments_message');
        });

        // Message reactions
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('message_id');
            $table->unsignedInteger('user_id');
            $table->string('emoji', 50);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['message_id', 'user_id', 'emoji'], 'uk_reactions');
            $table->foreign('message_id', 'fk_reactions_message')->references('id')->on('messages')->onDelete('cascade');
            $table->foreign('user_id', 'fk_reactions_user')->references('id')->on('users')->onDelete('cascade');

            $table->index('message_id', 'idx_reactions_message');
        });

        // Message mentions
        Schema::create('message_mentions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('message_id');
            $table->string('mention_type', 20);
            $table->unsignedInteger('mentioned_id')->nullable();

            $table->unique(['message_id', 'mention_type', 'mentioned_id'], 'uk_mentions');
            $table->foreign('message_id', 'fk_mentions_message')->references('id')->on('messages')->onDelete('cascade');

            $table->index('message_id', 'idx_mentions_message');
        });

        DB::statement("ALTER TABLE message_mentions ADD CONSTRAINT chk_mentions_type CHECK (mention_type IN ('user','team','channel','everyone','here'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('message_mentions');
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_conversations_updated_at ON conversations');
        Schema::dropIfExists('conversations');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_chat_rooms_updated_at ON chat_rooms');
        Schema::dropIfExists('chat_rooms');
    }
};
