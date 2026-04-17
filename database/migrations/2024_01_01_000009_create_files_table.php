<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('team_id')->nullable();
            $table->unsignedInteger('channel_id')->nullable();
            $table->unsignedInteger('folder_id')->nullable();
            $table->string('name', 255);
            $table->string('file_type', 10)->default('file');
            $table->string('mime_type', 100)->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->string('storage_path', 500)->nullable();
            $table->string('storage_provider', 50)->default('local');
            $table->string('public_url', 500)->nullable();
            $table->integer('version')->default(1);
            $table->unsignedInteger('parent_version_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->unsignedInteger('uploaded_by');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('team_id', 'fk_files_team')->references('id')->on('teams')->onDelete('cascade');
            $table->foreign('channel_id', 'fk_files_channel')->references('id')->on('channels')->onDelete('cascade');
            $table->foreign('folder_id', 'fk_files_folder')->references('id')->on('files')->onDelete('cascade');
            $table->foreign('parent_version_id', 'fk_files_parent_version')->references('id')->on('files')->onDelete('set null');
            $table->foreign('uploaded_by', 'fk_files_uploader')->references('id')->on('users');

            $table->index('team_id', 'idx_files_team');
            $table->index('channel_id', 'idx_files_channel');
        });

        DB::statement("ALTER TABLE files ADD CONSTRAINT chk_files_file_type CHECK (file_type IN ('file','folder','link'))");

        DB::unprepared('
            CREATE TRIGGER trg_files_updated_at
                BEFORE UPDATE ON files
                FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_files_updated_at ON files');
        Schema::dropIfExists('files');
    }
};
