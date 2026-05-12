# Complaint System

Complaint System is a Laravel-based API for submitting, tracking, and managing citizen complaints. It supports role-based workflows for citizens, employees, and administrators, and provides notifications, reporting, and auditing.

## Features

- Citizen registration, authentication, email verification, and password reset
- Complaint submission with attachments and status tracking
- Employee workflow for reviewing, updating, and requesting complaint updates
- Admin tools for employee management, complaint summaries, and PDF reports
- Notifications via Firebase Cloud Messaging and in-app notification records
- System logging, complaint audit trails, and health endpoints

## Tech Stack

- Laravel 12 / PHP 8.2+
- Laravel Sanctum for API authentication
- Spatie Permissions for role-based access
- Redis-compatible cache store and visit counters
- Firebase Admin SDK for push notifications
- DomPDF for PDF exports
- Vite + Tailwind for asset bundling

## Setup

1. Install backend dependencies:
   ```bash
   composer install
   ```
2. Install frontend dependencies:
   ```bash
   npm install
   ```
3. Create a `.env` file and configure at least:
   - `APP_KEY`
   - `DB_*` connection values
   - `CACHE_STORE` / `REDIS_*` (if using Redis)
   - `MAIL_*` for email verification and password reset
   - `FIREBASE_CREDENTIALS` (service account JSON path)
4. Generate the application key:
   ```bash
   php artisan key:generate
   ```
5. Run database migrations:
   ```bash
   php artisan migrate
   ```

## Running Locally

- Start the API server:
  ```bash
  php artisan serve
  ```
- Run the Vite dev server (if working on assets):
  ```bash
  npm run dev
  ```
- Or use the combined dev script:
  ```bash
  composer run dev
  ```

## Scripts

- `composer run dev` – start API server, queue listener, log tailing, and Vite
- `composer run test` – run application tests
- `npm run build` – build production assets
- `npm run dev` – run Vite in development mode

## API Overview

All API routes are prefixed with `/api`. Highlights include:

- Authentication: `signup`, `signin`, `signout`, email verification, password reset
- Complaints: create, update, submit updates, view details, change status
- Notifications: list, mark as read, mark all as read
- Admin: manage employees, complaint summaries, system stats, PDF export
- Public data: governorates, departments, grouped departments
- Health check: `GET /health`

See `routes/api.php` and `routes/web.php` for full endpoint definitions.

## Non-Functional Requirements Overview

The following non-functional requirements are implemented in this project:

- **Security**: Token-based auth (Sanctum), role-based access control (Spatie), hashed passwords, email verification, and server-side validation.
- **Reliability & Data Integrity**: Database transactions with retry logic for critical flows, after-commit notification handling, and complaint audit logs.
- **Performance**: Cached complaint lists with TTL, pagination for list endpoints, and Redis-ready caching/visit counters.
- **Availability**: `/health` endpoint for monitoring and load balancer checks.
- **Observability**: System logging middleware captures request metadata, response status, and execution time.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
