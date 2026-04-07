## Phase 1 - Foundation & Security Handshake

Status: complete for the current Phase 1 scope. Implementation progress and evidence are tracked in `docs/PHASE_1_ANDROID_PROGRESS.md`.

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

## **Current Phase:** Phase 2 - Threshold Logic & Backend Database

Status: complete for the current Phase 2 MVP scope. The implemented reporting flow and verification evidence are now tracked in the backend code, tests, and ADRs.

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
