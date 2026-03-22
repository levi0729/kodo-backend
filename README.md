# Nexus Backend — Laravel API

Complete REST API backend for the Nexus project management platform.  
Built with **Laravel 11 + Sanctum** token authentication.

---

## Quick Setup

```bash
# 1. Copy these files into your existing Laravel project
#    (Models → app/Models, Controllers → app/Http/Controllers/Api, etc.)

# 2. Install Sanctum if not already installed
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# 3. Add Sanctum middleware to api middleware group in bootstrap/app.php
#    ->withMiddleware(function (Middleware $middleware) {
#        $middleware->statefulApi();
#    })

# 4. Run migrations (tables already exist from your SQL dump)
php artisan migrate --seed
```

---

## Architecture Overview

```
app/
├── Http/
│   ├── Controllers/Api/     # 12 controllers
│   │   ├── AuthController           — register, login, logout, me
│   │   ├── UserController           — list users, profile, status
│   │   ├── UserSettingsController   — theme, language, notifications
│   │   ├── ProjectController        — CRUD + soft-delete + restore
│   │   ├── TeamController           — CRUD + join/leave + private teams
│   │   ├── TaskController           — CRUD + bulk status update
│   │   ├── ChatController           — conversations, send, read, pin, delete
│   │   ├── FriendController         — request, accept, decline, remove
│   │   ├── TimeEntryController      — CRUD + date-range summary
│   │   ├── ActivityLogController    — user logs, project logs, global feed
│   │   ├── ParticipantController    — add/remove/role for projects & teams
│   │   └── DashboardController      — aggregate stats
│   ├── Requests/            # 12 form request validators
│   └── Resources/           # 8 API resource transformers
├── Models/                  # 10 Eloquent models with relationships
└── routes/api.php           # All API route definitions
```

---

## Authentication

All endpoints except `POST /api/auth/register` and `POST /api/auth/login` require a Bearer token.

```
Authorization: Bearer {token}
```

---

## API Endpoints

### Auth
| Method | Endpoint              | Description          |
|--------|-----------------------|----------------------|
| POST   | `/api/auth/register`  | Register new user    |
| POST   | `/api/auth/login`     | Login, get token     |
| POST   | `/api/auth/logout`    | Revoke current token |
| GET    | `/api/auth/me`        | Current user + settings |

### Dashboard
| Method | Endpoint         | Description                          |
|--------|------------------|--------------------------------------|
| GET    | `/api/dashboard` | Stats: projects, tasks, hours, activity |

### Users
| Method | Endpoint                | Description                |
|--------|-------------------------|----------------------------|
| GET    | `/api/users`            | List users (`?search=`)    |
| GET    | `/api/users/{id}`       | Show user profile          |
| PUT    | `/api/profile`          | Update own profile         |
| PATCH  | `/api/profile/status`   | Update online status       |

### Settings
| Method | Endpoint        | Description       |
|--------|-----------------|-------------------|
| GET    | `/api/settings` | Get user settings |
| PUT    | `/api/settings` | Update settings   |

### Projects
| Method | Endpoint                       | Description              |
|--------|--------------------------------|--------------------------|
| GET    | `/api/projects`                | List (`?status=`, `?mine_only=`) |
| POST   | `/api/projects`                | Create project           |
| GET    | `/api/projects/{id}`           | Show with teams & tasks  |
| PUT    | `/api/projects/{id}`           | Update project           |
| DELETE | `/api/projects/{id}`           | Soft-delete              |
| POST   | `/api/projects/{id}/restore`   | Restore deleted          |

### Teams
| Method | Endpoint                   | Description                    |
|--------|----------------------------|--------------------------------|
| GET    | `/api/teams`               | List (`?project_id=`)          |
| POST   | `/api/teams`               | Create team                    |
| GET    | `/api/teams/{id}`          | Show with participants         |
| PUT    | `/api/teams/{id}`          | Update team                    |
| DELETE | `/api/teams/{id}`          | Soft-delete                    |
| POST   | `/api/teams/{id}/join`     | Join team (password for private) |
| POST   | `/api/teams/{id}/leave`    | Leave team                     |

### Tasks
| Method | Endpoint                  | Description                          |
|--------|---------------------------|--------------------------------------|
| GET    | `/api/tasks`              | List (`?project_id=`, `?team_id=`, `?status=`, `?priority=`, `?assignee=`) |
| POST   | `/api/tasks`              | Create task                          |
| GET    | `/api/tasks/{id}`         | Show task                            |
| PUT    | `/api/tasks/{id}`         | Update task                          |
| DELETE | `/api/tasks/{id}`         | Delete task                          |
| POST   | `/api/tasks/bulk-status`  | Bulk update statuses (kanban)        |

### Chat
| Method | Endpoint                           | Description             |
|--------|------------------------------------|-------------------------|
| GET    | `/api/chat/conversations`          | List conversations      |
| GET    | `/api/chat/rooms/{roomId}/messages`| Messages in a room      |
| POST   | `/api/chat/send`                   | Send message            |
| PATCH  | `/api/chat/rooms/{roomId}/read`    | Mark as read            |
| PATCH  | `/api/chat/messages/{id}/pin`      | Toggle pin              |
| DELETE | `/api/chat/messages/{id}`          | Soft-delete message     |

### Friends
| Method | Endpoint                       | Description           |
|--------|--------------------------------|-----------------------|
| GET    | `/api/friends`                 | List accepted friends |
| GET    | `/api/friends/pending`         | Pending requests      |
| POST   | `/api/friends/request`         | Send friend request   |
| PATCH  | `/api/friends/{id}/accept`     | Accept request        |
| PATCH  | `/api/friends/{id}/decline`    | Decline request       |
| DELETE | `/api/friends/{id}`            | Remove friend         |

### Time Entries
| Method | Endpoint                       | Description                    |
|--------|--------------------------------|--------------------------------|
| GET    | `/api/time-entries`            | List (`?project_id=`, `?from=`, `?to=`) |
| POST   | `/api/time-entries`            | Create entry                   |
| PUT    | `/api/time-entries/{id}`       | Update entry                   |
| DELETE | `/api/time-entries/{id}`       | Delete entry                   |
| GET    | `/api/time-entries/summary`    | Hours per project (`?from=&to=`) |

### Activity Logs
| Method | Endpoint                                  | Description             |
|--------|-------------------------------------------|-------------------------|
| GET    | `/api/activity-logs`                      | User's logs (filterable)|
| GET    | `/api/activity-logs/feed`                 | Global activity feed    |
| GET    | `/api/activity-logs/project/{projectId}`  | Logs for a project      |

### Participants
| Method | Endpoint                  | Description                      |
|--------|---------------------------|----------------------------------|
| GET    | `/api/participants`       | List members (`?entity_type=&entity_id=`) |
| POST   | `/api/participants`       | Add member                       |
| PATCH  | `/api/participants/role`  | Update member role               |
| DELETE | `/api/participants`       | Remove member                    |

---

## Key Features

- **Sanctum token auth** with login/register/logout
- **Soft deletes** on projects and teams with restore capability
- **Activity logging** on every create/update/delete action
- **Private teams** with password-protected join flow
- **Kanban support** via bulk task status updates
- **Time tracking** with date-range summaries and per-project aggregation
- **Chat system** with rooms, read receipts, pin, and soft-delete
- **Friend system** with request/accept/decline workflow
- **Participant system** for polymorphic project/team membership
- **Dashboard endpoint** with aggregated stats
- **Form Request validation** on all write endpoints
- **API Resources** for consistent JSON output
- **Pagination** with meta on all list endpoints
