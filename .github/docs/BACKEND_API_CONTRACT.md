# Backend API Contract

This document defines the Android-facing contract for the Laravel backend during Phase 1. Android should treat this as the source of truth for request and response handling.

## Transport

- Base path: `/api`
- Content type: `application/json`
- Phone number format: E.164, for example `+421900123456`
- Time format: ISO 8601 UTC timestamps

## Response Envelope

All API responses use a stable envelope.

### Success

```json
{
    "success": true,
    "code": "auth.initiated",
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

Requests a one-hour installation password for a phone number.

### Request

```json
{
    "phone_number": "+421900123456"
}
```

### Success `201 Created`

```json
{
    "success": true,
    "code": "auth.initiated",
    "data": {
        "phone_number": "+421900123456",
        "password": "ABCD2345EF",
        "expires_at": "2026-03-31T13:00:00+00:00"
    },
    "meta": {}
}
```

### Failure `422 Unprocessable Entity`

Example invalid phone number:

```json
{
    "success": false,
    "code": "validation_failed",
    "message": "The request could not be processed.",
    "errors": {
        "phone_number": ["The phone number field format is invalid."]
    },
    "meta": {}
}
```

## POST /api/auth/verify

Attempts to complete the device-binding handshake.

### Request

```json
{
    "phone_number": "+421900123456",
    "password": "ABCD2345EF",
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
        "phone_number": "+421900123456",
        "verified_at": "2026-03-31T12:10:00+00:00"
    },
    "meta": {}
}
```

### Failure `422 Unprocessable Entity`

Example expired or invalid password:

```json
{
    "success": false,
    "code": "validation_failed",
    "message": "The request could not be processed.",
    "errors": {
        "password": ["The provided password is invalid or expired."]
    },
    "meta": {}
}
```

Example invalid device signature:

```json
{
    "success": false,
    "code": "validation_failed",
    "message": "The request could not be processed.",
    "errors": {
        "signature": ["The provided device signature is invalid."]
    },
    "meta": {}
}
```

Example ad verification failure:

```json
{
    "success": false,
    "code": "escort_ad_not_verified",
    "message": "No active escort advertisement could be verified for this phone number.",
    "errors": {
        "phone_number": [
            "No active escort advertisement could be verified for this phone number."
        ]
    },
    "meta": {
        "retryable": false
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

## Android Handshake Notes

These rules are part of the contract. Android should not infer them.

### Public Key Format

- `public_key` must be sent as a PEM-encoded public key string.
- The backend normalizes PEM line endings from `\r\n` to `\n`.
- The backend trims leading and trailing whitespace around the PEM string before verification.
- Android should apply the same normalization before generating the signature payload to avoid signing a different byte sequence than Laravel verifies.

### Canonical Payload To Sign

Laravel verifies the signature against a JSON string with exactly these fields and this order:

```json
{
    "phone_number": "+421900123456",
    "public_key": "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"
}
```

The payload is produced as compact JSON with no pretty printing and no extra whitespace. The backend equivalent is:

```php
json_encode([
    'phone_number' => $phoneNumber,
    'public_key' => $normalizedPublicKey,
], JSON_THROW_ON_ERROR);
```

Android should sign the UTF-8 bytes of that exact compact JSON string.

### Signature Encoding

- The private key signs the canonical JSON payload.
- The backend verifies with `OPENSSL_ALGO_SHA256`.
- Android should use the equivalent SHA-256 signature algorithm supported by the chosen key type.
- The binary signature output must be Base64-encoded before sending as `signature`.

### Local Development Base URL

- Android emulator to local Laravel in WSL/host usually uses `http://10.0.2.2:8000/api`.
- A real Android device should use the host machine LAN IP and reachable port, for example `http://192.168.x.x:8000/api`.
- `http://127.0.0.1` from Android points to the Android device or emulator itself, not the Laravel host.

### Android Error Handling Notes

- Treat `errors` as an object in all cases. It may be empty for non-validation domain failures.
- Treat `escort_portal_timeout` and `escort_portal_unavailable` as retryable when `meta.retryable` is `true`.
- Treat `escort_ad_not_verified` as a hard stop for the current verification attempt unless the user changes the underlying state.

## Android Client Guidance

- Parse `success` first.
- Route behavior by `code` second.
- Display field-specific messages from `errors`.
- Treat `data.password` as sensitive and avoid long-term storage.
- Treat `expires_at` as server authority.
- Respect `meta.retryable` when deciding whether Android should offer retry vs. a hard stop.
