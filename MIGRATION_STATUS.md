# Migration Status

## Current target structure

- `backend/`: Laravel 12 backend with Sanctum, Fortify, Spatie Permission, Spatie Activitylog, Spatie Medialibrary, Laravel Excel, Redis client, and Pusher.
- `frontend/`: Vue 2 frontend with Vuetify, Vuex, Vue Router, Axios, Chart.js, VeeValidate, Uppy, and Vue Toastification.

## Commands

- Root frontend dev: `npm run dev`
- Root frontend build: `npm run build`
- Root backend serve: `npm run serve:backend`
- Root backend migrate: `npm run migrate:backend`
- Root backend tests: `npm test`
- Direct backend run: `php artisan serve`
- Direct backend tests: `php artisan test`
- Direct frontend dev: `npm run serve`
- Direct frontend build: `npm run build`

## React to Vue migration map

- Legacy React pages were replaced by Vue route equivalents under `frontend/src/views`.
- Legacy Supabase data model was replaced by Laravel code in `backend/app` and `backend/database/migrations`.

## Backend migration map

- Supabase auth -> Laravel Fortify + Sanctum
- Supabase tables -> Laravel migrations + Eloquent models
- `api/send-push.ts` -> Laravel notifications, broadcasting, or queued jobs
- Previous SQL references -> `backend/database/migrations`
- File upload handling -> Spatie Medialibrary + S3 disk
- Realtime flows -> Laravel broadcasting + Pusher

## Database source of truth

- Canonical schema: `backend/database/migrations`
- Canonical runtime database: Laravel MySQL configuration in `backend/.env`
- No parallel schema source remains in the active workspace structure.

## Remaining blockers before full structure parity

1. Complete behavioral parity for the public and user-facing Vue pages now created in `frontend/src/views`.
2. Finalize production MySQL credentials and deployment environment files.

## Next implementation order

1. Create Laravel migrations from current Supabase schema.
2. Build auth endpoints and roles/permissions.
3. Port dashboard APIs and spreadsheet import/export.
4. Port public site pages from React to Vue 2.
5. Port dashboard pages from React to Vue 2.
6. Refine user-facing Vue behavior and deploy on the Laravel/Vue structure only.