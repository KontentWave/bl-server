# Backend API Contract

This document defines the Android-facing contract for the Laravel backend across the current MVP phases. Android should treat this as the source of truth for request and response handling.

This document supersedes the earlier phone-number-plus-password handshake. The current verification contract is ad-URL plus SMS OTP plus hardware-bound signature. Phase 5 scraper hardening changes the backend implementation behind `POST /api/auth/initiate`, but it does not change the Android-facing request or response shape.

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
