# ADR 3: Real-Time Query API

- Status: accepted
- Date: 2026-04-08
- Scope: Phase 3 Laravel real-time blacklist query backend

## Context

After Phase 2 established zero-knowledge report storage and Level 2 promotion, the next backend requirement was a fast query path the Android app can call at the moment an incoming number is encountered.

The MVP goal for this phase is not bulk sync. The backend should answer a single signed query for a single hashed target and reveal only already-promoted Level 2 features. This preserves the privacy boundary established in Phase 2 and avoids shipping any local blacklist database to the device.

Phase 3 also needs to preserve the Phase 1 trust model. A query must come from a previously bound device key, not from an anonymous client or an app-only token.

## Decision

The accepted Phase 3 design is a signed, real-time query endpoint on Laravel: `POST /api/blacklist/check`.

The Android client sends a `target_hash`, `public_key`, and `signature`. Laravel verifies the request using the existing hardware-bound device-binding model, resolves the queried client hash, and returns only the feature labels that are already Level 2 for that target.

Unknown targets and Level 1-only targets both return a successful empty result so the API does not leak hidden buffer state.

## Implementation Decisions

### 1. Query authorization reuses the Phase 1 bound device model

Phase 3 does not introduce a separate auth layer for queries.

Implementation:

- `POST /api/blacklist/check` requires `public_key` and `signature`.
- Laravel normalizes the submitted public key using the same rules established in Phase 1.
- The request is authorized only if the public key belongs to an existing `DeviceBinding`.
- Invalid or missing authorization fails with `blacklist_query_unauthorized`.

### 2. The signature payload is minimal and canonical

The query signature covers only the fields required to authorize a single-target lookup.

Implementation:

- The canonical payload contains exactly `target_hash` and normalized `public_key`.
- `DeviceSignatureService` builds this payload as compact JSON in a fixed field order.
- Laravel verifies the Base64-encoded SHA-256 signature before performing the lookup.

### 3. Only Level 2 features are disclosed

The endpoint must not reveal Level 1 evidence or whether a target is merely present in the reporting system.

Implementation:

- `BlacklistQueryService` reads only promoted `client_feature_levels` rows.
- The response includes human-readable feature labels for rows where `is_level_two = true`.
- If no promoted rows exist, the endpoint returns `features: []` with `200 OK`.

### 4. The query is single-target and stateless

The backend serves one hash lookup per request and does not maintain a session for this phase.

Why:

- The mobile use case is real-time caller evaluation, not background sync.
- A single-target endpoint is easier to authorize, test, and optimize.
- This keeps the API surface smaller while Android integration is still narrow.

Implementation:

- `CheckBlacklistRequest` validates a single 64-character SHA-256 `target_hash`.
- `CheckBlacklistController` returns the standard API envelope with `code: blacklist.checked`.
- `BlacklistQueryService` encapsulates the authorization and lookup behavior.

### 5. The database path is indexed for the query pattern

Phase 3 adds explicit indexing support for frequent target-hash lookups.

Implementation:

- The query path relies on the `clients.client_hash` lookup established in Phase 2.
- An additional Phase 3 migration adds indexes to support efficient reads from `client_feature_levels` for promoted-feature queries.
- The service resolves the client first and then reads only the promoted feature rows for that client.

## Rejected Alternatives

### Shipping a local blacklist database to the device

Rejected because it weakens the privacy model, complicates sync invalidation, and is unnecessary for the MVP single-lookup use case.

### Returning Level 1 or partial evidence in responses

Rejected because it would leak hidden buffer state and undermine the threshold design from Phase 2.

### Introducing a separate API-token model for queries

Rejected because the existing hardware-bound device-binding model already provides a stronger trust anchor and keeps the security model consistent across phases.

## Implementation Outcome

The current Phase 3 backend includes:

- `POST /api/blacklist/check`
- `CheckBlacklistRequest` and `CheckBlacklistController`
- `BlacklistQueryService` for authorization and result lookup
- `DeviceSignatureService` support for canonical query payload generation and verification
- a dedicated Phase 3 index migration for faster promoted-feature reads
- stable error handling through `BlacklistQueryUnauthorizedException`

Focused regression coverage currently validates:

- successful return of Level 2 feature labels for a matching target hash
- empty results for unknown targets and Level 1-only targets
- unauthorized rejection for invalid signatures
- coexistence with the already passing Phase 1 and Phase 2 focused suites

## Consequences

Positive:

- Android can perform a privacy-preserving real-time blacklist lookup without local synced data.
- The backend keeps threshold enforcement and visibility rules centralized.
- The security model remains consistent with the Phase 1 bound-device design.
- The query surface is narrow and easy to reason about.

Tradeoffs:

- Every lookup depends on network reachability and backend latency.
- Android still needs client-side wiring before the full user flow exists.
- The MVP endpoint supports only single-target queries, not batch sync or offline caching.

## Follow-up Notes

- Android should consume the `POST /api/blacklist/check` contract from `docs/BACKEND_API_CONTRACT.md`.
- A later phase can decide whether to keep the system purely request/response or introduce any limited sync or cache layer.
- Future ADRs should capture Phase 4 caller interception and any production performance hardening beyond the current indexing strategy.
