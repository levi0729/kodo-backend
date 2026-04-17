<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sessions
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->bigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity');

            $table->index('user_id', 'idx_sessions_user');
            $table->index('last_activity', 'idx_sessions_activity');
        });

        // Cache
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key', 255)->primary();
            $table->text('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key', 255)->primary();
            $table->string('owner', 255);
            $table->integer('expiration');
        });

        // Jobs
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue', 255);
            $table->text('payload');
            $table->smallInteger('attempts');
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at');
            $table->integer('created_at');

            $table->index('queue', 'idx_jobs_queue');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('name', 255);
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->text('failed_job_ids');
            $table->text('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uuid', 255)->unique();
            $table->text('connection');
            $table->text('queue');
            $table->text('payload');
            $table->text('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // Password reset tokens
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 255)->primary();
            $table->string('token', 255);
            $table->timestamp('created_at')->nullable();
        });

        // Database views
        DB::unprepared("
            CREATE OR REPLACE VIEW v_team_members_active AS
            SELECT
                tm.team_id,
                tm.user_id,
                tm.role,
                tm.joined_at,
                u.username,
                u.display_name,
                u.email,
                u.avatar_url,
                u.presence_status,
                u.job_title
            FROM team_members tm
            JOIN users u ON tm.user_id = u.id
            WHERE u.deleted_at IS NULL;
        ");

        DB::unprepared("
            CREATE OR REPLACE VIEW v_project_task_summary AS
            SELECT
                p.id AS project_id,
                p.name AS project_name,
                COUNT(t.id) AS total_tasks,
                COUNT(CASE WHEN t.status = 'todo' THEN 1 END) AS todo_count,
                COUNT(CASE WHEN t.status = 'in_progress' THEN 1 END) AS in_progress_count,
                COUNT(CASE WHEN t.status = 'in_review' THEN 1 END) AS in_review_count,
                COUNT(CASE WHEN t.status = 'done' THEN 1 END) AS done_count,
                COUNT(CASE WHEN t.status = 'blocked' THEN 1 END) AS blocked_count,
                ROUND(
                    COUNT(CASE WHEN t.status = 'done' THEN 1 END)::NUMERIC /
                    NULLIF(COUNT(t.id), 0) * 100, 2
                ) AS completion_percentage
            FROM projects p
            LEFT JOIN tasks t ON t.project_id = p.id AND t.deleted_at IS NULL
            WHERE p.deleted_at IS NULL
            GROUP BY p.id, p.name;
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS v_project_task_summary');
        DB::unprepared('DROP VIEW IF EXISTS v_team_members_active');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
    }
};
