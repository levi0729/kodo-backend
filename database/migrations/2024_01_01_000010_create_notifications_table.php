<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('notification_type', 50);
            $table->unsignedInteger('actor_id')->nullable();
            $table->unsignedInteger('team_id')->nullable();
            $table->unsignedInteger('channel_id')->nullable();
            $table->unsignedInteger('message_id')->nullable();
            $table->unsignedInteger('task_id')->nullable();
            $table->unsignedInteger('event_id')->nullable();
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->string('action_url', 500)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_push_sent')->default(false);
            $table->boolean('is_email_sent')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id', 'fk_notifications_user')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('actor_id', 'fk_notifications_actor')->references('id')->on('users')->onDelete('set null');
            $table->foreign('team_id', 'fk_notifications_team')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('channel_id', 'fk_notifications_channel')->references('id')->on('channels')->onDelete('cascade');
            $table->foreign('task_id', 'fk_notifications_task')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('event_id', 'fk_notifications_event')->references('id')->on('calendar_events')->onDelete('cascade');

            $table->index(['user_id', 'is_read', 'created_at'], 'idx_notifications_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
