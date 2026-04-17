<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Routing\Middleware\ThrottleRequests;

class TaskTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    // ══════════════════════════════════════════════════════════════
    //  INDEX
    // ══════════════════════════════════════════════════════════════

    public function test_index_returns_tasks_for_owned_projects(): void
    {
        $owner = $this->createUser();
        $project = $this->createProject($owner);
        $this->createTask($project, $owner, ['title' => 'Owner Task']);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/tasks');

        $response->assertOk()
            ->assertJsonStructure([
                'tasks',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $titles = collect($response->json('tasks'))->pluck('title');
        $this->assertTrue($titles->contains('Owner Task'));
    }

    public function test_index_returns_tasks_for_projects_user_is_member_of(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $project = $this->createProject($owner);
        $this->addProjectMember($project, $member);
        $this->createTask($project, $owner, ['title' => 'Member Visible Task']);

        $response = $this->actingAs($member, 'sanctum')
            ->getJson('/api/tasks');

        $response->assertOk();

        $titles = collect($response->json('tasks'))->pluck('title');
        $this->assertTrue($titles->contains('Member Visible Task'));
    }

    public function test_index_can_filter_by_project_id(): void
    {
        $owner = $this->createUser();
        $projectA = $this->createProject($owner, ['name' => 'Project A']);
        $projectB = $this->createProject($owner, ['name' => 'Project B']);
        $this->createTask($projectA, $owner, ['title' => 'Task A']);
        $this->createTask($projectB, $owner, ['title' => 'Task B']);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/tasks?project_id={$projectA->id}");

        $response->assertOk();

        $titles = collect($response->json('tasks'))->pluck('title');
        $this->assertTrue($titles->contains('Task A'));
        $this->assertFalse($titles->contains('Task B'));
    }

    public function test_index_can_filter_by_status(): void
    {
        $owner = $this->createUser();
        $project = $this->createProject($owner);
        $this->createTask($project, $owner, ['title' => 'Todo Task', 'status' => 'todo']);
        $this->createTask($project, $owner, ['title' => 'Done Task', 'status' => 'done']);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/tasks?status=todo');

        $response->assertOk();

        $titles = collect($response->json('tasks'))->pluck('title');
        $this->assertTrue($titles->contains('Todo Task'));
        $this->assertFalse($titles->contains('Done Task'));
    }

    public function test_index_can_filter_by_priority(): void
    {
        $owner = $this->createUser();
        $project = $this->createProject($owner);
        $this->createTask($project, $owner, ['title' => 'Urgent Task', 'priority' => 'urgent']);
        $this->createTask($project, $owner, ['title' => 'Low Task', 'priority' => 'low']);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/tasks?priority=urgent');

        $response->assertOk();

        $titles = collect($response->json('tasks'))->pluck('title');
        $this->assertTrue($titles->contains('Urgent Task'));
        $this->assertFalse($titles->contains('Low Task'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/tasks');
        $response->assertStatus(401);
    }

    // ══════════════════════════════════════════════════════════════
    //  STORE
    // ══════════════════════════════════════════════════════════════

    public function test_store_creates_task(): void
    {
        $user = $this->createUser();
        $project = $this->createProject($user);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'project_id'  => $project->id,
                'title'       => 'New Task',
                'description' => 'Task description',
                'status'      => 'todo',
                'priority'    => 'high',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'task' => ['id', 'title'],
            ])
            ->assertJsonPath('task.title', 'New Task');

        $this->assertDatabaseHas('tasks', [
            'title'      => 'New Task',
            'project_id' => $project->id,
            'created_by' => $user->id,
            'priority'   => 'high',
        ]);
    }

    public function test_store_creates_task_with_assignees(): void
    {
        $user = $this->createUser();
        $assignee = $this->createUser();
        $project = $this->createProject($user);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'project_id' => $project->id,
                'title'      => 'Assigned Task',
                'assignees'  => [$assignee->id],
            ]);

        $response->assertStatus(201);

        $taskId = $response->json('task.id');
        $this->assertDatabaseHas('task_assignees', [
            'task_id' => $taskId,
            'user_id' => $assignee->id,
        ]);
    }

    public function test_store_creates_activity_log(): void
    {
        $user = $this->createUser();
        $project = $this->createProject($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'project_id' => $project->id,
                'title'      => 'Logged Task',
            ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id'     => $user->id,
            'action'      => 'CREATED_TASK',
            'target_type' => 'task',
        ]);
    }

    public function test_store_fails_without_required_fields(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', []);

        $response->assertStatus(422);
        $details = $response->json('error.details');
        $this->assertArrayHasKey('project_id', $details);
        $this->assertArrayHasKey('title', $details);
    }

    public function test_store_fails_with_invalid_project_id(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'project_id' => 99999,
                'title'      => 'Orphan Task',
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('project_id', $response->json('error.details'));
    }

    public function test_store_validates_status_enum(): void
    {
        $user = $this->createUser();
        $project = $this->createProject($user);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'project_id' => $project->id,
                'title'      => 'Bad Status',
                'status'     => 'invalid',
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('status', $response->json('error.details'));
    }

    public function test_store_validates_priority_enum(): void
    {
        $user = $this->createUser();
        $project = $this->createProject($user);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'project_id' => $project->id,
                'title'      => 'Bad Priority',
                'priority'   => 'invalid',
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('priority', $response->json('error.details'));
    }

    // ══════════════════════════════════════════════════════════════
    //  SHOW
    // ══════════════════════════════════════════════════════════════

    public function test_show_returns_task_for_project_member(): void
    {
        $owner = $this->createUser();
        $member = $this->createUser();
        $project = $this->createProject($owner);
        $this->addProjectMember($project, $member);
        $task = $this->createTask($project, $owner, ['title' => 'Visible Task']);

        $response = $this->actingAs($member, 'sanctum')
            ->getJson("/api/tasks/{$task->id}");

        $response->assertOk()
            ->assertJsonPath('task.title', 'Visible Task');
    }

    public function test_show_returns_task_for_creator(): void
    {
        $creator = $this->createUser();
        $project = $this->createProject($creator);
        $task = $this->createTask($project, $creator, ['title' => 'Creator Task']);

        $response = $this->actingAs($creator, 'sanctum')
            ->getJson("/api/tasks/{$task->id}");

        $response->assertOk()
            ->assertJsonPath('task.title', 'Creator Task');
    }

    public function test_show_returns_403_for_non_member(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();
        $project = $this->createProject($owner);
        $task = $this->createTask($project, $owner);

        $response = $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(403);
    }

    public function test_show_returns_404_for_nonexistent_task(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/tasks/99999');

        $response->assertStatus(404);
    }

    // ══════════════════════════════════════════════════════════════
    //  UPDATE
    // ══════════════════════════════════════════════════════════════

    public function test_update_modifies_task(): void
    {
        $user = $this->createUser();
        $project = $this->createProject($user);
        $task = $this->createTask($project, $user, ['title' => 'Old Title']);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", [
                'title'    => 'New Title',
                'priority' => 'urgent',
            ]);

        $response->assertOk()
            ->assertJsonPath('task.title', 'New Title');

        $this->assertDatabaseHas('tasks', [
            'id'       => $task->id,
            'title'    => 'New Title',
            'priority' => 'urgent',
        ]);
    }

    public function test_update_sets_completed_at_when_status_changes_to_done(): void
    {
        $user = $this->createUser();
        $project = $this->createProject($user);
        $task = $this->createTask($project, $user, ['status' => 'in_progress']);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", [
                'status' => 'done',
            ]);

        $response->assertOk();

        $task->refresh();
        $this->assertEquals('done', $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertEquals(100, $task->progress);
    }

    public function test_update_syncs_assignees(): void
    {
        $user = $this->createUser();
        $assigneeA = $this->createUser();
        $assigneeB = $this->createUser();
        $project = $this->createProject($user);
        $task = $this->createTask($project, $user);

        // Initially assign A
        $task->assignees()->attach($assigneeA->id, [
            'assigned_at' => now(),
            'assigned_by' => $user->id,
        ]);

        // Update to assign B instead
        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", [
                'assignees' => [$assigneeB->id],
            ]);

        $response->assertOk();

        $this->assertDatabaseMissing('task_assignees', [
            'task_id' => $task->id,
            'user_id' => $assigneeA->id,
        ]);
        $this->assertDatabaseHas('task_assignees', [
            'task_id' => $task->id,
            'user_id' => $assigneeB->id,
        ]);
    }

    public function test_update_creates_activity_log(): void
    {
        $user = $this->createUser();
        $project = $this->createProject($user);
        $task = $this->createTask($project, $user);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", [
                'title' => 'Updated',
            ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id'     => $user->id,
            'action'      => 'UPDATED_TASK',
            'target_type' => 'task',
            'target_id'   => $task->id,
        ]);
    }

    public function test_update_returns_403_for_non_member(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();
        $project = $this->createProject($owner);
        $task = $this->createTask($project, $owner);

        $response = $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", [
                'title' => 'Hacked Title',
            ]);

        $response->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════
    //  DELETE
    // ══════════════════════════════════════════════════════════════

    public function test_destroy_soft_deletes_task(): void
    {
        $user = $this->createUser();
        $project = $this->createProject($user);
        $task = $this->createTask($project, $user);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/tasks/{$task->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Task deleted.']);

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_destroy_creates_activity_log(): void
    {
        $user = $this->createUser();
        $project = $this->createProject($user);
        $task = $this->createTask($project, $user);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/tasks/{$task->id}");

        $this->assertDatabaseHas('activity_logs', [
            'user_id'     => $user->id,
            'action'      => 'DELETED_TASK',
            'target_type' => 'task',
            'target_id'   => $task->id,
        ]);
    }

    public function test_destroy_returns_403_for_non_member(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();
        $project = $this->createProject($owner);
        $task = $this->createTask($project, $owner);

        $response = $this->actingAs($stranger, 'sanctum')
            ->deleteJson("/api/tasks/{$task->id}");

        $response->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════
    //  BULK STATUS UPDATE
    // ══════════════════════════════════════════════════════════════

    public function test_bulk_update_status_changes_multiple_tasks(): void
    {
        $user = $this->createUser();
        $project = $this->createProject($user);
        $task1 = $this->createTask($project, $user, ['title' => 'Bulk 1', 'status' => 'todo']);
        $task2 = $this->createTask($project, $user, ['title' => 'Bulk 2', 'status' => 'todo']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks/bulk-status', [
                'tasks' => [
                    ['id' => $task1->id, 'status' => 'in_progress'],
                    ['id' => $task2->id, 'status' => 'done'],
                ],
            ]);

        $response->assertOk()
            ->assertJson(['message' => 'Tasks updated.']);

        $this->assertDatabaseHas('tasks', ['id' => $task1->id, 'status' => 'in_progress']);
        $this->assertDatabaseHas('tasks', ['id' => $task2->id, 'status' => 'done']);
    }

    public function test_bulk_update_sets_completed_at_for_done_tasks(): void
    {
        $user = $this->createUser();
        $project = $this->createProject($user);
        $task = $this->createTask($project, $user, ['status' => 'todo']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks/bulk-status', [
                'tasks' => [
                    ['id' => $task->id, 'status' => 'done'],
                ],
            ]);

        $task->refresh();
        $this->assertNotNull($task->completed_at);
        $this->assertEquals(100, $task->progress);
    }

    public function test_bulk_update_skips_tasks_user_cannot_access(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();
        $project = $this->createProject($owner);
        $task = $this->createTask($project, $owner, ['status' => 'todo']);

        // Stranger's own project + task for comparison
        $strangerProject = $this->createProject($stranger);
        $strangerTask = $this->createTask($strangerProject, $stranger, ['status' => 'todo']);

        $this->actingAs($stranger, 'sanctum')
            ->postJson('/api/tasks/bulk-status', [
                'tasks' => [
                    ['id' => $task->id, 'status' => 'done'],
                    ['id' => $strangerTask->id, 'status' => 'done'],
                ],
            ]);

        // Owner's task should remain unchanged
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'todo']);
        // Stranger's task should be updated
        $this->assertDatabaseHas('tasks', ['id' => $strangerTask->id, 'status' => 'done']);
    }

    public function test_bulk_update_validates_required_fields(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks/bulk-status', []);

        $response->assertStatus(422);
        $this->assertArrayHasKey('tasks', $response->json('error.details'));
    }

    public function test_bulk_update_validates_status_enum(): void
    {
        $user = $this->createUser();
        $project = $this->createProject($user);
        $task = $this->createTask($project, $user);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks/bulk-status', [
                'tasks' => [
                    ['id' => $task->id, 'status' => 'invalid_status'],
                ],
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('tasks.0.status', $response->json('error.details'));
    }

    public function test_bulk_update_enforces_max_50_tasks(): void
    {
        $user = $this->createUser();

        $tasks = [];
        for ($i = 0; $i < 51; $i++) {
            $tasks[] = ['id' => $i + 1, 'status' => 'done'];
        }

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks/bulk-status', [
                'tasks' => $tasks,
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('tasks', $response->json('error.details'));
    }
}
