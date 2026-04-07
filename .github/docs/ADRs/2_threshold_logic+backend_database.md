# ADR 2: Threshold Logic and Zero-Knowledge Reporting Database

- Status: accepted
- Date: 2026-04-07
- Scope: Phase 2 Laravel reporting backend

## Context

After Phase 1 established a working hardware-bound onboarding flow, the next backend priority was the reporting pipeline. The system needs to accept worker-submitted reports, prevent duplicate spam, and promote repeated reports into syncable Level 2 signals without storing plain-text client phone numbers in the database.

The MVP requirement for this phase is speed and privacy correctness, not scraper realism. The scraper remains limited to the existing Phase 1 verification boundary. Phase 2 should stand on top of the already-verified worker identity and implement the reporting system as a separate zero-knowledge slice.

## Decision

The accepted Phase 2 design is a zero-knowledge reporting flow that stores only cryptographic hashes for client and reporter identities while preserving threshold logic on the backend.

The Laravel backend accepts a signed report request, normalizes the submitted client phone number server-side, hashes it, derives a hashed reporter identity from the Phase 1 verified device binding, and stores only hashed identifiers plus feature metadata.

Level 2 promotion is tracked per client and per feature after 3 distinct verified reporters submit the same feature.

## Implementation Decisions

### 1. Zero-knowledge storage is enforced at rest

The database stores no raw client phone numbers and no raw reporter phone numbers in the reporting tables.

Implementation:

- `clients.client_hash` stores a 64-character SHA-256 hex string.
- `reports.reporter_hash` stores a 64-character SHA-256 hex string.
- Raw client numbers are normalized server-side only for the purpose of hashing and signature verification.
- The reporting tables do not persist plain-text phone numbers.

### 2. Reporter identity is derived from Phase 1 device binding

Phase 2 reporting does not introduce a separate user-account layer. Instead, a report is authorized by the already bound device public key from Phase 1.

Implementation:

- `POST /api/reports` requires `public_key` and `signature`.
- Laravel looks up the matching `DeviceBinding` by normalized public key.
- If no verified binding exists, the report is rejected.
- `reporter_hash` is derived from the verified worker identity behind that binding.

### 3. The report request is signed with the bound device key

The reporting endpoint requires a signature over a canonical payload to prevent unauthenticated report submission.

Implementation:

- The report payload contains exactly `client_phone_number`, `feature`, and `public_key`.
- Laravel verifies the signature with the same public-key handling rules established in Phase 1.
- The signature check happens before persistence.

### 4. Features are immutable backend-defined keys

Reportable features are backend-defined and must not be free-form text.

Why:

- Threshold logic depends on consistent feature keys.
- Android and Laravel need a stable contract.
- The MVP should avoid taxonomy drift.

Implementation:

- Features are defined in `config/reporting.php`.
- The request validator only accepts configured feature keys.
- Human-readable labels are returned from the backend config.

### 5. Duplicate prevention is strict and per client/feature/reporter tuple

The system treats repeated reports by the same reporter for the same client and the same feature as duplicates.

Implementation:

- The backend checks for an existing report row with the same `client_id`, `reporter_hash`, and `feature`.
- The database also enforces a unique constraint on that tuple.
- Duplicate submissions are rejected with a domain error instead of incrementing counts.

### 6. Promotion is per feature, not global per client

A client can be Level 2 for one feature while remaining Level 1 for another.

Implementation:

- `client_feature_levels` stores per-client, per-feature counts and promotion state.
- `unique_reporter_count` increases only within the client + feature scope.
- `is_level_two` becomes true when the count reaches the configured threshold.
- `promoted_at` is recorded the first time Level 2 is reached.

## Rejected Alternatives

### Storing plain-text phone numbers in reporting tables

Rejected because it violates the privacy boundary of the reporting system and weakens the project's zero-knowledge goals.

### Free-form feature text from the client

Rejected because it makes threshold logic inconsistent and creates Android/backend taxonomy drift.

### Depending on scraper realism for Phase 2 reporting

Rejected for the MVP because it slows down delivery of the reporting core without improving the main privacy and threshold guarantees of this phase.

## Implementation Outcome

The current Phase 2 backend includes:

- reporting schema for `clients`, `reports`, and `client_feature_levels`
- `POST /api/reports`
- server-side normalization and hashing of client phone numbers
- signed report authorization through the Phase 1 bound device key
- duplicate report rejection
- Level 2 promotion after 3 distinct reporters for the same feature
- backend configuration for immutable feature keys and threshold value

Focused regression coverage currently validates:

- zero-knowledge persistence of hashes
- Level 1 behavior at 1 and 2 reports
- strict duplicate rejection
- Level 2 promotion on the third distinct reporter
- independent counting for separate features on the same client

## Consequences

Positive:

- The reporting pipeline preserves privacy better than storing raw identifiers.
- The backend owns promotion logic and anti-spam enforcement.
- Phase 2 can progress independently from scraper sophistication.
- Android receives a stable signed endpoint to integrate against.

Tradeoffs:

- The MVP still depends on the existing verified device binding as the reporter identity source.
- Raw client phone numbers are transiently handled in request processing before hashing.
- Feature taxonomy changes must be coordinated because keys are intentionally immutable at the API level.

## Follow-up Notes

- Android should consume the `POST /api/reports` contract from `docs/BACKEND_API_CONTRACT.md`.
- Future ADRs should capture Phase 3 sync design, including what exact Level 2 data becomes device-syncable and how deduplicated feature evidence is serialized for clients.
