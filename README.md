# Proector API

Backend API for client app, security personnel app, and admin panel.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

## Swagger

- UI: `http://127.0.0.1:8000/api/documentation`
- Generate docs:

```bash
php artisan l5-swagger:generate
```

## API Response Format (Single Standard)

All API endpoints must return JSON.

### Success format

```json
{
  "status": "success",
  "message": "Optional success message",
  "data": {}
}
```

Notes:
- Some existing endpoints return domain keys like `booking`, `payments`, `teams` instead of `data`.
- New development should prefer the same envelope shape and keep payload under `data`.

### Error format

```json
{
  "status": "error",
  "code": 422,
  "message": "Validation failed.",
  "errors": {
    "field": ["Error message"]
  }
}
```

Implemented global API JSON errors:
- `401` unauthenticated
- `403` forbidden
- `404` endpoint not found
- `422` validation
- `500` server error

## Authentication

- Client:
  - `POST /api/client/signup/send-otp`
  - `POST /api/client/signup`
  - `POST /api/client/signin/send-otp`
  - `POST /api/client/signin`
- Security: `POST /api/security/login`
- Admin: `POST /api/admin/login`

Protected routes use Sanctum bearer token.

## Seed Data

Database seeding includes:
- Security teams
- Vehicles (with `image_url`)

Run:

```bash
php artisan db:seed
```
