# Hisabi Introduction

## Overview

Hisabi is a self-hosted personal finance application built for people who want a clear picture of their money without giving up control of their data. The product combines transaction tracking, budgeting, account-level audit history, SMS transaction intake, analytics, and AI-assisted financial workflows in one Laravel application.

## What The Product Does

- Tracks accounts, balances, and transaction history.
- Organizes spending and income with budgets that target specific accounts.
- Generates dashboard metrics and visual reports.
- Parses bank SMS messages into transaction records.
- Records account audit activity for create, update, and delete events.
- Provides AI-assisted finance tooling, including semantic search across accounts, transactions, and budgets.

## Core User Flow

1. A visitor lands on the public marketing page at `/`.
2. After registration or login, the user is redirected to the dashboard.
3. From the dashboard, the user moves into accounts, transactions, budgets, and settings.
4. Data-heavy pages rely on API endpoints under `/api/v1` and render through Inertia-powered React pages.

## Technical Stack

- Backend: Laravel 12 on PHP 8.4
- Frontend: Inertia.js v2 with React
- Styling: Tailwind CSS v4 with shadcn/ui-style components
- Authentication: Laravel session auth and Sanctum support
- Testing: Pest and PHPUnit

## Main Application Areas

- Dashboard: high-level financial insights and charts.
- Accounts: balances, sharing, and audit trail.
- Transactions: searchable and filterable ledger scoped to the user's accounts.
- Budgets: recurring or custom budget tracking, attached to one or more accounts.
- Settings: profile and preference management.

## Project Structure Snapshot

- `app/`: Laravel domain logic, controllers, services, models, policies, and business logic.
- `routes/`: web and API route definitions.
- `resources/js/`: Inertia pages, layouts, components, utilities, and translations.
- `resources/css/`: Tailwind entrypoint and theme variables.
- `tests/`: feature and unit coverage.

## Running The Project

The repository already includes Docker and Makefile helpers. The fastest local setup is:

```bash
make build
make run
make install
```

Then visit `http://localhost`.

## Notes For Contributors

- Public visitors now see the landing page on `/`.
- Authenticated users are redirected from `/` to `/dashboard`.
- The frontend uses React pages under `resources/js/pages` resolved by Inertia.
- Shared UI primitives live under `resources/js/components/ui`.