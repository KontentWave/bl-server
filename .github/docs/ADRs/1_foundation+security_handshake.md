# ADR 1: Foundation and Security Handshake

- Status: accepted
- Date: 2026-04-02
- Scope: Phase 1 Laravel backend handshake

## Context

Phase 1 started from an earlier concept that used a phone number plus a temporary password as the pre-auth handshake between Android and Laravel. During implementation, that approach was rejected because the client-provided phone number and SIM-derived identity are not strong trust anchors for this project.

The backend now has to support a stricter flow:

- Android submits only an escort ad URL during initiation.
- Laravel fetches the ad server-side and extracts the phone number itself.
- Laravel sends a short-lived SMS OTP to the scraped phone number.
- Android proves possession of the OTP and submits a hardware-backed public key plus signature.
- Laravel verifies the OTP and signature before binding the device key.

This ADR records the backend decisions made so the implemented flow does not drift back toward the older phone/password model.

## Decision

The accepted Phase 1 handshake is ad-URL plus SMS OTP plus hardware-bound signature.

The Laravel backend is the source of truth for identity establishment. Android is a thin client during initiation and must not provide a free phone-number field.

The server issues and verifies a challenge-based OTP flow:

- `POST /api/auth/initiate` accepts `ad_url` only.
- Laravel extracts the phone number from the ad and stores an OTP challenge.
- Laravel returns a stable `challenge_id`, masked phone metadata, and OTP expiry.
- `POST /api/auth/verify` accepts `challenge_id`, `otp`, `public_key`, and `signature`.
- Laravel verifies the OTP, verifies the device signature over the canonical challenge payload, and stores the device binding.

## Implementation Decisions

### 1. Identity is derived server-side from the ad URL

The backend, not the Android client, determines the phone identity by scraping the submitted ad URL.

Why:

- Client-supplied phone numbers are easy to spoof.
- SIM state and MSISDN are unreliable and device-dependent.
- A backend-owned fetch-and-parse path is easier to audit and harden.

Implementation:

- `ExtractPhoneFromAdJob` performs ad retrieval and extraction.
- `EscortPortalClient` abstracts the fetch boundary.
- `EscortAdHtmlParser` extracts the primary usable phone number.
- `EscortPhoneNumberNormalizer` normalizes the result to E.164.

### 2. OTP replaces the temporary password flow

The earlier temporary-password design was replaced with a 6-digit OTP stored only as a hash with a short expiry.

Why:

- OTP is a better fit for proving possession of the scraped phone number.
- It removes the need for a password-like credential during onboarding.
- The UX maps cleanly to a two-step Android flow: ad URL, then OTP.

Implementation:

- `OtpChallengeService` generates the OTP and expiry.
- `OtpChallenge` stores `challenge_id`, `ad_url`, `phone_number`, `otp_hash`, and `expires_at`.
- Existing schema was renamed from `auth_challenges` to `otp_challenges`.

### 3. Device binding remains hardware-bound

The OTP alone is not sufficient for final trust. The backend also requires a public key and a valid signature from the client device.

Why:

- The project goal is hardware-bound identity, not SMS-only identity.
- OTP proves access to the scraped number.
- The signature proves control of the device key that will be bound.

Implementation:

- `DeviceSignatureService` normalizes the PEM public key before payload generation and verification.
- The canonical payload is compact JSON with exactly `challenge_id` and normalized `public_key`.
- Signatures are Base64-encoded and verified with SHA-256.
- On Android, hardware-backed Keystore material may come from TEE/KeyMint or StrongBox; the server contract does not distinguish between those secure-hardware implementations.

### 4. API responses are stable and Android-facing

All Phase 1 endpoints return a stable response envelope.

Why:

- Android needs predictable field paths for success, validation, and domain failures.
- Error handling should not depend on Laravel default exception output.

Implementation:

- `ApiResponse` provides success and error envelopes.
- `bootstrap/app.php` renders validation and domain exceptions into API-safe JSON.
- Domain error codes include `invalid_ad_url`, `phone_extraction_failed`, `otp_invalid_or_expired`, `signature_invalid`, and `challenge_not_found`.

### 5. Scraper and SMS boundaries are abstracted

The code uses interfaces and environment-driven implementations rather than hardwiring the production transport path.

Why:

- Tests need deterministic fixture-based behavior.
- Production needs an HTTP-capable client and a replaceable SMS transport.
- The backend should support a non-production phone override without changing controller logic.

Implementation:

- `EscortPortalClient` has fixture and HTTP-backed implementations.
- `SmsSender` currently uses a logging implementation.
- Non-production environments can override the scraped phone number before SMS dispatch.

## Rejected Alternatives

### Client-supplied phone number plus password

Rejected because it gives the client too much control over the claimed identity and does not strongly prove ownership of the scraped ad phone number.

### SIM-derived identity as trust anchor

Rejected because it is inconsistent across devices, carriers, permissions, and real-world Android states.

### Android-side scraping

Rejected because it would expose core trust logic to the client, complicate auditing, and make backend verification less authoritative.

## Implementation Outcome

The current Laravel Phase 1 backend includes:

- `/api/auth/initiate` and `/api/auth/verify`
- OTP challenge issuance and verification
- server-side ad fetch and phone extraction
- device public-key binding after OTP and signature verification
- stable API error envelopes
- fixture-backed and HTTP-backed portal fetching
- structured logging and redaction for sensitive phone data

Focused regression coverage currently validates:

- OTP issuance and expiry
- challenge replacement for the same scraped phone number
- invalid ad URLs and extraction failures
- successful device binding
- expired OTP rejection
- invalid signature rejection
- unknown challenge rejection
- fixture and HTTP client scraper behavior

## Consequences

Positive:

- The backend owns identity establishment.
- The Android onboarding flow is simpler and less spoofable.
- The system is closer to the project's zero-trust, hardware-bound goal.
- Testability improved through clear service boundaries.

Tradeoffs:

- SMS delivery is now part of the critical auth path.
- Ad parsing reliability matters to onboarding success.
- The backend must maintain scraper compatibility with target portals.

## Follow-up Notes

- Android implementation should follow `docs/BACKEND_API_CONTRACT.md` as the request/response source of truth.
- Android implementation progress is tracked separately in `docs/PHASE_1_ANDROID_PROGRESS.md`.
- Future ADRs should capture later decisions such as real SMS provider selection, production scraper hardening, and re-verification strategy for long-lived device bindings.
