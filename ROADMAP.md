# Kodo - Project Roadmap & Audit

**What:** Team/project management app with chat, tasks, calendar, time tracking
**For:** IKT Exam
**Stack:** React 18 + Vite | Laravel 12 + Sanctum | PostgreSQL 15+
**Updated:** 2026-04-16

---

## How to read this

Everything is organized from most urgent to least urgent. Each item has a checkbox so you can track progress. The "Phase" section at the bottom tells you what order to tackle things for the exam.

---

## Phase 1 — Fix These Before the Exam (Security)

These are things an examiner could easily spot or that would break the app in a demo.

### Secrets are exposed in git

1. [x] The `.env` files in both `kodo/` and `nexus-backend/` are committed to git with real credentials:
   - Database password: `Emma2Bella2`
   - Gmail App Password: `mped wzck pwsf oiph`
   - Resend API Key: `re_QPGy7F8A_...`
   - Laravel APP_KEY (can decrypt all session data)
   - **Fix:** Add `.env` to `.gitignore`, remove from git history, rotate all credentials

2. [x] `APP_DEBUG=true` in the backend — this makes the app return the 2FA verification code directly in the API response (line 81-83 in `VerificationController.php`). Anyone can see the code without checking their email.
   - **Fix:** Set `APP_DEBUG=false`

### Anyone can do anything (missing authorization)

3. [x] **ParticipantController** has zero authorization. Any logged-in user can:
   - Add anyone to any team or project
   - Remove anyone from any team or project
   - Promote anyone to admin/owner of any team or project
   - **Fix:** Check that the requesting user is an admin/owner before allowing these actions

4. [x] **TaskController** has no permission checks on edit/delete. Any logged-in user can:
   - Edit any task (change title, status, assignee, etc.)
   - Delete any task
   - Bulk-update the status of any tasks
   - **Fix:** Check that the user belongs to the task's project/team or is the assignee

5. [x] **ProjectController** shows all projects to everyone. Any logged-in user can see every project in the system, including private ones.
   - **Fix:** Only return projects the user owns or is a participant of

6. [x] **TeamController** shows all teams to everyone, including private and hidden teams.
   - **Fix:** Only return teams the user is a member of, plus public teams

7. [x] **CalendarEventController** lets anyone see all events and edit any event.
   - **Fix:** Scope to user's own events + events in their teams. Only let the organizer edit.

8. [x] **ChatController** has three holes:
   - `poll()` — anyone can read messages from any team room by guessing the room ID
   - `sendMessage()` — anyone can send messages to any team room
   - `markAsRead()` — anyone can mark messages as read in any room
   - **Fix:** Verify team membership before allowing any of these

9. [x] **TimeEntryController** leaks data — when `team=1` is passed, it returns ALL users' time entries for a project, not just the requester's.
   - **Fix:** Check team/project membership

10. [x] **ActivityLogController** feed shows all users' activity to everyone.
    - **Fix:** Scope to the user's own teams and projects

### The Node.js server has no security at all

11. [x] The `kodo/server/` Express backend has **zero authentication**. It doesn't check JWT tokens. The `/api/users/me` endpoint literally hardcodes `WHERE id = 1`. Every endpoint is wide open.
    - **Fix:** Either remove it entirely (the Laravel backend covers everything) or add proper auth middleware

### 2FA can be bypassed

12. [x] **VerificationController** — the `sendCode` and `verifyCode` endpoints accept any `user_id` without authentication. An attacker can:
    - Spam verification emails to any user (email bombing)
    - Try to brute-force the 6-digit code (rate limit is only 10/min)
    - **Fix:** Add failed attempt counter, lock after X failures, don't expose `user_id` in the flow

### Token storage is insecure

13. [ ] Auth token is stored in `localStorage`, which any JavaScript on the page can read. If there's ever an XSS vulnerability, the attacker gets the token. The full user object (email, phone number) is also stored there.
    - **Fix:** Use `httpOnly` cookies instead, or at minimum use `sessionStorage`

### Tokens never expire

14. [x] Sanctum tokens are created without an expiration date. Once issued, they're valid forever.
    - **Fix:** Add `->expiresAt(now()->addDays(30))` to `createToken()` calls

---

## Phase 2 — Medium Security & Quality Issues

These are less critical but still worth fixing.

15. [x] **Weak password policy** — only requires 8 characters. No uppercase, numbers, or symbols required.
    - **Fix:** Use `Password::min(8)->mixedCase()->numbers()` in `RegisterRequest.php`

16. [x] **User enumeration via login** — the error messages are different for "user not found" vs "wrong password" (one mentions remaining attempts, the other doesn't). An attacker can tell which emails are registered.
    - **Fix:** Use the same generic error message for both cases

17. [x] **No rate limiting on authenticated routes** — only the login/register endpoints have rate limiting. All other endpoints can be hammered unlimited.
    - **Fix:** Add `throttle:60,1` to the auth middleware group

18. [ ] **Chat room ID system is fragile** — uses a magic number (100,000) to tell team rooms apart from DM rooms. If the app grows past 100,000 teams or users, everything breaks.
    - **Fix:** Use a `type` column on the room or separate endpoints for team vs DM

19. [x] **Team owners can leave their own team**, which orphans it with no admin.
    - **Fix:** Block this or require transferring ownership first

20. [x] **`per_page` parameter not capped** — someone could request `?per_page=999999` on activity logs and dump the whole database.
    - **Fix:** Cap at 100

21. [x] **Session encryption is off** and secure cookies aren't enforced.
    - **Fix:** Set `SESSION_ENCRYPT=true` and `SESSION_SECURE_COOKIE=true` in `.env`

---

## Phase 3 — Frontend Bugs

These are actual bugs that would be visible during a demo.

22. [x] **Sidebar project creation bug** — `Sidebar.jsx:281` passes the project password as the description. The `addProject(name, description)` function receives the password in the description field.
    - **Fix:** Update `addProject` to accept a password parameter, or fix the Sidebar call

23. [x] **Dashboard calendar is frozen in time** — `Dashboard.jsx:87` hardcodes `today = 14` and `March 2026`. It won't show the right date on any other day.
    - **Fix:** Use `new Date()` to get the actual current date

24. [x] **All pages load at once** — `App.jsx:35-42` creates all 7 page components in an object on every render, even though only one is shown. This means Messages starts polling, Tasks starts fetching, etc., all at once.
    - **Fix:** Only render the active page component

25. [x] **Name extraction is wrong for some names** — `Dashboard.jsx:315` tries to get the first name by splitting on spaces and taking index [1] (Hungarian name order). Breaks for names with 3+ parts.

26. [x] **401 handling race condition** — `api.js:43` calls `window.location.reload()` and then throws an error. The reload happens before the error reaches any handler.
    - **Fix:** Redirect to auth state instead of reloading

27. [x] **Memory leak in Messages** — `Messages.jsx:194` creates object URLs for file previews but never revokes them when clearing attachments after sending.

28. [x] **Hardcoded Hungarian strings** — `ServerStatusBanner.jsx:14` and `AuthPage.jsx:253` have Hungarian text that isn't going through the translation system.
    - **Fix:** Use translation keys instead

29. [x] **Password confirmation silently bypassed** — `AuthContext.jsx:117` defaults `passwordConfirmation` to the password itself if not provided.

---

## Phase 4 — Features That Exist in the Database But Not in the App

The database schema defines these tables, but there are no API endpoints or UI for them.

30. [x] **Kanban columns (`task_buckets`)** — the schema has a full table for custom Kanban columns with positions and WIP limits, but the frontend just hardcodes 4 columns (Todo, In Progress, Review, Done).
    - **Impact:** Implementing this would be very impressive for an exam

31. [x] **Task checklists (`task_checklists` + `task_checklist_items`)** — sub-tasks within tasks, with completion tracking.
    - **Impact:** Common feature in project management tools, shows depth

32. [x] **Password reset flow (`password_reset_tokens`)** — the table exists but there's no forgot-password endpoint or UI.
    - **Impact:** Essential for any real auth system

33. [ ] **Team channels (`channels`)** — the schema supports different channel types (general, public, private, announcement) but the chat system doesn't use them.

34. [x] **Message reactions (`message_reactions`)** — the frontend has a reaction UI but it's purely local. The backend table exists but has no API.

35. [ ] **Message mentions (`message_mentions`)** — same as reactions. Frontend shows mentions but nothing is saved or delivered.

36. [x] **File attachments (`message_attachments`)** — full schema for file metadata (size, type, dimensions) but no upload/download API.

37. [ ] **Group conversations (`conversations` + `conversation_participants`)** — designed for multi-person chats separate from team rooms.

38. [ ] **Organizations (`organizations`)** — workspace management with plan types (free, standard, business, pro, enterprise).

39. [x] **Calendar RSVP** — the schema has `response_status` on event attendees but no API to accept/decline invitations.

40. [ ] **Most user settings are inaccessible** — the `user_settings` table has 20+ columns but only 7 are exposed through the API. Missing: DND mode, typing indicators, message previews, enter-to-send, etc.

41. [x] **CalendarEvent model** is missing many fields from `$fillable` that the schema supports: timezone, recurring events, categories, meeting links.

42. [x] **Friend re-request** — declined friend requests can't be re-sent because the old record blocks new ones.

---

## Phase 5 — Features the API Supports But the Frontend Doesn't Show

The Laravel backend already has endpoints for these. You just need to build the UI.

43. [x] **Time tracking page** — the backend has full CRUD for time entries plus a summary endpoint. No frontend page exists.
    - **Effort:** Medium (backend done, just need UI)

44. [x] **Activity log page** — the backend has user, project, and global activity feeds. No frontend page.
    - **Effort:** Low-medium (backend done)

45. [x] **Friend management page** — the backend supports send/accept/decline/remove friend requests. No UI.
    - **Effort:** Medium

46. [x] **Team editing/deletion/leaving** — `api.js` already has `teams.update()`, `teams.destroy()`, `teams.leave()` functions. Just need buttons and modals.
    - **Effort:** Low

47. [x] **Calendar event editing and deletion** — same, `api.js` already has the functions.
    - **Effort:** Low

48. [x] **Notification settings from server** — `Settings.jsx` has toggle switches but they use hardcoded defaults instead of loading from `settingsApi.get()`.
    - **Effort:** Low (just wire up existing API)

49. [x] **User presence/status** — backend supports online/away/busy/dnd/brb/offline/invisible. Frontend doesn't show or set it.
    - **Effort:** Low-medium

50. [x] **Avatar images** — the `User` model has `avatar_url` but `Avatar.jsx` always shows initials.
    - **Effort:** Low

51. [x] **URL-based routing** — `react-router-dom` is already installed in `package.json` but completely unused. The app uses state to switch pages, meaning no bookmarks, no back/forward, no deep links.
    - **Effort:** Medium (needs refactoring App.jsx routing)

52. [x] **Pagination** — projects are capped at 50, tasks at 100. No "load more" or pagination UI.
    - **Effort:** Low

---

## Phase 6 — Accessibility & UX Polish

53. [x] **Zero ARIA attributes** in the entire codebase — no `aria-label`, `aria-expanded`, `aria-live` anywhere.
54. [x] **No keyboard navigation** — modals don't trap focus, dropdowns aren't keyboard-accessible, no Escape to close.
55. [x] **Interactive elements are divs** — the sidebar collapse toggle, various clickable areas use `<div onClick>` instead of `<button>`.
56. [x] **Form labels aren't linked to inputs** — no `htmlFor`/`id` pairs on any form.
57. [x] **Possible color contrast issues** — very small text (10-11px) with dim colors on dark backgrounds.
58. [x] **No loading states** — no skeleton loaders or error boundaries.
59. [x] **Message polling never pauses** — keeps making requests every 3 seconds even when the browser tab is hidden.
60. [x] **No form validation on calendar** — you can set an end time before the start time.

---

## Phase 7 — Impressive Exam Additions

If you have time, these would really stand out:

61. [x] **Drag-and-drop Kanban board** — use a library like `@hello-pangea/dnd` to make task columns draggable. Schema already supports it.
62. [ ] **Real-time messaging with WebSockets** — replace the 3-second polling with Laravel Reverb or Pusher. Instant message delivery.
63. [x] **File upload for attachments** — implement real file upload with progress bars and previews.
64. [x] **User profile page with avatar upload** — show user stats, activity, let them upload a photo.
65. [x] **Search** — the database already has a full-text search index on messages (supports Hungarian!). Build a search bar.
66. [x] **Data export** — CSV/PDF reports for time tracking or project status.
67. [x] **Proper Laravel migrations** — only 1 migration exists. The whole schema is in raw SQL. Converting to migrations shows Laravel mastery.

---

## Phase 8 — Testing & DevOps

68. [ ] Write actual backend tests (only placeholder `ExampleTest.php` exists)
69. [ ] Test the authorization checks once you implement them
70. [ ] Add API documentation (Swagger or update the README)
71. [ ] Write frontend tests with Vitest
72. [x] Create proper `.env.example` files
73. [x] Remove or integrate the Node.js server (`kodo/server/`)
74. [ ] Clean up unused npm dependencies

---

## Phase 9 — Frontend Architecture & Polish

These are frontend issues found during a code audit. They aren't visible bugs like Phase 3, but they'll show up during a demo (laggy UI, mobile layout breaks, mixed languages, etc.) and matter for an examiner reviewing the code.

### Component responsibility (god components)

75. [ ] **Messages.jsx is 826 lines** with 19 `useState` hooks mixing team/DM switching, emoji picker, mention popup, friend search, file uploads, and message sending.
    - **Fix:** Split into `TeamList`, `DMList`, `MessageThread`, `ComposeBox`, `FriendSearchPanel`. Move state into `MessagesContext` where it belongs.

76. [ ] **Calendar.jsx is 851 lines** — the page, the event detail popup, the create/edit modal, and the day/week/month views all live in one file.
    - **Fix:** Extract `EventDetailPopup`, `EventCreateForm`, and the view renderers into `components/calendar/`.

77. [ ] **Dashboard.jsx is 543 lines** and calls 3 different APIs inline instead of through a context.
    - **Fix:** Extract `WeeklyChart`, `MiniCalendar`, `TeamMembersWidget` and move data fetching into a `DashboardContext`.

78. [ ] **AuthPage.jsx is 562 lines** with `VerificationScreen` and `ForgotPasswordScreen` defined inside it.
    - **Fix:** Split into three sibling files so each screen is independently testable.

79. [ ] **Sidebar.jsx is 348 lines** — navigation, project dropdown, create-project modal, status popup, and logout all in one component.
    - **Fix:** Extract the create-project modal and the status popup into their own components.

### Responsiveness / mobile

80. [ ] **Messages.jsx:312** uses `h-[calc(100dvh-100px)]` with no `min-h` fallback; the layout collapses on older mobile browsers.

81. [ ] **Calendar.jsx:161** — `EventDetailPopup` has `max-w-[380px]` but no mobile padding; popup can bleed past the viewport on phones.

82. [ ] **Calendar.jsx:377** — `EventCreateForm` modal at `max-w-[520px] max-h-[90vh]` leaves no safe margin on iPad portrait.

83. [ ] **Dashboard.jsx:46** — `WeeklyChart` is fixed at `h-[150px] md:h-[190px]` with no scaling for phones under 320px.

84. [ ] **TopBar.jsx:84** — long page titles can truncate without wrap on xs screens because the container has `overflow-hidden`.

### State & data flow

85. [ ] **Messages.jsx:27-46** has 19 independent `useState` hooks with no derived state — prime candidate for `useReducer` or context consolidation.

86. [ ] **Dashboard.jsx:32** calls `friendsApi.list()` while `Sidebar` separately fetches projects; user-related data is fetched in multiple mount points with no cache.
    - **Fix:** Create a `UserDataContext` or use a query cache (TanStack Query).

87. [ ] **Calendar.jsx:524-526** loads `allUsers`, `projectMembers`, `events` in parallel but without memoization; every filter toggle re-renders the whole tree.

88. [ ] **TasksContext.jsx:68-82** — optimistic update on task drag has no rollback on API failure. If the PATCH fails, the UI stays in the wrong state until a manual refetch.
    - **Fix:** Keep the previous state in a ref, revert on error.

89. [ ] **Error toasts are hardcoded in English** in `MessagesContext.jsx:58,149,163` (`'Failed to load messages'`, `'Failed to send message'`) — bypass the translation system.

### Performance

90. [ ] **Calendar.jsx:56,72** — `EventCard` runs nested `.map()` over members on every render without `React.memo`.

91. [ ] **Messages.jsx:317,464** — `handleSelectTeam` and team/DM button renderers aren't wrapped in `useCallback`, so child buttons re-render on every keystroke in the compose box.

92. [ ] **Dashboard.jsx:21** — `WeeklyChart` isn't memoized; recalculates colours and hours on every parent render.

93. [ ] **Messages.jsx:225-235** — friend search hits `usersApi.list()` on every keystroke. Needs a 300ms debounce or client-side filter.

94. [ ] **No virtualization anywhere** — long task lists, message lists, and activity feeds render every item to the DOM.
    - **Fix:** Use `react-window` for lists expected to grow.

### i18n coverage gaps

95. [ ] **AuthPage.jsx:87** — `toast.success('Sikeres bejelentkezés!')` is hardcoded Hungarian with no translation key.

96. [ ] **AuthPage.jsx:384** — same issue: `toast.success('Sikeres regisztráció!')` hardcoded.

97. [ ] **AuthPage.jsx:287** — `placeholder="nev@kodo.io"` is Hungarian even when the UI is in English.

98. [ ] **AuthPage.jsx:253** — Hungarian fallback `'Elfelejtett jelszó'` used instead of trusting the translation key.

99. [ ] **Calendar.jsx:109** — the string `'Online'` is hardcoded in the event detail meeting indicator.

### Forms & validation UX

100. [ ] **NewTaskModal.jsx:313** — submit button has no `disabled` state during submission; a double-click sends two POSTs.

101. [ ] **NewTaskModal.jsx:150-157** — invalid fields show a red border but no `aria-invalid="true"`; screen readers don't know the field is wrong.

102. [ ] **NewTeamModal.jsx:120** — team name label has no `*` required indicator even though the validator rejects empty names.

103. [ ] **Settings.jsx:83,87** — password-change errors (`'Passwords do not match'`, `'Password must be at least 8 characters'`) are hardcoded English.

104. [ ] **AuthPage.jsx:368-372** — register form calls both `setError()` and `toast.error()` for the same error, doubling up the message; the submit button has no loading spinner.

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
