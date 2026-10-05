# Backend API Contract

This document defines the Android-facing contract for the Laravel backend across the current MVP phases. Android should treat this as the source of truth for request and response handling.

This document supersedes the earlier phone-number-plus-password handshake. The current verification contract is ad-URL plus SMS OTP plus hardware-bound signature. Phase 5 scraper hardening changes the backend implementation behind `POST /api/auth/initiate`, but it does not change the Android-facing request or response shape.

The same response schema remains unchanged when the hosted backend is running in the temporary Vonage-trial integration mode documented in ADR 5. Android should continue to parse the normal success and failure envelopes and should not branch on whether OTP routing is currently live-scraped or temporarily redirected to a verified test number.

## Transport

- Base path: `/api`
- Content type: `application/json`
- Android client input: escort ad URL only
- Phone number format on the backend: E.164, for example `+421900123456`
- Time format: ISO 8601 UTC timestamps

## Response Envelope

All API responses use a stable envelope.

### Success

```json
{
    "success": true,
    "code": "auth.sms_initiated",
    "data": {},
    "meta": {}
}
```

### Validation or Domain Failure

```json
{
    "success": false,
    "code": "validation_failed",
    "message": "The request could not be processed.",
    "errors": {
        "field_name": ["Human readable error message"]
    },
    "meta": {}
}
```

Android should treat `errors` as authoritative for field-specific failures.

## Beta URL and Abuse Controls

These controls require an approved deployment before they apply to the hosted beta. They do not establish overall beta readiness or prove SMS delivery.

### Ad URL and parsing policy

- Only HTTPS URLs on `amaterky.sk`, `www.amaterky.sk`, `eurogirlsescort.com` and `www.eurogirlsescort.com` are accepted. Arbitrary subdomains, URL credentials and non-443 ports are rejected with `invalid_ad_url`.
- The HTTP fetcher rejects non-public IPv4/IPv6 DNS answers and pins the connection to a validated address, including proxy tunnels. TLS hostname/certificate verification remains enabled. DNS failure fails closed with `escort_portal_unavailable`.
- Redirects are not followed, even to another supported host. Submit the canonical HTTPS ad URL; live portal/proxy compatibility still needs approved verification.
- The synthetic `portal.example.test` host is accepted only by initiation in `fixture` mode in `local` or `testing`, never by the HTTP fetcher.
- Supported portals use their dedicated phone extractors without a generic visible-number fallback. Host case does not bypass disabled-ad checks.
- `ad_url` is limited to 2048 characters. Public keys are limited to 8192 characters and signatures to 4096 characters on all signed endpoints. Oversized fields return `validation_failed` with HTTP 422 before cryptographic parsing.

### Default limits

Values are configurable through `.env.example` and `config/security.php`, and bounded to at least one. IP limits use the server-resolved address, not an arbitrary forwarded header.

| Scope                                        | Default                                                                |
| -------------------------------------------- | ---------------------------------------------------------------------- |
| All API requests per IP                      | 60 per minute                                                          |
| Initiation requests per IP                   | 3 per minute and 10 per hour                                           |
| Verification requests per IP                 | 10 per minute                                                          |
| Verification requests per existing challenge | 5 until challenge expiry, including invalid OTP/key/signature attempts |
| SMS resend per recipient                     | 60-second cooldown                                                     |
| SMS attempts per recipient                   | 3 per hour and 5 per day                                               |
| SMS attempts across the application          | 20 per hour and 100 per day                                            |

Windows have fixed TTLs from their first reservation/request, not calendar-day or sliding-window boundaries. SMS limits count dispatch reservations, not currency or confirmed deliveries. Reservations are serialized through a shared cache lock before challenge replacement and sending. They are not refunded after provider failures; partially reserved requests can consume capacity conservatively. API middleware limits scraper load even when an ad yields no phone. Per-recipient limits cover different ad URLs/IPs resolving to the same phone, and global limits cover different recipients.

### HTTP 429

All API and OTP/SMS limit failures use `rate_limited`, a `Retry-After` header in seconds, and this envelope:

```json
{
    "success": false,
    "code": "rate_limited",
    "message": "Too many requests. Please try again later.",
    "errors": [],
    "meta": { "retryable": true, "retry_after": 60 }
}
```

The current empty-error serialization is `[]`, not the object used by some older examples; the pre-existing global envelope mismatch is not corrected in this slice. Android compatibility with the actual envelope and the new limit states must be checked before rollout. Display the retry delay and avoid automatic SMS resend, especially after an ambiguous transport failure. A blocked resend does not return a new challenge or replace the existing one. After a challenge exhausts its attempt budget, request a new challenge when resend limits permit rather than repeatedly submitting the same OTP. Cache-lock contention also returns 429 with a short retry delay.

### Operator requirements

- Outside `local`/`testing`, `CACHE_LIMITER` must select a shared `database` or `redis` store with working locks; other drivers fail closed with HTTP 503 and `abuse_protection_unavailable`. Provision the existing cache/lock tables for database storage. All workers must share the store, key prefix and application key. Redis must preserve counters for their TTL; eviction, cache flushing or isolated per-worker stores invalidate the budget guarantees.
- The portal fetcher requires PHP cURL supporting `CURLOPT_CONNECT_TO` (libcurl 7.49+). Verify the actual hosted PHP transport, DNS resolution, HTTPS proxy tunnel and portal URLs before release. Missing pinning support fails closed; do not disable TLS verification to make a test pass.
- Configure trusted reverse proxies narrowly and enforce web-server request/body limits. Set production/debug/scraper/recipient-override flags as required by the approved beta deployment; this code does not change hosted settings.
- OTP validation, binding mutation and challenge consumption run in one database transaction. Verification and resend lock by the same phone index; verification rechecks the requested challenge ID under that lock. Both transactions permit at most three attempts for database concurrency errors. Verification-attempt reservation is outside the transaction so database-backed counters survive rejected verification/rollback; SMS dispatch is outside the resend transaction and is not retried by it. Request fields, signatures, success/failure envelopes and replacement-before-SMS semantics remain unchanged. The separate [MariaDB test runner](../../scripts/test-mariadb.php) exercises genuine local overlap on MariaDB 11.4 with `REPEATABLE-READ` and `READ-COMMITTED`; this does not establish the deployed isolation/configuration or hosted readiness. Local reporting serialization is described below; publication/deployment, dependency advisories and the other beta gates remain separate.

## POST /api/auth/initiate

Starts the SMS verification flow from an escort ad URL.

### Request

```json
{
    "ad_url": "https://amaterky.sk/32116"
}
```

### Success `201 Created`

```json
{
    "success": true,
    "code": "auth.sms_initiated",
    "data": {
        "challenge_id": "0d5f35ea-331d-4df1-b9d6-3df7ab7fdc7c",
        "masked_phone_number": "+421***456",
        "otp_expires_at": "2026-04-01T12:15:00+00:00"
    },
    "meta": {}
}
```

### Failure `400 Bad Request`

Example invalid or unsupported ad URL:

```json
{
    "success": false,
    "code": "invalid_ad_url",
    "message": "The provided ad URL is invalid or unsupported.",
    "errors": {
        "ad_url": ["The provided ad URL is invalid or unsupported."]
    },
    "meta": {}
}
```

Example ad did not yield a usable phone number:

```json
{
    "success": false,
    "code": "phone_extraction_failed",
    "message": "The system could not extract a valid phone number from the supplied ad.",
    "errors": {
        "ad_url": [
            "The system could not extract a valid phone number from the supplied ad."
        ]
    },
    "meta": {
        "retryable": false
    }
}
```

Example ad is temporarily disabled by its owner:

```json
{
    "success": false,
    "code": "ad_temporarily_disabled",
    "message": "The provided ad is temporarily disabled by its owner.",
    "errors": {
        "ad_url": ["The provided ad is temporarily disabled by its owner."]
    },
    "meta": {
        "retryable": false,
        "ad_state": "temporarily_disabled"
    }
}
```

Example escort portal timeout:

```json
{
    "success": false,
    "code": "escort_portal_timeout",
    "message": "Escort portal verification timed out.",
    "errors": {},
    "meta": {
        "retryable": true
    }
}
```

Example escort portal unavailable:

```json
{
    "success": false,
    "code": "escort_portal_unavailable",
    "message": "Escort portal verification is currently unavailable.",
    "errors": {},
    "meta": {
        "retryable": true,
        "upstream_status": 502
    }
}
```

### Current backend implementation notes

- The current live-validated production scraper path is `amaterky.sk`.
- The backend currently routes live portal fetches through the rotating proxy configuration in `config/scraping.php` when enabled.
- For `amaterky.sk`, extraction currently prefers `tel:` links, then `sms:` links, then the nearby contact heading.
- For `amaterky.sk`, the backend also recognizes the disabled-ad heading `Vypnutý zadávateľom` and classifies it separately from a generic extraction failure.
- The backend only proceeds to phone extraction when the ad is in a phone-bearing state. A temporarily disabled ad is treated as a valid ad-state classification but a hard stop for the current initiate attempt.
- Android should treat these as backend implementation details and continue to rely only on the documented success and failure envelopes.

## POST /api/auth/verify

Attempts to complete the OTP and device-binding handshake.

### Request

```json
{
    "challenge_id": "0d5f35ea-331d-4df1-b9d6-3df7ab7fdc7c",
    "otp": "123456",
    "public_key": "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----",
    "signature": "base64-signature"
}
```

### Success `200 OK`

```json
{
    "success": true,
    "code": "auth.verified",
    "data": {
        "challenge_id": "0d5f35ea-331d-4df1-b9d6-3df7ab7fdc7c",
        "masked_phone_number": "+421***456",
        "verified_at": "2026-03-31T12:10:00+00:00"
    },
    "meta": {}
}
```

### Failure `422 Unprocessable Entity`

Example expired or invalid OTP:

```json
{
    "success": false,
    "code": "otp_invalid_or_expired",
    "message": "The provided OTP is invalid or expired.",
    "errors": {
        "otp": ["The provided OTP is invalid or expired."]
    },
    "meta": {
        "retryable": false
    }
}
```

Example invalid device signature:

```json
{
    "success": false,
    "code": "signature_invalid",
    "message": "The provided device signature is invalid.",
    "errors": {
        "signature": ["The provided device signature is invalid."]
    },
    "meta": {
        "retryable": false
    }
}
```

Example unknown or already-used challenge:

```json
{
    "success": false,
    "code": "challenge_not_found",
    "message": "The verification challenge could not be found.",
    "errors": {
        "challenge_id": ["The verification challenge could not be found."]
    },
    "meta": {
        "retryable": false
    }
}
```

## Android Handshake Notes

These rules are part of the contract. Android should not infer them.

### UI Inputs

- The Android UI should expose only the escort ad URL field during initiation.
- The Android UI should not expose a free phone-number field.
- After initiation succeeds, the Android UI should move to OTP entry and optionally display only the masked phone number returned by the backend.

### Public Key Format

- `public_key` must be sent as a PEM-encoded public key string.
- The backend normalizes PEM line endings from `\r\n` to `\n`.
- The backend trims leading and trailing whitespace around the PEM string before verification.
- The backend also trims whitespace on each PEM line and removes blank PEM lines before payload creation and verification.
- Android should apply the same normalization before generating the signature payload to avoid signing a different byte sequence than Laravel verifies.

### Canonical Payload To Sign

Laravel verifies the signature against a JSON string with exactly these fields and this order:

```json
{
    "challenge_id": "0d5f35ea-331d-4df1-b9d6-3df7ab7fdc7c",
    "public_key": "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"
}
```

The payload is produced as compact JSON with no pretty printing and no extra whitespace. The backend equivalent is:

```php
json_encode([
    'challenge_id' => $challengeId,
    'public_key' => $normalizedPublicKey,
], JSON_THROW_ON_ERROR);
```

Android should sign the UTF-8 bytes of that exact compact JSON string.

Important interoperability note:

- Because Laravel/PHP `json_encode(...)` is used without `JSON_UNESCAPED_SLASHES`, forward slashes inside the PEM/base64 content are escaped as `\/` in the canonical JSON string.
- Android must mirror that behavior when constructing the signed payload or the backend will reject the signature as invalid even if the same key pair is used.

### Signature Encoding

- The private key signs the canonical JSON payload.
- The backend verifies with SHA-256.
- Android should use the equivalent SHA-256 signature algorithm supported by the chosen key type.
- The binary signature output must be Base64-encoded before sending as `signature`.

### Local Development Base URL

- Android emulator to local Laravel in WSL/host usually uses `http://10.0.2.2:8000/api`.
- A real Android device can use `http://127.0.0.1:8000/api` when it is connected over USB and `adb reverse tcp:8000 tcp:8000` is active.
- A real Android device without `adb reverse` should use the host machine LAN IP and reachable port, for example `http://192.168.x.x:8000/api`.
- `http://127.0.0.1` from Android points to the Android device or emulator itself, not the Laravel host.
- The `127.0.0.1` exception above applies only when traffic is intentionally tunneled through `adb reverse` from a USB-connected physical device.

### Android Error Handling Notes

- Treat `errors` as an object in all cases. It may be empty for non-validation domain failures.
- Treat `escort_portal_timeout` and `escort_portal_unavailable` as retryable when `meta.retryable` is `true`.
- Treat `phone_extraction_failed`, `ad_temporarily_disabled`, `signature_invalid`, `otp_invalid_or_expired`, and `challenge_not_found` as hard stops for the current verification attempt unless the user starts a new challenge.

## Android Client Guidance

- Parse `success` first.
- Route behavior by `code` second.
- Display field-specific messages from `errors`.
- Treat `data.challenge_id` as sensitive challenge state and avoid persisting it longer than necessary.
- Treat `otp_expires_at` as server authority.
- Respect `meta.retryable` when deciding whether Android should offer retry vs. a hard stop.

## POST /api/reports

Submits a new worker report into the zero-knowledge reporting pipeline.

### Request

```json
{
    "client_phone_number": "+421900123456",
    "feature": "no_show",
    "public_key": "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----",
    "signature": "base64-signature"
}
```

### Success `201 Created`

```json
{
    "success": true,
    "code": "report.created",
    "data": {
        "client_hash": "64-char-sha256",
        "reporter_hash": "64-char-sha256",
        "feature": "no_show",
        "feature_label": "No-Show",
        "unique_reporter_count": 1,
        "level": "level_1",
        "ready_for_sync": false
    },
    "meta": {}
}
```

### Canonical Payload To Sign

Laravel verifies the report signature against a compact JSON string with exactly these fields and this order:

```json
{
    "client_phone_number": "+421900123456",
    "feature": "no_show",
    "public_key": "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"
}
```

The backend equivalent is:

```php
json_encode([
    'client_phone_number' => $normalizedClientPhoneNumber,
    'feature' => $feature,
    'public_key' => $normalizedPublicKey,
], JSON_THROW_ON_ERROR);
```

### Failure Notes

- `validation_failed`: request fields are missing or invalid.
- `device_not_bound`: the provided public key is not bound to a verified worker.
- `signature_invalid`: the report signature does not match the canonical payload.
- `duplicate_report`: the same bound reporter has already submitted the same feature for the same client.
- `client_phone_number_invalid`: the client phone number could not be normalized into supported E.164 form.

### Atomicity and concurrent submissions

Local CB-07 implementation preserves the request, signature and success/error shapes; no Android wire change is required. Publication and deployment are separate approvals.

- Client creation, report insertion and materialized per-feature count/promotion commit or roll back together. Client-level database serialization protects first creation and concurrent reports; independent feature counts remain separate.
- With threshold three, the second and third distinct reporters return counts two and three in serialized order, leaving three report rows and Level 2. Successful counts describe that submission's committed transaction, not a promise that no later report has arrived.
- Racing identical client/reporter/feature submissions produce one report and the existing duplicate failure: HTTP **422**, `success: false`, `code: duplicate_report`, the existing message and `errors.feature`, and `meta: {retryable: false, feature: <key>}`. The reporter identity is still the bound phone hash, not the public key.
- The first `promoted_at` is retained. Signed blacklist checks expose only committed Level 2 state; uncommitted/rolled-back promotion is hidden.
- Database-only work permits at most three attempts for Laravel-classified concurrency errors. Signature/binding checks and external effects are not repeated by this transaction; non-concurrency or exhausted failures are not converted into success/duplicate responses. This is not an instruction for Android to replay a request automatically.
- Local MariaDB 11.4 tests exercise both isolation levels with independent signed HTTP workers and observed InnoDB lock waits. In-memory SQLite regressions, local HTTP workers and synthetic trigger failures do not certify the deployed engine, cache, workers, Android device or hosted behavior.

## POST /api/blacklist/check

Checks whether a target hash currently has any Level 2 features.

### Request

```json
{
    "target_hash": "64-char-sha256",
    "public_key": "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----",
    "signature": "base64-signature"
}
```

### Success `200 OK`

```json
{
    "success": true,
    "code": "blacklist.checked",
    "data": {
        "target_hash": "64-char-sha256",
        "features": ["Aggressive", "No-Show"]
    },
    "meta": {}
}
```

If the target is unknown or only has Level 1 reports, `features` is an empty array.

### Canonical Payload To Sign

Laravel verifies the blacklist-check signature against a compact JSON string with exactly these fields and this order:

```json
{
    "target_hash": "64-char-sha256",
    "public_key": "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"
}
```

The backend equivalent is:

```php
json_encode([
    'target_hash' => strtolower($targetHash),
    'public_key' => $normalizedPublicKey,
], JSON_THROW_ON_ERROR);
```

### Failure Notes

- `validation_failed`: the target hash is malformed.
- `blacklist_query_unauthorized`: the request is missing or has an invalid hardware-bound authorization signature.
