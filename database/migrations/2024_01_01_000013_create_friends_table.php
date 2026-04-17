<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friends', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id_1');
            $table->unsignedInteger('user_id_2');
            $table->string('status', 20)->default('pending');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['user_id_1', 'user_id_2'], 'uk_friends');
            $table->foreign('user_id_1', 'fk_friends_user1')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_id_2', 'fk_friends_user2')->references('id')->on('users')->onDelete('cascade');

            $table->index('user_id_2', 'idx_friends_user2');
        });

        DB::statement("ALTER TABLE friends ADD CONSTRAINT chk_friends_status CHECK (status IN ('pending','accepted','blocked','declined'))");
        DB::statement("ALTER TABLE friends ADD CONSTRAINT chk_friends_not_self CHECK (user_id_1 <> user_id_2)");

        DB::unprepared('
            CREATE TRIGGER trg_friends_updated_at
                BEFORE UPDATE ON friends
                FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_friends_updated_at ON friends');
        Schema::dropIfExists('friends');
    }
};
