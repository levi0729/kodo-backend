<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('team_id');
            $table->string('name', 255);
            $table->string('slug', 100);
            $table->text('description')->nullable();
            $table->string('channel_type', 20)->default('standard');
            $table->boolean('is_default')->default(false);
            $table->boolean('allow_threads')->default(true);
            $table->boolean('allow_reactions')->default(true);
            $table->boolean('allow_mentions')->default(true);
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable();

            $table->unique(['team_id', 'slug'], 'uk_channels_slug');
            $table->foreign('team_id', 'fk_channels_team')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('created_by', 'fk_channels_creator')->references('id')->on('users');

            $table->index('team_id', 'idx_channels_team');
        });

        DB::statement("ALTER TABLE channels ADD CONSTRAINT chk_channels_channel_type CHECK (channel_type IN ('general','standard','public','private','announcement'))");

        DB::unprepared('
            CREATE TRIGGER trg_channels_updated_at
                BEFORE UPDATE ON channels
                FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_channels_updated_at ON channels');
        Schema::dropIfExists('channels');
    }
};
