<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Local PHP dependencies

The [manifest](composer.json) has root PHP constraint **`^8.4.1`**, matching the [resolved graph](composer.lock)'s minimum. The approved October 5 remediation aligns the root constraint with Symfony 8.1's actual minimum. Validate the complete graph on each intended runtime; only local PHP 8.4.12 was tested, and hosted PHP compatibility remains unverified.

For a local install without application-mutating Composer hooks or plugins:

```sh
composer install --no-interaction --no-plugins --no-scripts
composer validate --strict --no-check-publish --no-interaction --no-plugins --no-scripts
composer check-platform-reqs --lock --no-dev --no-interaction --no-plugins --no-scripts
composer audit --locked --no-dev --abandoned=report --no-interaction --no-plugins --no-scripts
```

The [readiness review](.github/docs/BETA_READINESS_REVIEW_2026-10-05.md#local-php-dependency-remediation) records exact updates, fresh zero-advisory production/complete audits, isolated regressions and remaining gates. No hooks, deployment or live SMS are authorized by these commands. A zero-advisory result is time-bound metadata, not overall closed-beta readiness.

## Isolated backend tests

The normal suite uses in-memory SQLite, regardless of inherited database settings, and rejects cached Laravel configuration:

```sh
DB_CONNECTION=sqlite DB_DATABASE=:memory: SMS_DRIVER=log \
SMS_LOG_OTP_IN_NON_PRODUCTION=false vendor/bin/phpunit --no-progress
```

For MariaDB 11.4 OTP and report concurrency checks, use a local Docker daemon, Docker Compose and PHP with `pdo_mysql`:

```sh
php scripts/test-mariadb.php
php scripts/test-mariadb.php --filter test_parallel_verifications_authorize_exactly_one_key
```

The [runner](scripts/test-mariadb.php) provisions a unique [test container](compose.mariadb-test.yaml) for each of `REPEATABLE-READ` and `READ-COMMITTED`. It uses a random loopback-only port, runtime-generated test credentials and temporary database storage, then removes its own container/network in a `finally` block. No existing SQLite server, local database, hosted service or persistent volume is changed.

The separate [MariaDB bootstrap](tests/bootstrap-mariadb.php) verifies container labels, port binding and temporary storage before allowing migrations. It forces a synthetic database/user, fixture portal extraction, log-only SMS with OTP logging disabled, and database-backed cache/locks. Tests reject stray HTTP requests. Existing configuration caches cause an explicit refusal, not an automatic cache deletion.

[OTP tests](tests/Database/OtpConcurrencyTest.php) boot independent PHP workers and require observed InnoDB lock waits for overlapping verification and both resend/verification orders. They also check binding/consumption rollback and persistent attempt accounting. [Report tests](tests/Database/ReportConcurrencyTest.php) submit signed requests through independently booted HTTP kernels, covering threshold crossing, stable duplicates, first creation, independent state, rollback, bounded retries and promotion visibility. The [shared lock helper](tests/Support/AssertsMariaDbLockWait.php) verifies actual overlap. Payloads travel over private process pipes, not command-line arguments or temporary files. These are local service/HTTP-kernel/database tests, not hosted web-server, live-SMS or Android end-to-end certification.

## Optional owner-phone enrollment

For an owner who cannot obtain a portal ad, a separate enrollment path skips **only
portal extraction**. It does not create an administrator role or bypass OTP,
device signatures, SMS budgets, IP limits, report thresholds or duplicate rules.
Existing ad-based enrollment is unchanged.

Disabled by default in [security configuration](config/security.php):

```dotenv
OWNER_ENROLLMENT_ENABLED=false
OWNER_PHONE_NUMBER=
```

To enable in an approved environment, supply the owner's number privately in
canonical E.164 form (`+` followed by 8-15 digits, no spaces, nonzero country-code
prefix), then enable the flag using the normal configuration/deployment process.
Never commit the real number or use the development recipient override for this.
Enabling this route does not configure SMS delivery or open HTTP admission.

### Additive API contract (Android coordination required)

- `POST /api/auth/owner/initiate`, JSON `{"phone_number":"<canonical E.164>"}`.
  Only the exact configured number is eligible; callers cannot select another
  recipient. Uses the same `throttle:api` and `throttle:auth-initiate` limiters as
  ad-based initiation, including shared cross-route IP/SMS budgets.
- Success is the existing **201 `auth.sms_initiated`** envelope: `success=true`,
  `data.challenge_id`, `data.masked_phone_number`, `data.otp_expires_at`, `meta={}`.
  No OTP or full phone is returned. This is SMS-adapter acceptance, not proof of
  delivery; production log-only SMS cannot complete real enrollment.
- Invalid input: **422 `validation_failed`**. Disabled or ineligible number:
  **403 `owner_enrollment_unavailable`**. Enabled with missing/malformed private
  configuration: **503 `owner_enrollment_unavailable`** and a sanitized operator
  error log. Both owner-unavailable errors use `meta.retryable=false`.
  Existing **429 `rate_limited`**, **503 `abuse_protection_unavailable`** and
  **503 `sms_dispatch_failed`** behavior applies.
- Continue with unchanged `POST /api/auth/verify`: `challenge_id`, received `otp`,
  the device's `public_key` and signature over the existing canonical challenge
  payload. OTP validity is 15 minutes with existing boundary semantics. Successful
  verification creates/replaces the normal device binding; report/query
  signatures remain mandatory.

The Kotlin/Compose client needs an explicit owner-phone entry option that calls
this endpoint, then reuses its existing OTP/device-signing and enrollment-state
flow. There is no new verification contract, static OTP or secret client token.
Do not hard-code the owner's number into the app. Client work and deployment are
separate from this local backend implementation.

Challenges share the existing unique-phone table and transaction/send lifecycle.
The `ad_url` field records the literal provenance marker `owner-enrollment`, not
a scraped or invented ad URL. Owner and ad challenges for the same phone replace
each other; budgets are not reset or refunded on replacement or uncertain sends.
Never automatically resend after a timeout or failed acceptance response.

Bindings currently have no automatic expiry and grant ordinary access only.
Keeping owner enrollment enabled allows later OTP-based enrollment on a new
device, replacing that phone's prior key under existing rules; it does not
guarantee irrevocable access or protect against number recycling/SIM takeover.
Disabling the flag stops new owner challenges, but does not revoke an existing
binding or an already-issued challenge (which retains normal expiry). Changing
the configured number does not remove the old binding. Such revocation/cleanup
must be handled explicitly, not by an automatic reset or migration.

No schema migration, automatic seed or special production phone is installed.
Local tests use synthetic numbers, in-memory SQLite and fake SMS/HTTP; they do not
establish hosted readiness, actual SMS receipt or physical-device acceptance.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
