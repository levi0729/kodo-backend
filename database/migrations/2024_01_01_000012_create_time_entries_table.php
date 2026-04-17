<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('project_id')->nullable();
            $table->unsignedInteger('task_id')->nullable();
            $table->decimal('hours', 10, 2);
            $table->date('date');
            $table->string('activity_type', 100)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->foreign('user_id', 'fk_time_entries_user')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('project_id', 'fk_time_entries_project')->references('id')->on('projects')->onDelete('set null');
            $table->foreign('task_id', 'fk_time_entries_task')->references('id')->on('tasks')->onDelete('set null');

            $table->index(['user_id', 'date'], 'idx_time_entries_user');
            $table->index('project_id', 'idx_time_entries_project');
        });

        DB::statement("ALTER TABLE time_entries ADD CONSTRAINT chk_time_entries_hours CHECK (hours > 0 AND hours <= 24)");

        DB::unprepared('
            CREATE TRIGGER trg_time_entries_updated_at
                BEFORE UPDATE ON time_entries
                FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_time_entries_updated_at ON time_entries');
        Schema::dropIfExists('time_entries');
    }
};
