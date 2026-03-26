# Kodo - Project Roadmap & Audit

**What:** Team/project management app with chat, tasks, calendar, time tracking
**For:** IKT Exam
**Stack:** React 18 + Vite | Laravel 12 + Sanctum | PostgreSQL 15+
**Updated:** 2026-03-26

---

## How to read this

Everything is organized from most urgent to least urgent. Each item has a checkbox so you can track progress. The "Phase" section at the bottom tells you what order to tackle things for the exam.

---

## Phase 1 — Fix These Before the Exam (Security)

These are things an examiner could easily spot or that would break the app in a demo.

### Secrets are exposed in git

1. [ ] The `.env` files in both `kodo/` and `nexus-backend/` are committed to git with real credentials:
   - Database password: `Emma2Bella2`
   - Gmail App Password: `mped wzck pwsf oiph`
   - Resend API Key: `re_QPGy7F8A_...`
   - Laravel APP_KEY (can decrypt all session data)
   - **Fix:** Add `.env` to `.gitignore`, remove from git history, rotate all credentials

2. [ ] `APP_DEBUG=true` in the backend — this makes the app return the 2FA verification code directly in the API response (line 81-83 in `VerificationController.php`). Anyone can see the code without checking their email.
   - **Fix:** Set `APP_DEBUG=false`

### Anyone can do anything (missing authorization)

3. [ ] **ParticipantController** has zero authorization. Any logged-in user can:
   - Add anyone to any team or project
   - Remove anyone from any team or project
   - Promote anyone to admin/owner of any team or project
   - **Fix:** Check that the requesting user is an admin/owner before allowing these actions

4. [ ] **TaskController** has no permission checks on edit/delete. Any logged-in user can:
   - Edit any task (change title, status, assignee, etc.)
   - Delete any task
   - Bulk-update the status of any tasks
   - **Fix:** Check that the user belongs to the task's project/team or is the assignee

5. [ ] **ProjectController** shows all projects to everyone. Any logged-in user can see every project in the system, including private ones.
   - **Fix:** Only return projects the user owns or is a participant of

6. [ ] **TeamController** shows all teams to everyone, including private and hidden teams.
   - **Fix:** Only return teams the user is a member of, plus public teams

7. [ ] **CalendarEventController** lets anyone see all events and edit any event.
   - **Fix:** Scope to user's own events + events in their teams. Only let the organizer edit.

8. [ ] **ChatController** has three holes:
   - `poll()` — anyone can read messages from any team room by guessing the room ID
   - `sendMessage()` — anyone can send messages to any team room
   - `markAsRead()` — anyone can mark messages as read in any room
   - **Fix:** Verify team membership before allowing any of these

9. [ ] **TimeEntryController** leaks data — when `team=1` is passed, it returns ALL users' time entries for a project, not just the requester's.
   - **Fix:** Check team/project membership

10. [ ] **ActivityLogController** feed shows all users' activity to everyone.
    - **Fix:** Scope to the user's own teams and projects

### The Node.js server has no security at all

11. [ ] The `kodo/server/` Express backend has **zero authentication**. It doesn't check JWT tokens. The `/api/users/me` endpoint literally hardcodes `WHERE id = 1`. Every endpoint is wide open.
    - **Fix:** Either remove it entirely (the Laravel backend covers everything) or add proper auth middleware

### 2FA can be bypassed

12. [ ] **VerificationController** — the `sendCode` and `verifyCode` endpoints accept any `user_id` without authentication. An attacker can:
    - Spam verification emails to any user (email bombing)
    - Try to brute-force the 6-digit code (rate limit is only 10/min)
    - **Fix:** Add failed attempt counter, lock after X failures, don't expose `user_id` in the flow

### Token storage is insecure

13. [ ] Auth token is stored in `localStorage`, which any JavaScript on the page can read. If there's ever an XSS vulnerability, the attacker gets the token. The full user object (email, phone number) is also stored there.
    - **Fix:** Use `httpOnly` cookies instead, or at minimum use `sessionStorage`

### Tokens never expire

14. [ ] Sanctum tokens are created without an expiration date. Once issued, they're valid forever.
    - **Fix:** Add `->expiresAt(now()->addDays(30))` to `createToken()` calls

---

## Phase 2 — Medium Security & Quality Issues

These are less critical but still worth fixing.

15. [ ] **Weak password policy** — only requires 8 characters. No uppercase, numbers, or symbols required.
    - **Fix:** Use `Password::min(8)->mixedCase()->numbers()` in `RegisterRequest.php`

16. [ ] **User enumeration via login** — the error messages are different for "user not found" vs "wrong password" (one mentions remaining attempts, the other doesn't). An attacker can tell which emails are registered.
    - **Fix:** Use the same generic error message for both cases

17. [ ] **No rate limiting on authenticated routes** — only the login/register endpoints have rate limiting. All other endpoints can be hammered unlimited.
    - **Fix:** Add `throttle:60,1` to the auth middleware group

18. [ ] **Chat room ID system is fragile** — uses a magic number (100,000) to tell team rooms apart from DM rooms. If the app grows past 100,000 teams or users, everything breaks.
    - **Fix:** Use a `type` column on the room or separate endpoints for team vs DM

19. [ ] **Team owners can leave their own team**, which orphans it with no admin.
    - **Fix:** Block this or require transferring ownership first

20. [ ] **`per_page` parameter not capped** — someone could request `?per_page=999999` on activity logs and dump the whole database.
    - **Fix:** Cap at 100

21. [ ] **Session encryption is off** and secure cookies aren't enforced.
    - **Fix:** Set `SESSION_ENCRYPT=true` and `SESSION_SECURE_COOKIE=true` in `.env`

---

## Phase 3 — Frontend Bugs

These are actual bugs that would be visible during a demo.

22. [ ] **Sidebar project creation bug** — `Sidebar.jsx:281` passes the project password as the description. The `addProject(name, description)` function receives the password in the description field.
    - **Fix:** Update `addProject` to accept a password parameter, or fix the Sidebar call

23. [ ] **Dashboard calendar is frozen in time** — `Dashboard.jsx:87` hardcodes `today = 14` and `March 2026`. It won't show the right date on any other day.
    - **Fix:** Use `new Date()` to get the actual current date

24. [ ] **All pages load at once** — `App.jsx:35-42` creates all 7 page components in an object on every render, even though only one is shown. This means Messages starts polling, Tasks starts fetching, etc., all at once.
    - **Fix:** Only render the active page component

25. [ ] **Name extraction is wrong for some names** — `Dashboard.jsx:315` tries to get the first name by splitting on spaces and taking index [1] (Hungarian name order). Breaks for names with 3+ parts.

26. [ ] **401 handling race condition** — `api.js:43` calls `window.location.reload()` and then throws an error. The reload happens before the error reaches any handler.
    - **Fix:** Redirect to auth state instead of reloading

27. [ ] **Memory leak in Messages** — `Messages.jsx:194` creates object URLs for file previews but never revokes them when clearing attachments after sending.

28. [ ] **Hardcoded Hungarian strings** — `ServerStatusBanner.jsx:14` and `AuthPage.jsx:253` have Hungarian text that isn't going through the translation system.
    - **Fix:** Use translation keys instead

29. [ ] **Password confirmation silently bypassed** — `AuthContext.jsx:117` defaults `passwordConfirmation` to the password itself if not provided.

---

## Phase 4 — Features That Exist in the Database But Not in the App

The database schema defines these tables, but there are no API endpoints or UI for them.

30. [ ] **Kanban columns (`task_buckets`)** — the schema has a full table for custom Kanban columns with positions and WIP limits, but the frontend just hardcodes 4 columns (Todo, In Progress, Review, Done).
    - **Impact:** Implementing this would be very impressive for an exam

31. [ ] **Task checklists (`task_checklists` + `task_checklist_items`)** — sub-tasks within tasks, with completion tracking.
    - **Impact:** Common feature in project management tools, shows depth

32. [ ] **Password reset flow (`password_reset_tokens`)** — the table exists but there's no forgot-password endpoint or UI.
    - **Impact:** Essential for any real auth system

33. [ ] **Team channels (`channels`)** — the schema supports different channel types (general, public, private, announcement) but the chat system doesn't use them.

34. [ ] **Message reactions (`message_reactions`)** — the frontend has a reaction UI but it's purely local. The backend table exists but has no API.

35. [ ] **Message mentions (`message_mentions`)** — same as reactions. Frontend shows mentions but nothing is saved or delivered.

36. [ ] **File attachments (`message_attachments`)** — full schema for file metadata (size, type, dimensions) but no upload/download API.

37. [ ] **Group conversations (`conversations` + `conversation_participants`)** — designed for multi-person chats separate from team rooms.

38. [ ] **Organizations (`organizations`)** — workspace management with plan types (free, standard, business, pro, enterprise).

39. [ ] **Calendar RSVP** — the schema has `response_status` on event attendees but no API to accept/decline invitations.

40. [ ] **Most user settings are inaccessible** — the `user_settings` table has 20+ columns but only 7 are exposed through the API. Missing: DND mode, typing indicators, message previews, enter-to-send, etc.

41. [ ] **CalendarEvent model** is missing many fields from `$fillable` that the schema supports: timezone, recurring events, categories, meeting links.

42. [ ] **Friend re-request** — declined friend requests can't be re-sent because the old record blocks new ones.

---

## Phase 5 — Features the API Supports But the Frontend Doesn't Show

The Laravel backend already has endpoints for these. You just need to build the UI.

43. [ ] **Time tracking page** — the backend has full CRUD for time entries plus a summary endpoint. No frontend page exists.
    - **Effort:** Medium (backend done, just need UI)

44. [ ] **Activity log page** — the backend has user, project, and global activity feeds. No frontend page.
    - **Effort:** Low-medium (backend done)

45. [ ] **Friend management page** — the backend supports send/accept/decline/remove friend requests. No UI.
    - **Effort:** Medium

46. [ ] **Team editing/deletion/leaving** — `api.js` already has `teams.update()`, `teams.destroy()`, `teams.leave()` functions. Just need buttons and modals.
    - **Effort:** Low

47. [ ] **Calendar event editing and deletion** — same, `api.js` already has the functions.
    - **Effort:** Low

48. [ ] **Notification settings from server** — `Settings.jsx` has toggle switches but they use hardcoded defaults instead of loading from `settingsApi.get()`.
    - **Effort:** Low (just wire up existing API)

49. [ ] **User presence/status** — backend supports online/away/busy/dnd/brb/offline/invisible. Frontend doesn't show or set it.
    - **Effort:** Low-medium

50. [ ] **Avatar images** — the `User` model has `avatar_url` but `Avatar.jsx` always shows initials.
    - **Effort:** Low

51. [ ] **URL-based routing** — `react-router-dom` is already installed in `package.json` but completely unused. The app uses state to switch pages, meaning no bookmarks, no back/forward, no deep links.
    - **Effort:** Medium (needs refactoring App.jsx routing)

52. [ ] **Pagination** — projects are capped at 50, tasks at 100. No "load more" or pagination UI.
    - **Effort:** Low

---

## Phase 6 — Accessibility & UX Polish

53. [ ] **Zero ARIA attributes** in the entire codebase — no `aria-label`, `aria-expanded`, `aria-live` anywhere.
54. [ ] **No keyboard navigation** — modals don't trap focus, dropdowns aren't keyboard-accessible, no Escape to close.
55. [ ] **Interactive elements are divs** — the sidebar collapse toggle, various clickable areas use `<div onClick>` instead of `<button>`.
56. [ ] **Form labels aren't linked to inputs** — no `htmlFor`/`id` pairs on any form.
57. [ ] **Possible color contrast issues** — very small text (10-11px) with dim colors on dark backgrounds.
58. [ ] **No loading states** — no skeleton loaders or error boundaries.
59. [ ] **Message polling never pauses** — keeps making requests every 3 seconds even when the browser tab is hidden.
60. [ ] **No form validation on calendar** — you can set an end time before the start time.

---

## Phase 7 — Impressive Exam Additions

If you have time, these would really stand out:

61. [ ] **Drag-and-drop Kanban board** — use a library like `@hello-pangea/dnd` to make task columns draggable. Schema already supports it.
62. [ ] **Real-time messaging with WebSockets** — replace the 3-second polling with Laravel Reverb or Pusher. Instant message delivery.
63. [ ] **File upload for attachments** — implement real file upload with progress bars and previews.
64. [ ] **User profile page with avatar upload** — show user stats, activity, let them upload a photo.
65. [ ] **Search** — the database already has a full-text search index on messages (supports Hungarian!). Build a search bar.
66. [ ] **Data export** — CSV/PDF reports for time tracking or project status.
67. [ ] **Proper Laravel migrations** — only 1 migration exists. The whole schema is in raw SQL. Converting to migrations shows Laravel mastery.

---

## Phase 8 — Testing & DevOps

68. [ ] Write actual backend tests (only placeholder `ExampleTest.php` exists)
69. [ ] Test the authorization checks once you implement them
70. [ ] Add API documentation (Swagger or update the README)
71. [ ] Write frontend tests with Vitest
72. [ ] Create proper `.env.example` files
73. [ ] Remove or integrate the Node.js server (`kodo/server/`)
74. [ ] Clean up unused npm dependencies

---

## Quick Reference: What to Do First

| Priority | What | Why | Effort |
|----------|------|-----|--------|
| **Do now** | Fix authorization (items 3-10) | Biggest security holes | High |
| **Do now** | Remove `.env` from git (item 1) | Credentials exposed | Low |
| **Do now** | Fix frontend bugs (items 22-24) | Visible during demo | Low |
| **Do soon** | Wire up existing APIs (items 43-52) | Max features, min effort | Medium |
| **Do soon** | Fix password/token security (items 13-15) | Shows security awareness | Low |
| **If time** | Kanban drag-drop (item 61) | Very impressive visually | Medium |
| **If time** | WebSocket messaging (item 62) | Shows real-time skills | High |
| **If time** | Proper migrations (item 67) | Shows Laravel mastery | Medium |
