## Phase 1 - Foundation & Security Handshake

Status: complete for the current Phase 1 scope. Implementation progress and evidence are tracked in `docs/BLACKLIST_ANDROID_PROGRESS.md`.

### **Action**

Set up the core development infrastructure, then establish a Zero-Trust, hardware-bound authentication handshake between the Android application and the Laravel backend, ensuring the user is verified via an active escort advertisement before storing their device's Public Key.

### **Task Breakdown**

1. **Infrastructure & Environment Setup:** Laravel local infrastructure remains MariaDB-backed and testable, but the authentication protocol is being refactored. Android setup should start from the new assumption that the UI exposes only an escort ad URL field. No free phone-number entry field should exist in the client.
2. **Laravel - Setup Initial Auth Endpoint:** Refactor `/api/auth/initiate` so it accepts an escort ad URL instead of a phone number. Laravel must scrape the ad URL, extract the primary phone number server-side, generate a secure 6-digit OTP, store only its hash with a short expiration (target: 15 minutes), dispatch the OTP by SMS to the scraped phone number, and return a stable challenge identifier plus masked phone metadata to the Android client.
3. **Laravel - Build the "Ad Scraper" Job:** Refactor the scraper job into an ad-URL-first flow, for example `ExtractPhoneFromAdJob`. It must take the submitted ad URL, route requests through a proxy-capable HTTP boundary, parse the HTML, validate that a usable active ad exists, and extract the primary phone number in normalized E.164 form. This extracted number becomes the server-derived identity target for SMS verification.
4. ✅ **Android - Keystore Integration:** `SecurityManager` now uses Android Keystore to generate an un-exportable elliptic-curve key pair with required hardware-backed secure storage, accepting TEE/KeyMint-backed devices instead of depending specifically on `StrongBox`.
5. ✅ **Android - Onboarding UI:** The Compose onboarding flow now uses a single user-entered active escort ad URL field followed by OTP confirmation. The client exposes no free phone-number field and displays only masked phone metadata returned by Laravel.
6. ✅ **Laravel & Android - The Handshake Endpoint:** `/api/auth/verify` now completes the end-to-end handshake. Android sends the challenge identifier, OTP, public key, and signature; Laravel verifies the OTP and canonical challenge signature, then binds the public key to the server-scraped phone identity.

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

Status: complete for the current Phase 2 MVP scope. The implemented reporting flow is now validated on both the Laravel backend and the Android client, with physical-device Android evidence tracked in `docs/BLACKLIST_ANDROID_PROGRESS.md`.

### **Action**

Implement the core reporting logic securely on the Laravel backend. This includes creating a Zero-Knowledge database schema that stores only cryptographic hashes, enforcing the "3 unique reporters" threshold, and promoting clients from Level 1 (Buffer/Hidden) to Level 2 (Active/Syncable).

### **Task Breakdown**

1. ✅ **Laravel - Database Schema (Migrations):** The reporting schema is now implemented.
    - `clients`: Stores `client_hash` (SHA-256 of the client's phone number).
    - `reports`: Stores the client relation, `reporter_hash` (SHA-256 of the verified reporter identity), and the immutable feature key.
    - `client_feature_levels`: Stores per-client, per-feature unique reporter counts and Level 2 promotion state.
    - _Security Note:_ Ensure database columns for hashes are appropriately sized (e.g., `VARCHAR(64)` for SHA-256 hex strings) and absolutely no raw phone numbers are stored.
2. ✅ **Laravel - Define Features:** The strict, immutable reportable feature list is now defined in backend configuration.
3. ✅ **Laravel - Reporting Endpoint (`POST /api/reports`):** The secure reporting endpoint is now implemented. The Android app submits a signed report request and Laravel authorizes it using the Phase 1 hardware-bound public key.
4. ✅ **Laravel - Threshold Logic & Promotion:** The core business logic is now implemented in a dedicated reporting service:
    - Check if the specific `reporter_hash` has already reported this `client_hash` for this specific `feature_id`. If so, ignore or return a "duplicate" response.
    - Count the unique `reporter_hash` entries for the client + feature combination.
    - If the count reaches 3, flag that specific feature on the client as "Level 2" (eligible for syncing to devices).

### **Implementation Notes**

- The current Phase 2 backend accepts a raw client phone number from the client request, normalizes it server-side to E.164 when possible, and stores only the SHA-256 `client_hash`.
- The reporter identity is derived from the Phase 1 verified device binding and persisted only as a SHA-256 `reporter_hash`.
- The reporting flow is intentionally independent from scraper realism. Phase 2 focuses on privacy, duplicate prevention, and threshold correctness, not on expanding the scraper beyond the existing Phase 1 verification boundary.
- Level 2 promotion is currently tracked per client and per feature once 3 distinct verified reporters submit the same feature.
- Testing note: the same verified reporter cannot increase the count for the same client + feature more than once. If QA needs to replay that exact tuple, the reset or reseed must be handled on the Laravel/database side, not by the Android client.

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

Status: complete for the current Phase 3 MVP scope. The backend query API and the Android real-time query client are both implemented, and the Android no-match path is validated on a physical device with evidence tracked in `docs/BLACKLIST_ANDROID_PROGRESS.md`.

### **Action**

Establish a high-speed, Zero-Knowledge query endpoint on the Laravel server that allows the Android app to securely check a single hashed phone number in real-time, eliminating the need for any local database storage on the client device.

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
- The current Laravel implementation returns the standard API success envelope with `code: blacklist.checked` and `data.features` as the only blacklist result surface.

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

Status: implemented and physically validated for the current Phase 4 MVP happy path. The end-to-end Shield warning flow now works on a physical Android device for a prepared Level 2 caller match, with detailed evidence tracked in `docs/BLACKLIST_ANDROID_PROGRESS.md`.

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
4. ✅ **Android - The Warning Overlay (`WindowManager`):** If the Laravel Oracle returns an array of features (e.g., `["Aggressive"]`), the Android client now uses `WindowManager` to draw a warning overlay and announce it for accessibility. This warning path is now physically validated on a real incoming call for a prepared Level 2 caller match. Remaining work is limited to optional refinement of dismissal behavior and broader edge-case coverage under repeated or OEM-specific call scenarios.
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
- ✅ **Integration Test 1:** A physical-device run on 2026-04-09 confirmed the end-to-end Shield MVP happy path: a real incoming call from `+421903223183` triggered Android call interception, a signed Laravel query, a returned Level 2 `Aggressive` match, and a visible red over-dialer warning overlay.
- ⚠️ **Remaining optional follow-up coverage:** explicit permission-denial handling, no-match physical-call evidence, and broader overlay dismissal behavior across repeated calls or device-specific OEM variants can still be recorded as hardening work, but they no longer block the current Phase 4 MVP happy-path claim.

## **Current Phase:** Phase 5 - Production Scraper Hardening (`amaterky.sk`)

### **Action**

Replace the Phase 1 dummy scraper path with a production-ready Laravel extraction pipeline. The current rollout now includes rotating-proxy telemetry, a live `amaterky.sk` portal adapter, and E.164 normalization that was validated against a real ad fetched through the rotating endpoint.

### **Current Hosted Integration State**

- ✅ The hosted Laravel backend at `https://bcuszlr92817.zafo-forum.sk` is now reachable for Android integration work.
- ✅ Real hosted `POST /api/auth/initiate` requests are succeeding against the deployed server.
- ✅ Vonage SMS API wiring is implemented on the backend and validated end to end through real trial-account SMS delivery.
- ⚠️ The current hosted OTP path is intentionally running in a temporary test configuration while Android integration proceeds:
    - the server is using Vonage trial SMS delivery
    - the server is forcing OTP delivery to a verified test number via `ESCORT_PORTAL_DEVELOPMENT_PHONE_OVERRIDE`
    - the hosted environment was temporarily switched away from `production` so that override can apply
- ✅ The override was last validated against the verified test number `+421917047260`, with the public initiate endpoint returning masked phone metadata `+421***260`.
- ⚠️ This means Android can now integrate against the real hosted backend URL, but that flow is still an integration-test path, not the final live production identity path.

### **Android Handoff Notes**

- Android should now target `https://bcuszlr92817.zafo-forum.sk` instead of a local Laravel base URL for the current integration slice.
- Android should continue to treat Laravel as the source of truth for masked phone metadata, OTP expiry, and signed verification behavior.
- During this hosted integration slice, Android should expect OTPs to arrive on the currently configured verified Vonage test number rather than on the real scraped ad number.
- Before declaring the onboarding flow production-ready, Laravel must be returned to `APP_ENV=production` and the temporary `ESCORT_PORTAL_DEVELOPMENT_PHONE_OVERRIDE` must be removed.

### **Task Breakdown**

1. ✅ **Laravel - Rotating proxy configuration and telemetry:** The backend now supports a dedicated rotating-proxy config surface in `.env` and `config/scraping.php`, plus a standalone `scraper:probe-proxy` command that records per-attempt telemetry into `scraper_proxy_attempts`. This was added first so transport health could be measured before tightening portal-specific parsing.
2. ✅ **Laravel - DOM parsing foundation:** `symfony/dom-crawler` and `symfony/css-selector` are now installed, and portal-specific DOM parsing is wired behind `EscortAdHtmlParser` rather than relying only on generic regex matching.
3. ✅ **Laravel - `amaterky.sk` extraction and normalization:** The first real portal adapter, `AmaterkySkPhoneExtractor`, is implemented. It currently prefers `a.detail-floater-link-phone[href^="tel:"]`, falls back to `a.detail-floater-link-sms[href^="sms:"]`, and then to `.card.card-contact h2`. Slovak normalization now handles local `09...`, `00421...`, and bare `421...` inputs, producing strict E.164 output.
4. ✅ **Laravel - Failure handling for the current slice:** The live HTTP client now distinguishes timeout and upstream-unavailable paths from non-usable pages. Focused fixtures cover active, SMS-only, heading-only, missing-phone, and suspended `amaterky.sk` ad shapes so the controller can preserve the retryable vs hard-stop behavior already expected by Android.
    - `amaterky.sk` ads that explicitly show `Vypnutý zadávateľom` are now classified separately from generic extraction failure. This is treated as a recognized ad state, but it is still a hard stop for the current initiate attempt because no phone-number extraction should start from that page state.
5. ⏭️ **Next Phase 5 follow-up:** The scraper architecture is now modular enough to add future portal adapters such as `eurogirlsescort.com` and `rosszlanyok.hu`, but those adapters are not part of the current completed slice.

### **Test Plan (TDD Acceptance Criteria)**

- ✅ **Laravel Test 1 (Proxy Timeout):** Covered by the rotating-proxy probe suite, which records timeout and transport-error outcomes without aborting the run.
- ✅ **Laravel Test 2 (DOM Extraction):** Covered by focused `amaterky.sk` extractor tests and fixture-backed job tests.
- ✅ **Laravel Test 3 (Normalization):** Covered by focused normalizer tests for Slovak local and dirty international-prefix input.
- ✅ **Laravel Test 4 (Missing Ad):** Covered for the current extractor slice through missing-phone and suspended fixtures returning no usable number.

### **Current Verification Status**

- ✅ **Focused proxy telemetry suite:** `ProxyProbeServiceTest` and `ProbeScraperProxyCommandTest` passed for success, blocked, timeout, transport-error, and multi-attempt recording behavior.
- ✅ **Focused extraction suite:** `ExtractPhoneFromAdJobTest`, `HttpEscortPortalClientTest`, `AmaterkySkPhoneExtractorTest`, and `EscortPhoneNumberNormalizerTest` passed together after the portal-adapter slice landed.
- ✅ **Real rotating-proxy transport validation:** repeated 100-attempt probe runs against `https://amaterky.sk/32116` improved from an initially bad Webshare pool to roughly 96-100 successful attempts after pool replacement, which is strong evidence that the transport path is operational enough for the current backend rollout.
- ✅ **Live extraction validation:** a direct Laravel execution of `ExtractPhoneFromAdJob('https://amaterky.sk/32116')` through the rotating proxy returned `+421944493008`.
- ✅ **Hosted OTP transport validation:** real hosted `POST /api/auth/initiate` requests now succeed through the deployed domain, and a Vonage trial SMS with body `Your Blacklist verification code is 322132[FREE SMS DEMO, TEST MESSAGE]` was received on a verified test number.
- ✅ **Hosted Android handoff validation:** after switching the temporary override to `+421917047260`, the public initiate endpoint returned challenge `3fc93e83-6a7e-4312-9e58-8df6305829ae` with masked phone metadata `+421***260`, proving that the hosted backend is ready for Android integration testing against the real server URL.
- ⚠️ **Remaining Phase 5 work:** broaden portal coverage beyond `amaterky.sk`, add more production hardening around page-shape drift, and decide whether periodic proxy probing should remain scheduled in non-production only or graduate into a longer-term operational signal.
