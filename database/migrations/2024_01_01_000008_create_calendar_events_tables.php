<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('team_id')->nullable();
            $table->unsignedInteger('channel_id')->nullable();
            $table->unsignedInteger('organizer_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('location', 500)->nullable();
            $table->boolean('is_online_meeting')->default(false);
            $table->string('meeting_url', 500)->nullable();
            $table->string('meeting_id', 100)->nullable();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->boolean('is_all_day')->default(false);
            $table->string('timezone', 50)->default('Europe/Budapest');
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_rule', 500)->nullable();
            $table->date('recurrence_end_date')->nullable();
            $table->unsignedInteger('parent_event_id')->nullable();
            $table->string('status', 20)->default('confirmed');
            $table->integer('reminder_minutes')->default(15);
            $table->string('color', 7)->nullable();
            $table->string('category', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('team_id', 'fk_events_team')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('channel_id', 'fk_events_channel')->references('id')->on('channels')->onDelete('cascade');
            $table->foreign('organizer_id', 'fk_events_organizer')->references('id')->on('users');
            $table->foreign('parent_event_id', 'fk_events_parent')->references('id')->on('calendar_events')->onDelete('cascade');

            $table->index('team_id', 'idx_events_team');
            $table->index('organizer_id', 'idx_events_organizer');
            $table->index(['start_time', 'end_time'], 'idx_events_time');
        });

        DB::statement("ALTER TABLE calendar_events ADD CONSTRAINT chk_events_status CHECK (status IN ('tentative','confirmed','cancelled'))");

        DB::unprepared('
            CREATE TRIGGER trg_calendar_events_updated_at
                BEFORE UPDATE ON calendar_events
                FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
        ');

        // Event attendees
        Schema::create('event_attendees', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('event_id');
            $table->unsignedInteger('user_id');
            $table->string('response_status', 20)->default('pending');
            $table->boolean('is_required')->default(true);
            $table->timestamp('responded_at')->nullable();

            $table->unique(['event_id', 'user_id'], 'uk_event_attendees');
            $table->foreign('event_id', 'fk_attendees_event')->references('id')->on('calendar_events')->onDelete('cascade');
            $table->foreign('user_id', 'fk_attendees_user')->references('id')->on('users')->onDelete('cascade');

            $table->index('user_id', 'idx_attendees_user');
        });

        DB::statement("ALTER TABLE event_attendees ADD CONSTRAINT chk_attendees_response CHECK (response_status IN ('pending','accepted','declined','tentative'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendees');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_calendar_events_updated_at ON calendar_events');
        Schema::dropIfExists('calendar_events');
    }
};
