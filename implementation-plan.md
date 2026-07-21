# PHASE 1 — Project Foundation & Infrastructure

> **Status**: Awaiting Approval  
> **Prerequisites**: Phase 0 approved ✅  
> **Depends on**: None (greenfield)

---

## 1. Analysis

### What We Are Building

Phase 1 establishes the **skeleton on which every subsequent phase will build**. Nothing domain-specific is implemented here. Instead, we are creating:

- A running Laravel application in Docker
- The module folder structure enforcing our architectural boundaries
- The core contracts (ports) for AI and Billing — these are the most critical architectural anchors
- The shared domain foundation (base classes, value objects)
- Configuration files for all external providers
- A proper logging setup (structured JSON)

### Why Now

Without this foundation, every future phase will introduce its own ad-hoc structure. The folder layout, namespace convention, service provider pattern, and contract locations must be established before any domain logic is written.

### Risks

- `composer create-project` places files directly — we then modify/extend them rather than replace them wholesale
- Laravel 12 uses `bootstrap/app.php` as the primary middleware registration point (no `Kernel.php`) — our middleware pipeline goes there
- The module service providers must be **explicitly registered** — Laravel does not auto-discover them from `app/Modules/`

---

## 2. Proposed Changes

### Commands to Execute (in order, after approval)

```bash
# 1. Scaffold Laravel into the current directory
composer create-project laravel/laravel . --prefer-dist

# 2. Install required PHP packages
composer require laravel/sanctum

# 3. Install dev packages
composer require --dev pestphp/pest pestphp/pest-plugin-laravel

# 4. After creating module structure, dump autoload
composer dump-autoload
```

### Files to Create

```
Dockerfile
docker-compose.yml
.dockerignore
docker/nginx/default.conf
docker/php/php.ini

config/ai.php
config/payments.php
config/subscriptions.php

app/Shared/Domain/Events/DomainEvent.php
app/Shared/Domain/ValueObjects/Email.php
app/Shared/Domain/ValueObjects/Money.php
app/Shared/Infrastructure/Logging/StructuredLogger.php
app/Shared/Http/Middleware/ResolveTenantMiddleware.php
app/Shared/Http/Middleware/EnforceSubscriptionLimitsMiddleware.php

app/Modules/Identity/Infrastructure/Providers/IdentityServiceProvider.php
app/Modules/Organizations/Infrastructure/Providers/OrganizationsServiceProvider.php
app/Modules/AI/Infrastructure/Providers/AIServiceProvider.php
app/Modules/AI/Application/Contracts/AIProviderPort.php
app/Modules/AI/Domain/ValueObjects/AICompletionRequest.php
app/Modules/AI/Domain/ValueObjects/AICompletionResponse.php
app/Modules/AI/Domain/ValueObjects/CostEstimate.php
app/Modules/Usage/Infrastructure/Providers/UsageServiceProvider.php
app/Modules/Subscriptions/Infrastructure/Providers/SubscriptionsServiceProvider.php
app/Modules/Billing/Infrastructure/Providers/BillingServiceProvider.php
app/Modules/Billing/Application/Contracts/PaymentGatewayPort.php
app/Modules/Billing/Application/DTOs/PaymentRequest.php
app/Modules/Billing/Application/DTOs/PaymentIntent.php
app/Modules/Billing/Application/DTOs/PaymentResult.php
app/Modules/Billing/Application/DTOs/RefundResult.php
app/Modules/Billing/Application/DTOs/SubscriptionRequest.php
app/Modules/Billing/Application/DTOs/ExternalSubscription.php
app/Modules/Billing/Application/DTOs/WebhookEvent.php
app/Modules/Billing/Domain/Exceptions/WebhookSignatureException.php
app/Modules/Webhooks/Infrastructure/Providers/WebhooksServiceProvider.php
app/Modules/Notifications/Infrastructure/Providers/NotificationsServiceProvider.php
app/Modules/Audit/Infrastructure/Providers/AuditServiceProvider.php

routes/modules/identity.php
routes/modules/organizations.php
routes/modules/ai.php
routes/modules/usage.php
routes/modules/subscriptions.php
routes/modules/billing.php
routes/modules/webhooks.php
routes/modules/notifications.php

database/migrations/identity/.gitkeep
database/migrations/organizations/.gitkeep
database/migrations/ai/.gitkeep
database/migrations/usage/.gitkeep
database/migrations/subscriptions/.gitkeep
database/migrations/billing/.gitkeep
database/migrations/webhooks/.gitkeep
database/migrations/audit/.gitkeep
```

### Files to Modify

```
composer.json          — add Modules/ and Shared/ to autoload psr-4
app/Providers/AppServiceProvider.php  — register all module service providers
bootstrap/app.php      — register shared middleware
config/logging.php     — add structured JSON channel
.env.example           — add all platform-specific variables
```

---

## 3. Complete Implementation

### `Dockerfile`

```dockerfile
FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    postgresql-dev \
    linux-headers \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    zip \
    xml

# Install Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction

# Copy application
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
```

---

### `docker-compose.yml`

```yaml
services:

  # ─── PHP Application ──────────────────────────────────────────────────────
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: ai_saas_app
    restart: unless-stopped
    environment:
      - APP_ENV=${APP_ENV:-local}
    volumes:
      - ./:/var/www/html
      - ./docker/php/php.ini:/usr/local/etc/php/conf.d/local.ini
    networks:
      - ai_saas
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy

  # ─── Nginx Web Server ─────────────────────────────────────────────────────
  nginx:
    image: nginx:1.25-alpine
    container_name: ai_saas_nginx
    restart: unless-stopped
    ports:
      - "${APP_PORT:-8080}:80"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    networks:
      - ai_saas
    depends_on:
      - app

  # ─── PostgreSQL ───────────────────────────────────────────────────────────
  postgres:
    image: postgres:16-alpine
    container_name: ai_saas_postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB:       ${DB_DATABASE:-ai_saas}
      POSTGRES_USER:     ${DB_USERNAME:-ai_saas_user}
      POSTGRES_PASSWORD: ${DB_PASSWORD:-secret}
    volumes:
      - postgres_data:/var/lib/postgresql/data
    ports:
      - "${DB_PORT_FORWARD:-5432}:5432"
    networks:
      - ai_saas
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-ai_saas_user} -d ${DB_DATABASE:-ai_saas}"]
      interval: 10s
      timeout: 5s
      retries: 5

  # ─── Redis ────────────────────────────────────────────────────────────────
  # Single Redis instance with two logical databases:
  #   DB 0 → Queue (noeviction policy)
  #   DB 1 → Cache (will be configured per key with TTLs)
  redis:
    image: redis:7-alpine
    container_name: ai_saas_redis
    restart: unless-stopped
    command: >
      redis-server
      --appendonly yes
      --maxmemory-policy noeviction
      --loglevel warning
    volumes:
      - redis_data:/data
    ports:
      - "${REDIS_PORT_FORWARD:-6379}:6379"
    networks:
      - ai_saas
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

  # ─── Queue Worker ─────────────────────────────────────────────────────────
  # Processes all named queues in priority order.
  queue:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: ai_saas_queue
    restart: unless-stopped
    command: >
      php artisan queue:work redis
      --queue=critical,default,ai,webhooks-outbound,notifications,low
      --sleep=3
      --tries=3
      --max-time=3600
      --memory=256
    volumes:
      - ./:/var/www/html
    networks:
      - ai_saas
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy

networks:
  ai_saas:
    driver: bridge

volumes:
  postgres_data:
  redis_data:
```

---

### `docker/nginx/default.conf`

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options        "SAMEORIGIN"   always;
    add_header X-Content-Type-Options "nosniff"      always;
    add_header X-XSS-Protection       "1; mode=block" always;
    add_header Referrer-Policy        "strict-origin-when-cross-origin" always;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # Block access to sensitive files
    location ~ /\.(?!well-known) { deny all; }
    location ~ /\.(env|git|htaccess) { deny all; }

    location ~ \.php$ {
        fastcgi_pass   app:9000;
        fastcgi_index  index.php;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
        fastcgi_param  SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include        fastcgi_params;
    }

    error_page 404 /index.php;

    access_log  /var/log/nginx/access.log;
    error_log   /var/log/nginx/error.log;
}
```

---

### `docker/php/php.ini`

```ini
; ─── Error Handling ──────────────────────────────────────────────────────────
display_errors = Off
log_errors     = On
error_log      = /var/log/php_errors.log

; ─── Performance ─────────────────────────────────────────────────────────────
opcache.enable       = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 0
opcache.validate_timestamps = 0

; ─── Limits ──────────────────────────────────────────────────────────────────
memory_limit       = 256M
max_execution_time = 60
upload_max_filesize = 20M
post_max_size       = 20M
max_input_vars      = 1000

; ─── Date ────────────────────────────────────────────────────────────────────
date.timezone = UTC
```

---

### `.dockerignore`

```
node_modules
vendor
.git
.env
.env.*
!.env.example
storage/logs/*.log
*.log
docker/
```

---

### `composer.json` — autoload section (changes only)

Add to the `autoload.psr-4` section of the generated `composer.json`:

```diff
 "autoload": {
     "psr-4": {
         "App\\": "app/",
+        "App\\Modules\\": "app/Modules/",
+        "App\\Shared\\": "app/Shared/",
         "Database\\Factories\\": "database/factories/",
         "Database\\Seeders\\": "database/seeders/"
     }
 },
```

---

### `config/ai.php`

```php
<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    | The provider used when no explicit provider is specified in a request.
    | Must match a key in the 'providers' array below.
    */
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    */
    'providers' => [

        'openai' => [
            'api_key'     => env('OPENAI_API_KEY'),
            'base_url'    => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout'     => (int) env('OPENAI_TIMEOUT_SECONDS', 60),
            'max_retries' => (int) env('OPENAI_MAX_RETRIES', 3),
            'models'      => [
                'gpt-4o',
                'gpt-4o-mini',
                'gpt-4-turbo',
                'gpt-3.5-turbo',
            ],
            // Cost per 1000 tokens in USD (approximate, update as pricing changes)
            'pricing' => [
                'gpt-4o'          => ['input' => 0.005, 'output' => 0.015],
                'gpt-4o-mini'     => ['input' => 0.00015, 'output' => 0.0006],
                'gpt-4-turbo'     => ['input' => 0.01, 'output' => 0.03],
                'gpt-3.5-turbo'   => ['input' => 0.0005, 'output' => 0.0015],
            ],
        ],

        'anthropic' => [
            'api_key'     => env('ANTHROPIC_API_KEY'),
            'base_url'    => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'timeout'     => (int) env('ANTHROPIC_TIMEOUT_SECONDS', 60),
            'max_retries' => (int) env('ANTHROPIC_MAX_RETRIES', 3),
            'models'      => [
                'claude-3-5-sonnet-20241022',
                'claude-3-5-haiku-20241022',
                'claude-3-opus-20240229',
            ],
            'pricing' => [
                'claude-3-5-sonnet-20241022' => ['input' => 0.003, 'output' => 0.015],
                'claude-3-5-haiku-20241022'  => ['input' => 0.001, 'output' => 0.005],
                'claude-3-opus-20240229'     => ['input' => 0.015, 'output' => 0.075],
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Configuration
    |--------------------------------------------------------------------------
    | When the primary provider fails, the system can fall back to another.
    | This requires explicit opt-in and is disabled by default.
    */
    'fallback' => [
        'enabled'  => (bool) env('AI_FALLBACK_ENABLED', false),
        'provider' => env('AI_FALLBACK_PROVIDER', 'anthropic'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    | Opens after N consecutive failures; recovers after recovery_seconds.
    */
    'circuit_breaker' => [
        'failure_threshold'  => (int) env('AI_CIRCUIT_BREAKER_THRESHOLD', 5),
        'recovery_seconds'   => (int) env('AI_CIRCUIT_BREAKER_RECOVERY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Async Queue
    |--------------------------------------------------------------------------
    */
    'async' => [
        'queue' => env('AI_QUEUE', 'ai'),
        'job_timeout_seconds' => (int) env('AI_JOB_TIMEOUT', 120),
    ],

];
```

---

### `config/payments.php`

```php
<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    */
    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Gateway Configurations
    |--------------------------------------------------------------------------
    */
    'gateways' => [

        'stripe' => [
            'secret_key'      => env('STRIPE_SECRET_KEY'),
            'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
            'webhook_secret'  => env('STRIPE_WEBHOOK_SECRET'),
            'api_version'     => env('STRIPE_API_VERSION', '2024-04-10'),
        ],

        'paymob' => [
            'api_key'        => env('PAYMOB_API_KEY'),
            'integration_id' => env('PAYMOB_INTEGRATION_ID'),
            'iframe_id'      => env('PAYMOB_IFRAME_ID'),
            'hmac_secret'    => env('PAYMOB_HMAC_SECRET'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    | Processed webhook event IDs are stored to prevent duplicate processing.
    */
    'idempotency' => [
        'ttl_hours' => (int) env('PAYMENT_IDEMPOTENCY_TTL_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'queue'          => env('PAYMENT_WEBHOOK_QUEUE', 'critical'),
        'max_retries'    => (int) env('PAYMENT_WEBHOOK_MAX_RETRIES', 3),
        'retry_delay'    => (int) env('PAYMENT_WEBHOOK_RETRY_DELAY', 10),
    ],

];
```

---

### `config/subscriptions.php`

```php
<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trial Configuration
    |--------------------------------------------------------------------------
    */
    'trial_days' => (int) env('SUBSCRIPTION_TRIAL_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Grace Period
    |--------------------------------------------------------------------------
    | Days after payment failure before access is restricted.
    */
    'grace_period_days' => (int) env('SUBSCRIPTION_GRACE_PERIOD_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Limit Enforcement
    |--------------------------------------------------------------------------
    | Set to false only in development/testing environments.
    */
    'enforce_limits' => (bool) env('SUBSCRIPTION_ENFORCE_LIMITS', true),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    | Seconds to cache resolved subscription status per organization.
    */
    'cache_ttl_seconds' => (int) env('SUBSCRIPTION_CACHE_TTL', 300),

];
```

---

### `app/Shared/Domain/Events/DomainEvent.php`

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Events;

use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * Base class for all domain events.
 *
 * Domain events represent facts that happened in the domain.
 * They are named in past tense: UserRegistered, AIRequestCompleted, etc.
 */
abstract class DomainEvent
{
    public readonly string            $eventId;
    public readonly DateTimeImmutable $occurredAt;

    public function __construct()
    {
        $this->eventId    = (string) Str::uuid();
        $this->occurredAt = new DateTimeImmutable();
    }

    /**
     * The canonical event name used for routing and serialization.
     * Convention: module.resource.verb_past — e.g., "identity.user.registered"
     */
    abstract public function eventName(): string;
}
```

---

### `app/Shared/Domain/ValueObjects/Email.php`

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Email value object.
 *
 * Guarantees:
 * - Always lowercase and trimmed
 * - Valid RFC email format
 * - Immutable
 */
final class Email
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                "The value '{$value}' is not a valid email address."
            );
        }

        $this->value = $normalized;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function domain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

---

### `app/Shared/Domain/ValueObjects/Money.php`

```php
<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Money value object using integer cents to avoid floating-point errors.
 *
 * Guarantees:
 * - Amount is always non-negative (use separate Debit/Credit semantics at domain level)
 * - Currency is always a 3-character ISO 4217 code in uppercase
 * - Immutable
 */
final class Money
{
    public readonly int    $amountCents;
    public readonly string $currency;

    public function __construct(int $amountCents, string $currency)
    {
        if ($amountCents < 0) {
            throw new InvalidArgumentException(
                "Money amount cannot be negative. Got: {$amountCents}"
            );
        }

        $currencyUpper = strtoupper(trim($currency));

        if (strlen($currencyUpper) !== 3) {
            throw new InvalidArgumentException(
                "Currency must be a 3-character ISO 4217 code. Got: '{$currency}'"
            );
        }

        $this->amountCents = $amountCents;
        $this->currency    = $currencyUpper;
    }

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountCents + $other->amountCents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountCents - $other->amountCents, $this->currency);
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amountCents > $other->amountCents;
    }

    public function isZero(): bool
    {
        return $this->amountCents === 0;
    }

    public function equals(self $other): bool
    {
        return $this->amountCents === $other->amountCents
            && $this->currency === $other->currency;
    }

    /**
     * Returns a human-readable string like "USD 12.50"
     */
    public function formatted(): string
    {
        return sprintf('%s %.2f', $this->currency, $this->amountCents / 100);
    }

    public function toFloat(): float
    {
        return $this->amountCents / 100;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot operate on different currencies: '{$this->currency}' and '{$other->currency}'"
            );
        }
    }
}
```

---

### `app/Shared/Infrastructure/Logging/StructuredLogger.php`

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use Illuminate\Support\Facades\Log;

/**
 * Structured logger for business and infrastructure events.
 *
 * Rules:
 * - Event names follow the convention: module.resource.verb_past
 * - Context values are sanitized to remove sensitive data
 * - Never log PII — always use IDs instead of emails, names, etc.
 */
final class StructuredLogger
{
    /** @var list<string> Keys whose values will be redacted */
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation',
        'token', 'access_token', 'refresh_token',
        'secret', 'api_key', 'client_secret',
        'authorization', 'x-api-key',
        'credit_card', 'card_number', 'cvv', 'cvc',
        'webhook_secret', 'hmac_secret', 'signing_secret',
    ];

    public static function info(string $event, array $context = []): void
    {
        Log::channel('structured')->info($event, self::sanitize($context));
    }

    public static function warning(string $event, array $context = []): void
    {
        Log::channel('structured')->warning($event, self::sanitize($context));
    }

    public static function error(string $event, array $context = []): void
    {
        Log::channel('structured')->error($event, self::sanitize($context));
    }

    public static function debug(string $event, array $context = []): void
    {
        Log::channel('structured')->debug($event, self::sanitize($context));
    }

    public static function critical(string $event, array $context = []): void
    {
        Log::channel('structured')->critical($event, self::sanitize($context));
    }

    /**
     * Recursively sanitize an array to redact values for sensitive keys.
     */
    private static function sanitize(array $context): array
    {
        array_walk_recursive($context, static function (mixed &$value, string|int $key): void {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, strict: true)) {
                $value = '[REDACTED]';
            }
        });

        return $context;
    }
}
```

---

### `app/Shared/Http/Middleware/ResolveTenantMiddleware.php`

```php
<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant context from the authenticated request.
 *
 * A tenant can be either:
 *   - An organization (identified by X-Organization-ID header or token claim)
 *   - A personal context (the authenticated user is the tenant)
 *
 * Implementation detail will be completed in Phase 2 (Identity & Auth)
 * and Phase 10 (Organizations). This stub establishes the middleware
 * position in the pipeline.
 */
final class ResolveTenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Phase 2 / Phase 10 will implement:
        //   1. Extract organization context from token or X-Organization-ID header
        //   2. Verify requesting user is a member of that organization
        //   3. Bind CurrentTenant to the service container for this request
        //   4. Apply TenantScope globally to all tenant-scoped models

        return $next($request);
    }
}
```

---

### `app/Shared/Http/Middleware/EnforceSubscriptionLimitsMiddleware.php`

```php
<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces subscription-based access controls and usage limits.
 *
 * Runs after authentication and tenant resolution.
 * Short-circuits the request with 402 or 429 if limits are exceeded.
 *
 * Implementation detail will be completed in Phase 6 (Usage & Limits)
 * and Phase 7 (Subscriptions). This stub establishes the middleware
 * position in the pipeline.
 */
final class EnforceSubscriptionLimitsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Phase 6 / Phase 7 will implement:
        //   1. Load current subscription for the resolved tenant
        //   2. Load plan features/limits for the subscription
        //   3. Check remaining quota for the requested resource
        //   4. Return 402 Payment Required if subscription is inactive
        //   5. Return 429 Too Many Requests if quota is exhausted

        return $next($request);
    }
}
```

---

### `app/Modules/AI/Application/Contracts/AIProviderPort.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\AI\Application\Contracts;

use App\Modules\AI\Domain\ValueObjects\AICompletionRequest;
use App\Modules\AI\Domain\ValueObjects\AICompletionResponse;
use App\Modules\AI\Domain\ValueObjects\CostEstimate;

/**
 * Port (interface) that the AI module's use cases depend on.
 *
 * Concrete adapters (OpenAIAdapter, AnthropicAdapter) implement this
 * interface in the Infrastructure layer. The domain never depends on
 * any concrete provider SDK.
 */
interface AIProviderPort
{
    /**
     * Execute a synchronous completion request.
     *
     * @throws \App\Modules\AI\Domain\Exceptions\AIProviderException on provider failure
     * @throws \App\Modules\AI\Domain\Exceptions\AIProviderTimeoutException on timeout
     * @throws \App\Modules\AI\Domain\Exceptions\AIProviderRateLimitException on rate limit
     */
    public function complete(AICompletionRequest $request): AICompletionResponse;

    /**
     * Estimate the cost of a request before execution.
     * The estimate is based on input tokens and a rough output estimate.
     * Always approximate unless the provider offers pre-computation.
     */
    public function estimateCost(AICompletionRequest $request): CostEstimate;

    /**
     * Determine whether this adapter supports the given model identifier.
     */
    public function supportsModel(string $model): bool;

    /**
     * Return the canonical provider name.
     * Examples: 'openai', 'anthropic'
     */
    public function providerName(): string;
}
```

---

### `app/Modules/AI/Domain/ValueObjects/AICompletionRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\AI\Domain\ValueObjects;

/**
 * Encapsulates a request to an AI completion provider.
 *
 * This is a pure value object with no dependency on any framework or provider SDK.
 * All provider adapters accept this object as input.
 */
final class AICompletionRequest
{
    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $metadata  Arbitrary caller metadata (e.g., user_id, request_id)
     */
    public function __construct(
        public readonly string  $model,
        public readonly array   $messages,
        public readonly float   $temperature  = 0.7,
        public readonly int     $maxTokens    = 1024,
        public readonly ?string $systemPrompt = null,
        public readonly bool    $stream       = false,
        public readonly array   $metadata     = [],
    ) {}

    /**
     * Estimate the number of input tokens (rough approximation).
     * Actual token counting requires provider-specific tokenizers.
     * ~4 characters per token is a common approximation.
     */
    public function estimatedInputTokens(): int
    {
        $text = $this->systemPrompt ?? '';
        foreach ($this->messages as $message) {
            $text .= ' ' . ($message['content'] ?? '');
        }

        return (int) ceil(strlen($text) / 4);
    }
}
```

---

### `app/Modules/AI/Domain/ValueObjects/AICompletionResponse.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\AI\Domain\ValueObjects;

/**
 * Encapsulates the normalized response from an AI completion provider.
 *
 * All provider adapters return this object, ensuring the use case
 * layer never needs to handle provider-specific response formats.
 */
final class AICompletionResponse
{
    public function __construct(
        public readonly string  $content,
        public readonly int     $promptTokens,
        public readonly int     $completionTokens,
        public readonly string  $provider,
        public readonly string  $model,
        public readonly float   $estimatedCostUsd,
        public readonly ?string $finishReason = null,
        /** @var array<string, mixed> Raw provider response for debugging/audit */
        public readonly array   $rawResponse  = [],
    ) {}

    public function totalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }

    public function wasCompleted(): bool
    {
        return $this->finishReason === 'stop';
    }

    public function wasTruncated(): bool
    {
        return $this->finishReason === 'length';
    }
}
```

---

### `app/Modules/AI/Domain/ValueObjects/CostEstimate.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\AI\Domain\ValueObjects;

/**
 * An estimated cost for an AI request before execution.
 * Always approximate unless the provider supports pre-computation.
 */
final class CostEstimate
{
    public function __construct(
        public readonly float  $estimatedCostUsd,
        public readonly int    $estimatedInputTokens,
        public readonly int    $estimatedOutputTokens,
        public readonly string $provider,
        public readonly string $model,
        public readonly bool   $isApproximate = true,
    ) {}

    public function estimatedTotalTokens(): int
    {
        return $this->estimatedInputTokens + $this->estimatedOutputTokens;
    }
}
```

---

### `app/Modules/Billing/Application/Contracts/PaymentGatewayPort.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Contracts;

use App\Modules\Billing\Application\DTOs\ExternalSubscription;
use App\Modules\Billing\Application\DTOs\PaymentIntent;
use App\Modules\Billing\Application\DTOs\PaymentRequest;
use App\Modules\Billing\Application\DTOs\PaymentResult;
use App\Modules\Billing\Application\DTOs\RefundResult;
use App\Modules\Billing\Application\DTOs\SubscriptionRequest;
use App\Modules\Billing\Application\DTOs\WebhookEvent;

/**
 * Port (interface) that billing use cases depend on for payment operations.
 *
 * Concrete adapters (StripeAdapter, PaymobAdapter) implement this
 * interface in the Infrastructure layer.
 */
interface PaymentGatewayPort
{
    /**
     * Create a payment intent on the provider side.
     * Returns a client-secret or redirect URL the frontend uses to complete payment.
     *
     * @throws \App\Modules\Billing\Domain\Exceptions\PaymentGatewayException
     */
    public function createPaymentIntent(PaymentRequest $request): PaymentIntent;

    /**
     * Confirm a previously created payment intent.
     *
     * @throws \App\Modules\Billing\Domain\Exceptions\PaymentGatewayException
     */
    public function confirmPayment(string $intentId, array $data = []): PaymentResult;

    /**
     * Issue a full or partial refund for a completed payment.
     * Pass null for $amountCents to refund the full amount.
     *
     * @throws \App\Modules\Billing\Domain\Exceptions\PaymentGatewayException
     * @throws \App\Modules\Billing\Domain\Exceptions\RefundNotPossibleException
     */
    public function refund(string $paymentId, ?int $amountCents = null): RefundResult;

    /**
     * Create a recurring subscription on the provider side.
     *
     * @throws \App\Modules\Billing\Domain\Exceptions\PaymentGatewayException
     */
    public function createSubscription(SubscriptionRequest $request): ExternalSubscription;

    /**
     * Cancel a subscription on the provider side.
     * If $immediately is false, cancels at the end of the billing period.
     *
     * @throws \App\Modules\Billing\Domain\Exceptions\PaymentGatewayException
     */
    public function cancelSubscription(string $externalId, bool $immediately = false): void;

    /**
     * Verify the webhook signature and parse the raw payload into a WebhookEvent.
     *
     * @throws \App\Modules\Billing\Domain\Exceptions\WebhookSignatureException on invalid signature
     */
    public function constructWebhookEvent(string $payload, string $signature): WebhookEvent;

    /**
     * Return the canonical gateway name. Examples: 'stripe', 'paymob'
     */
    public function gatewayName(): string;
}
```

---

### `app/Modules/Billing/Application/DTOs/PaymentRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\DTOs;

use App\Shared\Domain\ValueObjects\Money;

final class PaymentRequest
{
    public function __construct(
        public readonly Money   $amount,
        public readonly string  $currency,
        public readonly string  $customerId,
        public readonly string  $description,
        public readonly ?string $paymentMethodId   = null,
        public readonly ?string $idempotencyKey    = null,
        public readonly array   $metadata          = [],
    ) {}
}
```

---

### `app/Modules/Billing/Application/DTOs/PaymentIntent.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\DTOs;

final class PaymentIntent
{
    public function __construct(
        public readonly string  $intentId,
        public readonly string  $clientSecret,
        public readonly string  $status,
        public readonly int     $amountCents,
        public readonly string  $currency,
        public readonly string  $gateway,
        public readonly array   $metadata = [],
    ) {}
}
```

---

### `app/Modules/Billing/Application/DTOs/PaymentResult.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\DTOs;

final class PaymentResult
{
    public function __construct(
        public readonly string  $paymentId,
        public readonly string  $status,
        public readonly int     $amountCents,
        public readonly string  $currency,
        public readonly string  $gateway,
        public readonly bool    $successful,
        public readonly ?string $failureReason = null,
        public readonly array   $metadata      = [],
    ) {}
}
```

---

### `app/Modules/Billing/Application/DTOs/RefundResult.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\DTOs;

final class RefundResult
{
    public function __construct(
        public readonly string  $refundId,
        public readonly string  $originalPaymentId,
        public readonly int     $amountCents,
        public readonly string  $currency,
        public readonly string  $status,
        public readonly string  $gateway,
    ) {}
}
```

---

### `app/Modules/Billing/Application/DTOs/SubscriptionRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\DTOs;

final class SubscriptionRequest
{
    public function __construct(
        public readonly string  $customerId,
        public readonly string  $planExternalId,
        public readonly ?string $paymentMethodId = null,
        public readonly ?int    $trialDays       = null,
        public readonly array   $metadata        = [],
    ) {}
}
```

---

### `app/Modules/Billing/Application/DTOs/ExternalSubscription.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\DTOs;

use DateTimeImmutable;

final class ExternalSubscription
{
    public function __construct(
        public readonly string            $externalId,
        public readonly string            $status,
        public readonly string            $gateway,
        public readonly DateTimeImmutable $currentPeriodStart,
        public readonly DateTimeImmutable $currentPeriodEnd,
        public readonly ?DateTimeImmutable $trialEnd = null,
        public readonly array             $metadata  = [],
    ) {}
}
```

---

### `app/Modules/Billing/Application/DTOs/WebhookEvent.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\DTOs;

final class WebhookEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly string $gateway,
        public readonly array  $payload,
        public readonly array  $rawData = [],
    ) {}
}
```

---

### `app/Modules/Billing/Domain/Exceptions/WebhookSignatureException.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Exceptions;

use RuntimeException;

final class WebhookSignatureException extends RuntimeException
{
    public static function invalidSignature(string $gateway): self
    {
        return new self("Invalid webhook signature from gateway: {$gateway}");
    }

    public static function missingSignature(string $gateway): self
    {
        return new self("Missing webhook signature header from gateway: {$gateway}");
    }
}
```

---

### Module Service Providers

Each of the 9 modules gets its own service provider. Shown in full for Identity; the rest follow the same pattern.

#### `app/Modules/Identity/Infrastructure/Providers/IdentityServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Phase 2: bind repository interfaces, OAuth adapters, etc.
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/identity'));
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        Route::middleware('api')
            ->prefix('api/v1')
            ->name('identity.')
            ->group(base_path('routes/modules/identity.php'));
    }
}
```

#### `app/Modules/Organizations/Infrastructure/Providers/OrganizationsServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Organizations\Infrastructure\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class OrganizationsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/organizations'));
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/v1/organizations')
            ->name('organizations.')
            ->group(base_path('routes/modules/organizations.php'));
    }
}
```

#### `app/Modules/AI/Infrastructure/Providers/AIServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\AI\Infrastructure\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Phase 3: bind AIProviderPort → concrete adapter based on config
        // $this->app->bind(AIProviderPort::class, fn() => ...);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/ai'));
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/v1/ai')
            ->name('ai.')
            ->group(base_path('routes/modules/ai.php'));
    }
}
```

#### `app/Modules/Usage/Infrastructure/Providers/UsageServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Usage\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

final class UsageServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/usage'));
    }
}
```

#### `app/Modules/Subscriptions/Infrastructure/Providers/SubscriptionsServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Infrastructure\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class SubscriptionsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/subscriptions'));
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/v1/subscriptions')
            ->name('subscriptions.')
            ->group(base_path('routes/modules/subscriptions.php'));
    }
}
```

#### `app/Modules/Billing/Infrastructure/Providers/BillingServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Phase 8: bind PaymentGatewayPort → configured adapter
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/billing'));
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        Route::middleware('api')
            ->prefix('api/v1')
            ->name('billing.')
            ->group(base_path('routes/modules/billing.php'));
    }
}
```

#### `app/Modules/Webhooks/Infrastructure/Providers/WebhooksServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

final class WebhooksServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/webhooks'));
    }
}
```

#### `app/Modules/Notifications/Infrastructure/Providers/NotificationsServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

final class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void {}
}
```

#### `app/Modules/Audit/Infrastructure/Providers/AuditServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/audit'));
    }
}
```

---

### `app/Providers/AppServiceProvider.php` (full replacement)

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\AI\Infrastructure\Providers\AIServiceProvider;
use App\Modules\Audit\Infrastructure\Providers\AuditServiceProvider;
use App\Modules\Billing\Infrastructure\Providers\BillingServiceProvider;
use App\Modules\Identity\Infrastructure\Providers\IdentityServiceProvider;
use App\Modules\Notifications\Infrastructure\Providers\NotificationsServiceProvider;
use App\Modules\Organizations\Infrastructure\Providers\OrganizationsServiceProvider;
use App\Modules\Subscriptions\Infrastructure\Providers\SubscriptionsServiceProvider;
use App\Modules\Usage\Infrastructure\Providers\UsageServiceProvider;
use App\Modules\Webhooks\Infrastructure\Providers\WebhooksServiceProvider;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * All module service providers are registered here.
     *
     * This is the single registration point for all bounded contexts.
     * Adding a new module requires only registering its provider here.
     */
    private array $moduleProviders = [
        IdentityServiceProvider::class,
        OrganizationsServiceProvider::class,
        AIServiceProvider::class,
        UsageServiceProvider::class,
        SubscriptionsServiceProvider::class,
        BillingServiceProvider::class,
        WebhooksServiceProvider::class,
        NotificationsServiceProvider::class,
        AuditServiceProvider::class,
    ];

    public function register(): void
    {
        foreach ($this->moduleProviders as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        //
    }
}
```

---

### `bootstrap/app.php` (add middleware aliases)

Add to the existing `bootstrap/app.php` after the `withRouting()` call:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'tenant'             => \App\Shared\Http\Middleware\ResolveTenantMiddleware::class,
        'subscription.limits' => \App\Shared\Http\Middleware\EnforceSubscriptionLimitsMiddleware::class,
    ]);
})
```

---

### `config/logging.php` — add structured channel

Add to the `channels` array:

```php
'structured' => [
    'driver'         => 'daily',
    'path'           => storage_path('logs/structured.log'),
    'level'          => env('LOG_LEVEL', 'debug'),
    'days'           => 30,
    'formatter'      => Monolog\Formatter\JsonFormatter::class,
    'formatter_with' => [],
],
```

---

### Route Stub Files

Each is a placeholder that will be populated in the relevant phase.

#### `routes/modules/identity.php`
```php
<?php

use Illuminate\Support\Facades\Route;

// Phase 2 — Identity & Authentication routes
// POST /api/v1/auth/register
// POST /api/v1/auth/login
// POST /api/v1/auth/refresh
// POST /api/v1/auth/logout
// GET  /api/v1/auth/oauth/{provider}/redirect
// GET  /api/v1/auth/oauth/{provider}/callback
// POST /api/v1/auth/email/verify/{id}/{hash}
// POST /api/v1/auth/password/forgot
// POST /api/v1/auth/password/reset

Route::get('/health', fn () => response()->json(['module' => 'identity', 'status' => 'ok']));
```

#### `routes/modules/organizations.php`
```php
<?php

use Illuminate\Support\Facades\Route;

// Phase 10 — Organizations routes

Route::get('/health', fn () => response()->json(['module' => 'organizations', 'status' => 'ok']));
```

#### `routes/modules/ai.php`
```php
<?php

use Illuminate\Support\Facades\Route;

// Phase 4/5 — AI routes

Route::get('/health', fn () => response()->json(['module' => 'ai', 'status' => 'ok']));
```

#### `routes/modules/subscriptions.php`
```php
<?php

use Illuminate\Support\Facades\Route;

// Phase 7 — Subscriptions routes

Route::get('/health', fn () => response()->json(['module' => 'subscriptions', 'status' => 'ok']));
```

#### `routes/modules/billing.php`
```php
<?php

use Illuminate\Support\Facades\Route;

// Phase 8/9 — Billing & Payment routes
// Webhook routes are intentionally outside auth:sanctum middleware

Route::get('/health', fn () => response()->json(['module' => 'billing', 'status' => 'ok']));
```

---

### `.env.example` additions

Append to the default Laravel `.env.example`:

```dotenv
# ─── Application ─────────────────────────────────────────────────────────────
APP_PORT=8080
DB_PORT_FORWARD=5432
REDIS_PORT_FORWARD=6379

# ─── Authentication ───────────────────────────────────────────────────────────
# Token TTLs
ACCESS_TOKEN_TTL_MINUTES=60
REFRESH_TOKEN_TTL_DAYS=30

# ─── OAuth Providers ──────────────────────────────────────────────────────────
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/api/v1/auth/oauth/google/callback"

GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI="${APP_URL}/api/v1/auth/oauth/github/callback"

FACEBOOK_CLIENT_ID=
FACEBOOK_CLIENT_SECRET=
FACEBOOK_REDIRECT_URI="${APP_URL}/api/v1/auth/oauth/facebook/callback"

LINKEDIN_CLIENT_ID=
LINKEDIN_CLIENT_SECRET=
LINKEDIN_REDIRECT_URI="${APP_URL}/api/v1/auth/oauth/linkedin/callback"

MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
MICROSOFT_REDIRECT_URI="${APP_URL}/api/v1/auth/oauth/microsoft/callback"

# ─── AI Providers ─────────────────────────────────────────────────────────────
AI_DEFAULT_PROVIDER=openai
AI_FALLBACK_ENABLED=false
AI_FALLBACK_PROVIDER=anthropic
AI_QUEUE=ai
AI_JOB_TIMEOUT=120
AI_CIRCUIT_BREAKER_THRESHOLD=5
AI_CIRCUIT_BREAKER_RECOVERY=60

OPENAI_API_KEY=
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_TIMEOUT_SECONDS=60
OPENAI_MAX_RETRIES=3

ANTHROPIC_API_KEY=
ANTHROPIC_BASE_URL=https://api.anthropic.com
ANTHROPIC_TIMEOUT_SECONDS=60
ANTHROPIC_MAX_RETRIES=3

# ─── Payment Gateways ─────────────────────────────────────────────────────────
PAYMENT_DEFAULT_GATEWAY=stripe
PAYMENT_IDEMPOTENCY_TTL_HOURS=24
PAYMENT_WEBHOOK_QUEUE=critical
PAYMENT_WEBHOOK_MAX_RETRIES=3
PAYMENT_WEBHOOK_RETRY_DELAY=10

STRIPE_SECRET_KEY=
STRIPE_PUBLISHABLE_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_API_VERSION=2024-04-10

PAYMOB_API_KEY=
PAYMOB_INTEGRATION_ID=
PAYMOB_IFRAME_ID=
PAYMOB_HMAC_SECRET=

# ─── Subscriptions ────────────────────────────────────────────────────────────
SUBSCRIPTION_TRIAL_DAYS=14
SUBSCRIPTION_GRACE_PERIOD_DAYS=3
SUBSCRIPTION_ENFORCE_LIMITS=true
SUBSCRIPTION_CACHE_TTL=300
```

---

## 4. Verification Plan

After implementation, the following must pass before Phase 2 begins:

### Automated

```bash
# 1. Container startup — all 5 services must reach healthy state
docker compose up -d
docker compose ps

# 2. PHP can connect to Postgres and Redis
docker compose exec app php artisan tinker --execute="DB::connection()->getPdo(); echo 'DB OK';"
docker compose exec app php artisan tinker --execute="Redis::ping(); echo 'Redis OK';"

# 3. Autoload resolves all new namespaces
docker compose exec app php artisan tinker --execute="new App\Shared\Domain\ValueObjects\Email('test@example.com'); echo 'OK';"
docker compose exec app php artisan tinker --execute="new App\Shared\Domain\ValueObjects\Money(1000, 'USD'); echo 'OK';"

# 4. Module routes are registered
docker compose exec app php artisan route:list | grep health

# 5. Run default tests
docker compose exec app php artisan test

# 6. Application key and config cache
docker compose exec app php artisan key:generate
docker compose exec app php artisan config:clear
```

### Manual

- Visit `http://localhost:8080/api/v1/health` — expect 200 from identity health route
- Confirm `storage/logs/structured.log` is created on first log write

---

## Open Questions

> [!IMPORTANT]
> Please confirm before execution:

1. **PHP version in Docker**: Using PHP **8.3-FPM-Alpine**. Is this confirmed?
2. **PostgreSQL version**: Using **PostgreSQL 16**. Any preference for 15?
3. **Redis single instance**: As agreed — single Redis with `noeviction` policy. Queue uses DB 0, cache DB 1. Confirm this is acceptable for Phase 1.
4. **Laravel Sanctum**: `composer require laravel/sanctum` will be run as part of Phase 1 since it's foundational to the authentication architecture decided in Phase 0.
5. **Pest vs PHPUnit**: Proposing **Pest** as the test runner (installed with `pestphp/pest` + `pestphp/pest-plugin-laravel`). Confirm or override.
