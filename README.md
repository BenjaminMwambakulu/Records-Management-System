# CSIT Society Records Management System

A full-stack application for managing CSIT Society records — members, events, documents, financial records, and assets.

## Tech Stack

- **Backend:** Laravel 11 (PHP 8.3+), PostgreSQL, Logto (auth)
- **Frontend:** React 19, Vite, Tailwind CSS v4, shadcn/ui

## Prerequisites

- PHP 8.3+
- Composer
- Node.js 20+
- npm
- PostgreSQL

## Quick Start

```bash
node setup.js
```

This installs dependencies, creates `.env` files, generates the app key, runs migrations, and seeds roles.

## Manual Setup

## Project Structure

```
Records Management System/
├── RecordsAPI/          # Laravel backend
└── RecordsFrontend/     # React frontend
```

---

## Backend Setup (RecordsAPI)

```bash
cd RecordsAPI

# Install dependencies
composer install

# Create .env and generate app key
cp .env.example .env
php artisan key:generate

# Configure your database in .env (PostgreSQL by default)
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=recordsapi
# DB_USERNAME=postgres
# DB_PASSWORD=

# Run migrations
php artisan migrate --force

# Seed roles & permissions
php artisan db:seed

# Start the dev server (runs on http://localhost:8000)
composer dev
```

### Environment Variables

The key Logto-related variables in `.env`:

| Variable | Description |
|---|---|
| `LOGTO_ENDPOINT` | Your Logto tenant endpoint |
| `LOGTO_ISSUER` | JWT issuer URL |
| `LOGTO_M2M_APP_ID` | Machine-to-machine app ID |
| `LOGTO_M2M_APP_SECRET` | M2M app secret |
| `LOGTO_API_RESOURCE` | API resource identifier |
| `LOGTO_WEBHOOK_SECRET` | Webhook signing secret |
| `LOGTO_MEMBER`, `LOGTO_SUPER_ADMIN`, `LOGTO_YEAR_REP`, `LOGTO_AUDITOR`, `LOGTO_ADMIN` | Role IDs from Logto |

---

## Frontend Setup (RecordsFrontend)

```bash
cd RecordsFrontend

# Install dependencies
npm install

# Create .env
cp .env.example .env

# Start the dev server (runs on http://localhost:5173)
npm run dev
```

### Environment Variables

| Variable | Description |
|---|---|
| `VITE_LOGTO_ENDPOINT` | Your Logto tenant endpoint |
| `VITE_LOGTO_APP_ID` | Logto application ID |
| `VITE_LOGTO_APP_RESOURCE` | API resource identifier |
| `VITE_REDIRECT_URI` | Auth callback URL (e.g. `http://localhost:5173/callback`) |
| `VITE_LOGTO_POST_LOGOUT_REDIRECT_URI` | Redirect after logout |
| `VITE_ENV` | `local` or `production` |

---

## Running Both

Start the backend and frontend in separate terminals:

```bash
# Terminal 1 — Backend
cd RecordsAPI && composer dev

# Terminal 2 — Frontend
cd RecordsFrontend && npm run dev
```

The frontend proxies API requests to the backend via the `VITE_API_BASE_URL` env var (defaults to `/api`).

---

## Testing

```bash
# Backend
cd RecordsAPI && composer test

# Frontend lint
cd RecordsFrontend && npm run lint
```
