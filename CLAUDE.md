# Kodo Project Context

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
