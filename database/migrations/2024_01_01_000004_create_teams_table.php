<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('organization_id')->nullable();
            $table->unsignedInteger('project_id')->nullable();
            $table->string('name', 255);
            $table->string('slug', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('icon_url', 500)->nullable();
            $table->string('color', 20)->default('#7360F9');
            $table->string('visibility', 20)->default('private');
            $table->boolean('is_private')->default(false);
            $table->string('password_hash', 255)->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('archived_by')->nullable();
            $table->unsignedInteger('owner_id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('organization_id', 'fk_teams_org')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('project_id', 'fk_teams_project')->references('id')->on('projects')->onDelete('set null');
            $table->foreign('owner_id', 'fk_teams_owner')->references('id')->on('users');
            $table->foreign('archived_by', 'fk_teams_archived_by')->references('id')->on('users')->onDelete('set null');

            $table->index('project_id', 'idx_teams_project');
            $table->index('visibility', 'idx_teams_visibility');
        });

        DB::statement("ALTER TABLE teams ADD CONSTRAINT chk_teams_visibility CHECK (visibility IN ('public','private','hidden'))");

        DB::unprepared('
            CREATE TRIGGER trg_teams_updated_at
                BEFORE UPDATE ON teams
                FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
        ');

        // Team members
        Schema::create('team_members', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('team_id');
            $table->unsignedInteger('user_id');
            $table->string('role', 20)->default('member');
            $table->string('notification_level', 20)->default('all');
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_muted')->default(false);
            $table->timestamp('joined_at')->useCurrent();
            $table->unsignedInteger('invited_by')->nullable();

            $table->unique(['team_id', 'user_id'], 'uk_team_members');
            $table->foreign('team_id', 'fk_team_members_team')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('user_id', 'fk_team_members_user')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('invited_by', 'fk_team_members_invited')->references('id')->on('users')->onDelete('set null');

            $table->index('user_id', 'idx_team_members_user');
        });

        DB::statement("ALTER TABLE team_members ADD CONSTRAINT chk_team_members_role CHECK (role IN ('owner','admin','member','guest'))");
        DB::statement("ALTER TABLE team_members ADD CONSTRAINT chk_team_members_notification_level CHECK (notification_level IN ('all','mentions','none'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_teams_updated_at ON teams');
        Schema::dropIfExists('teams');
    }
};
