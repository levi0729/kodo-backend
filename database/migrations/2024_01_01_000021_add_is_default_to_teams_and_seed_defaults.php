<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_archived');
        });

        // Create default "General" team for every existing project that doesn't have one
        $projects = DB::table('projects')->get();

        foreach ($projects as $project) {
            $exists = DB::table('teams')
                ->where('project_id', $project->id)
                ->where('is_default', true)
                ->exists();

            if ($exists) {
                continue;
            }

            $teamId = DB::table('teams')->insertGetId([
                'project_id'  => $project->id,
                'name'        => 'General',
                'slug'        => 'general',
                'description' => null,
                'color'       => '#6366f1',
                'visibility'  => 'public',
                'is_private'  => false,
                'is_default'  => true,
                'is_archived' => false,
                'owner_id'    => $project->owner_id,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // Create default #general channel for this team
            DB::table('channels')->insert([
                'team_id'      => $teamId,
                'name'         => 'general',
                'slug'         => 'general',
                'channel_type' => 'standard',
                'is_default'   => true,
                'created_by'   => $project->owner_id,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // Add all existing project participants to this team
            $participants = DB::table('participants')
                ->where('entity_type', 'project')
                ->where('entity_id', $project->id)
                ->get();

            foreach ($participants as $participant) {
                $alreadyInTeam = DB::table('participants')
                    ->where('entity_type', 'team')
                    ->where('entity_id', $teamId)
                    ->where('user_id', $participant->user_id)
                    ->exists();

                if (! $alreadyInTeam) {
                    DB::table('participants')->insert([
                        'entity_type' => 'team',
                        'entity_id'   => $teamId,
                        'user_id'     => $participant->user_id,
                        'role'        => $participant->role,
                        'joined_at'   => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Remove default teams
        $defaultTeamIds = DB::table('teams')->where('is_default', true)->pluck('id');
        DB::table('participants')->where('entity_type', 'team')->whereIn('entity_id', $defaultTeamIds)->delete();
        DB::table('channels')->whereIn('team_id', $defaultTeamIds)->delete();
        DB::table('teams')->whereIn('id', $defaultTeamIds)->delete();

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
