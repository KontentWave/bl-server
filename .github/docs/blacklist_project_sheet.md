## Phase 1 - Foundation & Security Handshake

### **Action**

Set up the core development infrastructure, then establish a Zero-Trust, hardware-bound authentication handshake between the Android application and the Laravel backend, ensuring the user is verified via an active escort advertisement before storing their device's Public Key.

### **Task Breakdown**

1. **Infrastructure & Environment Setup:** Laravel local infrastructure remains MariaDB-backed and testable, but the authentication protocol is being refactored. Android setup should start from the new assumption that the UI exposes only an escort ad URL field. No free phone-number entry field should exist in the client.
2. **Laravel - Setup Initial Auth Endpoint:** Refactor `/api/auth/initiate` so it accepts an escort ad URL instead of a phone number. Laravel must scrape the ad URL, extract the primary phone number server-side, generate a secure 6-digit OTP, store only its hash with a short expiration (target: 15 minutes), dispatch the OTP by SMS to the scraped phone number, and return a stable challenge identifier plus masked phone metadata to the Android client.
3. **Laravel - Build the "Ad Scraper" Job:** Refactor the scraper job into an ad-URL-first flow, for example `ExtractPhoneFromAdJob`. It must take the submitted ad URL, route requests through a proxy-capable HTTP boundary, parse the HTML, validate that a usable active ad exists, and extract the primary phone number in normalized E.164 form. This extracted number becomes the server-derived identity target for SMS verification.
4. **Android - Keystore Integration:** Create a `SecurityManager` class in Kotlin that utilizes the Android Keystore system (specifically requesting `StrongBox` hardware backing) to generate an un-exportable RSA or Elliptic Curve key pair.
5. **Android - Onboarding UI:** Build the initial Compose UI around a single user-entered field for the active escort ad URL, followed by OTP confirmation. The client should not expose a free phone-number field. After Laravel sends the OTP to the scraped number, the Android app can display only masked phone metadata and the OTP entry UI.
6. **Laravel & Android - The Handshake Endpoint:** Refactor `/api/auth/verify` so the Android app sends the server-issued challenge identifier, OTP, public key, and signature. Laravel must verify the OTP against the stored hash and expiry, verify the signature over the canonical challenge payload, and only then bind the public key to the server-scraped phone identity.

### **Accessibility (Android `ContentDescriptions`)**

- Ensure the Escort Ad URL and OTP input fields have clear `contentDescription` tags for screen readers (TalkBack).
- Ensure error states (e.g., "OTP expired" or "Ad URL invalid") are announced to the accessibility service immediately upon UI update.
- Maintain a minimum color contrast ratio of 4.5:1 for all text and warning elements in the onboarding flow.

### **Test Plan (TDD Acceptance Criteria)**

- **Environment Test 0 (NEW):** Assert that both the Laravel backend and Android client successfully compile, connect to their respective local environments, and can execute a dummy unit test to prove the testing frameworks are active.
- **Laravel Test 1:** Assert that a generated OTP expires and is unusable strictly after the configured short window (target: 15 minutes).
- **Laravel Test 2:** Assert that the ad extraction job correctly returns the normalized phone number for a known active ad URL and fails cleanly for a missing, suspended, or malformed ad.
- **Android Test 1:** Assert that the `SecurityManager` successfully generates a key pair and throws an exception if `StrongBox` hardware is unavailable (forcing a fallback or exit).
- **Integration Test 1:** Assert that a payload signed by the Android Private Key is successfully verified by the Laravel backend after OTP verification using the stored challenge identifier and Public Key.
