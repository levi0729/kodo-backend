# Kodo Project Context

> **New to this project?** Read `ROADMAP.md` for the full audit (security issues, missing features, bugs, prioritized plan). This file (`CLAUDE.md`) is automatically loaded by Claude Code to give context about the project.

---

## Getting Started with Claude Code on This Project

### Step 1: Pull the repo
```bash
git pull origin main
```

### Step 2: Open Claude Code in the project folder
```bash
cd path/to/iktporjekt/kodo       # or nexus-backend
claude
```

Claude Code automatically reads this `CLAUDE.md` file when you start a session, so it already knows the project structure, tech stack, conventions, and known issues.

### Step 3: What you can ask Claude to do
- **"Fix authorization in TaskController"** — it knows the exact security issues from ROADMAP.md
- **"Add the time tracking page"** — it knows the backend API already exists and just needs a frontend
- **"What's broken in the chat system?"** — it knows the 3 authorization holes in ChatController
- **"Implement task checklists"** — it knows the schema exists but there's no API or UI yet
- **"Wire up notification settings in Settings.jsx"** — it knows the toggles use hardcoded defaults

### Step 4: Check progress
Open `ROADMAP.md` and check off items as you complete them. It's organized by priority for the exam.

---

## What is this
Kodo is a team/project management web app (like a mini Slack + Trello + Google Calendar). Built for an IKT exam.

## Architecture
```
iktporjekt/
├── kodo/                    React 18 + Vite + Tailwind (frontend)
│   ├── src/
│   │   ├── components/      10 reusable components
│   │   ├── context/         5 React contexts (Auth, Messages, Project, Tasks, Theme)
│   │   ├── pages/           7 pages (Auth, Dashboard, Messages, Tasks, Teams, Calendar, Settings)
│   │   ├── services/api.js  API client (Bearer token auth)
│   │   ├── i18n/            Hungarian + English translations
│   │   └── data/mockData.js Fallback mock data
│   └── server/              Legacy Node.js Express server (DO NOT USE — no auth, to be removed)
│
└── nexus-backend/           Laravel 12 + Sanctum (backend API)
    ├── app/Http/Controllers/Api/   15 API controllers
    ├── app/Models/                 14 Eloquent models
    ├── app/Http/Requests/          13 form request validators
    ├── app/Http/Resources/         8 API resource transformers
    ├── routes/api.php              All API routes (Sanctum-protected)
    └── database/schema.sql         Full PostgreSQL schema (28 tables)
```

## Tech Stack
- **Frontend:** React 18, Vite 5, Tailwind CSS 3, Lucide React icons
- **Backend:** Laravel 12, PHP 8.2+, Laravel Sanctum (token auth)
- **Database:** PostgreSQL 15+ (DB name: `kodo`, user: `postgres`)
- **Deployment:** Vercel (frontend), Render.com + Docker (backend)

## How to Run
```bash
# Frontend
cd kodo && npm install && npm run dev    # runs on localhost:5173

# Backend
cd nexus-backend && composer install && php artisan serve    # runs on localhost:8000
```

## Key Conventions
- Frontend API calls go through `kodo/src/services/api.js` — never call fetch directly
- All API routes are in `nexus-backend/routes/api.php`
- Auth: Sanctum Bearer tokens, stored in localStorage (known issue — should be httpOnly cookies)
- Chat uses polling (3s interval), room IDs use `min(id1,id2)*100000+max(id1,id2)` for DMs
- The Node.js server in `kodo/server/` is legacy — the Laravel backend is the real API
- Database schema is in `nexus-backend/database/schema.sql` (only 1 proper Laravel migration exists)
- i18n: Use translation keys from `kodo/src/i18n/translations.js`, not hardcoded strings

## Known Issues
See `ROADMAP.md` for the full audit. Key problems:
- Most controllers have no authorization (any user can see/edit everything)
- `.env` files committed with real credentials
- Frontend fakes some features (file attachments, reactions, mentions are client-only)
- Many database tables have no corresponding API or UI
- No proper URL routing (react-router-dom installed but unused)

## When Making Changes
- Always add authorization checks to new controller methods (check ownership/membership)
- Use Form Request classes for validation, not inline validation
- Use API Resource classes for response formatting
- Add activity logging for CRUD operations
- Use translation keys for all user-facing strings
- Test with multiple user accounts to verify authorization works
