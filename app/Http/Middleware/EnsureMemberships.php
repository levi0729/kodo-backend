<?php

namespace App\Http\Middleware;

use App\Models\Participant;
use App\Models\Project;
use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class EnsureMemberships
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user) {
            // Run once per user per 10 minutes (cached flag)
            $cacheKey = "memberships_ensured:{$user->id}";
            if (!Cache::has($cacheKey)) {
                foreach (Project::pluck('id') as $pid) {
                    Participant::firstOrCreate(
                        ['entity_type' => 'project', 'entity_id' => $pid, 'user_id' => $user->id],
                        ['role' => 'member', 'joined_at' => now()]
                    );
                }
                foreach (Team::pluck('id') as $tid) {
                    Participant::firstOrCreate(
                        ['entity_type' => 'team', 'entity_id' => $tid, 'user_id' => $user->id],
                        ['role' => 'member', 'joined_at' => now()]
                    );
                }
                Cache::put($cacheKey, true, 600); // 10 minutes
            }
        }

        return $next($request);
    }
}
