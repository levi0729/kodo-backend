<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Task buckets (Kanban columns)
        Schema::create('task_buckets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('project_id');
            $table->string('name', 100);
            $table->string('key', 50)->nullable();
            $table->string('color', 7)->nullable();
            $table->integer('position')->default(0);
            $table->integer('wip_limit')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('project_id', 'fk_buckets_project')->references('id')->on('projects')->onDelete('cascade');

            $table->index('project_id', 'idx_buckets_project');
        });

        // Tasks
        Schema::create('tasks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('project_id');
            $table->unsignedInteger('team_id')->nullable();
            $table->unsignedInteger('bucket_id')->nullable();
            $table->unsignedInteger('parent_task_id')->nullable();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('todo');
            $table->string('priority', 10)->default('medium');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('estimated_hours', 10, 2)->nullable();
            $table->decimal('actual_hours', 10, 2)->nullable();
            $table->smallInteger('progress')->default(0);
            $table->integer('position')->default(0);
            $table->jsonb('labels')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('project_id', 'fk_tasks_project')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('team_id', 'fk_tasks_team')->references('id')->on('teams')->onDelete('set null');
            $table->foreign('bucket_id', 'fk_tasks_bucket')->references('id')->on('task_buckets')->onDelete('set null');
            $table->foreign('parent_task_id', 'fk_tasks_parent')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('created_by', 'fk_tasks_creator')->references('id')->on('users');

            $table->index('project_id', 'idx_tasks_project');
            $table->index('team_id', 'idx_tasks_team');
            $table->index('bucket_id', 'idx_tasks_bucket');
            $table->index('status', 'idx_tasks_status');
            $table->index('due_date', 'idx_tasks_due_date');
            $table->index('parent_task_id', 'idx_tasks_parent');
        });

        DB::statement("ALTER TABLE tasks ADD CONSTRAINT chk_tasks_status CHECK (status IN ('todo','in_progress','in_review','done','blocked','cancelled'))");
        DB::statement("ALTER TABLE tasks ADD CONSTRAINT chk_tasks_priority CHECK (priority IN ('urgent','high','medium','low','none'))");
        DB::statement("ALTER TABLE tasks ADD CONSTRAINT chk_tasks_progress CHECK (progress >= 0 AND progress <= 100)");

        DB::unprepared('
            CREATE TRIGGER trg_tasks_updated_at
                BEFORE UPDATE ON tasks
                FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
        ');

        // Task assignees
        Schema::create('task_assignees', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('task_id');
            $table->unsignedInteger('user_id');
            $table->timestamp('assigned_at')->useCurrent();
            $table->unsignedInteger('assigned_by')->nullable();

            $table->unique(['task_id', 'user_id'], 'uk_task_assignees');
            $table->foreign('task_id', 'fk_task_assignees_task')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('user_id', 'fk_task_assignees_user')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_by', 'fk_task_assignees_by')->references('id')->on('users')->onDelete('set null');

            $table->index('user_id', 'idx_task_assignees_user');
        });

        // Task checklists
        Schema::create('task_checklists', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('task_id');
            $table->string('title', 255);
            $table->integer('position')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('task_id', 'fk_task_checklists_task')->references('id')->on('tasks')->onDelete('cascade');

            $table->index('task_id', 'idx_task_checklists_task');
        });

        // Task checklist items
        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('checklist_id');
            $table->string('title', 500);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('completed_by')->nullable();
            $table->integer('position')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('checklist_id', 'fk_checklist_items_checklist')->references('id')->on('task_checklists')->onDelete('cascade');
            $table->foreign('completed_by', 'fk_checklist_items_completed_by')->references('id')->on('users')->onDelete('set null');

            $table->index('checklist_id', 'idx_checklist_items_checklist');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('task_checklists');
        Schema::dropIfExists('task_assignees');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_tasks_updated_at ON tasks');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_buckets');
    }
};
