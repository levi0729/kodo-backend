<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Routing\Middleware\ThrottleRequests;

class ProjectTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    // ══════════════════════════════════════════════════════════════
    //  INDEX
    // ══════════════════════════════════════════════════════════════

    public function test_index_returns_projects_user_owns(): void
    {
        $owner = $this->createUser();
        $project = $this->createProject($owner, ['name' => 'My Project']);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/projects');

        $response->assertOk()
            ->assertJsonStructure([
                'projects',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $names = collect($response->json('projects'))->pluck('name');
        $this->assertTrue($names->contains('My Project'));
    }

    public function test_index_returns_projects_user_is_participant_of(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();

        $project = $this->createProject($owner, ['name' => 'Shared Project']);
        $this->addProjectMember($project, $member);

        $response = $this->actingAs($member, 'sanctum')
            ->getJson('/api/projects');

        $response->assertOk();

        $names = collect($response->json('projects'))->pluck('name');
        $this->assertTrue($names->contains('Shared Project'));
    }

    public function test_index_does_not_return_projects_user_has_no_access_to(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();

        $this->createProject($owner, ['name' => 'Private Project']);

        $response = $this->actingAs($stranger, 'sanctum')
            ->getJson('/api/projects');

        $response->assertOk();

        $names = collect($response->json('projects'))->pluck('name');
        $this->assertFalse($names->contains('Private Project'));
    }

    public function test_index_can_filter_by_status(): void
    {
        $owner = $this->createUser();
        $this->createProject($owner, ['name' => 'Active One', 'status' => 'active']);
        $this->createProject($owner, ['name' => 'Archived One', 'status' => 'archived']);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/projects?status=active');

        $response->assertOk();

        $names = collect($response->json('projects'))->pluck('name');
        $this->assertTrue($names->contains('Active One'));
        $this->assertFalse($names->contains('Archived One'));
    }

    public function test_index_paginates_results(): void
    {
        $owner = $this->createUser();

        for ($i = 1; $i <= 5; $i++) {
            $this->createProject($owner, ['name' => "Project $i"]);
        }

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/projects?per_page=2');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);

        $this->assertCount(2, $response->json('projects'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/projects');
        $response->assertStatus(401);
    }

    // ══════════════════════════════════════════════════════════════
    //  STORE
    // ══════════════════════════════════════════════════════════════

    public function test_store_creates_project(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/projects', [
                'name'        => 'New Project',
                'description' => 'A test project',
                'status'      => 'planning',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'project' => ['id', 'name'],
            ])
            ->assertJsonPath('project.name', 'New Project');

        $this->assertDatabaseHas('projects', [
            'name'     => 'New Project',
            'owner_id' => $user->id,
            'status'   => 'planning',
        ]);
    }

    public function test_store_auto_generates_slug_from_name(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/projects', [
                'name' => 'My Awesome Project',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('projects', [
            'name' => 'My Awesome Project',
            'slug' => 'my-awesome-project',
        ]);
    }

    public function test_store_creates_activity_log(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/projects', [
                'name' => 'Logged Project',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('activity_logs', [
            'user_id'     => $user->id,
            'action'      => 'CREATED_PROJECT',
            'target_type' => 'project',
        ]);
    }

    public function test_store_fails_without_name(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/projects', []);

        $response->assertStatus(422);
        $this->assertArrayHasKey('name', $response->json('error.details'));
    }

    public function test_store_validates_status_enum(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/projects', [
                'name'   => 'Bad Status',
                'status' => 'invalid_status',
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('status', $response->json('error.details'));
    }

    public function test_store_validates_project_type_enum(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/projects', [
                'name'         => 'Bad Type',
                'project_type' => 'invalid_type',
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('project_type', $response->json('error.details'));
    }

    // ══════════════════════════════════════════════════════════════
    //  SHOW
    // ══════════════════════════════════════════════════════════════

    public function test_show_returns_project_for_owner(): void
    {
        $owner = $this->createUser();
        $project = $this->createProject($owner, ['name' => 'Visible Project']);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('project.name', 'Visible Project');
    }

    public function test_show_returns_project_for_member(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $project = $this->createProject($owner, ['name' => 'Member Project']);
        $this->addProjectMember($project, $member);

        $response = $this->actingAs($member, 'sanctum')
            ->getJson("/api/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('project.name', 'Member Project');
    }

    public function test_show_returns_403_for_non_member(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();
        $project = $this->createProject($owner);

        $response = $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/projects/{$project->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden.']);
    }

    public function test_show_returns_404_for_nonexistent_project(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/projects/99999');

        $response->assertStatus(404);
    }

    // ══════════════════════════════════════════════════════════════
    //  UPDATE
    // ══════════════════════════════════════════════════════════════

    public function test_update_modifies_project_for_owner(): void
    {
        $owner = $this->createUser();
        $project = $this->createProject($owner, ['name' => 'Old Name']);

        $response = $this->actingAs($owner, 'sanctum')
            ->putJson("/api/projects/{$project->id}", [
                'name'   => 'Updated Name',
                'status' => 'on_hold',
            ]);

        $response->assertOk()
            ->assertJsonPath('project.name', 'Updated Name');

        $this->assertDatabaseHas('projects', [
            'id'     => $project->id,
            'name'   => 'Updated Name',
            'status' => 'on_hold',
        ]);
    }

    public function test_update_creates_activity_log(): void
    {
        $owner = $this->createUser();
        $project = $this->createProject($owner);

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/projects/{$project->id}", [
                'name' => 'Updated For Log',
            ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id'     => $owner->id,
            'action'      => 'UPDATED_PROJECT',
            'target_type' => 'project',
            'target_id'   => $project->id,
        ]);
    }

    public function test_update_returns_403_for_non_owner(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $project = $this->createProject($owner);
        $this->addProjectMember($project, $member);

        $response = $this->actingAs($member, 'sanctum')
            ->putJson("/api/projects/{$project->id}", [
                'name' => 'Hacked Name',
            ]);

        $response->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════
    //  DELETE
    // ══════════════════════════════════════════════════════════════

    public function test_destroy_soft_deletes_project_for_owner(): void
    {
        $owner = $this->createUser();
        $project = $this->createProject($owner);

        $response = $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/projects/{$project->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Project deleted.']);

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_destroy_creates_activity_log(): void
    {
        $owner = $this->createUser();
        $project = $this->createProject($owner);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/projects/{$project->id}");

        $this->assertDatabaseHas('activity_logs', [
            'user_id'     => $owner->id,
            'action'      => 'DELETED_PROJECT',
            'target_type' => 'project',
            'target_id'   => $project->id,
        ]);
    }

    public function test_destroy_returns_403_for_non_owner(): void
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
    }

    // ══════════════════════════════════════════════════════════════
    //  RESTORE
    // ══════════════════════════════════════════════════════════════

    public function test_restore_recovers_soft_deleted_project(): void
    {
        $owner = $this->createUser();
        $project = $this->createProject($owner);
        $project->delete();

        $this->assertSoftDeleted('projects', ['id' => $project->id]);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/projects/{$project->id}/restore");

        $response->assertOk()
            ->assertJson(['message' => 'Project restored.']);

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertNull(Project::find($project->id)->deleted_at);
    }

    public function test_restore_returns_403_for_non_owner(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();
        $project = $this->createProject($owner);
        $project->delete();

        $response = $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/projects/{$project->id}/restore");

        $response->assertStatus(403);
    }
}
