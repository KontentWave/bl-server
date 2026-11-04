# BlackList Project Sheet

**Updated:** 2026-11-04 15:22:08 CET (UTC+01:00), verified local clock. Existing backend quiet-renewal/offboarding changes reviewed, corrected and locally qualified. Owner authorized the backend branch/commit/push/PR/merge/return-to-main publication workflow; hosted rollout is not executed in this publication slice.

**Active client:** Native Android Kotlin/Compose in the client workspace's `legacy_android_kotlin/` directory. Its directory name is historical; it is the shipping client target.

**Evidence boundary:** Implementation baseline is main `a8d2d9a91ac11d55320df434fce68b82470d4de9`; last recorded deployed source is `8d98219a06c932fd966d761a3159135832331bbd`. Last executed hosted activation/closeout remains October 29. November 4 qualification executed isolated SQLite/MariaDB tests; source publication is a separate approved step. No hosted access, scraping, SMS, client distribution or deployment in this publication slice. Client build/device outcomes remain client/owner-reported.

**References:** [API contract](./BACKEND_API_CONTRACT.md), [integration map](./plugin_integration_map.md), [deployment and rollback evidence](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md), [client handoff](./client-docs/CLIENT_TO_SERVER.md), [server handoff](./client-docs/SERVER_TO_CLIENT.md) and [owner decision package](./OWNER_PRIVACY_OPERATOR_DECISIONS_2026-10-06.md). The handoffs are local coordination records, not Git publication requirements. Phase 1-5 retain historical MVP context; the current status and renewal requirement below take precedence over older rollout assumptions.

## Current Status - November 4

| Area | Completed work / evidence | Remaining limits |
| --- | --- | --- |
| Initial ad enrollment | Existing URL -> extracted recipient -> SMS OTP -> signed verification flow. On October 29, owner reported the tester received the actual OTP, verified it and was onboarded on beta. | This is owner-reported/device acceptance, not independent post-enrollment provider/database inspection or proof of renewal. |
| Optional phone enrollment | Existing phone screen reused with a private allowlist; every eligible phone still completes SMS OTP and signed verification. | Phone-only bindings are renewal-exempt; allowlist changes are issuance-only. No new role, privilege or hosted setting change. |
| Scraping and SMS | Amaterky and EuroGirlsEscort host-specific parsers implemented; Webshare rotating endpoint integrated. October 29 beta configuration enabled HTTP/Webshare and SMSTools, production/debug-off, OTP logging off, no recipient override, probes off. | Rotating exits and ad markup/state can fail; one successful fetch is not a pool reliability guarantee. No parser fallback to a supplied recipient. |
| Signed reports/query | Local lifecycle-aware selected-generation authorization preserves signatures, duplicates, threshold three and Level 2-only results. | Overdue access continues; only confirmed terminal state denies. Queries do not wait for renewal cache locks/scraping. Hosted lifecycle behavior is unqualified. |
| Backend validation | November 4: **248 tests / 1,509 assertions** in memory SQLite; **24 tests / 690 assertions at each of two MariaDB isolation levels** in verified disposable containers. PHP syntax, changed/new PHP Pint and whitespace pass. | No live delivery, hosted worker parity, arbitrary-worker cancellation or backup/recovery claim. |
| Native client publication/install | Client handoff reports renewal PR #17 merged at `70ed318491a13f4c1f7d45d5fd1c5ce29454ebc2`. Earlier beta.3 install evidence remains historical. | Published client source is not proof of installed renewal, Android job/Keystore execution or hosted API compatibility. No client distribution here. |
| Incoming-call warning | Owner personally attended a successful real-call test before genuine ad onboarding: three reporters, one tester saw Level 2 Aggressive. Earlier physical-device MVP evidence remains historical. | Waiting is not universally diagnosed/fixed. System call UI can obscure warnings; overlay is blocked on one older phone. Combined post-ad-onboarding call test remains unreported, not grounds for redundant enrollment. |
| Quiet renewal | Implemented and locally qualified; partial-contact false offboarding and silent unexpected inspector failures corrected. Exact agreed wire contract retained. | Source publication does not establish hosted behavior. Legacy testers remain exempt without separately approved explicit ad enrollment; server-first hosted rollout pending. |

### Beta operation and acceptance boundary

- Active beta API: `https://beta.zafo-forum.sk/api/`. Do not point the installed client back to the historical integration hostname.
- October 20 activation preserved APP_KEY, data, device keys/bindings, SMS budgets and shared stores. October 29 configuration-only Webshare activation reverified 94 runtime files against the recorded deployment, actual bindings, HTTPS health200 and no-send JSON422 responses. No schema migration, data reset or new authentication model was used for these activations.
- Owner selected ongoing ad availability without a fixed cutoff. Last verified configuration therefore stays enabled until owner pause; this document does not establish current hosted health or automatically change that setting.
- The private phone allowlist restricts only phone enrollment, not public ad initiation. Recorded global SMS budgets were 20/hour and 100/day, recipient limits 3/hour and 5/day. These are shared limits, not a hard one-SMS cap or reserved capacity for a tester; no budget reset/refund to manufacture headroom.
- Existing owner-operated pause saves/restores the actual beta rewrite file and applies `Require all denied`, with public 403/200 verification. It blocks all new beta HTTP, including phone/query/report; it cannot cancel already-admitted work or provider-accepted SMS. A provider timeout/unknown acceptance or uncertain delivery requires a stop and private investigation, not automatic resend.
- Keep personal ad URLs, numbers, hashes, OTPs, challenge IDs, credentials and signing material out of this sheet. Preserve existing app data/keys. Use the owner-tested Reset onboarding only when intentionally testing fresh enrollment; its exact key/storage effects remain uninspected.
- Historical non-TLS database/shared-account filesystem exposure and untested recovery were explicitly accepted for the bounded original beta slice, not remediated or silently accepted for broader distribution. See the [deployment record](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md).

## Phase 1 - Foundation & Security Handshake

Status: initial handshake MVP implemented, with historical physical-device validation and October 29 owner-reported genuine beta onboarding. Periodic renewal is the separately qualified local lifecycle below, not part of that historical onboarding acceptance. Historical Android progress references refer to the separate client workspace.

### **Action**

Set up the core development infrastructure, then establish a Zero-Trust, hardware-bound authentication handshake between the Android application and the Laravel backend, ensuring the user is verified via an active escort advertisement before storing their device's Public Key.

### **Task Breakdown**

1. **Infrastructure & Environment Setup:** Standard local tests force isolated in-memory SQLite; separate disposable MariaDB tests cover database concurrency. The native client supports ad onboarding plus optional allowlisted phone onboarding; making ads mandatory is not the current beta policy.
2. ✅ **Laravel - Initial Auth Endpoint:** `/api/auth/initiate` accepts an ad URL, extracts the recipient server-side, generates a six-digit OTP, stores its hash with a 15-minute expiry, sends SMS and returns a challenge identifier, masked recipient and expiry using the existing JSON envelope.
3. ✅ **Laravel - Ad Scraper:** [ExtractPhoneFromAdJob](../../app/Jobs/ExtractPhoneFromAdJob.php) uses the configured portal adapter and host-specific parser. Despite implementing `ShouldQueue`, initiation invokes it synchronously; it is not queued by this flow.
4. ✅ **Android - Keystore Integration:** `SecurityManager` now uses Android Keystore to generate an un-exportable elliptic-curve key pair with required hardware-backed secure storage, accepting TEE/KeyMint-backed devices instead of depending specifically on `StrongBox`.
5. ✅ **Android - Onboarding UI:** Client handoff reports the existing ad-URL/OTP screens and optional owner-phone/OTP mode in installed beta.3. Phone input is authorized by the server's private allowlist; both modes use the same signed verification and normal access.
6. ✅ **Laravel & Android - The Handshake Endpoint:** `/api/auth/verify` now completes the end-to-end handshake. Android sends the challenge identifier, OTP, public key, and signature; Laravel verifies the OTP and canonical challenge signature, then binds the public key to the server-scraped phone identity.
7. ✅ **Backend hardening:** [OtpAbuseProtection](../../app/Services/OtpAbuseProtection.php) enforces shared recipient/global SMS reservations, resend cooldown and verification-attempt budgets. [Verification](../../app/Services/AuthVerificationService.php) uses transactional phone-row locking and atomically binds/consumes the challenge. Failed/unknown sends preserve reservations; verification and resend concurrency have isolated MariaDB coverage.
8. ✅ **Optional phone path:** [OtpChallengeService](../../app/Services/OtpChallengeService.php) validates the entire configured allowlist before SMS reservation and sends to the exact eligible submitted phone. Disabled/ineligible issuance fails closed. Removing a number/disabling issuance does not revoke existing bindings or challenges. No invitation endpoint/model/schema remains.

### **Accessibility (Android `ContentDescriptions`)**

- Ensure the Escort Ad URL and OTP input fields have clear `contentDescription` tags for screen readers (TalkBack).
- Ensure error states (e.g., "OTP expired" or "Ad URL invalid") are announced to the accessibility service immediately upon UI update.
- Maintain a minimum color contrast ratio of 4.5:1 for all text and warning elements in the onboarding flow.

### **Test Plan (TDD Acceptance Criteria)**

- **Environment Test 0 (NEW):** Assert that both the Laravel backend and Android client successfully compile, connect to their respective local environments, and can execute a dummy unit test to prove the testing frameworks are active.
- **Laravel Test 1:** Assert that a generated OTP expires and is unusable strictly after the configured short window (target: 15 minutes).
- **Laravel Test 2:** Assert that the ad extraction job correctly returns the normalized phone number for a known active ad URL and fails cleanly for a missing, suspended, or malformed ad.
- ✅ **Android Test 1:** `SecurityManager` generation and hardware-backing behavior are covered by Android tests and runtime validation, with TEE/KeyMint-backed devices accepted without requiring `StrongBox` specifically.
- ✅ **Integration Test 1:** A payload signed by the Android private key is now successfully verified by the Laravel backend after OTP verification using the stored challenge identifier and public key, confirmed by the successful physical-device Phase 1 run on 2026-04-07.

## Phase 2 - Threshold Logic & Backend Database

Status: reporting MVP implemented, with backend tests and historical physical-device client evidence. Current device outcomes are distinguished in the [client handoff](./client-docs/CLIENT_TO_SERVER.md) and current-status section above.

### **Action**

Implement signed reporting with hashed target/reporter identifiers, enforcing the "3 unique reporters" threshold and promoting per-target features from Level 1 (hidden) to Level 2 (queryable). This is not a zero-knowledge system: onboarding challenges and device bindings contain personal phone numbers, and phone hashes are enumerable.

### **Task Breakdown**

1. ✅ **Laravel - Database Schema (Migrations):** The reporting schema is now implemented.
    - `clients`: Stores `client_hash` (SHA-256 of the client's phone number).
    - `reports`: Stores the client relation, `reporter_hash` (SHA-256 of the verified reporter identity), and the immutable feature key.
    - `client_feature_levels`: Stores per-client, per-feature unique reporter counts and Level 2 promotion state.
    - _Privacy boundary:_ Reporting tables use 64-character SHA-256 identifiers; raw onboarding/binding numbers remain elsewhere in the backend. Do not equate hashing with anonymous or non-personal data.
2. ✅ **Laravel - Define Features:** The strict, immutable reportable feature list is now defined in backend configuration.
3. ✅ **Laravel - Reporting Endpoint (`POST /api/reports`):** The secure reporting endpoint is now implemented. The Android app submits a signed report request and Laravel authorizes it using the Phase 1 hardware-bound public key.
4. ✅ **Laravel - Threshold Logic & Promotion:** The core business logic is now implemented in a dedicated reporting service:
    - Check if the specific `reporter_hash` has already reported this `client_hash` for this specific `feature_id`. If so, ignore or return a "duplicate" response.
    - Count the unique `reporter_hash` entries for the client + feature combination.
    - If the count reaches 3, flag that specific feature on the client as "Level 2" (eligible for syncing to devices).

### **Implementation Notes**

- The current Phase 2 backend accepts a raw client phone number from the client request, normalizes it server-side to E.164 when possible, and stores only the SHA-256 `client_hash`.
- The report's reporter identity is derived from the verified bound phone and stored in reporting tables as SHA-256 `reporter_hash`; the associated binding still contains the phone/public key.
- The reporting flow is intentionally independent from scraper realism. Phase 2 focuses on privacy, duplicate prevention, and threshold correctness, not on expanding the scraper beyond the existing Phase 1 verification boundary.
- Level 2 promotion is currently tracked per client and per feature once 3 distinct verified reporters submit the same feature.
- The same verified phone cannot increase the count for the same target/feature more than once, even after key replacement. Do not reset/reseed real beta reports or manufacture reports to repeat a test; use isolated synthetic data for repeatability.
- [ReportSubmissionService](../../app/Services/ReportSubmissionService.php) serializes target updates, preserves duplicate semantics and commits report/count/promotion together. [Disposable MariaDB tests](../../tests/Database/ReportConcurrencyTest.php) cover genuine overlapping workers, first creation, rollback and visibility under both tested isolation levels; historical execution is not hosted all-worker certification.

### **Accessibility & API Contract**

- Ensure API error responses (e.g., "You have already reported this client for this feature") are returned in the standard JSON envelope so the Android app can easily map them to user-friendly, TalkBack-accessible UI alerts later.

### **Test Plan (TDD Acceptance Criteria)**

- **Laravel Test 1 (Data Privacy):** Assert that submitting a report with raw data successfully hashes the information and _only_ the 64-character SHA-256 strings are saved in the test database.
- **Laravel Test 2 (Level 1 Buffer):** Assert that a client with 1 or 2 unique reports remains classified as Level 1 and does not appear in "Level 2" queries.
- **Laravel Test 3 (Anti-Spam):** Assert that if the same `reporter_hash` submits the same `feature_id` for the same `client_hash` multiple times, the report count remains strictly at 1.
- **Laravel Test 4 (Level 2 Promotion):** Assert that when a 3rd distinct `reporter_hash` submits the same `feature_id` for a client, the system accurately promotes that client's feature status to Level 2.

### **Current Verification Status**

- ✅ **Laravel Test 1 (Data Privacy):** Passing.
- ✅ **Laravel Test 2 (Level 1 Buffer):** Passing.
- ✅ **Laravel Test 3 (Anti-Spam):** Passing.
- ✅ **Laravel Test 4 (Level 2 Promotion):** Passing.
- ✅ **Additional coverage:** Independent feature counting is also covered so one feature can remain Level 1 while another feature on the same client starts its own promotion path.
- ✅ **Android live validation:** A physical-device run on 2026-04-08 confirmed signed report creation, duplicate rejection, and independent-feature acceptance for the same client through the Android reporting UI.

## Phase 3 - Real-Time Query API (The Oracle)

Status: backend query MVP implemented with historical Android no-match validation. Current evidence and limitations are tracked in the [client handoff](./client-docs/CLIENT_TO_SERVER.md) and current-status section above.

### **Action**

Provide a signed query endpoint for one hashed target, returning only stored Level 2 features without downloading the reporting database. Hashing does not make the protocol zero-knowledge or eliminate the need for privacy controls.

### **Task Breakdown**

1. ✅ **Laravel - The Query Endpoint (`POST /api/blacklist/check`):** The secure query endpoint is now implemented.
    - **Input:** The Android app will send a `target_hash` (the SHA-256 hash of the incoming caller's number), authorized by the standard Phase 1 `public_key` and `signature`.
    - **Logic:** Laravel now checks whether the `target_hash` maps to any `client_feature_levels` rows that are already promoted to Level 2.
    - **Output:** The endpoint returns the human-readable labels for only those Level 2 features (for example `["Aggressive", "No-Show"]`). Unknown targets and Level 1-only targets both return a clean, empty `features` array.
2. ✅ **Laravel - Performance Optimization:** The backend query path is now indexed for the Phase 3 access pattern, including the added migration that supports fast Level 2 lookups.
3. ✅ **Android - Real-Time Networking Prep:** The Android client now exposes a signed Phase 3 query screen, wires a Retrofit request for `POST /api/blacklist/check`, and renders empty-result vs matched-feature states for the current MVP scope.

### **Implementation Notes**

- The query endpoint reuses the Phase 1 device-binding trust model instead of introducing a separate API key or session layer.
- The request signature is verified against a canonical payload containing only `target_hash` and the normalized `public_key`.
- The backend intentionally reveals only Level 2 features. Level 1 evidence remains indistinguishable from an unknown target.
- The current Laravel implementation returns the standard API success envelope with `code: blacklist.checked`, `data.target_hash` and `data.features`.
- [BlacklistQueryService](../../app/Services/BlacklistQueryService.php) checks a stored device key and signature but does not check verification age/ad state. This is the current renewal gap, not an implemented indefinite-access policy decision.

### **Test Plan (TDD Acceptance Criteria)**

- **Laravel Test 1 (Level 2 Match):** Assert that submitting a `target_hash` that has Level 2 features returns exactly those features.
- **Laravel Test 2 (Level 1 / Unknown Target):** Assert that submitting a `target_hash` that only has Level 1 reports, or doesn't exist at all, returns a 200 OK with an empty array (revealing no buffer data).
- **Laravel Test 3 (Security):** Assert that the endpoint rejects queries with a 401 Unauthorized if the hardware signature is missing or invalid.

### **Current Verification Status**

- ✅ **Laravel Test 1 (Level 2 Match):** Passing.
- ✅ **Laravel Test 2 (Level 1 / Unknown Target):** Passing.
- ✅ **Laravel Test 3 (Security):** Passing.
- ✅ **Focused regression coverage:** `CheckBlacklistTest`, `StoreReportTest`, `InitiateAuthTest`, and `VerifyAuthTest` passed together after the Phase 3 migration and index changes were applied.
- ✅ **Android live validation:** A physical-device run on 2026-04-08 confirmed the signed `POST /api/blacklist/check` no-match path, including correct empty-feature rendering for an unknown target hash.

## Phase 4 - Call Interception & UI Shield (The Shield)

Status: historical MVP happy path physically validated on one Android device, with later owner-observed Level 2 warning evidence. Current per-device limitations are recorded above and in the [client handoff](./client-docs/CLIENT_TO_SERVER.md); this is not universal device qualification.

### **Action**

Build the core Android user experience. The app must detect incoming phone calls, instantly hash the caller ID, query the Laravel "Oracle" endpoint, and display a high-priority, red warning overlay on the screen if the Level 2 threshold has been met.

### **Task Breakdown**

1. ✅ **Android - Permissions & Manifest:** The Android client now requests and evaluates the necessary Shield capabilities: `READ_PHONE_STATE` (to detect the phone ringing), `READ_CALL_LOG` (required by newer Android versions to get the actual phone number), and `SYSTEM_ALERT_WINDOW` (to draw the warning box _over_ the native phone dialer). Manifest declaration, verified-home permission wiring, receiver registration, and a physical-device readiness check are now in place for the current Phase 4 MVP path.
2. ✅ **Android - The Call Receiver (`BroadcastReceiver`):** A manifest-registered receiver now wakes up on `TelephonyManager.EXTRA_STATE_RINGING` and dispatches the Phase 4 processing pipeline off the main thread.
3. ✅ **Android - Real-Time Hashing & Query:** Inside the Receiver, the current Android client now:
    - Extract the incoming phone number.
    - Convert it to E.164 format and hash it using SHA-256.
    - Dispatch the lightning-fast signed network request to our Phase 3 `POST /api/blacklist/check` endpoint.
    - Persist live diagnostics into the verified-home app shell so physical-device tests can confirm whether the number was seen, normalized, hashed, and queried successfully.
4. ✅ **Android - The Warning Overlay (`WindowManager`):** Historical happy-path evidence covers an overlay and accessibility announcement for a Level 2 match. Current device evidence is narrower: call UI can obscure the warning and one older phone blocks overlay permission. Broader lifecycle/OEM behavior and usability remain unqualified; do not describe all remaining work as cosmetic.
    - The UI should be prominent (e.g., Red background, White text) and explicitly list the features returned by the server.
5. ✅ **Android - Reporting UI foundation already exists:** The Compose reporting screen from Phase 2 is already implemented and validated. Phase 4 should reuse that existing report flow from the verified app shell and may optionally deep-link to it from the Shield experience after a warning.
6. ✅ **Android - Shield status in the app shell:** The verified-home screen now exposes Shield readiness so the user can see whether call monitoring permissions and overlay capability are active, partially missing, or blocked, and can jump directly into the relevant permission flows.

### **Accessibility (Android `ContentDescriptions`)**

- The `SYSTEM_ALERT_WINDOW` overlay MUST trigger an immediate, high-priority accessibility event so TalkBack instantly reads the warning out loud to the user over the ringing sound: _"Warning. Incoming caller reported for: Aggressive."_

### **Test Plan (TDD Acceptance Criteria)**

- **Android Test 1 (Permissions):** Assert that the app gracefully prompts the user and handles the flow if the user denies the `SYSTEM_ALERT_WINDOW` permission.
- **Android Test 2 (Hashing):** Assert that the local SHA-256 hashing function perfectly matches the expected backend hash for a known test number.
- **Android Test 3 (Shield Status UI):** Assert that the verified-home UI correctly displays whether Shield permissions are granted, missing, or partially unavailable.
- **Integration Test 1:** Assert that a mock incoming call with a known "bad" number triggers the `BroadcastReceiver`, makes the network call, and successfully inflates the overlay view.

### **Current Verification Status**

- ✅ **Android Test 2 (Hashing):** The local caller-number normalization and SHA-256 hashing path is implemented and covered by focused Android unit tests.
- ✅ **Android Test 3 (Shield Status UI):** The verified-home Shield readiness card is implemented and was physically observed showing that all required permissions were available on the validated device.
- ✅ **Integration Test 1 (historical):** The recorded physical-device run on 2026-04-09 confirmed call interception, signed query, Level 2 Aggressive match and an over-dialer warning on that device. No identifying caller value is retained here.
- ✅ **Later owner-observed evidence:** Before genuine ad onboarding, the owner attended a call test with three reporters and a Level 2 Aggressive warning on one tester's phone. Accept this result without repeating onboarding or calls merely for documentation. It does not prove every Android/OEM/overlay combination works.
- ⚠️ **Remaining optional follow-up coverage:** explicit permission-denial handling, no-match physical-call evidence, and broader overlay dismissal behavior across repeated calls or device-specific OEM variants can still be recorded as hardening work, but they no longer block the current Phase 4 MVP happy-path claim.

## Phase 5 - Production Scraper Hardening (`amaterky.sk`)

### **Action**

Replace the Phase 1 dummy scraper path with a production-ready Laravel extraction pipeline. The current rollout now includes rotating-proxy telemetry, a live `amaterky.sk` portal adapter, and E.164 normalization that was validated against a real ad fetched through the rotating endpoint.

### **Hosted Integration State - last verified October 29**

- Beta uses `https://beta.zafo-forum.sk/api/`, production/debug-off and SMSTools, not the old integration hostname/Vonage trial setup.
- Genuine ad extraction through Webshare matched the privately expected recipient, with target TLS verification, validated public destination pinning and no redirects. Beta was then configured to use HTTP/Webshare persistently at the owner's request, without a fixed cutoff.
- OTP logging and recipient override are off. Phone enrollment/private allowlist stays enabled. No real numbers, private URLs, OTPs or challenge IDs belong in this sheet.
- Owner reports actual ad -> SMS receipt -> OTP verification -> onboarding passed October 29. This qualifies that test, not daily renewal, pool-wide reliability or public-launch readiness.
- The earlier Vonage trial/recipient-override setup is superseded historical integration evidence, not the current beta identity path. [VonageSmsSender](../../app/Services/VonageSmsSender.php) remains an available adapter; [SmstoolsSmsSender](../../app/Services/SmstoolsSmsSender.php) is the recorded beta binding.

### **Android Handoff Notes**

- Installed beta.3 is client-reported to target `https://beta.zafo-forum.sk/api/`; no new client build was required to enable genuine ad extraction.
- Android should continue to treat Laravel as the source of truth for masked phone metadata, OTP expiry, and signed verification behavior.
- OTPs must go to the genuinely extracted contact, never a development substitute; owner-reported receipt completed that beta test.
- Unknown provider acceptance/delivery is a stop, not permission to automatically resend. SMSTools checks success/OK, valid batch/message identifiers and the matching accepted recipient; acceptance alone is not delivery.

### **Task Breakdown**

1. ✅ **Laravel - Rotating proxy configuration and telemetry:** The backend now supports a dedicated rotating-proxy config surface in `.env` and `config/scraping.php`, plus a standalone `scraper:probe-proxy` command that records per-attempt telemetry into `scraper_proxy_attempts`. This was added first so transport health could be measured before tightening portal-specific parsing.
2. ✅ **Laravel - DOM parsing foundation:** `symfony/dom-crawler` and `symfony/css-selector` are now installed, and portal-specific DOM parsing is wired behind `EscortAdHtmlParser` rather than relying only on generic regex matching.
3. ✅ **Laravel - `amaterky.sk` extraction and normalization:** [AmaterkySkPhoneExtractor](../../app/Scraper/Portals/AmaterkySkPhoneExtractor.php) prefers `a.detail-floater-link-phone[href^="tel:"]`, then SMS links, then `.card.card-contact h2`. The shared normalizer handles local `09...`, `00421...` and bare `421...`. Normalization is not proof of ownership, reachability or fully validated E.164 length.
4. ✅ **Laravel - Failure handling for the current slice:** The live HTTP client now distinguishes timeout and upstream-unavailable paths from non-usable pages. Focused fixtures cover active, SMS-only, heading-only, missing-phone, and suspended `amaterky.sk` ad shapes so the controller can preserve the retryable vs hard-stop behavior already expected by Android.
    - `amaterky.sk` ads that explicitly show `Vypnutý zadávateľom` are now classified separately from generic extraction failure. This is treated as a recognized ad state, but it is still a hard stop for the current initiate attempt because no phone-number extraction should start from that page state.
5. ✅ **EuroGirlsEscort adapter:** [EuroGirlsEscortPhoneExtractor](../../app/Scraper/Portals/EuroGirlsEscortPhoneExtractor.php) reads contact attributes/encoded phone values and is covered by fixtures. First contact selection and multiple social/contact numbers remain risks; no current live EuroGirls delivery acceptance is claimed.
6. ✅ **URL/transport hardening:** [EscortAdUrlPolicy](../../app/Services/EscortAdUrlPolicy.php) restricts HTTPS/443 to the two supported portal domains and their `www` variants, rejects credentials/unsafe DNS destinations; [HTTP transport](../../app/Services/HttpEscortPortalClient.php) pins a validated public address, verifies TLS and refuses redirects. Supported portals do not fall back to unrelated generic page numbers.
7. **Remaining transport limits:** One fetch per ordinary initiation, no automatic retry loop. Proxy connect timeout and overall portal timeout are separate. The generic probe service has different URL/redirect/telemetry behavior and is not production ad qualification. Scheduled probes remain off on last verified beta. HTTP proxy-hop credentials are not protected by target HTTPS; bounded existing-hop risk was accepted for the original beta.
8. **Additional portals:** No implemented Rosszlanyok adapter or allowlisted host; future coverage must be explicitly designed and tested.

### **Test Plan (TDD Acceptance Criteria)**

- ✅ **Laravel Test 1 (Proxy Timeout):** Covered by the rotating-proxy probe suite, which records timeout and transport-error outcomes without aborting the run.
- ✅ **Laravel Test 2 (DOM Extraction):** Covered by focused `amaterky.sk` extractor tests and fixture-backed job tests.
- ✅ **Laravel Test 3 (Normalization):** Covered by focused normalizer tests for Slovak local and dirty international-prefix input.
- ✅ **Laravel Test 4 (Missing Ad):** Covered for the current extractor slice through missing-phone and suspended fixtures returning no usable number.

### **Current Verification Status**

- ✅ **Focused proxy telemetry suite:** `ProxyProbeServiceTest` and `ProbeScraperProxyCommandTest` passed for success, blocked, timeout, transport-error, and multi-attempt recording behavior.
- ✅ **Focused extraction suite:** `ExtractPhoneFromAdJobTest`, `HttpEscortPortalClientTest`, `AmaterkySkPhoneExtractorTest`, and `EscortPhoneNumberNormalizerTest` passed together after the portal-adapter slice landed.
- **Historical proxy evidence:** Earlier pool replacement/probe results and live extraction are retained in [ADR 4](./ADRs/4_production_scraper_hardening.md), not a current reliability measurement or permission for pool-wide scans.
- ✅ **October 24 hosted extraction:** One guarded Webshare fetch returned HTTP200/verified TLS/no redirects and matched the expected recipient; the owner then paused before SMS.
- ✅ **October 29 ad-state diagnosis:** Initial HTTP200 pages said the ad had expired and contained no contact. Preparation stopped without SMS. After owner-confirmed reactivation, one fetch matched the intended recipient; reversible configuration-only beta activation followed. No recipient substitution or TLS relaxation.
- ✅ **October 29 delivery/onboarding:** Owner reports the tester received OTP from her submitted ad flow, verified and onboarded. No independent post-test provider/database claim.
- **Remaining scraper work:** Page-state/markup drift and rotating-exit failures require bounded diagnostics. An ad-expired page currently falls through to missing-phone failure; recognized advertiser-disabled headings have their own domain error. Neither state currently revokes a binding. Daily checking/offboarding belongs to the new renewal requirement, not this completed initial-extraction slice.

## **Current Phase:** Closed-Beta Qualification - Native Android and Laravel

### **Client and Backend Direction**

Continue the existing Kotlin/Compose client and Laravel API. Reuse native Android Keystore signing, the existing network/repository layer, onboarding/reporting screens and call-interception/overlay implementation. Do not scaffold a replacement client or introduce cross-platform bridges.

Backend source publication and local regression results do not establish the deployed revision, real SMS delivery or overall beta readiness. Keep the [API contract](./BACKEND_API_CONTRACT.md) stable and coordinate any client-visible behavior change before implementation.

### **Action**

Continue from completed two-tester setup and genuine ad onboarding rather than restarting them. Implement/qualify the missing renewal lifecycle, record ordinary signed use/persistence and device-specific limitations, then consider a staged usability-focused expansion.

### **Task Breakdown**

1. **Completed setup:** Backend allowlisted phone enrollment published through server PR #22; documentation baseline PR #23; beta deployed source/configuration and no-send HTTPS checks recorded. October 29 Webshare activation required configuration only. No invitations, migrations, seeds or reset of existing data/keys/budgets.
2. **Completed native delivery:** Client handoff reports PR #15 publication, same-publisher beta.3 update/install and beta URL selection. Reported final validation: 183 JVM tests discovered, 182 passed, one skipped; release build/lint passed, Android tests compiled but were not executed. These are client-reported historical results, not a new local build or client source audit.
3. **Completed real-world acceptance:** Owner-reported phone onboarding, attended real-call Level 2 warning and subsequent genuine ad/SMS/OTP enrollment are separate successes. Preserve them; do not require re-running either successful path just to prepare other work.
4. **Outstanding functional work:** Quiet renewal below; ordinary signed BlackList use/report and persistence; combined call behavior after ad onboarding remains unreported. The older tester phone is scoped to onboarding/ordinary availability, not blocked overlay/incoming-call qualification. Second-phone overlay testing is planned, not a completed result. Earlier Waiting behavior remains unresolved in general.
5. **Privacy/operator/recovery work:** Keep a practical private support contact/pause procedure. Purposes/notice, retention/deletion, provider handling and acceptable database recovery expectations still need explicit treatment before expansion. Do not infer a tested backup/restore or waive historical exposure findings through local test success.
6. **Possible expansion:** Owner proposes about 15-20 consenting nontechnical users after combined-flow confidence and a user-friendly Kotlin/Compose iteration. This is a proposed staged closed beta, not approved distribution or overall readiness. Prioritize clear onboarding/errors, expired-ad and renewal states, permission limitations, no-match versus unavailable results, and simple non-destructive recovery.

### **Accessibility**

- Preserve Compose accessibility semantics, TalkBack announcements, clear permission/error states and readable warning overlays.
- Qualify actual device behavior; historical happy-path evidence does not replace current permission-denial, call-lifecycle or OEM checks.

### **Test Plan (Acceptance Criteria)**

- Preserve canonical signing, stable API envelopes, duplicate-report semantics and no automatic request replay.
- Record signed APK identity/update outcomes and physical-device permission, restart, key and call/overlay results separately from JVM/emulator evidence.
- Record hosted protection/configuration evidence separately from local mocked/concurrency tests; provider acceptance alone is not SMS delivery.
- Record initial live ad SMS/onboarding as passed on owner-reported evidence without claiming every broader acceptance item is closed. Coordinate gate status with the client handoff rather than editing its owner-controlled attachment or asserting overall readiness.
- This document authorizes no build/signing, device operation, hosted access, deployment, live SMS, revocation or expanded distribution.

## Coordinated Lifecycle - Locally Qualified, Not Hosted

**History:** requirement recorded November 2 at 19:05:52 CET; signed-HTTPS,
overdue-access and exemption policy accepted November 3 in
[ADR 006](./ADRs/006_coordinated_renewal_and_offboarding.md). November 4 local
qualification at the timestamp above supersedes the earlier "not implemented"
assessment. Initial onboarding/call acceptance remains intact, not restarted.

### Desired behavior

1. **Initial manual enrollment:** The user submits the active ad, receives SMS OTP and completes verification signed with the device's existing hardware-backed key.
2. **Quiet renewal after 24 hours:** Signed HTTPS with the existing key, no renewal SMS/OTP. First due is explicit verification plus 86,400 seconds; subsequent due is successful renewal commit plus 86,400 seconds. Preserve historical verification time. The server rechecks the retained ad and original verified recipient.
3. **Repeat while eligible:** Continue the cycle while the ad is active and the identity/contact still matches. Do not advance the successful-renewal timestamp merely because a check was attempted or the UI opened.
4. **Ad off:** Confirmed ad deactivation offboards the client and requires fresh manual onboarding. Reactivation alone must not silently restore authorization once offboarding has occurred. Offboarding must mean server-enforced loss of query/report authority, not merely hiding a client screen; do not erase community reports or regenerate hardware keys as a workaround.
5. **Overdue phone:** If the phone is powered off/offline beyond the interval, attempt the overdue renewal as soon as the app can execute with connectivity again. After successful renewal, count the next 24 hours from that success, not a missed scheduled attempt; do not replay one renewal for every missed day.
6. **User-visible state:** Client displays the server-confirmed latest successful renewal time and distinguishes pending/unavailable renewal from confirmed offboarding. Android scheduling/boot/resume behavior must be inspected and tested; execution while powered off or exact background timing cannot be promised.

### Implemented and inspected server boundaries

- [API routes](../../routes/api.php) add signed status/renewal through the
  [lifecycle service](../../app/Services/AuthLifecycleService.php), preserving
  [exact canonical payloads/envelopes](./BACKEND_API_CONTRACT.md#coordinated-renewal-and-offboarding).
- [Nullable migration](../../database/migrations/2026_11_03_210000_add_binding_lifecycle.php)
  adds provenance, generations, selection, due/state/version, persisted retry
  reservations and bounded receipts. Legacy provenance is never inferred.
- [Verification](../../app/Services/AuthVerificationService.php) copies ad
  provenance only from an explicit challenge, selects a new generation and
  retains keys/community rows. Phone-only and legacy bindings are exempt.
- [Authorization](../../app/Services/BindingLifecycle.php) uses short selected
  binding transactions. Query/report access does not expire when overdue.
  Portal I/O is outside transactions and incoming-call queries never await
  renewal locks/scraping. No scheduled enrolled-ad crawling or automatic renewal OTP.
- [Inspector](../../app/Services/RenewalAdInspector.php) requires narrow
  supported inactivity or a complete confidently parsed excluding contact set.
  Timeout, challenges, missing/partial contacts and bare404/410 remain unknown.
  Unexpected failures emit exception-class-only diagnostics, no private content.

### Accepted protection and rollout constraints

- Failure delays remain 900/3,600/21,600 seconds with longer validated delays
  honored; four enrollment fetch reservations/fixed day, 100 app fetches/day,
  20 signed lifecycle requests/key/hour and three/IP/minute. Receipts remain
  eight/enrollment for48h; durable pending claims fence cache expiry and late results.
- Existing beta testers cannot be silently associated with ads consumed during
  old enrollment. Keep them legacy/exempt. Enabling renewal requires separately
  approved intentional supported-ad OTP verification with the retained key and
  a client-owner-confirmed key/data-preserving entry path. No repeat enrollment
  merely to reproduce accepted October onboarding.
- Backend publication to `github` is owner-authorized on November 4. Bounded
  hosted backup/preflight/migration/API rollout via `origin` remains a separate
  execution step, not part of this publication-only slice. Server
  must precede separately approved client distribution. Live portal/SMS,
  enrollment/binding actions and device qualification each require explicit scope.
- Preserve APP_KEY, keys, bindings, data, budgets and hosted configuration.
  Migration rollback compatibility passed locally, but pre-lifecycle application
  rollback would ignore terminal offboarding. Retain schema/state and forward-fix
  a lifecycle-aware release; approve any containment pause separately.

### Qualification and remaining acceptance

- Manual enrollment establishes the server-authoritative renewal baseline; a due successful check validates both active ad and matching contact, advances success time and sets the next 24-hour deadline.
- Early, duplicate, concurrent and replayed requests do not create extra SMS/provider effects or conflicting renewal state; failed checks do not advance the successful timestamp.
- Confirmed inactive ad prevents normal signed query/report authorization and requires manual enrollment even after reactivation; the old signature/key alone cannot bypass the offboarded state.
- Transient fetch failures are distinguishable from inactive ads and follow the agreed access/retry policy. Changed recipient cannot silently renew the original identity.
- A phone offline for more than 24 hours performs one overdue renewal when execution/connectivity resumes and schedules the next interval from actual success, without key reset or catch-up send storms.
- Client updates its displayed timestamp only from successful server results, retains clear pending/offboarded states across restart, and handles Android background restrictions without promising exact wakeup times.
- Phone-only users follow the explicitly selected policy; existing onboarding, SMS budgets, signatures, report thresholds and duplicates retain regression coverage.
- Executed full isolated local regression: SQLite248/1,509; MariaDB24/690 at each
  of REPEATABLE-READ and READ-COMMITTED. Local cases above pass, including
  complete/encoded contacts and incomplete-set unknowns, genuine process
  overlap, no portal transaction, migration compatibility and preserved
  duplicate/threshold/allowlist/onboarding behavior.
- Hosted release, real provider reliability, Android scheduler/Keystore/process
  death and client distribution remain unqualified. Local success does not
  establish beta readiness or SMS delivery. See the
  [integration map](./plugin_integration_map.md) and
  [server handoff](./client-docs/SERVER_TO_CLIENT.md) for commands, safeguards,
  dated historical evidence and the server-first proposal.
