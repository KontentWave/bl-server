# Backend API Contract

This document defines the Android-facing contract for the Laravel backend during Phase 1. Android should treat this as the source of truth for request and response handling.

This document supersedes the earlier phone-number-plus-password handshake. The new Phase 1 contract is ad-URL plus SMS OTP plus hardware-bound signature.

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
    "ad_url": "https://www.eurogirlsescort.com/escort/miriam/..."
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

### UI Inputs

- The Android UI should expose only the escort ad URL field during initiation.
- The Android UI should not expose a free phone-number field.
- After initiation succeeds, the Android UI should move to OTP entry and optionally display only the masked phone number returned by the backend.

### Public Key Format

- `public_key` must be sent as a PEM-encoded public key string.
- The backend normalizes PEM line endings from `\r\n` to `\n`.
- The backend trims leading and trailing whitespace around the PEM string before verification.
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

### Signature Encoding

- The private key signs the canonical JSON payload.
- The backend verifies with SHA-256.
- Android should use the equivalent SHA-256 signature algorithm supported by the chosen key type.
- The binary signature output must be Base64-encoded before sending as `signature`.

### Local Development Base URL

- Android emulator to local Laravel in WSL/host usually uses `http://10.0.2.2:8000/api`.
- A real Android device should use the host machine LAN IP and reachable port, for example `http://192.168.x.x:8000/api`.
- `http://127.0.0.1` from Android points to the Android device or emulator itself, not the Laravel host.

### Android Error Handling Notes

- Treat `errors` as an object in all cases. It may be empty for non-validation domain failures.
- Treat `escort_portal_timeout` and `escort_portal_unavailable` as retryable when `meta.retryable` is `true`.
- Treat `phone_extraction_failed`, `signature_invalid`, `otp_invalid_or_expired`, and `challenge_not_found` as hard stops for the current verification attempt unless the user starts a new challenge.

## Android Client Guidance

- Parse `success` first.
- Route behavior by `code` second.
- Display field-specific messages from `errors`.
- Treat `data.challenge_id` as sensitive challenge state and avoid persisting it longer than necessary.
- Treat `otp_expires_at` as server authority.
- Respect `meta.retryable` when deciding whether Android should offer retry vs. a hard stop.
