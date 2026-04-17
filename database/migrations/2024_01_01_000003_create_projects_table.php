<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('organization_id')->nullable();
            $table->string('name', 255);
            $table->string('slug', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('color', 20)->default('#7360F9');
            $table->string('icon', 50)->nullable();
            $table->string('project_type', 20)->default('kanban');
            $table->string('status', 20)->default('active');
            $table->date('start_date')->nullable();
            $table->date('target_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->smallInteger('progress')->default(0);
            $table->jsonb('settings')->nullable();
            $table->unsignedInteger('owner_id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('owner_id', 'fk_projects_owner')->references('id')->on('users');
            $table->foreign('organization_id', 'fk_projects_org')->references('id')->on('organizations')->onDelete('set null');

            $table->index('owner_id', 'idx_projects_owner');
            $table->index('status', 'idx_projects_status');
        });

        DB::statement("ALTER TABLE projects ADD CONSTRAINT chk_projects_project_type CHECK (project_type IN ('kanban','list','timeline','calendar'))");
        DB::statement("ALTER TABLE projects ADD CONSTRAINT chk_projects_status CHECK (status IN ('planning','active','on_hold','completed','archived'))");
        DB::statement("ALTER TABLE projects ADD CONSTRAINT chk_projects_progress CHECK (progress >= 0 AND progress <= 100)");

        DB::unprepared('
            CREATE TRIGGER trg_projects_updated_at
                BEFORE UPDATE ON projects
                FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_projects_updated_at ON projects');
        Schema::dropIfExists('projects');
    }
};
