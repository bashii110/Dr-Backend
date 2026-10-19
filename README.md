# Dr-Backend

REST API backend for the **Doctor Appointment App** (Flutter client). Built with **Laravel 12** and PHP 8.2+. It handles authentication for patients and doctors, and is the foundation for doctor profiles, appointment booking, reviews, and email OTP verification.

## Live Demo App

<p align="center">
  <a href="https://github.com/bashii110/Dr-App/releases/download/v1.0.1/app-release.apk">
    <img src="https://img.shields.io/badge/Download%20APK-v1.0.1-1A73E8?style=for-the-badge&logo=android&logoColor=white" alt="Download APK" />
  </a>
</p>

## Tech Stack

- PHP ^8.2, Laravel ^12
- SQLite by default (MySQL / PostgreSQL supported via `.env`)
- Token-based API authentication (Laravel Sanctum)
- Vite + Tailwind CSS 4 (default Laravel frontend tooling)
- PHPUnit 11 for tests

## Features

### Implemented
- User registration with role selection (`patient` or `doctor`)
- Login with email/password and API token issuance
- Database schema for users, doctors, and user details

### In progress / planned
These are scaffolded (files exist) or already called by the Flutter app, but not yet implemented:

- Email OTP verification (`OtpMail`, `OtpCode`, `otp.blade.php`)
- Doctor listing, search, categories, and details (`DoctorController`)
- Appointment booking, cancelling, and status updates (`AppointmentController`)
- Doctor reviews and ratings (`ReviewController`, `Review`)
- Profile update and password change (`ProfileController`)
- New-appointment notifications (`NewAppointmentNotification`)

## Getting Started

### Prerequisites
- PHP 8.2+ and Composer
- Node.js 18+ and npm
- SQLite (or MySQL/PostgreSQL)

### Installation

```bash
git clone https://github.com/bashii110/Dr-Backend.git
cd Dr-Backend

composer install
cp .env.example .env
php artisan key:generate

# SQLite (default)
touch database/database.sqlite

php artisan migrate

npm install
npm run build
```

Or use the bundled shortcut:

```bash
composer run setup
```

### Run the server

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Use `--host=0.0.0.0` so a physical device or emulator on the same network can reach the API. Then set the base URL in the Flutter app (`lib/service/api_service.dart`) to:

```
http://<your-machine-ip>:8000/api
```

For a full dev environment (server, queue worker, logs, Vite):

```bash
composer run dev
```

## Configuration

Key `.env` settings:

| Variable | Description | Default |
|---|---|---|
| `APP_URL` | Base URL of the app | `http://localhost` |
| `DB_CONNECTION` | `sqlite`, `mysql`, `pgsql` | `sqlite` |
| `MAIL_MAILER` | Mail driver (OTP emails) | `log` |
| `MAIL_HOST` / `MAIL_PORT` | SMTP server | `127.0.0.1` / `2525` |
| `MAIL_USERNAME` / `MAIL_PASSWORD` | SMTP credentials | `null` |
| `QUEUE_CONNECTION` | Queue driver | `database` |

To use MySQL, set:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dr_backend
DB_USERNAME=root
DB_PASSWORD=
```

With `MAIL_MAILER=log`, emails (including OTP codes) are written to `storage/logs/laravel.log`, which is handy during development.

## API Reference

Base path: `/api`. Send `Accept: application/json` and `Content-Type: application/json`.

### `POST /api/register`

Create a new account.

**Body**

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "secret123",
  "type": "patient"
}
```

| Field | Rules |
|---|---|
| `name` | required, string |
| `email` | required, valid email, unique |
| `password` | required, min 6 characters |
| `type` | required, `patient` or `doctor` |

**Response**

```json
{ "status": 200, "message": "Registered Successfully! Please Login." }
```

### `POST /api/login`

**Body**

```json
{ "email": "jane@example.com", "password": "secret123" }
```

**Success**

```json
{
  "status": 200,
  "token": "1|abc...",
  "user": { "id": 1, "name": "Jane Doe", "email": "jane@example.com", "type": "patient" },
  "message": "Successfully Login!"
}
```

**Failure**

```json
{ "status": 401, "message": "Email or password is wrong!" }
```

Send the token on protected routes:

```
Authorization: Bearer <token>
```

### Endpoints expected by the Flutter app (not yet implemented)

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/verify-otp`, `/resend-otp` | Email OTP verification |
| POST | `/logout`, GET `/me` | Session |
| POST | `/forgot-password` | Password reset |
| GET | `/doctors`, `/doctors/categories`, `/doctors/{id}`, `/doctors/{id}/reviews` | Browse doctors |
| POST/GET | `/appointments` | Book / list patient appointments |
| PATCH | `/appointments/{id}/cancel` | Cancel appointment |
| PUT | `/doctor/profile` | Doctor updates profile |
| GET | `/doctor/appointments` | Doctor's appointments |
| PATCH | `/doctor/appointments/{id}/status` | Confirm / complete / cancel |
| POST | `/reviews` | Submit review |
| PUT | `/profile`, POST `/profile/change-password` | Account management |

## Database Schema

**users**: `id`, `name`, `type` (`patient` default / `doctor`), `email` (unique), `email_verified_at`, `password`, `remember_token`, `current_team_id`, `profile_photo_path`, timestamps

**doctors**: `id`, `doc_id` (FK → users.id, unique, cascade delete), `category`, `patient`, `experience`, `bio_data`, `status`, timestamps

**user_details**: `id`, `user_id` (FK → users.id, unique, cascade delete), `bio_data`, `status`, timestamps

Plus Laravel defaults: `password_reset_tokens`, `sessions`, `cache`, `jobs`, `job_batches`, `failed_jobs`.

Relationships: `User hasOne Doctor` (via `doc_id`) and `User hasOne UserDetails` (via `user_id`).

## Project Structure

```
app/
  Http/Controllers/   AuthController (done); Doctor/Appointment/Review/Profile (stubs)
  Mail/               OtpMail (stub)
  Models/             User, Doctor, UserDetails (done); Appointment, OtpCode, Review (stubs)
  Notifications/      NewAppointmentNotification (stub)
bootstrap/app.php     Routing setup (web + api + console)
config/               Framework configuration
database/migrations/  Schema definitions
routes/api.php        API routes
routes/web.php        Web routes
tests/                PHPUnit tests
```

## Testing

```bash
composer test
```

## Known Issues / TODO

- **Sanctum is not installed.** `AuthController@login` calls `createToken()`, but `laravel/sanctum` is not in `composer.json` and `User` doesn't use `HasApiTokens`. Run `composer require laravel/sanctum`, then `php artisan install:api`, add `use Laravel\Sanctum\HasApiTokens;` to `User`, and migrate.
- **Doctor table is missing columns** the Flutter app uses: `consultation_fee`, `available_from`, `available_to`, `rating`, `rating_count`. Also the migration column is `patient` while the model's `$fillable` has `patients`.
- **Doctor and UserDetails models** use `HasFactory` without importing `Illuminate\Database\Eloquent\Factories\HasFactory`.
- **Rename** `NewAppointmentNOtification.php` to `NewAppointmentNotification.php`.
- Registration should be followed by OTP verification (create `otp_codes` migration, send `OtpMail`, add `/verify-otp` and `/resend-otp`).
- Missing migrations for `appointments` and `reviews`.
- Return proper HTTP status codes instead of 200 with `status` in the body, and use API resources for responses.
- Add feature tests for auth and booking flows.

## Related

- Flutter client: Doctor Appointment App (`doctor_app`)

## License

Built on the Laravel framework, which is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
