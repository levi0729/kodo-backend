<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trigger function for auto-updating updated_at timestamps
        DB::unprepared('
            CREATE OR REPLACE FUNCTION update_updated_at_column()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email', 255);
            $table->string('username', 100);
            $table->string('password', 255);
            $table->string('display_name', 150)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->string('cover_image_url', 500)->nullable();
            $table->text('bio')->nullable();
            $table->string('timezone', 50)->default('Europe/Budapest');
            $table->string('locale', 10)->default('hu');

            // Online presence
            $table->string('presence_status', 20)->default('offline');
            $table->string('presence_message', 255)->nullable();
            $table->timestamp('presence_expiry')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            // Account status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_admin')->default(false);

            // Account lockout
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();

            // Laravel auth
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('email_verified_at')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable();

            $table->unique('email', 'uk_users_email');
            $table->unique('username', 'uk_users_username');
            $table->index('presence_status', 'idx_users_presence');
        });

        // CHECK constraint for presence_status
        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_presence_status CHECK (presence_status IN ('online','away','busy','dnd','brb','offline','invisible'))");

        // Trigger for updated_at
        DB::unprepared('
            CREATE TRIGGER trg_users_updated_at
                BEFORE UPDATE ON users
                FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
        ');

        // Laravel Sanctum personal access tokens
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tokenable_type', 255);
            $table->bigInteger('tokenable_id');
            $table->string('name', 255);
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index(['tokenable_type', 'tokenable_id'], 'idx_pat_tokenable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');

        DB::unprepared('DROP TRIGGER IF EXISTS trg_users_updated_at ON users');
        Schema::dropIfExists('users');

        DB::unprepared('DROP FUNCTION IF EXISTS update_updated_at_column()');
    }
};
