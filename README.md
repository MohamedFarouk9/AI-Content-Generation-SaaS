````markdown
# AI Content Generation SaaS

A production-grade, multi-tenant AI content generation platform built with Laravel. Designed with clean architecture, modular boundaries, and database-level data integrity.

## 🏗 Architecture

**Modular Monolith** — Each domain (Identity, Organization, AI, Billing) lives in its own module under `app/Modules/`, with clear namespace boundaries and no cross-module coupling through shared interfaces.

```
app/
├── Modules/
│   ├── Identity/          # Users, Authentication, OAuth
│   ├── Organization/      # Multi-tenancy, Teams, Roles
│   └── AI/                # Provider Integration, Requests
├── Shared/
│   ├── Enums/             # Shared PHP Backed Enums
│   └── Traits/            # HasPublicId, BelongsToOrganization
```

## 🧰 Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.2+ |
| Framework | Laravel 12 |
| Database | PostgreSQL |
| Cache / Queue / Sessions | Redis |
| Testing | Pest v3 |
| Static Analysis | Larastan (PHPStan Level 5) |
| Code Style | Laravel Pint |
| Containerization | Docker (Laravel Sail) |
| OAuth | Laravel Socialite |

## 📦 Module Overview

### Identity Module
- Email/password registration & login
- OAuth (Google, GitHub) via Socialite
- Session-based authentication (HttpOnly cookies, CSRF protected)
- Account linking (multiple OAuth providers per user)
- Login throttling (5 attempts/min per email+IP)

### Organization Module
- Multi-tenant architecture (single database, `organization_id` scoping)
- Role-based membership: Owner, Admin, Member
- Workspace switching (`current_organization_id`)
- Automatic tenant scoping via `BelongsToOrganization` global scope

### AI Module
- Provider abstraction via `AiProviderContract` interface
- OpenAI driver (GPT-4o, GPT-4o Mini)
- Database-driven model registry with token pricing
- Request tracking with full token usage and cost calculation
- Normalized `AiResponse` DTO for provider-agnostic responses

## 🚀 Getting Started

### Prerequisites
- Docker & Docker Compose
- Composer
- Node.js & npm

### Installation

```bash
# Clone the repository
git clone <your-repo-url>
cd AI-Content-Generation-SaaS

# Copy environment file
cp .env.example .env

# Start Docker containers
./vendor/bin/sail up -d

# Install dependencies
./vendor/bin/sail composer install
npm install

# Generate application key
./vendor/bin/sail artisan key:generate

# Run migrations
./vendor/bin/sail artisan migrate

# Seed the database with test data
./vendor/bin/sail artisan db:seed
```

### Environment Variables

Add your API keys to `.env`:

```env
OPENAI_API_KEY=your-openai-api-key-here
```

### Development Server

```bash
composer run dev
```

This starts the Laravel dev server, queue worker, and Vite simultaneously.

## 🧪 Testing

```bash
# Run all tests
./vendor/bin/sail artisan test --compact

# Run a specific test file
./vendor/bin/sail artisan test --filter=RegistrationTest

# Run with coverage
./vendor/bin/sail artisan test --coverage
```

### Test Structure
```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── RegistrationTest.php
│   │   ├── LoginTest.php
│   │   ├── LogoutTest.php
│   │   └── UserTest.php
│   ├── Organization/
│   │   └── OrganizationTest.php
│   └── AI/
│       └── AiModelSeederTest.php
└── Unit/
    └── AI/
        ├── AiModelCostTest.php
        ├── AiResponseDtoTest.php
        └── AiManagerTest.php
```

## 🔒 Security

- **Authentication**: Stateful session cookies (HttpOnly, Secure, SameSite)
- **CSRF**: Automatic via Laravel middleware
- **Password Hashing**: Bcrypt with 12 rounds
- **OAuth**: State parameter for CSRF protection during OAuth flow
- **Tenant Isolation**: Automatic query scoping via global scopes
- **Rate Limiting**: Login throttling (5 attempts), configurable per-route
- **Public IDs**: ULIDs exposed in APIs instead of sequential integers

## 📊 Database Design Principles

- **NOT NULL by default** — nullable only with documented business reason
- **Foreign keys on all relationships** — database-level referential integrity
- **UNIQUE constraints** — emails, public_ids, provider identities
- **Integer money** — financial values stored in cents (no floats)
- **ULID public IDs** — sequential, URL-safe, prevents enumeration
- **BigInt internal IDs** — optimal B-tree index performance

## 📋 API Endpoints

### Authentication
| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/auth/register` | Guest | Register a new user |
| POST | `/api/auth/login` | Guest | Login |
| POST | `/api/auth/logout` | Auth | Logout |
| GET | `/api/auth/user` | Auth | Get current user |
| GET | `/api/auth/auth/{provider}/redirect` | Guest | OAuth redirect |
| GET | `/api/auth/auth/{provider}/callback` | Guest | OAuth callback |

### AI
| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/ai/models` | Auth | List active AI models |
| POST | `/api/ai/generate` | Auth | Generate text |
| GET | `/api/ai/history` | Auth | Request history (paginated) |
| GET | `/api/ai/requests/{id}` | Auth | Show single request |

## 🗺 Roadmap

- [x] Phase 1 — Foundation & Dev Infrastructure
- [x] Phase 2 — Database Conventions
- [x] Phase 3 — Authentication & OAuth
- [x] Phase 4 — Organizations & Multi-Tenancy
- [x] Phase 5 — AI Provider Integration
- [x] Phase 6 — AI Request Processing
- [ ] Phase 7 — Background Jobs & Queues
- [ ] Phase 8 — Usage, Limits & Cost Tracking
- [ ] Phase 9 — Plans & Subscriptions
- [ ] Phase 10 — Payment Providers
- [ ] Phase 11 — Payment Webhooks & Billing
- [ ] Phase 12 — Authorization & Permissions
- [ ] Phase 13 — Notifications & Auditing
- [ ] Phase 14 — Observability
- [ ] Phase 15 — Performance Optimization
- [ ] Phase 16 — Frontend (SPA)

## 📄 License

Proprietary — All rights reserved.
````

---

## 5. File Summary

### Files to CREATE
| # | File |
|---|------|
| 1 | `database/migrations/2026_08_07_100000_create_ai_requests_table.php` |
| 2 | `app/Shared/Enums/AiRequestStatus.php` |
| 3 | `app/Modules/AI/Models/AiRequest.php` |
| 4 | `app/Modules/AI/Requests/GenerateTextRequest.php` |
| 5 | `app/Modules/AI/Controllers/AiController.php` |
| 6 | `app/Modules/AI/routes.php` |
| 7 | `database/factories/AiRequestFactory.php` |
| 8 | `database/seeders/AiRequestSeeder.php` |
| 9 | `tests/Feature/Auth/RegistrationTest.php` |
| 10 | `tests/Feature/Auth/LoginTest.php` |
| 11 | `tests/Feature/Auth/LogoutTest.php` |
| 12 | `tests/Feature/Auth/UserTest.php` |
| 13 | `tests/Feature/Organization/OrganizationTest.php` |
| 14 | `tests/Unit/AI/AiModelCostTest.php` |
| 15 | `tests/Unit/AI/AiResponseDtoTest.php` |
| 16 | `tests/Unit/AI/AiManagerTest.php` |
| 17 | `tests/Feature/AI/AiModelSeederTest.php` |
| 18 | `README.md` |

### Files to MODIFY
| # | File | Change |
|---|------|--------|
| 1 | `bootstrap/app.php` | Add AI routes |
| 2 | `database/seeders/DatabaseSeeder.php` | Add AiModelSeeder + AiRequestSeeder |
| 3 | `tests/Pest.php` | Enable RefreshDatabase |

---

PHASE 6 PROPOSAL COMPLETE.

No files have been modified and no commands have been executed.

Review the AI request processing code, tests, and README.

Explicitly approve, reject, or modify the proposal.

I will not continue to PHASE 7 until you give explicit approval.
