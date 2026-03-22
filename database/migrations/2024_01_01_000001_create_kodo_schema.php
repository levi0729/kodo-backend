<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Read and execute the PostgreSQL schema file
        $sql = file_get_contents(base_path('database/schema.sql'));
        DB::unprepared($sql);
    }

    public function down(): void
    {
        DB::unprepared('
            DROP VIEW IF EXISTS v_project_task_summary CASCADE;
            DROP VIEW IF EXISTS v_team_members_active CASCADE;
            DROP TABLE IF EXISTS activity_logs CASCADE;
            DROP TABLE IF EXISTS participants CASCADE;
            DROP TABLE IF EXISTS friends CASCADE;
            DROP TABLE IF EXISTS time_entries CASCADE;
            DROP TABLE IF EXISTS user_settings CASCADE;
            DROP TABLE IF EXISTS notifications CASCADE;
            DROP TABLE IF EXISTS event_attendees CASCADE;
            DROP TABLE IF EXISTS calendar_events CASCADE;
            DROP TABLE IF EXISTS task_checklist_items CASCADE;
            DROP TABLE IF EXISTS task_checklists CASCADE;
            DROP TABLE IF EXISTS task_assignees CASCADE;
            DROP TABLE IF EXISTS tasks CASCADE;
            DROP TABLE IF EXISTS task_buckets CASCADE;
            DROP TABLE IF EXISTS files CASCADE;
            DROP TABLE IF EXISTS message_mentions CASCADE;
            DROP TABLE IF EXISTS message_reactions CASCADE;
            DROP TABLE IF EXISTS message_attachments CASCADE;
            DROP TABLE IF EXISTS messages CASCADE;
            DROP TABLE IF EXISTS conversation_participants CASCADE;
            DROP TABLE IF EXISTS conversations CASCADE;
            DROP TABLE IF EXISTS chat_rooms CASCADE;
            DROP TABLE IF EXISTS channels CASCADE;
            DROP TABLE IF EXISTS team_members CASCADE;
            DROP TABLE IF EXISTS teams CASCADE;
            DROP TABLE IF EXISTS projects CASCADE;
            DROP TABLE IF EXISTS organizations CASCADE;
            DROP TABLE IF EXISTS personal_access_tokens CASCADE;
            DROP TABLE IF EXISTS users CASCADE;
            DROP FUNCTION IF EXISTS update_updated_at_column() CASCADE;
        ');
    }
};
