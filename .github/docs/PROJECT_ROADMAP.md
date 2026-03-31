# `PROJECT_ROADMAP.md`

## 🌍 Project Vision

To build a highly secure, privacy-first Android application and Laravel backend that protects sex workers from dangerous clients. The system operates on a Zero-Trust, Hardware-Bound authentication model, ensuring that only verified workers can access or submit community-validated, cryptographic hashes of dangerous phone numbers.

## 🎯 MVP (Minimal Viable Product) Definition

The MVP will be an Android application (distributed directly via APK) and a Laravel API. It will successfully bind to a user's device hardware, verify their identity via an active escort advertisement, sync a local encrypted database of "Level 2" (verified dangerous) hashed phone numbers, and display a warning overlay during incoming calls from those numbers.

---

## 🗺️ Development Phases (The Plan)

### Phase 1: Foundation & Security Handshake (The Lock)

_Goal: Establish the secure communication channel and identity verification between Android and Laravel._

- **Laravel:** Set up the base API. Create the "Stealth Scraper" logic to verify a phone number against an active escort portal ad. Implement the 1-hour temporary password generation and validation endpoints.
- **Android:** Implement the Android Keystore (StrongBox) hardware key generation. Build the onboarding UI to accept the phone number and 1-hour password.
- **Integration:** Complete the handshake where the app signs the payload, Laravel verifies the ad, and Laravel stores the device's Public Key for all future authentications.
