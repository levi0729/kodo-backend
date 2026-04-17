<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('theme', 10)->default('dark');
            $table->string('language', 10)->default('hu');
            $table->string('date_format', 20)->default('YYYY-MM-DD');
            $table->string('time_format', 5)->default('24h');
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('email_notifications')->default(true);
            $table->boolean('push_notifications')->default(true);
            $table->boolean('notification_sound')->default(true);
            $table->boolean('desktop_notifications')->default(true);
            $table->boolean('dnd_enabled')->default(false);
            $table->time('dnd_start_time')->nullable();
            $table->time('dnd_end_time')->nullable();
            $table->boolean('enter_to_send')->default(true);
            $table->boolean('show_typing_indicator')->default(true);
            $table->boolean('show_read_receipts')->default(true);
            $table->boolean('reduce_motion')->default(false);
            $table->boolean('high_contrast')->default(false);
            $table->string('font_size', 10)->default('medium');
            $table->boolean('show_online_status')->default(true);
            $table->string('allow_direct_messages', 20)->default('everyone');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique('user_id', 'uk_user_settings_user');
            $table->foreign('user_id', 'fk_user_settings_user')->references('id')->on('users')->onDelete('cascade');
        });

        DB::statement("ALTER TABLE user_settings ADD CONSTRAINT chk_user_settings_theme CHECK (theme IN ('dark','light','system'))");
        DB::statement("ALTER TABLE user_settings ADD CONSTRAINT chk_user_settings_time_format CHECK (time_format IN ('12h','24h'))");
        DB::statement("ALTER TABLE user_settings ADD CONSTRAINT chk_user_settings_font_size CHECK (font_size IN ('small','medium','large','xlarge'))");
        DB::statement("ALTER TABLE user_settings ADD CONSTRAINT chk_user_settings_allow_dm CHECK (allow_direct_messages IN ('everyone','team_members','nobody'))");

        DB::unprepared('
            CREATE TRIGGER trg_user_settings_updated_at
                BEFORE UPDATE ON user_settings
                FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_user_settings_updated_at ON user_settings');
        Schema::dropIfExists('user_settings');
    }
};
