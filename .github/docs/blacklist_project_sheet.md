## Phase 1 - Foundation & Security Handshake

### **Action**

Set up the core development infrastructure, then establish a Zero-Trust, hardware-bound authentication handshake between the Android application and the Laravel backend, ensuring the user is verified via an active escort advertisement before storing their device's Public Key.

### **Task Breakdown**

1. **Infrastructure & Environment Setup:** Laravel is initialized and running against MariaDB in local development, with PHPUnit active and passing. The backend test environment is configured separately for MariaDB-backed tests. Android project initialization in Android Studio is still pending.
2. **Laravel - Setup Initial Auth Endpoint:** Implemented. The `/api/auth/initiate` route accepts an E.164 phone number, generates a secure randomized password valid for exactly 1 hour, stores only a hashed password in the database, invalidates any previous challenge for the same phone number, and returns a stable JSON API envelope.
3. **Laravel - Build the "Stealth Scraper" Job:** Implemented in Laravel as a backend-only verification path. `VerifyEscortAdJob` verifies a phone number against fixture-backed HTML in tests, uses a production-shaped HTTP client boundary for future real portal integration, and currently supports domain error codes for timeout, unavailable upstream, and ad-not-verified outcomes. Real target-portal-specific request building, proxy hardening, and live scraping behavior remain pending.
4. **Android - Keystore Integration:** Create a `SecurityManager` class in Kotlin that utilizes the Android Keystore system (specifically requesting `StrongBox` hardware backing) to generate an un-exportable RSA or Elliptic Curve key pair.
5. **Android - Onboarding UI:** Build the initial Compose UI screens (or XML layouts) for the user to input their phone number and the 1-hour generated password.
6. **Laravel & Android - The Handshake Endpoint:** Laravel side implemented. The `/api/auth/verify` endpoint accepts the phone number, temporary password, public key, and signature; verifies the signed payload; validates the 1-hour password; runs escort-ad verification; and binds the device public key on success. The Android client still needs to implement the request flow against the backend contract documented in `.github/docs/BACKEND_API_CONTRACT.md`.

### **Accessibility (Android `ContentDescriptions`)**

- Ensure the Phone Number and Password input fields have clear `contentDescription` tags for screen readers (TalkBack).
- Ensure error states (e.g., "Password expired") are announced to the accessibility service immediately upon UI update.
- Maintain a minimum color contrast ratio of 4.5:1 for all text and warning elements in the onboarding flow.

### **Test Plan (TDD Acceptance Criteria)**

- **Environment Test 0 (NEW):** Laravel side is satisfied: the backend compiles, connects to MariaDB, runs migrations, and executes tests successfully. Android side is still pending.
- **Laravel Test 1:** Implemented and passing. A generated password remains valid through the 60-minute boundary and becomes unusable strictly after that window.
- **Laravel Test 2:** Implemented and passing in emulation form. `VerifyEscortAdJob` returns `true` for an active fixture and `false` for a suspended or missing fixture. The parser and HTTP client boundary are also covered by tests.
- **Android Test 1:** Assert that the `SecurityManager` successfully generates a key pair and throws an exception if `StrongBox` hardware is unavailable (forcing a fallback or exit).
- **Integration Test 1:** Laravel-side signature verification is implemented and passing in backend tests using generated key pairs. Full Android-to-Laravel device integration is still pending.
