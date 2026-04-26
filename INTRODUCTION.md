# Nexo Introduction

## Overview

Nexo is a self-hosted personal finance application for people who want a clear picture of their money without outsourcing their data. The current product combines accounts, transactions, budgets, dashboard metrics, reports, SMS ingestion, AI-assisted workflows, billing, and localization-aware React pages inside one Laravel application.

## What The Product Does Today

- Tracks accounts, balances, transactions, and transfer-style activity.
- Organizes spending and income with budgets attached to one or more accounts.
- Generates dashboard metrics and analytical reports through dedicated API endpoints.
- Parses bank SMS messages into reviewable finance records.
- Records account audit activity and supports account sharing permissions.
- Provides AI chat, guided tool confirmation, and audio transcription flows.
- Stores user locale and serves localized UI state, including `ltr` and `rtl` handling.
- Supports billing, credits, and subscription management in the web application.

## Core User Flow

1. A visitor lands on the public marketing page at `/`.
2. After registration or login, the user is redirected to `/dashboard`.
3. The authenticated app surface includes dashboard, accounts, transactions, budgets, AI chat, guide, billing, settings, and reports.
4. The React/Inertia pages consume backend data directly and rely heavily on `/api/v1` endpoints for CRUD, metrics, AI, and settings operations.

## API Overview

The current API surface in `routes/api.php` includes:

- `auth`: register, login, current user, logout
- `accounts`: list, create, update, delete, show, list-all, sharing, audits
- `transactions`: CRUD
- `budgets`: CRUD
- `sms`: list, create, update, delete
- `settings`: profile, locale, currency preferences, currency rates
- `metrics`: income, expenses, assets, liabilities, equity, trends, account stats, circle pack, transaction stats
- `ai`: chat, tool response continuation, transcription, transcription token
- `user`: profile update

API collections exist under `ApiCollection/` in Bruno and Postman formats. Some collection entries are historical and no longer implemented, so route and controller code should be treated as authoritative when there is a mismatch.

## Deployment Reality

Nexo is now operated primarily with a Linux-native deployment stack rather than Docker-first infrastructure.

Current deployment assumptions:

- Laravel and PHP run natively on Linux
- Nginx serves the application layer
- Caddy handles edge and TLS concerns
- Docker files remain in the repository, but they are no longer the primary deployment path

## Localization Status

The application already includes locale plumbing for English and Arabic:

- user locale is stored on the backend
- Inertia shares `locale` and `direction`
- the frontend sets `document.documentElement.lang` and `dir`
- translation resources exist under `resources/lang/en` and `resources/lang/ar`

Current gap: the public landing page still needs to better reflect the latest product scope and make the `en` / `ar` language experience more explicit.

## Technical Stack

- Backend: Laravel 12 on PHP 8.4
- Frontend: Inertia.js v2 with React
- Styling: Tailwind CSS v4 with shared UI components
- Authentication: Laravel session auth and Sanctum
- AI: `laravel/ai` with app-specific tools and interactive tool-response flows
- Testing: Pest, PHPUnit, Jest, and TypeScript checks

## Main Application Areas

- Landing: public marketing page at `/`
- Dashboard: high-level financial overview
- Accounts: balances, sharing, and audit trail
- Transactions: searchable ledger and edits
- Budgets: recurring or custom budget tracking
- AI: HisabAI chat, suggestions, charts, and tool confirmation
- Guide: product-help surface
- Billing: plans, credits, checkout, and admin billing management
- Settings: profile, locale, currency, and other user preferences
- Report: printable/report-style summary view

## Project Structure Snapshot

- `app/`: domain logic, controllers, services, models, policies, AI tools, and business logic
- `routes/`: web and API route definitions
- `resources/js/`: Inertia pages, layouts, components, hooks, utilities, and translations
- `resources/lang/`: locale resources for `en` and `ar`
- `ApiCollection/`: Bruno and Postman API collections
- `tests/`: feature and unit coverage

## Running The Project

The default setup direction is native Linux, but local development can still use the repository helpers.

Typical bootstrap:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan hisabi:install
npm run build
```

For development:

```bash
composer run dev
```

If a contributor deliberately uses Docker, that should be treated as a compatibility path rather than the primary operational model.

## Notes For Contributors

- Public visitors see the landing page on `/`.
- Authenticated users are redirected from `/` to `/dashboard`.
- React pages live under `resources/js/pages` and are resolved by Inertia.
- Shared UI primitives live under `resources/js/components/ui`.
- Markdown documentation must be updated whenever product behavior, API surface, deployment assumptions, or major UX flows change.