# `PROJECT_ROADMAP.md`

## 🌍 Project Vision

To build a highly secure, privacy-first Android application and Laravel backend that protects sex workers from dangerous clients. The system operates on a Zero-Trust, Hardware-Bound authentication model, ensuring that only verified workers can access or submit community-validated, cryptographic hashes of dangerous phone numbers.

## 🎯 MVP (Minimal Viable Product) Definition

The MVP will be an Android application (distributed directly via APK) and a Laravel API. It will successfully bind to a user's device hardware, verify their identity via an active escort advertisement, perform signed real-time checks against Level 2 (verified dangerous) hashed phone numbers, and display a warning overlay during incoming calls from those numbers.

---

## 🗺️ Development Phases (The Plan)

### Phase 1: Foundation & Security Handshake (The Lock)

_Goal: Establish the secure communication channel and identity verification between Android and Laravel._

Status: complete for the current MVP Phase 1 scope, including a successful end-to-end Android-to-Laravel verify/bind run on a physical Android device.

- **Laravel:** Set up the base API. Create the ad-URL-first scraper flow that fetches an active escort portal ad, extracts the phone number server-side, generates a short-lived SMS OTP, and validates the OTP plus device signature.
- **Android:** Implement hardware-backed Android Keystore key generation, accepting TEE/KeyMint-backed or StrongBox-backed devices. Build the onboarding UI around the escort ad URL first, then OTP confirmation using only the masked phone metadata returned by Laravel.
- **Integration:** Complete the handshake where the app signs the canonical challenge payload, Laravel verifies the SMS OTP and hardware signature, and Laravel stores the device's Public Key for all future authentications. This phase is now confirmed by a successful physical-device verify/bind run.

### Phase 2: Threshold Logic & Backend Database (The Brain)

_Goal: Implement the Level 1 (Buffer) and Level 2 (Active) reporting logic securely on the server._

Status: complete for the current MVP Phase 2 scope, with reporting, duplicate prevention, and Level 2 promotion covered by backend tests.

- **Laravel:** Design the database schema to store _only_ SHA-256 hashes of client numbers and worker numbers.
- **Laravel:** Define the fixed list of reportable features (e.g., Aggressive, No-Show, Refused Protection).
- **Laravel:** Implement the logic that counts unique reporter hashes per feature. Create the trigger that promotes a client from Level 1 (invisible) to Level 2 (syncable) when the 3-unique-reporter threshold is met.

### Phase 3: Real-Time Query API (The Oracle)

_Goal: Establish a high-speed, Zero-Knowledge query endpoint to check incoming numbers in real-time without storing the database locally._

Status: complete for the current MVP scope. The Laravel query endpoint is implemented and the Android client now exercises the signed query path during the Phase 4 Shield flow.

- **Laravel:** Implement `POST /api/blacklist/check`. This endpoint accepts a `target_hash` (the SHA-256 of an incoming caller), verifies the hardware-bound device signature, and returns any associated Level 2 features (e.g., `["Aggressive"]`) or an empty array.
- **Laravel:** Optimize the database indexing for the promoted-feature lookup path so real-time reads stay fast.
- **Android:** Prepare the Retrofit network layer to securely formulate and sign this high-speed query, keeping the app strictly as a "Thin Client."

### Phase 4: Call Interception & UI Shield (The Shield)

_Goal: The core user experience—detecting calls and warning the worker._

Status: complete for the current MVP happy path. A physical-device run confirmed incoming-call interception, signed caller lookup, and a visible over-dialer warning overlay for a prepared Level 2 caller match.

- **Android:** Implement a `BroadcastReceiver` to detect incoming calls (`READ_PHONE_STATE`, `READ_CALL_LOG`).
- **Android:** Build the real-time hashing logic to convert the incoming caller ID to SHA-256 and call the signed Laravel query API instantly.
- **Android:** Implement the `SYSTEM_ALERT_WINDOW` permission to display the red warning overlay containing the Level 2 features if a match is found.
- **Android:** Build the UI screen for a worker to submit a new report (select from predefined features, hash the number, and send to Laravel).

### Phase 5: Production Scraper Hardening (`amaterky.sk`)

_Goal: Replace the Phase 1 dummy scraper path with a production-ready backend extraction pipeline that can survive real portal markup and rotating-proxy conditions._

Status: in progress, with the first backend slice complete and live-validated. Rotating-proxy telemetry is implemented, `amaterky.sk` parsing now uses a dedicated portal adapter, and a live extraction run returned `+421944493008` from `https://amaterky.sk/32116`.

Current integration note: the hosted backend at `https://bcuszlr92817.zafo-forum.sk` is now ready for Android integration testing, but OTP onboarding is still running in a temporary trial-SMS testing mode. Android should use the hosted URL now, while the team should avoid treating the current override-backed OTP path as final production behavior until `APP_ENV=production` and the phone override are restored to true live settings.

- **Laravel:** Centralize rotating-proxy settings and reuse them in both the probe tooling and the live escort-portal HTTP client.
- **Laravel:** Record per-attempt transport telemetry so proxy-pool health can be separated from parser bugs.
- **Laravel:** Route ad parsing through portal-specific adapters, starting with `amaterky.sk` and selector priority `tel:` -> `sms:` -> contact heading.
- **Laravel:** Normalize Slovak phone-number formats into strict E.164 so Phase 1 verification and Phase 4 incoming-call matching stay aligned.
- **Next:** add more portal adapters and long-term hardening once the current `amaterky.sk` slice remains stable.

---

## 🔮 Backlog / Future Enhancements (Post-MVP)

- **iOS Implementation:** Adapting the app for iOS using Alternative Web Distribution and the Call Directory Extension.
- **Automated Ad Renewal:** Periodically re-verifying the escort ad to ensure the worker is still active, revoking hardware keys if the ad disappears for a long time.
- **Self-Destruct Mechanism:** A panic button or remote wipe command that instantly deletes the local Android Encrypted Room database and local Keystore keys.
- **Infrastructure Cloaking (Cloudflare Tunnels & WAF):** Route all backend API traffic through Cloudflare to hide the origin server IP, mitigate DDoS attacks, and enforce strict rate-limiting on the pre-auth endpoints.
