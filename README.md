<h1 align="center">
<img width="300" src="./public/images/logo.svg" />
</h1>

<p align="center">
  <b>Nexo is a self-hosted personal finance platform for accounts, transactions, budgets, SMS ingestion, analytics, AI-assisted workflows, and bilingual user experiences.</b>
</p>

<p align="center"><a href="https://www.youtube.com/watch?v=kfwcMdlFn9o&list=PLw5MK6ws-o1_rNobmZCmnH5G11vwCiKKk&ab_channel=ILoveMathAcademy" target="__blank"><img src="https://raw.githubusercontent.com/hisabi-app/hisabi/refs/heads/main/public/images/showcase.png" /></a></p>

## Product Snapshot

Nexo currently ships with:

- Self-hosted finance management with full data ownership
- Multiple accounts, including sharing and account-level audit history
- Transactions, budgets, reports, and metrics endpoints under `/api/v1`
- SMS parsing flows for transaction ingestion
- HisabAI chat, tool confirmation flows, and audio transcription endpoints
- React + Inertia web experience with localization infrastructure for English and Arabic
- Billing, credits, subscription plans, and admin billing management

## Features

- 🔐 Self-hosted deployment with native Linux hosting support
- 💳 Multi-account finance tracking
- 📩 SMS intake and review workflows
- 📊 Dashboard metrics, reports, and analytical endpoints
- 🤖 HisabAI chat, tool responses, and transcription support
- 🔄 Account sharing and audit history
- 🌍 Localization-ready application with `en` and `ar` support in the stack
- 🆓 MIT licensed

## API Surface

The application exposes a versioned API under `/api/v1` for:

- Auth
- Accounts and account sharing
- Transactions
- Budgets
- SMS records
- Settings and currency preferences
- Metrics
- AI chat and tool responses
- Audio transcription
- User profile updates

The repository also includes API collections under `ApiCollection/` in Bruno and Postman formats.

Note: the API collection still contains some historical requests, including category endpoints, that are not currently exposed by `routes/api.php`. Treat the route files and controller code as the source of truth.

## Deployment

The project has moved from Docker-first development to a Linux-native deployment model using Nginx and Caddy.

Current production-style hosting assumptions:

- PHP/Laravel runs directly on the host
- Nginx serves the application stack
- Caddy is used for edge/TLS responsibilities
- Native process management should be considered the default operational path

The repository still contains Docker artifacts for historical or fallback use, but Docker is no longer the primary deployment story.

## Local Setup

Typical local bootstrap steps:

```bash
git clone https://github.com/hisabi-app/hisabi && cd hisabi
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan hisabi:install
npm run build
```

For active development, the repository also provides:

```bash
composer run dev
```

If you intentionally want the older container workflow, the existing `Makefile` and `docker-compose.yml` are still present, but they should be treated as legacy support rather than the main path.

## Known Product Gap

The application already has locale infrastructure for English and Arabic, and the frontend sets document language and direction from server props. The public landing page still needs to be updated to clearly reflect the newest product surface and expose the language experience more explicitly as `en` / `ar`.

## Documentation Policy

When a feature, API, deployment path, or major UX behavior changes, the affected markdown files in this repository must be updated in the same change.

At minimum, review:

- `README.md`
- `INTRODUCTION.md`
- `.github/copilot-instructions.md`
- `tasks.md`
- Any feature-specific planning document impacted by the change

Documentation must be verified against the codebase and API collections, not copied forward from older markdown files.

## Demo

Try the app with [live demo](https://hisabi.on-forge.com/).

## License

This project is licensed under the MIT License. See [LICENSE](https://github.com/hisabi-app/hisabi/blob/main/LICENSE).
