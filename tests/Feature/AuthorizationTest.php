<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Routing\Middleware\ThrottleRequests;

class AuthorizationTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    // ══════════════════════════════════════════════════════════════
    //  UNAUTHENTICATED ACCESS
    // ══════════════════════════════════════════════════════════════

    public function test_unauthenticated_user_cannot_access_projects(): void
    {
        $this->getJson('/api/projects')->assertStatus(401);
        $this->postJson('/api/projects', ['name' => 'Test'])->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_tasks(): void
    {
        $this->getJson('/api/tasks')->assertStatus(401);
        $this->postJson('/api/tasks', ['title' => 'Test'])->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_teams(): void
    {
        $this->getJson('/api/teams')->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_auth_routes(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
        $this->postJson('/api/auth/logout')->assertStatus(401);
        $this->postJson('/api/auth/change-password')->assertStatus(401);
    }

    public function test_public_routes_are_accessible_without_auth(): void
    {
        // Health check
        $this->getJson('/api/health')->assertOk();

        // Register endpoint returns validation errors, not 401
        $this->postJson('/api/auth/register', [])->assertStatus(422);

        // Login endpoint returns validation errors, not 401
        $this->postJson('/api/auth/login', [])->assertStatus(422);
    }

    // ══════════════════════════════════════════════════════════════
    //  PROJECT AUTHORIZATION
    // ══════════════════════════════════════════════════════════════

    public function test_non_member_cannot_view_project(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();
        $project = $this->createProject($owner, ['name' => 'Secret Project']);

        $response = $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/projects/{$project->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden.']);
    }

    public function test_member_can_view_project(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $project = $this->createProject($owner, ['name' => 'Team Project']);
        $this->addProjectMember($project, $member);

        $response = $this->actingAs($member, 'sanctum')
            ->getJson("/api/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('project.name', 'Team Project');
    }

    public function test_non_owner_cannot_update_project(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $project = $this->createProject($owner);
        $this->addProjectMember($project, $member);

        $response = $this->actingAs($member, 'sanctum')
            ->putJson("/api/projects/{$project->id}", [
                'name' => 'Unauthorized Update',
            ]);

        $response->assertStatus(403);

        // Verify project name was not changed
        $project->refresh();
        $this->assertNotEquals('Unauthorized Update', $project->name);
    }

    public function test_non_owner_cannot_delete_project(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $project = $this->createProject($owner);
        $this->addProjectMember($project, $member);

        $response = $this->actingAs($member, 'sanctum')
            ->deleteJson("/api/projects/{$project->id}");

        $response->assertStatus(403);

        // Project should still exist
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertNull(Project::find($project->id)->deleted_at);
    }

    public function test_non_owner_cannot_restore_project(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $project = $this->createProject($owner);
        $this->addProjectMember($project, $member);
        $project->delete();

        $response = $this->actingAs($member, 'sanctum')
            ->postJson("/api/projects/{$project->id}/restore");

        $response->assertStatus(403);

        // Project should still be soft-deleted
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_owner_can_perform_all_project_operations(): void
    {
        $owner = $this->createUser();
        $project = $this->createProject($owner, ['name' => 'Owner Project']);

        // Show
        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/projects/{$project->id}")
            ->assertOk();

        // Update
        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/projects/{$project->id}", ['name' => 'Updated'])
            ->assertOk();

        // Delete
        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/projects/{$project->id}")
            ->assertOk();

        // Restore
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/projects/{$project->id}/restore")
            ->assertOk();
    }

    // ══════════════════════════════════════════════════════════════
    //  TASK AUTHORIZATION
    // ══════════════════════════════════════════════════════════════

    public function test_non_member_cannot_view_task(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();
        $project = $this->createProject($owner);
        $task = $this->createTask($project, $owner, ['title' => 'Secret Task']);

        $response = $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden.']);
    }

    public function test_non_member_cannot_update_task(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();
        $project = $this->createProject($owner);
        $task = $this->createTask($project, $owner, ['title' => 'Original Title']);

        $response = $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", [
                'title' => 'Hacked Title',
            ]);

        $response->assertStatus(403);

        $task->refresh();
        $this->assertEquals('Original Title', $task->title);
    }

    public function test_non_member_cannot_delete_task(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();
        $project = $this->createProject($owner);
        $task = $this->createTask($project, $owner);

        $response = $this->actingAs($stranger, 'sanctum')
            ->deleteJson("/api/tasks/{$task->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_project_member_can_view_task(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $project = $this->createProject($owner);
        $this->addProjectMember($project, $member);
        $task = $this->createTask($project, $owner, ['title' => 'Member Task']);

        $response = $this->actingAs($member, 'sanctum')
            ->getJson("/api/tasks/{$task->id}");

        $response->assertOk()
            ->assertJsonPath('task.title', 'Member Task');
    }

    public function test_project_member_can_update_task(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $project = $this->createProject($owner);
        $this->addProjectMember($project, $member);
        $task = $this->createTask($project, $owner);

        $response = $this->actingAs($member, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", [
                'status' => 'in_progress',
            ]);

        $response->assertOk();
    }

    public function test_project_member_can_delete_task(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $project = $this->createProject($owner);
        $this->addProjectMember($project, $member);
        $task = $this->createTask($project, $owner);

        $response = $this->actingAs($member, 'sanctum')
            ->deleteJson("/api/tasks/{$task->id}");

        $response->assertOk();
    }

    public function test_task_creator_can_access_task(): void
    {
        $owner = $this->createUser();
        $creator = $this->createUser();
        $project = $this->createProject($owner);

        // Creator is a project member who creates a task, then membership is removed
        $this->addProjectMember($project, $creator);
        $task = $this->createTask($project, $creator, ['title' => 'Creator Only']);

        // Remove membership
        Participant::where('entity_type', 'project')
            ->where('entity_id', $project->id)
            ->where('user_id', $creator->id)
            ->delete();

        // Creator should still have access since they created the task
        $response = $this->actingAs($creator, 'sanctum')
            ->getJson("/api/tasks/{$task->id}");

        $response->assertOk()
            ->assertJsonPath('task.title', 'Creator Only');
    }

    public function test_task_assignee_can_access_task(): void
    {
        $owner = $this->createUser();
        $assignee = $this->createUser();
        $project = $this->createProject($owner);
        $task = $this->createTask($project, $owner, ['title' => 'Assigned Task']);

        // Assign user to task (without project membership)
        $task->assignees()->attach($assignee->id, [
            'assigned_at' => now(),
            'assigned_by' => $owner->id,
        ]);

        $response = $this->actingAs($assignee, 'sanctum')
            ->getJson("/api/tasks/{$task->id}");

        $response->assertOk()
            ->assertJsonPath('task.title', 'Assigned Task');
    }

    // ══════════════════════════════════════════════════════════════
    //  CROSS-USER DATA ISOLATION
    // ══════════════════════════════════════════════════════════════

    public function test_project_index_only_shows_own_projects(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();

        $this->createProject($userA, ['name' => 'User A Project']);
        $this->createProject($userB, ['name' => 'User B Project']);

        // User A should only see their own
        $responseA = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/projects');

        $namesA = collect($responseA->json('projects'))->pluck('name');
        $this->assertTrue($namesA->contains('User A Project'));
        $this->assertFalse($namesA->contains('User B Project'));

        // User B should only see their own
        $responseB = $this->actingAs($userB, 'sanctum')
            ->getJson('/api/projects');

        $namesB = collect($responseB->json('projects'))->pluck('name');
        $this->assertFalse($namesB->contains('User A Project'));
        $this->assertTrue($namesB->contains('User B Project'));
    }

    public function test_task_index_only_shows_accessible_tasks(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();

        $projectA = $this->createProject($userA);
        $projectB = $this->createProject($userB);

        $this->createTask($projectA, $userA, ['title' => 'Task of A']);
        $this->createTask($projectB, $userB, ['title' => 'Task of B']);

        // User A should only see their tasks
        $responseA = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/tasks');

        $titlesA = collect($responseA->json('tasks'))->pluck('title');
        $this->assertTrue($titlesA->contains('Task of A'));
        $this->assertFalse($titlesA->contains('Task of B'));
    }

    // ══════════════════════════════════════════════════════════════
    //  TEAM-BASED TASK ACCESS
    // ══════════════════════════════════════════════════════════════

    public function test_team_member_can_access_team_task(): void
    {
        $owner = $this->createUser();
        $teamMember = $this->createUser();

        $project = $this->createProject($owner);
        $team = Team::create([
            'name'       => 'Test Team',
            'slug'       => 'test-team',
            'owner_id'   => $owner->id,
            'project_id' => $project->id,
        ]);

        // Add team membership via participants table
        Participant::create([
            'entity_type' => 'team',
            'entity_id'   => $team->id,
            'user_id'     => $teamMember->id,
            'role'        => 'member',
            'joined_at'   => now(),
        ]);

        $task = $this->createTask($project, $owner, [
            'title'   => 'Team Task',
            'team_id' => $team->id,
        ]);

        $response = $this->actingAs($teamMember, 'sanctum')
            ->getJson("/api/tasks/{$task->id}");

        $response->assertOk()
            ->assertJsonPath('task.title', 'Team Task');
    }

    public function test_non_team_member_cannot_access_team_task_without_project_membership(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();

        $project = $this->createProject($owner);
        $team = Team::create([
            'name'       => 'Private Team',
            'slug'       => 'private-team',
            'owner_id'   => $owner->id,
            'project_id' => $project->id,
        ]);

        $task = $this->createTask($project, $owner, [
            'title'   => 'Private Team Task',
            'team_id' => $team->id,
        ]);

        $response = $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════
    //  PARTICIPANT ROLE CHECKS
    // ══════════════════════════════════════════════════════════════

    public function test_different_participant_roles_can_view_project(): void
    {
        $owner = $this->createUser();
        $project = $this->createProject($owner);

        $roles = ['member', 'admin'];

        foreach ($roles as $role) {
            $user = $this->createUser();
            $this->addProjectMember($project, $user, $role);

            $response = $this->actingAs($user, 'sanctum')
                ->getJson("/api/projects/{$project->id}");

            $response->assertOk();
        }
    }

    public function test_only_owner_can_modify_project_regardless_of_participant_role(): void
    {
        $owner = $this->createUser();
        $admin = $this->createUser();
        $project = $this->createProject($owner);
        $this->addProjectMember($project, $admin, 'admin');

        // Admin participant still can't update project
        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/projects/{$project->id}", [
                'name' => 'Admin Update Attempt',
            ]);

        $response->assertStatus(403);

        // Admin participant still can't delete project
        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/projects/{$project->id}");

        $response->assertStatus(403);
    }
}
