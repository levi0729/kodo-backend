<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Base test case for API feature tests.
 *
 * Uses SQLite in-memory for speed. Skips Laravel migrations (they contain
 * PL/pgSQL) and builds a compatible schema manually.
 */
abstract class ApiTestCase extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * On SQLite, skip the standard migrate:fresh (which would fail on
     * PL/pgSQL) and build the schema ourselves.
     */
    protected function refreshTestDatabase(): void
    {
        if (DB::getDriverName() === 'sqlite' && !RefreshDatabaseState::$migrated) {
            $this->buildSqliteSchema();
            RefreshDatabaseState::$migrated = true;
        } elseif (!RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    /**
     * Create all necessary tables using SQLite-compatible schema.
     * Skips PG triggers, CHECK constraints, PL/pgSQL functions, GIN indexes, and views.
     */
    private function buildSqliteSchema(): void
    {
        // Drop tables that may have been partially created by failed PG migrations
        $tables = [
            'chat_room_attachments', 'chat_room_reactions',
            'password_reset_tokens', 'failed_jobs', 'job_batches', 'jobs',
            'cache_locks', 'cache', 'sessions',
            'activity_logs', 'participants',
            'friends',
            'time_entries',
            'user_settings',
            'notifications',
            'files',
            'event_attendees', 'calendar_events',
            'task_checklist_items', 'task_checklists', 'task_assignees', 'tasks', 'task_buckets',
            'message_mentions', 'message_reactions', 'message_attachments', 'messages',
            'conversation_participants', 'conversations',
            'chat_rooms',
            'channels',
            'team_members', 'teams',
            'projects',
            'organizations',
            'verification_codes', 'trusted_devices',
            'personal_access_tokens',
            'users',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        // ── Users ──
        Schema::create('users', function ($t) {
            $t->increments('id');
            $t->string('email', 255)->unique();
            $t->string('username', 100)->unique();
            $t->string('password', 255);
            $t->string('display_name', 150)->nullable();
            $t->string('job_title', 100)->nullable();
            $t->string('department', 100)->nullable();
            $t->string('phone_number', 50)->nullable();
            $t->string('avatar_url', 500)->nullable();
            $t->string('cover_image_url', 500)->nullable();
            $t->text('bio')->nullable();
            $t->string('timezone', 50)->default('Europe/Budapest');
            $t->string('locale', 10)->default('hu');
            $t->string('presence_status', 20)->default('offline');
            $t->string('presence_message', 255)->nullable();
            $t->timestamp('presence_expiry')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('is_verified')->default(false);
            $t->boolean('is_admin')->default(false);
            $t->integer('failed_login_attempts')->default(0);
            $t->timestamp('locked_until')->nullable();
            $t->string('remember_token', 100)->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        // ── Personal Access Tokens (Sanctum) ──
        Schema::create('personal_access_tokens', function ($t) {
            $t->bigIncrements('id');
            $t->string('tokenable_type', 255);
            $t->unsignedBigInteger('tokenable_id');
            $t->string('name', 255);
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->index(['tokenable_type', 'tokenable_id']);
        });

        // ── Organizations ──
        Schema::create('organizations', function ($t) {
            $t->increments('id');
            $t->string('name', 255);
            $t->string('slug', 100)->unique();
            $t->text('description')->nullable();
            $t->string('logo_url', 500)->nullable();
            $t->string('domain', 255)->nullable();
            $t->json('settings')->nullable();
            $t->json('allowed_email_domains')->nullable();
            $t->string('plan_type', 20)->default('free');
            $t->integer('max_members')->default(50);
            $t->integer('max_storage_gb')->default(5);
            $t->timestamps();
            $t->softDeletes();
        });

        // ── Projects ──
        Schema::create('projects', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('organization_id')->nullable();
            $t->string('name', 255);
            $t->string('slug', 100)->nullable();
            $t->text('description')->nullable();
            $t->string('color', 20)->default('#7360F9');
            $t->string('icon', 50)->nullable();
            $t->string('project_type', 20)->default('kanban');
            $t->string('status', 20)->default('active');
            $t->date('start_date')->nullable();
            $t->date('target_end_date')->nullable();
            $t->date('actual_end_date')->nullable();
            $t->smallInteger('progress')->default(0);
            $t->json('settings')->nullable();
            $t->unsignedInteger('owner_id');
            $t->timestamps();
            $t->softDeletes();
            $t->foreign('owner_id')->references('id')->on('users');
        });

        // ── Teams ──
        Schema::create('teams', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('organization_id')->nullable();
            $t->unsignedInteger('project_id')->nullable();
            $t->string('name', 255);
            $t->string('slug', 100)->nullable();
            $t->text('description')->nullable();
            $t->string('icon_url', 500)->nullable();
            $t->string('color', 20)->default('#7360F9');
            $t->string('visibility', 20)->default('private');
            $t->boolean('is_private')->default(false);
            $t->string('password_hash', 255)->nullable();
            $t->boolean('is_archived')->default(false);
            $t->timestamp('archived_at')->nullable();
            $t->unsignedInteger('archived_by')->nullable();
            $t->unsignedInteger('owner_id');
            $t->timestamps();
            $t->softDeletes();
            $t->foreign('owner_id')->references('id')->on('users');
            $t->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
        });

        Schema::create('team_members', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('team_id');
            $t->unsignedInteger('user_id');
            $t->string('role', 20)->default('member');
            $t->string('notification_level', 20)->default('all');
            $t->boolean('is_favorite')->default(false);
            $t->boolean('is_muted')->default(false);
            $t->timestamp('joined_at')->useCurrent();
            $t->unsignedInteger('invited_by')->nullable();
            $t->unique(['team_id', 'user_id']);
            $t->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ── Channels ──
        Schema::create('channels', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('team_id');
            $t->string('name', 255);
            $t->string('slug', 100);
            $t->text('description')->nullable();
            $t->string('channel_type', 20)->default('standard');
            $t->boolean('is_default')->default(false);
            $t->boolean('allow_threads')->default(true);
            $t->boolean('allow_reactions')->default(true);
            $t->boolean('allow_mentions')->default(true);
            $t->unsignedInteger('created_by');
            $t->timestamps();
            $t->softDeletes();
            $t->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $t->foreign('created_by')->references('id')->on('users');
        });

        // ── Chat rooms ──
        Schema::create('chat_rooms', function ($t) {
            $t->increments('id');
            $t->bigInteger('room_id');
            $t->string('room_type', 10)->default('dm');
            $t->unsignedInteger('sender_id');
            $t->unsignedInteger('receiver_id');
            $t->text('message');
            $t->boolean('is_read')->default(false);
            $t->boolean('is_pinned')->default(false);
            $t->boolean('is_deleted')->default(false);
            $t->timestamp('sent_at')->useCurrent();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('receiver_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ── Conversations ──
        Schema::create('conversations', function ($t) {
            $t->increments('id');
            $t->string('conversation_type', 10)->default('direct');
            $t->string('name', 255)->nullable();
            $t->string('icon_url', 500)->nullable();
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();
            $t->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('conversation_participants', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('conversation_id');
            $t->unsignedInteger('user_id');
            $t->string('role', 10)->default('member');
            $t->boolean('is_muted')->default(false);
            $t->timestamp('last_read_at')->nullable();
            $t->unsignedInteger('last_read_message_id')->nullable();
            $t->timestamp('joined_at')->useCurrent();
            $t->timestamp('left_at')->nullable();
            $t->unique(['conversation_id', 'user_id']);
            $t->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ── Messages ──
        Schema::create('messages', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('channel_id')->nullable();
            $t->unsignedInteger('conversation_id')->nullable();
            $t->unsignedInteger('parent_message_id')->nullable();
            $t->integer('thread_reply_count')->default(0);
            $t->timestamp('thread_last_reply_at')->nullable();
            $t->unsignedInteger('sender_id');
            $t->text('content')->nullable();
            $t->string('content_type', 20)->default('text');
            $t->json('formatted_content')->nullable();
            $t->boolean('has_attachments')->default(false);
            $t->boolean('is_pinned')->default(false);
            $t->boolean('is_announcement')->default(false);
            $t->boolean('is_edited')->default(false);
            $t->timestamp('edited_at')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->softDeletes();
            $t->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $t->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            $t->foreign('sender_id')->references('id')->on('users');
        });

        Schema::create('message_attachments', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('message_id');
            $t->string('file_name', 255);
            $t->string('file_type', 100)->nullable();
            $t->bigInteger('file_size')->nullable();
            $t->string('file_url', 500);
            $t->string('thumbnail_url', 500)->nullable();
            $t->integer('width')->nullable();
            $t->integer('height')->nullable();
            $t->integer('duration_seconds')->nullable();
            $t->unsignedInteger('uploaded_by');
            $t->timestamp('created_at')->useCurrent();
            $t->foreign('message_id')->references('id')->on('messages')->onDelete('cascade');
            $t->foreign('uploaded_by')->references('id')->on('users');
        });

        Schema::create('message_reactions', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('message_id');
            $t->unsignedInteger('user_id');
            $t->string('emoji', 50);
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['message_id', 'user_id', 'emoji']);
            $t->foreign('message_id')->references('id')->on('messages')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('message_mentions', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('message_id');
            $t->string('mention_type', 20);
            $t->unsignedInteger('mentioned_id')->nullable();
            $t->unique(['message_id', 'mention_type', 'mentioned_id']);
            $t->foreign('message_id')->references('id')->on('messages')->onDelete('cascade');
        });

        // ── Tasks ──
        Schema::create('task_buckets', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('project_id');
            $t->string('name', 100);
            $t->string('key', 50)->nullable();
            $t->string('color', 7)->nullable();
            $t->integer('position')->default(0);
            $t->integer('wip_limit')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });

        Schema::create('tasks', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('project_id');
            $t->unsignedInteger('team_id')->nullable();
            $t->unsignedInteger('bucket_id')->nullable();
            $t->unsignedInteger('parent_task_id')->nullable();
            $t->string('title', 500);
            $t->text('description')->nullable();
            $t->string('status', 20)->default('todo');
            $t->string('priority', 10)->default('medium');
            $t->date('start_date')->nullable();
            $t->date('due_date')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->decimal('estimated_hours', 10, 2)->nullable();
            $t->decimal('actual_hours', 10, 2)->nullable();
            $t->smallInteger('progress')->default(0);
            $t->integer('position')->default(0);
            $t->json('labels')->nullable();
            $t->json('metadata')->nullable();
            $t->unsignedInteger('created_by');
            $t->timestamps();
            $t->softDeletes();
            $t->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $t->foreign('team_id')->references('id')->on('teams')->onDelete('set null');
            $t->foreign('created_by')->references('id')->on('users');
        });

        Schema::create('task_assignees', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('task_id');
            $t->unsignedInteger('user_id');
            $t->timestamp('assigned_at')->useCurrent();
            $t->unsignedInteger('assigned_by')->nullable();
            $t->unique(['task_id', 'user_id']);
            $t->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('task_checklists', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('task_id');
            $t->string('title', 255);
            $t->integer('position')->default(0);
            $t->timestamp('created_at')->useCurrent();
            $t->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
        });

        Schema::create('task_checklist_items', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('checklist_id');
            $t->string('title', 500);
            $t->boolean('is_completed')->default(false);
            $t->timestamp('completed_at')->nullable();
            $t->unsignedInteger('completed_by')->nullable();
            $t->integer('position')->default(0);
            $t->timestamp('created_at')->useCurrent();
            $t->foreign('checklist_id')->references('id')->on('task_checklists')->onDelete('cascade');
        });

        // ── Calendar Events ──
        Schema::create('calendar_events', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('team_id')->nullable();
            $t->unsignedInteger('channel_id')->nullable();
            $t->unsignedInteger('organizer_id');
            $t->string('title', 255);
            $t->text('description')->nullable();
            $t->string('location', 500)->nullable();
            $t->boolean('is_online_meeting')->default(false);
            $t->string('meeting_url', 500)->nullable();
            $t->string('meeting_id', 100)->nullable();
            $t->timestamp('start_time');
            $t->timestamp('end_time');
            $t->boolean('is_all_day')->default(false);
            $t->string('timezone', 50)->default('Europe/Budapest');
            $t->boolean('is_recurring')->default(false);
            $t->string('recurrence_rule', 500)->nullable();
            $t->date('recurrence_end_date')->nullable();
            $t->unsignedInteger('parent_event_id')->nullable();
            $t->string('status', 20)->default('confirmed');
            $t->integer('reminder_minutes')->default(15);
            $t->string('color', 7)->nullable();
            $t->string('category', 100)->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $t->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $t->foreign('organizer_id')->references('id')->on('users');
        });

        Schema::create('event_attendees', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('event_id');
            $t->unsignedInteger('user_id');
            $t->string('response_status', 20)->default('pending');
            $t->boolean('is_required')->default(true);
            $t->timestamp('responded_at')->nullable();
            $t->unique(['event_id', 'user_id']);
            $t->foreign('event_id')->references('id')->on('calendar_events')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ── Files ──
        Schema::create('files', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('team_id')->nullable();
            $t->unsignedInteger('channel_id')->nullable();
            $t->unsignedInteger('folder_id')->nullable();
            $t->string('name', 255);
            $t->string('file_type', 10)->default('file');
            $t->string('mime_type', 100)->nullable();
            $t->bigInteger('size_bytes')->nullable();
            $t->string('storage_path', 500)->nullable();
            $t->string('storage_provider', 50)->default('local');
            $t->string('public_url', 500)->nullable();
            $t->integer('version')->default(1);
            $t->unsignedInteger('parent_version_id')->nullable();
            $t->json('metadata')->nullable();
            $t->unsignedInteger('uploaded_by');
            $t->timestamps();
            $t->softDeletes();
            $t->foreign('uploaded_by')->references('id')->on('users');
        });

        // ── Notifications ──
        Schema::create('notifications', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->string('notification_type', 50);
            $t->unsignedInteger('actor_id')->nullable();
            $t->unsignedInteger('team_id')->nullable();
            $t->unsignedInteger('channel_id')->nullable();
            $t->unsignedInteger('message_id')->nullable();
            $t->unsignedInteger('task_id')->nullable();
            $t->unsignedInteger('event_id')->nullable();
            $t->string('title', 255);
            $t->text('body')->nullable();
            $t->string('action_url', 500)->nullable();
            $t->boolean('is_read')->default(false);
            $t->timestamp('read_at')->nullable();
            $t->boolean('is_push_sent')->default(false);
            $t->boolean('is_email_sent')->default(false);
            $t->timestamp('created_at')->useCurrent();
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ── User Settings ──
        Schema::create('user_settings', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id')->unique();
            $t->string('theme', 10)->default('dark');
            $t->string('language', 10)->default('hu');
            $t->string('date_format', 20)->default('YYYY-MM-DD');
            $t->string('time_format', 5)->default('24h');
            $t->boolean('notifications_enabled')->default(true);
            $t->boolean('email_notifications')->default(true);
            $t->boolean('push_notifications')->default(true);
            $t->boolean('notification_sound')->default(true);
            $t->boolean('desktop_notifications')->default(true);
            $t->boolean('dnd_enabled')->default(false);
            $t->time('dnd_start_time')->nullable();
            $t->time('dnd_end_time')->nullable();
            $t->boolean('enter_to_send')->default(true);
            $t->boolean('show_typing_indicator')->default(true);
            $t->boolean('show_read_receipts')->default(true);
            $t->boolean('reduce_motion')->default(false);
            $t->boolean('high_contrast')->default(false);
            $t->string('font_size', 10)->default('medium');
            $t->boolean('show_online_status')->default(true);
            $t->string('allow_direct_messages', 20)->default('everyone');
            $t->timestamps();
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ── Time Entries ──
        Schema::create('time_entries', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('project_id')->nullable();
            $t->unsignedInteger('task_id')->nullable();
            $t->decimal('hours', 10, 2);
            $t->date('date');
            $t->string('activity_type', 100)->nullable();
            $t->text('description')->nullable();
            $t->timestamps();
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ── Friends ──
        Schema::create('friends', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id_1');
            $t->unsignedInteger('user_id_2');
            $t->string('status', 20)->default('pending');
            $t->timestamps();
            $t->unique(['user_id_1', 'user_id_2']);
            $t->foreign('user_id_1')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('user_id_2')->references('id')->on('users')->onDelete('cascade');
        });

        // ── Participants ──
        Schema::create('participants', function ($t) {
            $t->increments('id');
            $t->string('entity_type', 20);
            $t->unsignedInteger('entity_id');
            $t->unsignedInteger('user_id');
            $t->string('role', 20)->default('member');
            $t->timestamp('joined_at')->useCurrent();
            $t->timestamps();
            $t->unique(['entity_type', 'entity_id', 'user_id']);
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ── Activity Logs ──
        Schema::create('activity_logs', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->string('action', 100);
            $t->string('target_type', 50)->nullable();
            $t->unsignedInteger('target_id')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ── Verification & Trusted Devices ──
        Schema::create('verification_codes', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->string('code', 6);
            $t->string('method', 10);
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('trusted_devices', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('user_id');
            $t->string('device_token', 255)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('created_at')->useCurrent();
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ── Laravel Support Tables ──
        Schema::create('sessions', function ($t) {
            $t->string('id', 255)->primary();
            $t->bigInteger('user_id')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->text('payload');
            $t->integer('last_activity');
        });

        Schema::create('cache', function ($t) {
            $t->string('key', 255)->primary();
            $t->text('value');
            $t->integer('expiration');
        });

        Schema::create('cache_locks', function ($t) {
            $t->string('key', 255)->primary();
            $t->string('owner', 255);
            $t->integer('expiration');
        });

        Schema::create('jobs', function ($t) {
            $t->bigIncrements('id');
            $t->string('queue', 255);
            $t->text('payload');
            $t->smallInteger('attempts');
            $t->integer('reserved_at')->nullable();
            $t->integer('available_at');
            $t->integer('created_at');
        });

        Schema::create('job_batches', function ($t) {
            $t->string('id', 255)->primary();
            $t->string('name', 255);
            $t->integer('total_jobs');
            $t->integer('pending_jobs');
            $t->integer('failed_jobs');
            $t->text('failed_job_ids');
            $t->text('options')->nullable();
            $t->integer('cancelled_at')->nullable();
            $t->integer('created_at');
            $t->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function ($t) {
            $t->bigIncrements('id');
            $t->string('uuid', 255)->unique();
            $t->text('connection');
            $t->text('queue');
            $t->text('payload');
            $t->text('exception');
            $t->timestamp('failed_at')->useCurrent();
        });

        Schema::create('password_reset_tokens', function ($t) {
            $t->string('email', 255)->primary();
            $t->string('token', 255);
            $t->timestamp('created_at')->nullable();
        });

        // ── Chat Room Reactions ──
        Schema::create('chat_room_reactions', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('chat_room_id');
            $t->unsignedInteger('user_id');
            $t->string('emoji', 50);
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['chat_room_id', 'user_id', 'emoji']);
            $t->foreign('chat_room_id')->references('id')->on('chat_rooms')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ── Chat Room Attachments ──
        Schema::create('chat_room_attachments', function ($t) {
            $t->increments('id');
            $t->unsignedInteger('chat_room_id');
            $t->unsignedInteger('uploaded_by');
            $t->string('file_name');
            $t->string('file_type', 100)->nullable();
            $t->unsignedInteger('file_size')->nullable();
            $t->string('file_url', 500);
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->foreign('chat_room_id')->references('id')->on('chat_rooms')->onDelete('cascade');
            $t->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    // ── Helper Methods ──────────────────────────────────────────

    /**
     * Create a test user with UserSetting, ready for Sanctum auth.
     */
    protected function createUser(array $overrides = []): User
    {
        $user = User::factory()->create($overrides);

        UserSetting::create([
            'user_id'               => $user->id,
            'theme'                 => 'dark',
            'language'              => 'hu',
            'notifications_enabled' => true,
        ]);

        return $user;
    }

    /**
     * Create a project owned by the given user.
     */
    protected function createProject(User $owner, array $overrides = []): Project
    {
        return Project::create(array_merge([
            'name'     => 'Test Project',
            'slug'     => 'test-project-' . uniqid(),
            'owner_id' => $owner->id,
            'status'   => 'active',
        ], $overrides));
    }

    /**
     * Create a task in a project, created by the given user.
     */
    protected function createTask(Project $project, User $creator, array $overrides = []): Task
    {
        return Task::create(array_merge([
            'project_id' => $project->id,
            'title'      => 'Test Task',
            'status'     => 'todo',
            'priority'   => 'medium',
            'created_by' => $creator->id,
        ], $overrides));
    }

    /**
     * Add a user as a participant of a project.
     */
    protected function addProjectMember(Project $project, User $user, string $role = 'member'): Participant
    {
        return Participant::create([
            'entity_type' => 'project',
            'entity_id'   => $project->id,
            'user_id'     => $user->id,
            'role'        => $role,
            'joined_at'   => now(),
        ]);
    }
}
