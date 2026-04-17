<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 255);
            $table->string('slug', 100);
            $table->text('description')->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('domain', 255)->nullable();
            $table->jsonb('settings')->nullable();
            $table->jsonb('allowed_email_domains')->nullable();
            $table->string('plan_type', 20)->default('free');
            $table->integer('max_members')->default(50);
            $table->integer('max_storage_gb')->default(5);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable();

            $table->unique('slug', 'uk_organizations_slug');
        });

        DB::statement("ALTER TABLE organizations ADD CONSTRAINT chk_organizations_plan_type CHECK (plan_type IN ('free','standard','business','pro','enterprise'))");

        DB::unprepared('
            CREATE TRIGGER trg_organizations_updated_at
                BEFORE UPDATE ON organizations
                FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_organizations_updated_at ON organizations');
        Schema::dropIfExists('organizations');
    }
};
