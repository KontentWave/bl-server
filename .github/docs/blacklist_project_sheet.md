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
