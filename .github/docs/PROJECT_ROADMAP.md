# `PROJECT_ROADMAP.md`

## 🌍 Project Vision

To build a highly secure, privacy-first Android application and Laravel backend that protects sex workers from dangerous clients. The system operates on a Zero-Trust, Hardware-Bound authentication model, ensuring that only verified workers can access or submit community-validated, cryptographic hashes of dangerous phone numbers.

## 🎯 MVP (Minimal Viable Product) Definition

The MVP will be an Android application (distributed directly via APK) and a Laravel API. It will successfully bind to a user's device hardware, verify their identity via an active escort advertisement, sync a local encrypted database of "Level 2" (verified dangerous) hashed phone numbers, and display a warning overlay during incoming calls from those numbers.

---

## 🗺️ Development Phases (The Plan)

### Phase 1: Foundation & Security Handshake (The Lock)

_Goal: Establish the secure communication channel and identity verification between Android and Laravel._

- **Laravel:** Set up the base API. Create the ad-URL-first scraper flow that fetches an active escort portal ad, extracts the phone number server-side, generates a short-lived SMS OTP, and validates the OTP plus device signature.
- **Android:** Implement the Android Keystore (StrongBox) hardware key generation. Build the onboarding UI around the escort ad URL first, then OTP confirmation using only the masked phone metadata returned by Laravel.
- **Integration:** Complete the handshake where the app signs the canonical challenge payload, Laravel verifies the SMS OTP and hardware signature, and Laravel stores the device's Public Key for all future authentications.

### Phase 2: Threshold Logic & Backend Database (The Brain)

_Goal: Implement the Level 1 (Buffer) and Level 2 (Active) reporting logic securely on the server._

- **Laravel:** Design the database schema to store _only_ SHA-256 hashes of client numbers and worker numbers.
- **Laravel:** Define the fixed list of reportable features (e.g., Aggressive, No-Show, Refused Protection).
- **Laravel:** Implement the logic that counts unique reporter hashes per feature. Create the trigger that promotes a client from Level 1 (invisible) to Level 2 (syncable) when the 3-unique-reporter threshold is met.

### Phase 3: Encrypted Sync & Background Operations (The Vault)

_Goal: Securely push Level 2 data to the phone without draining the battery._

- **Android:** Implement SQLCipher (Encrypted Room Database) to store the Level 2 blacklist locally.
- **Laravel & Android:** Integrate Firebase Cloud Messaging (FCM) for silent, data-only push notifications.
- **Android:** Set up `WorkManager` to wake up upon receiving a silent push, securely authenticate with Laravel, and download/sync the latest Level 2 hashes into the local database.

### Phase 4: Call Interception & UI Shield (The Shield)

_Goal: The core user experience—detecting calls and warning the worker._

- **Android:** Implement a `BroadcastReceiver` to detect incoming calls (`READ_PHONE_STATE`, `READ_CALL_LOG`).
- **Android:** Build the real-time hashing logic to convert the incoming caller ID to SHA-256 and query the local Encrypted Room database instantly.
- **Android:** Implement the `SYSTEM_ALERT_WINDOW` permission to display the red warning overlay containing the Level 2 features if a match is found.
- **Android:** Build the UI screen for a worker to submit a new report (select from predefined features, hash the number, and send to Laravel).

---

## 🔮 Backlog / Future Enhancements (Post-MVP)

- **iOS Implementation:** Adapting the app for iOS using Alternative Web Distribution and the Call Directory Extension.
- **Automated Ad Renewal:** Periodically re-verifying the escort ad to ensure the worker is still active, revoking hardware keys if the ad disappears for a long time.
- **Self-Destruct Mechanism:** A panic button or remote wipe command that instantly deletes the local Android Encrypted Room database and local Keystore keys.
- **Infrastructure Cloaking (Cloudflare Tunnels & WAF):** Route all backend API traffic through Cloudflare to hide the origin server IP, mitigate DDoS attacks, and enforce strict rate-limiting on the pre-auth endpoints.
