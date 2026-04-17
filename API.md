# Kodo API Documentation

**Base URL:** `https://kodo-api-wvfz.onrender.com/api`
**Auth:** Bearer token (Laravel Sanctum) — include `Authorization: Bearer <token>` header
**Content-Type:** `application/json`

---

## Authentication

### POST `/auth/register`
Register a new user account.

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `username` | string | yes | max:100, unique, alphanumeric + `._-` |
| `email` | string | yes | valid email, max:255, unique |
| `password` | string | yes | min:8, mixed case, numbers, confirmed |
| `password_confirmation` | string | yes | must match password |
| `phone_number` | string | yes | max:20 |
| `display_name` | string | no | max:150 |
| `job_title` | string | no | max:100 |

**Response `201`:**
```json
{ "message": "Registration successful.", "user": { UserResource }, "token": "plain-text-token" }
```

---

### POST `/auth/login`
Login with email and password. Returns verification challenge unless device is trusted.

| Field | Type | Required |
|-------|------|----------|
| `email` | string | yes |
| `password` | string | yes |
| `device_token` | string | no |

**Response `200` (verification required):**
```json
{ "message": "Verification required.", "verification_required": true, "user_id": 1, "email": "le***@example.com", "phone": "****1234", "has_phone": true }
```

**Response `200` (trusted device):**
```json
{ "message": "Login successful.", "user": { UserResource }, "token": "plain-text-token" }
```

**Error `401`:** Invalid credentials | **`403`:** Account deactivated | **`423`:** Account locked

---

### POST `/auth/logout` `AUTH`
Logs out the current user, deletes the token.

**Response `200`:** `{ "message": "Logged out successfully." }`

---

### GET `/auth/me` `AUTH`
Returns the current authenticated user with settings.

**Response `200`:** `{ "user": { UserResource }, "settings": { UserSettingsResource } }`

---

### POST `/auth/change-password` `AUTH`

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `current_password` | string | yes | must match current |
| `password` | string | yes | min:8, mixed case, numbers, confirmed |
| `password_confirmation` | string | yes | must match password |

**Response `200`:** `{ "message": "Password changed successfully." }`

---

## Verification (2FA)

### POST `/verification/send`
Send a verification code via email or SMS.

| Field | Type | Required |
|-------|------|----------|
| `user_id` | integer | yes |
| `method` | string | yes (`email` or `sms`) |

---

### POST `/verification/verify`
Verify the code and complete login.

| Field | Type | Required |
|-------|------|----------|
| `user_id` | integer | yes |
| `code` | string | yes (6 digits) |
| `trust_device` | boolean | no |

**Response `200`:** `{ "message": "Verification successful.", "user": { UserResource }, "token": "..." }`

---

### POST `/verification/check-device`
Check if a device token is still trusted.

| Field | Type | Required |
|-------|------|----------|
| `user_id` | integer | yes |
| `device_token` | string | yes |

---

## Projects `AUTH`

### GET `/projects`
List projects the user owns or participates in.

| Query | Type | Default |
|-------|------|---------|
| `status` | string | all (`planning`, `active`, `on_hold`, `completed`, `archived`) |
| `per_page` | integer | 15 (max 100) |

**Response `200`:** `{ "projects": [ ProjectResource... ], "meta": { current_page, last_page, per_page, total } }`

---

### POST `/projects`

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `name` | string | yes | max:255 |
| `slug` | string | no | max:100 (auto-generated if omitted) |
| `description` | string | no | max:2000 |
| `color` | string | no | max:20 |
| `icon` | string | no | max:50 |
| `project_type` | string | no | `kanban`, `list`, `timeline`, `calendar` |
| `status` | string | no | `planning`, `active`, `on_hold`, `completed`, `archived` |
| `start_date` | date | no | |
| `target_end_date` | date | no | after_or_equal:start_date |

**Response `201`:** `{ "message": "Project created.", "project": { ProjectResource } }`

---

### GET `/projects/{id}`
Show a single project. **Requires membership** (owner or participant).

**Response `200`:** `{ "project": { ProjectResource } }` | **`403`:** Not a member

---

### PUT `/projects/{id}`
Update a project. **Owner only.**

Same fields as POST (all optional). **Response `200`** | **`403`:** Not owner

---

### DELETE `/projects/{id}`
Soft-delete a project. **Owner only.**

**Response `200`:** `{ "message": "Project deleted." }` | **`403`:** Not owner

---

### POST `/projects/{id}/restore`
Restore a soft-deleted project. **Owner only.**

**Response `200`:** `{ "message": "Project restored.", "project": { ProjectResource } }`

---

## Tasks `AUTH`

### GET `/tasks`
List tasks from the user's projects/teams, or tasks they created/are assigned to.

| Query | Type | Default |
|-------|------|---------|
| `project_id` | integer | all |
| `team_id` | integer | all |
| `status` | string | all (`todo`, `in_progress`, `in_review`, `done`, `blocked`, `cancelled`) |
| `priority` | string | all (`urgent`, `high`, `medium`, `low`, `none`) |
| `per_page` | integer | 20 (max 100) |

**Response `200`:** `{ "tasks": [ TaskResource... ], "meta": { ... } }`

---

### POST `/tasks`

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `project_id` | integer | yes | exists |
| `team_id` | integer | no | exists |
| `title` | string | yes | max:500 |
| `description` | string | no | max:5000 |
| `status` | string | no | see enum above |
| `priority` | string | no | see enum above |
| `start_date` | date | no | |
| `due_date` | date | no | |
| `estimated_hours` | number | no | 0-9999 |
| `labels` | string[] | no | each max:50 |
| `assignees` | integer[] | no | user IDs, must exist |

**Response `201`:** `{ "message": "Task created.", "task": { TaskResource } }`

---

### GET `/tasks/{id}`
Show a single task. **Requires access** (project/team member, creator, or assignee).

---

### PUT `/tasks/{id}`
Update a task. **Requires access.** Setting `status=done` auto-sets `completed_at` and `progress=100`.

---

### DELETE `/tasks/{id}`
Soft-delete a task. **Requires access.**

---

### POST `/tasks/bulk-status`
Update status of multiple tasks at once (max 50). Inaccessible tasks are silently skipped.

```json
{ "tasks": [ { "id": 1, "status": "done" }, { "id": 2, "status": "in_progress" } ] }
```

**Response `200`:** `{ "message": "Tasks updated." }`

---

## Teams `AUTH`

### GET `/teams`
List teams the user participates in, owns, or that are public.

| Query | Type | Default |
|-------|------|---------|
| `project_id` | integer | all |
| `per_page` | integer | 15 (max 100) |

---

### POST `/teams`
Create a team. Creator is auto-added as admin.

| Field | Type | Required |
|-------|------|----------|
| `name` | string | yes |
| `description` | string | no |
| `project_id` | integer | no |
| `color` | string | no |
| `is_private` | boolean | no |
| `password` | string | no (required if `is_private=true`) |

**Response `201`:** `{ "message": "Team created.", "team": { TeamResource } }`

---

### GET `/teams/{id}` | PUT `/teams/{id}` | DELETE `/teams/{id}`
Show / Update / Delete a team. Update and Delete are **owner only**.

---

### POST `/teams/{id}/join`
Join a team. Private teams require `password` field.

**Response `200`** | **`403`:** Wrong password | **`409`:** Already a member

---

### POST `/teams/{id}/leave`
Leave a team. **Team owners cannot leave** (must transfer ownership first).

---

## Chat `AUTH`

### GET `/chat/conversations`
List all chat conversations (team rooms + DMs) for the current user.

**Response `200`:** `{ "conversations": [ { room_id, latest_message, unread_count, ... } ] }`

---

### GET `/chat/rooms/{roomId}/messages`
Get messages for a room (paginated).

| Query | Default |
|-------|---------|
| `per_page` | 50 (max 100) |

---

### GET `/chat/rooms/{roomId}/poll`
Long-poll for new messages since a given ID.

| Query | Default |
|-------|---------|
| `since_id` | 0 |

---

### POST `/chat/send`
Send a message to a team room or DM.

| Field | Type | Required |
|-------|------|----------|
| `team_id` | integer | no (for team messages) |
| `receiver_id` | integer | no (for DMs) |
| `message` | string | yes |
| `attachments` | array | no |

---

### PATCH `/chat/rooms/{roomId}/read`
Mark all messages in a room as read.

### PATCH `/chat/messages/{id}/pin`
Toggle pin on a message.

### DELETE `/chat/messages/{id}`
Soft-delete a message (sender only).

### POST `/chat/messages/{id}/reactions`
Toggle an emoji reaction on a message.

| Field | Type | Required |
|-------|------|----------|
| `emoji` | string | yes (max:50) |

---

## Calendar Events `AUTH`

### GET `/calendar-events`

| Query | Type | Default |
|-------|------|---------|
| `team_id` | integer | all |
| `start_after` | datetime | - |
| `start_before` | datetime | - |
| `status` | string | all (`confirmed`, `tentative`, `cancelled`) |
| `per_page` | integer | 20 (max 100) |

---

### POST `/calendar-events`

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| `title` | string | yes | max:200 |
| `team_id` | integer | no | exists |
| `description` | string | no | |
| `location` | string | no | max:200 |
| `is_online_meeting` | boolean | no | |
| `meeting_url` | string | no | max:500 |
| `start_time` | datetime | yes | ISO 8601 |
| `end_time` | datetime | yes | after_or_equal:start_time |
| `is_all_day` | boolean | no | |
| `recurrence_rule` | string | no | max:255 |
| `status` | string | no | `confirmed`, `tentative`, `cancelled` |
| `reminder_minutes` | integer | no | min:0 |
| `color` | string | no | max:20 |
| `attendees` | integer[] | no | user IDs |

---

### GET `/calendar-events/{id}` | PUT `/calendar-events/{id}` | DELETE `/calendar-events/{id}`
Show / Update (**organizer only**) / Delete (**organizer only**) an event.

---

### POST `/calendar-events/{id}/rsvp`
RSVP to an event (must be an attendee).

| Field | Type | Required |
|-------|------|----------|
| `response_status` | string | yes (`accepted`, `declined`, `tentative`) |

---

## Time Entries `AUTH`

### GET `/time-entries`

| Query | Type | Default |
|-------|------|---------|
| `project_id` | integer | all |
| `from` | date | - |
| `to` | date | - |
| `per_page` | integer | 20 (max 100) |

---

### POST `/time-entries`

| Field | Type | Required |
|-------|------|----------|
| `project_id` | integer | no |
| `task_id` | integer | no |
| `hours` | number | yes |
| `date` | date | yes |
| `activity_type` | string | no |
| `description` | string | no |

---

### PUT `/time-entries/{id}` | DELETE `/time-entries/{id}`
Update or delete a time entry (**owner only**).

---

### GET `/time-entries/summary`
Get aggregated time summary by project.

| Query | Type | Required |
|-------|------|----------|
| `from` | date | yes |
| `to` | date | yes |

**Response `200`:** `{ "summary": [ { project, total_hours } ], "grand_total": 42.5 }`

---

## Friends `AUTH`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/friends` | GET | List accepted friends |
| `/friends/pending` | GET | Incoming friend requests |
| `/friends/sent` | GET | Outgoing friend requests |
| `/friends/request` | POST | Send friend request (`user_id` required) |
| `/friends/{id}/accept` | PATCH | Accept a pending request |
| `/friends/{id}/decline` | PATCH | Decline a pending request |
| `/friends/{id}` | DELETE | Remove a friend |

---

## Participants `AUTH`
Manage project/team membership.

### GET `/participants`
| Query | Required |
|-------|----------|
| `entity_type` | yes (`project` or `team`) |
| `entity_id` | yes |

### POST `/participants`
Add a member. **Requires admin/owner role.**

| Field | Type | Required |
|-------|------|----------|
| `entity_type` | string | yes |
| `entity_id` | integer | yes |
| `user_id` | integer | yes |
| `role` | string | no (default: `member`) |

### PATCH `/participants/role`
Change a member's role. **Requires admin/owner role.**

### DELETE `/participants`
Remove a member. **Requires admin/owner role.**

---

## Notifications `AUTH`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/notifications` | GET | List notifications (paginated, `per_page` max 100) |
| `/notifications/{id}/read` | PATCH | Mark one as read |
| `/notifications/read-all` | POST | Mark all as read |
| `/notifications/{id}` | DELETE | Delete a notification |

---

## Users `AUTH`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/users` | GET | List all users |
| `/users/{id}` | GET | Show a user profile |
| `/profile` | PUT | Update own profile (display_name, job_title, bio, etc.) |
| `/profile/status` | PATCH | Update presence status (`online`, `away`, `busy`, `dnd`, `brb`, `offline`, `invisible`) |

---

## User Settings `AUTH`

### GET `/settings`
Returns current user's settings.

### PUT `/settings`
Update settings. All fields are optional.

| Field | Type | Description |
|-------|------|-------------|
| `theme` | string | `dark` or `light` |
| `language` | string | `hu` or `en` |
| `notifications_enabled` | boolean | Global toggle |
| `email_notifications` | boolean | |
| `push_notifications` | boolean | |
| `notification_sound` | boolean | |
| `desktop_notifications` | boolean | |
| `dnd_enabled` | boolean | Do Not Disturb |
| `dnd_start_time` | time | HH:MM |
| `dnd_end_time` | time | HH:MM |
| `enter_to_send` | boolean | |
| `show_typing_indicator` | boolean | |
| `show_read_receipts` | boolean | |
| `reduce_motion` | boolean | |
| `high_contrast` | boolean | |
| `font_size` | string | `small`, `medium`, `large` |
| `show_online_status` | boolean | |
| `allow_direct_messages` | string | `everyone`, `friends`, `nobody` |

---

## Dashboard `AUTH`

### GET `/dashboard`
Returns aggregated dashboard data.

**Response `200`:**
```json
{
  "projects_count": 5,
  "recent_projects": [ ProjectResource... ],
  "tasks_by_status": { "todo": 12, "in_progress": 5, "done": 30 },
  "hours_this_week": 24.5,
  "friends_count": 8,
  "recent_activity": [ ActivityLogResource... ]
}
```

---

## Activity Logs `AUTH`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/activity-logs` | GET | User's own activity logs |
| `/activity-logs/feed` | GET | Activity feed from user's projects/teams |
| `/activity-logs/project/{projectId}` | GET | Activity for a specific project |

---

## Files `AUTH`

### POST `/files/upload`
Upload a file (max 10MB). Send as `multipart/form-data`.

| Field | Type | Required |
|-------|------|----------|
| `file` | file | yes |

**Response `201`:** `{ "file": { file_name, file_type, file_size, file_url, ... } }`

### POST `/files/attach-message`
Attach metadata to a message.

### DELETE `/files/{id}`
Delete a file (**uploader only**).

---

## Conversations (Group Chats) `AUTH`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/conversations` | GET | List conversations |
| `/conversations` | POST | Create group conversation (`name`, `participant_ids[]`) |
| `/conversations/{id}` | GET | Show conversation |
| `/conversations/{id}/messages` | GET | Get messages |
| `/conversations/{id}/messages` | POST | Send message |
| `/conversations/{id}/participants` | POST | Add participants |
| `/conversations/{id}/leave` | POST | Leave conversation |

---

## Channels `AUTH`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/channels` | GET | List channels |
| `/channels` | POST | Create channel |
| `/channels/{id}` | GET | Show channel |
| `/channels/{id}` | PUT | Update channel |
| `/channels/{id}` | DELETE | Delete channel |
| `/channels/{id}/messages` | GET | Get channel messages |
| `/channels/{id}/messages` | POST | Send message to channel |

---

## Message Reactions `AUTH`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/messages/{id}/reactions` | GET | List reactions on a message |
| `/messages/{id}/reactions` | POST | Toggle a reaction (`emoji` required) |

---

## Organizations `AUTH`

Full CRUD: `GET /organizations`, `POST /organizations`, `GET /organizations/{id}`, `PUT /organizations/{id}`, `DELETE /organizations/{id}`

---

## Health Check

### GET `/health`
No auth required. **Response `200`:** `{ "status": "ok" }`

---

## Error Responses

All validation errors return status `422`:
```json
{ "error": { "code": "VALIDATION_ERROR", "details": { "field": ["error message"] } } }
```

Authentication errors return `401`. Authorization errors return `403`.
Account lockout returns `423`.

---

## Rate Limits

| Routes | Limit |
|--------|-------|
| `/auth/register`, `/auth/login`, `/verification/*` | 10 requests/minute |
| All authenticated routes | 60 requests/minute |
