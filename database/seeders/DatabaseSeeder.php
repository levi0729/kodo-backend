<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use App\Models\ChatRoom;
use App\Models\Friend;
use App\Models\Participant;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Users ────────────────────────────────────────────
        $password = Hash::make('password123');

        $admin = User::create([
            'username'        => 'admin',
            'email'           => 'admin@kodo.dev',
            'password'        => $password,
            'display_name'    => 'Kovács Anna',
            'job_title'       => 'Project Manager',
            'department'      => 'Management',
            'presence_status' => 'online',
            'last_seen_at'    => now(),
            'is_active'       => true,
        ]);

        $tester = User::create([
            'username'        => 'tester',
            'email'           => 'tester@kodo.dev',
            'password'        => $password,
            'display_name'    => 'Szabó Péter',
            'job_title'       => 'QA Engineer',
            'department'      => 'Quality Assurance',
            'presence_status' => 'online',
            'last_seen_at'    => now(),
            'is_active'       => true,
        ]);

        $dev1 = User::create([
            'username'        => 'dev1',
            'email'           => 'dev1@kodo.dev',
            'password'        => $password,
            'display_name'    => 'Nagy László',
            'job_title'       => 'Backend Developer',
            'department'      => 'Engineering',
            'presence_status' => 'away',
            'last_seen_at'    => now()->subMinutes(15),
            'is_active'       => true,
        ]);

        $dev2 = User::create([
            'username'        => 'dev2',
            'email'           => 'dev2@kodo.dev',
            'password'        => $password,
            'display_name'    => 'Tóth Eszter',
            'job_title'       => 'Frontend Developer',
            'department'      => 'Engineering',
            'presence_status' => 'dnd',
            'last_seen_at'    => now()->subMinutes(5),
            'is_active'       => true,
        ]);

        $designer = User::create([
            'username'        => 'designer',
            'email'           => 'designer@kodo.dev',
            'password'        => $password,
            'display_name'    => 'Kiss Márk',
            'job_title'       => 'UI/UX Designer',
            'department'      => 'Design',
            'presence_status' => 'offline',
            'last_seen_at'    => now()->subHours(2),
            'is_active'       => true,
        ]);

        $allUsers = [$admin, $tester, $dev1, $dev2, $designer];

        // ── User Settings ────────────────────────────────────
        foreach ($allUsers as $user) {
            UserSetting::create([
                'user_id'               => $user->id,
                'theme'                 => 'dark',
                'language'              => 'hu',
                'notifications_enabled' => true,
                'email_notifications'   => true,
                'push_notifications'    => true,
                'show_online_status'    => true,
            ]);
        }

        // ── Friends ──────────────────────────────────────────
        Friend::create(['user_id_1' => $admin->id, 'user_id_2' => $tester->id, 'status' => 'accepted']);
        Friend::create(['user_id_1' => $admin->id, 'user_id_2' => $dev1->id,   'status' => 'accepted']);
        Friend::create(['user_id_1' => $admin->id, 'user_id_2' => $dev2->id,   'status' => 'accepted']);
        Friend::create(['user_id_1' => $tester->id, 'user_id_2' => $dev1->id,  'status' => 'accepted']);
        Friend::create(['user_id_1' => $dev1->id, 'user_id_2' => $designer->id, 'status' => 'pending']);

        // ── Projects ─────────────────────────────────────────
        $projectKodo = Project::create([
            'name'            => 'Kodo Platform',
            'slug'            => 'kodo-platform',
            'description'     => 'Main project management platform',
            'color'           => '#6366f1',
            'project_type'    => 'kanban',
            'status'          => 'active',
            'start_date'      => now()->subMonths(2),
            'target_end_date' => now()->addMonths(3),
            'progress'        => 45,
            'owner_id'        => $admin->id,
        ]);

        $projectMobile = Project::create([
            'name'            => 'Kodo Mobile',
            'slug'            => 'kodo-mobile',
            'description'     => 'Mobile companion app',
            'color'           => '#ec4899',
            'project_type'    => 'kanban',
            'status'          => 'active',
            'start_date'      => now()->subMonth(),
            'target_end_date' => now()->addMonths(4),
            'progress'        => 20,
            'owner_id'        => $admin->id,
        ]);

        $projectDesign = Project::create([
            'name'            => 'Design System',
            'slug'            => 'design-system',
            'description'     => 'Shared component library & design tokens',
            'color'           => '#14b8a6',
            'project_type'    => 'list',
            'status'          => 'active',
            'start_date'      => now()->subWeeks(3),
            'target_end_date' => now()->addMonths(2),
            'progress'        => 60,
            'owner_id'        => $designer->id,
        ]);

        // ── Project Participants ─────────────────────────────
        foreach ($allUsers as $user) {
            Participant::create([
                'entity_type' => 'project',
                'entity_id'   => $projectKodo->id,
                'user_id'     => $user->id,
                'role'        => $user->id === $admin->id ? 'admin' : 'member',
                'joined_at'   => now()->subMonths(2),
            ]);
        }
        foreach ([$admin, $dev1, $dev2] as $user) {
            Participant::create([
                'entity_type' => 'project',
                'entity_id'   => $projectMobile->id,
                'user_id'     => $user->id,
                'role'        => $user->id === $admin->id ? 'admin' : 'member',
                'joined_at'   => now()->subMonth(),
            ]);
        }
        foreach ([$admin, $designer, $dev2] as $user) {
            Participant::create([
                'entity_type' => 'project',
                'entity_id'   => $projectDesign->id,
                'user_id'     => $user->id,
                'role'        => $user->id === $designer->id ? 'admin' : 'member',
                'joined_at'   => now()->subWeeks(3),
            ]);
        }

        // ── Teams ────────────────────────────────────────────
        $teamBackend = Team::create([
            'project_id'  => $projectKodo->id,
            'name'        => 'Backend Team',
            'slug'        => 'backend-team',
            'description' => 'API & server-side development',
            'color'       => '#6366f1',
            'visibility'  => 'public',
            'owner_id'    => $admin->id,
        ]);

        $teamFrontend = Team::create([
            'project_id'  => $projectKodo->id,
            'name'        => 'Frontend Team',
            'slug'        => 'frontend-team',
            'description' => 'UI & client-side development',
            'color'       => '#ec4899',
            'visibility'  => 'public',
            'owner_id'    => $admin->id,
        ]);

        $teamDesign = Team::create([
            'project_id'  => $projectDesign->id,
            'name'        => 'Design Team',
            'slug'        => 'design-team',
            'description' => 'UI/UX design & prototyping',
            'color'       => '#14b8a6',
            'visibility'  => 'public',
            'owner_id'    => $designer->id,
        ]);

        $teamQA = Team::create([
            'project_id'  => $projectKodo->id,
            'name'        => 'QA Team',
            'slug'        => 'qa-team',
            'description' => 'Testing & quality assurance',
            'color'       => '#f59e0b',
            'visibility'  => 'private',
            'is_private'  => true,
            'owner_id'    => $tester->id,
        ]);

        // ── Team Participants ────────────────────────────────
        $teamMemberships = [
            $teamBackend->id  => [$admin, $dev1, $tester],
            $teamFrontend->id => [$admin, $dev2, $designer],
            $teamDesign->id   => [$designer, $dev2, $admin],
            $teamQA->id       => [$tester, $admin, $dev1],
        ];

        foreach ($teamMemberships as $teamId => $members) {
            foreach ($members as $i => $user) {
                Participant::create([
                    'entity_type' => 'team',
                    'entity_id'   => $teamId,
                    'user_id'     => $user->id,
                    'role'        => $i === 0 ? 'admin' : 'member',
                    'joined_at'   => now()->subWeeks(rand(1, 8)),
                ]);
            }
        }

        // ── Tasks ────────────────────────────────────────────
        $tasks = [
            // Kodo Platform - TODO
            ['project_id' => $projectKodo->id, 'title' => 'Set up CI/CD pipeline', 'description' => 'Configure GitHub Actions for automated testing and deployment', 'status' => 'todo', 'priority' => 'high', 'labels' => ['infra', 'urgent'], 'created_by' => $admin->id, 'assignees' => [$dev1->id]],
            ['project_id' => $projectKodo->id, 'title' => 'Add file upload to chat', 'description' => 'Allow users to share files in direct messages', 'status' => 'todo', 'priority' => 'medium', 'labels' => ['backend', 'frontend'], 'created_by' => $admin->id, 'assignees' => [$dev1->id, $dev2->id]],
            ['project_id' => $projectKodo->id, 'title' => 'Create onboarding flow', 'description' => 'Welcome wizard for new users', 'status' => 'todo', 'priority' => 'low', 'labels' => ['ux', 'frontend'], 'created_by' => $designer->id, 'assignees' => [$designer->id]],

            // Kodo Platform - IN PROGRESS
            ['project_id' => $projectKodo->id, 'title' => 'Implement real-time notifications', 'description' => 'WebSocket-based live notifications', 'status' => 'in_progress', 'priority' => 'high', 'labels' => ['backend', 'frontend'], 'created_by' => $admin->id, 'assignees' => [$dev1->id, $dev2->id], 'progress' => 60],
            ['project_id' => $projectKodo->id, 'title' => 'Redesign settings page', 'description' => 'Improve layout and add new settings', 'status' => 'in_progress', 'priority' => 'medium', 'labels' => ['design', 'frontend'], 'created_by' => $designer->id, 'assignees' => [$dev2->id], 'progress' => 30],

            // Kodo Platform - IN REVIEW
            ['project_id' => $projectKodo->id, 'title' => 'Fix login rate limiting', 'description' => 'Account lockout after 5 failed attempts', 'status' => 'in_review', 'priority' => 'high', 'labels' => ['security', 'backend'], 'created_by' => $tester->id, 'assignees' => [$dev1->id], 'progress' => 90],
            ['project_id' => $projectKodo->id, 'title' => 'Add task drag-and-drop', 'description' => 'Kanban board drag to reorder', 'status' => 'in_review', 'priority' => 'medium', 'labels' => ['frontend', 'ux'], 'created_by' => $admin->id, 'assignees' => [$dev2->id], 'progress' => 85],

            // Kodo Platform - DONE
            ['project_id' => $projectKodo->id, 'title' => 'User authentication system', 'description' => 'Login, register, password reset with Sanctum', 'status' => 'done', 'priority' => 'high', 'labels' => ['backend', 'security'], 'created_by' => $admin->id, 'assignees' => [$dev1->id], 'progress' => 100],
            ['project_id' => $projectKodo->id, 'title' => 'Dashboard layout', 'description' => 'Main dashboard with stats and widgets', 'status' => 'done', 'priority' => 'medium', 'labels' => ['frontend', 'design'], 'created_by' => $designer->id, 'assignees' => [$dev2->id, $designer->id], 'progress' => 100],
            ['project_id' => $projectKodo->id, 'title' => 'Database schema design', 'description' => 'PostgreSQL schema for all entities', 'status' => 'done', 'priority' => 'high', 'labels' => ['backend', 'infra'], 'created_by' => $admin->id, 'assignees' => [$dev1->id], 'progress' => 100],

            // Kodo Mobile tasks
            ['project_id' => $projectMobile->id, 'title' => 'Mobile app wireframes', 'description' => 'Figma wireframes for all screens', 'status' => 'in_progress', 'priority' => 'high', 'labels' => ['design'], 'created_by' => $admin->id, 'assignees' => [$dev2->id], 'progress' => 50],
            ['project_id' => $projectMobile->id, 'title' => 'Set up React Native project', 'description' => 'Initialize project with navigation and state management', 'status' => 'todo', 'priority' => 'high', 'labels' => ['frontend', 'infra'], 'created_by' => $admin->id, 'assignees' => [$dev2->id]],

            // Design System tasks
            ['project_id' => $projectDesign->id, 'title' => 'Button component variants', 'description' => 'Primary, secondary, ghost, danger button styles', 'status' => 'done', 'priority' => 'medium', 'labels' => ['design', 'frontend'], 'created_by' => $designer->id, 'assignees' => [$designer->id, $dev2->id], 'progress' => 100],
            ['project_id' => $projectDesign->id, 'title' => 'Color token system', 'description' => 'CSS custom properties for all brand colors', 'status' => 'in_progress', 'priority' => 'high', 'labels' => ['design'], 'created_by' => $designer->id, 'assignees' => [$designer->id], 'progress' => 70],
            ['project_id' => $projectDesign->id, 'title' => 'Avatar component', 'description' => 'Avatar with status indicator and stack variant', 'status' => 'done', 'priority' => 'medium', 'labels' => ['frontend'], 'created_by' => $designer->id, 'assignees' => [$dev2->id], 'progress' => 100],
        ];

        foreach ($tasks as $taskData) {
            $assignees = $taskData['assignees'] ?? [];
            unset($taskData['assignees']);
            $taskData['labels'] = $taskData['labels'] ?? [];
            $taskData['progress'] = $taskData['progress'] ?? 0;
            $taskData['due_date'] = now()->addDays(rand(3, 30));
            $taskData['estimated_hours'] = rand(2, 40);

            $task = Task::create($taskData);

            if (!empty($assignees)) {
                $pivotData = [];
                foreach ($assignees as $userId) {
                    $pivotData[$userId] = [
                        'assigned_at' => now(),
                        'assigned_by' => $taskData['created_by'],
                    ];
                }
                $task->assignees()->attach($pivotData);
            }
        }

        // ── Chat Messages (DMs) ──────────────────────────────
        $conversations = [
            [$admin, $tester, [
                ['Hi! How is the testing going?', 0],
                ['Going well! Found a few bugs in the messaging system.', 1],
                ['Great, can you log them as tasks?', 0],
                ['Already on it. The sender display is broken.', 1],
                ['Thanks, I will take a look at it today.', 0],
            ]],
            [$admin, $dev1, [
                ['Hey, can you review the PR for notifications?', 0],
                ['Sure, I will check it after lunch.', 1],
                ['No rush, just before end of day would be great.', 0],
                ['Done! Left a few comments, mostly looks good.', 1],
            ]],
            [$dev1, $dev2, [
                ['The API endpoint for tasks is ready.', 0],
                ['Nice! I will hook up the frontend tomorrow.', 1],
                ['I added pagination, check the docs.', 0],
            ]],
            [$tester, $dev2, [
                ['The calendar page has a rendering issue on mobile.', 0],
                ['Can you send me a screenshot?', 1],
                ['Sent it in the task description, check task #12.', 0],
            ]],
        ];

        foreach ($conversations as [$user1, $user2, $msgs]) {
            $roomId = min($user1->id, $user2->id) * 100000 + max($user1->id, $user2->id);
            foreach ($msgs as $i => [$text, $senderIdx]) {
                $sender = $senderIdx === 0 ? $user1 : $user2;
                $receiver = $senderIdx === 0 ? $user2 : $user1;
                ChatRoom::create([
                    'room_id'     => $roomId,
                    'sender_id'   => $sender->id,
                    'receiver_id' => $receiver->id,
                    'message'     => $text,
                    'is_read'     => $i < count($msgs) - 1,
                    'sent_at'     => now()->subMinutes((count($msgs) - $i) * 15),
                ]);
            }
        }

        // ── Team Chat Messages ───────────────────────────────
        $teamConversations = [
            [$teamBackend->id, [
                [$admin, 'Welcome to the Backend Team chat!', 60],
                [$dev1, 'Hey everyone! Ready to start on the API.', 55],
                [$tester, 'I will be monitoring for bugs, keep me posted.', 50],
                [$admin, 'Sprint planning is tomorrow at 9 AM.', 30],
                [$dev1, 'The auth endpoints are ready for review.', 15],
            ]],
            [$teamFrontend->id, [
                [$admin, 'Frontend team, let\'s coordinate on the UI components.', 45],
                [$dev2, 'I\'m working on the dashboard layout now.', 40],
                [$designer, 'I\'ll share the design files by end of day.', 35],
                [$dev2, 'The calendar component is almost done.', 10],
            ]],
            [$teamDesign->id, [
                [$designer, 'Design system kickoff! Let\'s define our tokens.', 90],
                [$admin, 'Looking forward to seeing the color palette.', 85],
                [$dev2, 'I can help with the CSS implementation.', 80],
            ]],
            [$teamQA->id, [
                [$tester, 'QA channel is live. Report bugs here.', 120],
                [$admin, 'Great idea, this will help track issues.', 115],
                [$dev1, 'Found a bug in the login flow, logging it now.', 60],
                [$tester, 'Thanks! I\'ll verify the fix once it\'s deployed.', 55],
            ]],
        ];

        foreach ($teamConversations as [$teamId, $msgs]) {
            foreach ($msgs as [$sender, $text, $minutesAgo]) {
                ChatRoom::create([
                    'room_id'     => $teamId,
                    'sender_id'   => $sender->id,
                    'receiver_id' => $sender->id, // Self-ref for team messages
                    'message'     => $text,
                    'is_read'     => true,
                    'sent_at'     => now()->subMinutes($minutesAgo),
                ]);
            }
        }

        // ── Calendar Events ──────────────────────────────────
        // Get Monday of the current week
        $thisMonday = now()->startOfWeek();

        $events = [
            // Events this week (Mon-Fri)
            [
                'team_id'       => $teamBackend->id,
                'organizer_id'  => $admin->id,
                'title'         => 'Sprint Planning',
                'description'   => 'Plan next sprint goals and task assignments',
                'start_time'    => $thisMonday->copy()->setTime(9, 0),
                'end_time'      => $thisMonday->copy()->setTime(10, 30),
                'is_online_meeting' => true,
                'meeting_url'   => 'https://meet.kodo.dev/sprint',
                'color'         => '#6366f1',
                'status'        => 'confirmed',
                'attendees'     => [$admin->id, $dev1->id, $dev2->id, $tester->id],
            ],
            [
                'team_id'       => $teamFrontend->id,
                'organizer_id'  => $dev2->id,
                'title'         => 'Frontend Standup',
                'description'   => 'Daily sync on frontend progress',
                'start_time'    => $thisMonday->copy()->addDay()->setTime(9, 30),
                'end_time'      => $thisMonday->copy()->addDay()->setTime(9, 45),
                'is_online_meeting' => true,
                'meeting_url'   => 'https://meet.kodo.dev/standup',
                'color'         => '#ec4899',
                'status'        => 'confirmed',
                'attendees'     => [$dev2->id, $designer->id, $admin->id],
            ],
            [
                'team_id'       => $teamDesign->id,
                'organizer_id'  => $designer->id,
                'title'         => 'Design Review',
                'description'   => 'Review latest UI mockups and gather feedback',
                'start_time'    => $thisMonday->copy()->addDays(2)->setTime(14, 0),
                'end_time'      => $thisMonday->copy()->addDays(2)->setTime(15, 0),
                'is_online_meeting' => false,
                'location'      => 'Meeting Room B',
                'color'         => '#14b8a6',
                'status'        => 'confirmed',
                'attendees'     => [$designer->id, $dev2->id, $admin->id],
            ],
            [
                'team_id'       => $teamQA->id,
                'organizer_id'  => $tester->id,
                'title'         => 'Bug Triage',
                'description'   => 'Review and prioritize reported bugs',
                'start_time'    => $thisMonday->copy()->addDays(3)->setTime(11, 0),
                'end_time'      => $thisMonday->copy()->addDays(3)->setTime(12, 0),
                'is_online_meeting' => true,
                'meeting_url'   => 'https://meet.kodo.dev/triage',
                'color'         => '#f59e0b',
                'status'        => 'confirmed',
                'attendees'     => [$tester->id, $admin->id, $dev1->id],
            ],
            [
                'organizer_id'  => $admin->id,
                'title'         => 'All-Hands Meeting',
                'description'   => 'Monthly company-wide sync',
                'start_time'    => $thisMonday->copy()->addDays(4)->setTime(16, 0),
                'end_time'      => $thisMonday->copy()->addDays(4)->setTime(17, 0),
                'is_online_meeting' => true,
                'meeting_url'   => 'https://meet.kodo.dev/allhands',
                'color'         => '#a855f7',
                'status'        => 'confirmed',
                'attendees'     => array_map(fn ($u) => $u->id, $allUsers),
            ],
            // Events next week
            [
                'team_id'       => $teamBackend->id,
                'organizer_id'  => $admin->id,
                'title'         => 'Code Review Session',
                'description'   => 'Review pending PRs and merge strategy',
                'start_time'    => $thisMonday->copy()->addWeek()->setTime(10, 0),
                'end_time'      => $thisMonday->copy()->addWeek()->setTime(11, 0),
                'is_online_meeting' => true,
                'meeting_url'   => 'https://meet.kodo.dev/review',
                'color'         => '#6366f1',
                'status'        => 'confirmed',
                'attendees'     => [$admin->id, $dev1->id, $dev2->id],
            ],
            [
                'team_id'       => $teamDesign->id,
                'organizer_id'  => $designer->id,
                'title'         => 'UX Testing Results',
                'description'   => 'Present findings from user testing sessions',
                'start_time'    => $thisMonday->copy()->addWeek()->addDays(2)->setTime(14, 0),
                'end_time'      => $thisMonday->copy()->addWeek()->addDays(2)->setTime(15, 30),
                'is_online_meeting' => false,
                'location'      => 'Conference Room A',
                'color'         => '#14b8a6',
                'status'        => 'confirmed',
                'attendees'     => [$designer->id, $admin->id, $tester->id],
            ],
        ];

        foreach ($events as $eventData) {
            $attendees = $eventData['attendees'] ?? [];
            unset($eventData['attendees']);
            $event = CalendarEvent::create($eventData);
            if (!empty($attendees)) {
                $event->attendees()->attach($attendees);
            }
        }

        // ── Time Entries ─────────────────────────────────────
        $workingUsers = [$admin, $dev1, $dev2, $designer, $tester];
        $activityTypes = ['development', 'review', 'meeting', 'design', 'testing'];

        for ($day = 0; $day < 14; $day++) {
            $date = now()->subDays($day);
            if ($date->isWeekend()) continue;

            foreach ($workingUsers as $user) {
                $entries = rand(1, 3);
                for ($e = 0; $e < $entries; $e++) {
                    TimeEntry::create([
                        'user_id'       => $user->id,
                        'project_id'    => $projectKodo->id,
                        'hours'         => round(rand(10, 40) / 10, 1),
                        'date'          => $date->toDateString(),
                        'activity_type' => $activityTypes[array_rand($activityTypes)],
                        'description'   => 'Work on project tasks',
                    ]);
                }
            }
        }
    }
}
