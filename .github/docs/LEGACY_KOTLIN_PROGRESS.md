# Blacklist Android Progress

- Status: complete
- Last updated: 2026-04-09
- Scope: Android client implementation completed through the current Phase 4 Shield MVP happy path, including physical-device validation of the incoming-call warning overlay against a Level 2 caller match

## Summary

The Android client now implements the Phase 1 onboarding flow defined by `docs/blacklist_project_sheet.md`, `docs/ADRs/1_foundation+security_handshake.md`, and `docs/BACKEND_API_CONTRACT.md`.

The current app supports:

- escort ad URL initiation only
- OTP challenge initiation against `POST /api/auth/initiate`
- OTP verification against `POST /api/auth/verify`
- signed report submission against `POST /api/reports`
- signed blacklist queries against `POST /api/blacklist/check`
- tolerant parsing for backend success envelopes that return `meta` as either `{}` or `[]`
- hardware-backed signing payload generation through `SecurityManager`
- canonical challenge payload signing with normalized PEM public key
- onboarding UI transition from ad URL entry to OTP entry to verified success
- verified-home navigation into Phase 2 reporting and Phase 3 query screens
- verified-home Shield readiness status with permission and overlay actions for Phase 4 setup
- manifest-registered incoming-call interception that normalizes and hashes caller numbers and reuses the signed Phase 3 query stack
- verified-home live Shield diagnostics for the latest ringing/query event
- `WindowManager`-driven warning overlay with accessibility announcement and processor-driven show/dismiss decisions
- accessibility semantics and Android UI/instrumentation test scaffolding

This means the Android client is currently complete through the documented Phase 3 MVP scope, and the current Phase 4 Shield MVP happy path is also implemented and physically validated. The app now includes the Shield readiness foundation, the incoming-call interception plus signed caller-query pipeline, and a live warning overlay path that was confirmed on a physical phone for a prepared Level 2 caller match, while reusing the already implemented Phase 2 reporting UI. Remaining Phase 4 work is now limited to optional hardening and broader edge-case coverage, not the first end-to-end runtime proof.

Note: the manual runtime observations recorded on 2026-04-04 and 2026-04-05 below were captured before the 2026-04-06 Android refactor away from a StrongBox-only client requirement. Those results remain useful historical evidence, but they should not be treated as the current client-side hardware policy.

## Implemented Slices

### Slice 1: Android foundation

Implemented:

- Kotlin/Compose Android app foundation
- `MainActivity` Compose host
- app theme and onboarding starter UI
- Gradle wrapper repair and Git repository initialization

Relevant commits:

- `79eeef4` Initialize Android foundation and security handshake scaffold

### Slice 2: Security foundation

Implemented:

- `SecurityManager` Android Keystore integration
- hardware-backed key enforcement with TEE/KeyMint-or-StrongBox acceptance
- PEM export and normalization
- canonical JSON payload creation
- Base64 signature encoding helpers

Key files:

- `app/src/main/java/com/example/myapplication/security/SecurityManager.kt`
- `app/src/main/java/com/example/myapplication/security/PublicKeyPemEncoder.kt`
- `app/src/main/java/com/example/myapplication/security/CanonicalPayloadFactory.kt`
- `app/src/main/java/com/example/myapplication/security/SignatureEncoder.kt`

### Slice 3: Initiate-auth networking

Implemented:

- `POST /api/auth/initiate` API integration
- response envelope mapping for initiation success/failure
- repository layer for challenge initiation
- onboarding state for ad URL submission and challenge display

Relevant commit:

- `5b0029e` Add initiate-auth networking and onboarding state

### Slice 4: OTP verify plus signed device binding

Implemented:

- `POST /api/auth/verify` API integration
- verify request models for `challenge_id`, `otp`, `public_key`, `signature`
- signed payload creation via `SecurityManager`
- onboarding OTP entry state and verified success state
- hard-stop handling for non-retryable verification failures

Relevant commit:

- `dd6199d` Add OTP verification and signed device binding flow

### Slice 5: Accessibility and Android test hardening

Implemented:

- live-region accessibility hints for error and status updates
- stable Compose test tags for onboarding states
- Compose UI tests for ad URL, OTP, and verified states
- instrumented `SecurityManager` tests for key generation and deterministic hardware-unavailable behavior

### Slice 6: Hardware-security policy refactor

Implemented:

- removed the Android client dependency on StrongBox-specific key generation
- retained the same Laravel-facing `public_key` plus `signature` verify contract
- switched client enforcement to hardware-backed Android Keystore material that may be backed by TEE/KeyMint or StrongBox
- added post-generation keystore inspection so software-only keys are rejected before verify-signing proceeds

Relevant workspace changes:

- `app/src/main/java/com/example/myapplication/security/SecurityManager.kt`
- `app/src/androidTest/java/com/example/myapplication/security/SecurityManagerInstrumentedTest.kt`
- `docs/ADRs/1_foundation+security_handshake.md`
- `docs/blacklist_project_sheet.md`

Relevant commit:

- `dda5402` Add onboarding accessibility and Android test coverage

### Slice 7: Phase 4 Shield readiness foundation

Implemented:

- Phase 4 Shield readiness domain model and evaluator
- Android permission checker for `READ_PHONE_STATE`, `READ_CALL_LOG`, and overlay capability
- verified-home Shield status card with active / action-required / blocked states
- direct verified-home actions for phone permission requests, overlay settings, and readiness refresh
- initial Phase 4 test coverage for Shield state classification and verified-home UI rendering

Key files:

- `app/src/main/java/com/example/myapplication/shield/ShieldReadiness.kt`
- `app/src/main/java/com/example/myapplication/shield/ShieldPermissionChecker.kt`
- `app/src/main/java/com/example/myapplication/MainActivity.kt`
- `app/src/main/java/com/example/myapplication/ui/home/HomeScreen.kt`
- `app/src/androidTest/java/com/example/myapplication/ui/home/HomeScreenTest.kt`
- `app/src/test/java/com/example/myapplication/shield/ShieldReadinessEvaluatorTest.kt`

### Slice 8: Phase 4 incoming-call query foundation

Implemented:

- manifest-registered incoming-call receiver for ringing-state events
- pure caller normalization to E.164-oriented format for local/device testing
- local SHA-256 hashing for normalized caller numbers
- reuse of the existing signed Phase 3 blacklist-query repository from the receiver path
- persisted live Shield diagnostics surfaced on the verified-home screen for manual testing
- focused unit coverage for caller normalization, hash parity, and incoming-call processor outcomes

Key files:

- `app/src/main/java/com/example/myapplication/shield/IncomingCallReceiver.kt`
- `app/src/main/java/com/example/myapplication/shield/IncomingCallProcessor.kt`
- `app/src/main/java/com/example/myapplication/shield/CallerNumberNormalizer.kt`
- `app/src/main/java/com/example/myapplication/shield/CallerNumberHasher.kt`
- `app/src/main/java/com/example/myapplication/shield/ShieldLiveStatus.kt`
- `app/src/main/java/com/example/myapplication/data/BlacklistQueryRepositoryProvider.kt`
- `app/src/test/java/com/example/myapplication/shield/CallerNumberNormalizerTest.kt`
- `app/src/test/java/com/example/myapplication/shield/IncomingCallProcessorTest.kt`

### Slice 9: Phase 4 warning overlay foundation

Implemented:

- `WindowManager`-based warning presenter for Level 2 caller matches
- accessibility announcement path for the warning overlay
- processor-driven overlay show on match and stale-overlay dismissal on new ringing/no-match paths
- persisted overlay outcome diagnostics in the verified-home Shield card
- focused processor tests for shown vs dismissed overlay outcomes

Key files:

- `app/src/main/java/com/example/myapplication/shield/ShieldWarningPresenter.kt`
- `app/src/main/java/com/example/myapplication/shield/WindowManagerShieldWarningPresenter.kt`
- `app/src/main/res/layout/shield_warning_overlay.xml`
- `app/src/main/java/com/example/myapplication/shield/IncomingCallProcessor.kt`
- `app/src/main/java/com/example/myapplication/shield/ShieldLiveStatus.kt`
- `app/src/test/java/com/example/myapplication/shield/IncomingCallProcessorTest.kt`

## Verification Evidence

### Confirmed build/test runs already executed

The following validations were executed successfully during implementation:

- `:app:assembleDebug`
- `:app:testDebugUnitTest`
- `:app:assembleDebugAndroidTest`
- `:app:connectedDebugAndroidTest`

This confirms:

- the Android app currently compiles
- unit tests compile and run successfully
- Android instrumented/UI test sources compile and package successfully
- connected Android tests execute successfully on a real emulator runtime

Additional validation executed on 2026-04-04:

- `:app:testDebugUnitTest --tests com.example.myapplication.data.AuthRepositoryTest`
- `:app:assembleDebug`

This additionally confirms:

- the repository layer now tolerates backend success envelopes where `meta` is an empty array
- the app no longer crashes on the known Laravel local success response shape

Additional validation executed on 2026-04-06 for the hardware-security refactor:

- `:app:assembleDebug`
- `:app:testDebugUnitTest`
- `:app:assembleDebugAndroidTest`

Result:

- `PASS` for compile, unit tests, and Android test packaging after replacing the StrongBox-only requirement with hardware-backed Android Keystore validation that accepts TEE/KeyMint-backed devices.
- `:app:connectedDebugAndroidTest` could not run in the local terminal session because no emulator or physical device was connected at execution time.

Additional validation executed on 2026-04-07 for Laravel signature parity:

- `:app:testDebugUnitTest --tests com.example.myapplication.security.CanonicalPayloadFactoryTest`
- `:app:testDebugUnitTest`
- `:app:assembleDebug`

Result:

- `PASS` for a canonical-payload fix that now escapes forward slashes as `\/`, matching Laravel/PHP `json_encode(...)` behavior.
- The most likely cause of the previously observed `auth.verify.rejected.invalid_signature` runtime failure was Android signing a payload with raw `/` characters while Laravel verified a payload with escaped `\/` characters.

Additional validation executed on 2026-04-09 for the initial Phase 4 Shield slice:

- `:app:testDebugUnitTest`
- `:app:assembleDebug`
- `:app:assembleDebugAndroidTest`

Result:

- `PASS` after adding the Shield readiness model, Android permission checker, verified-home readiness card, and Phase 4 state/UI tests.
- This validates the first implemented Phase 4 slice without claiming that incoming-call interception or the warning overlay are complete yet.

Additional validation executed on 2026-04-09 for the incoming-call query slice:

- `:app:testDebugUnitTest --tests com.example.myapplication.shield.ShieldReadinessEvaluatorTest --tests com.example.myapplication.shield.CallerNumberNormalizerTest --tests com.example.myapplication.shield.IncomingCallProcessorTest`
- `:app:assembleDebug`
- `:app:assembleDebugAndroidTest`

Result:

- `PASS` after adding the manifest-registered incoming-call receiver, caller normalization/hash utilities, shared query provider reuse, and verified-home live diagnostics.
- This validates the receiver/query foundation, but it does not yet prove a user-visible over-dialer warning because the overlay slice is still pending.

Additional validation executed on 2026-04-09 for the warning overlay slice:

- `:app:testDebugUnitTest`
- `:app:assembleDebug`
- `:app:assembleDebugAndroidTest`

Result:

- `PASS` after adding the `WindowManager` warning presenter, overlay outcome persistence, and processor-driven overlay show/dismiss behavior.
- The overlay implementation is now present in the Android workspace, but real phone-call validation is still required before claiming end-to-end Shield runtime completion.

### Physical-device Phase 4 Shield live validation recorded on 2026-04-09

Execution recorded on 2026-04-09 using:

- runtime: physical Android device
- device: `Redmi A5`
- Android version: `15`
- backend mode: local Laravel development server with manually seeded Level 2 caller state
- backend base URL from device during local development: `http://127.0.0.1:8000/api/` via `adb reverse`
- tested incoming caller: `+421903223183`
- prepared Level 2 backend feature for the caller: `Aggressive`

Observed manual flow:

1. The verified phone had already completed the Phase 1 device-binding flow and showed Shield permissions as available in the verified-home app shell.
2. Laravel-side data was manually seeded so caller `+421903223183` existed as a promoted Level 2 target for feature `aggressive`.
3. A real incoming call from `+421903223183` reached the verified worker phone.
4. The Android Shield pipeline intercepted the ringing event, normalized and hashed the caller, and sent the signed Phase 3 query request.
5. Laravel returned a Level 2 match for feature `Aggressive`.
6. Android displayed the red over-dialer warning overlay during the incoming call and the verified-home diagnostics later showed that the overlay outcome was `shown`.

Observed Android evidence:

- call-screen overlay headline: `Warning: reported caller`
- call-screen overlay body confirmed the incoming caller `+421903223183`
- call-screen overlay body confirmed returned Level 2 feature: `Aggressive`
- call-screen overlay body displayed the target hash `47663b65b3296181d6d52e5e6fe0758302a2e944c9b4f7482bd5bbb73e196741`
- verified-home Shield status confirmed: `All required permissions are available. Incoming-call monitoring and overlay warning setup are ready.`
- verified-home live diagnostics confirmed: `Level 2 match returned`
- verified-home live diagnostics confirmed: `Overlay outcome: shown`

Observed Android result summary:

- physical incoming-call interception: `PASS`
- caller normalization/hash pipeline for the tested call: `PASS`
- signed Shield query dispatch on physical device: `PASS`
- Level 2 feature match rendering on physical device: `PASS`
- over-dialer warning overlay display on physical device: `PASS`
- verified-home post-call diagnostics for the same event: `PASS`

Conclusion:

- the core Phase 4 Shield user experience is now validated on a physical Android device
- a prepared Level 2 caller match successfully triggers the red warning overlay during a real incoming call
- Phase 4 no longer depends on first-time live validation; remaining work is optional hardening, repeatability, and additional edge-path coverage

### Connected Android test execution recorded

Execution recorded on 2026-04-03 using:

- runtime: Android Emulator
- device: `Medium_Phone(AVD)`
- Android version: `7.0`
- API level: `24`

Environment note:

- emulator boot recovery succeeded after running `Wipe Data` on the AVD

Observed result summary:

- total connected tests: `6`
- failures: `0`
- skipped: `0`
- overall result: `PASS`

Per-test-class result summary:

- `ExampleInstrumentedTest`: `1/1` passed
- `SecurityManagerInstrumentedTest`: `2/2` passed
- `OnboardingScreenTest`: `3/3` passed

### Unit tests currently present

- `app/src/test/java/com/example/myapplication/security/CanonicalPayloadFactoryTest.kt`
- `app/src/test/java/com/example/myapplication/data/AuthRepositoryTest.kt`

### Android instrumented / UI tests currently present

- `app/src/androidTest/java/com/example/myapplication/ui/onboarding/OnboardingScreenTest.kt`
- `app/src/androidTest/java/com/example/myapplication/security/SecurityManagerInstrumentedTest.kt`

### Manual Android-to-Laravel run recorded

Execution recorded on 2026-04-04 using:

- runtime: Android Emulator
- device: `Medium_Phone(AVD)`
- Android version: `7.0`
- API level: `24`
- backend mode: Laravel fixture-backed escort portal client
- backend base URL from emulator: `http://10.0.2.2:8000/api/`
- test ad URL: `https://portal.example.test/escort/miriam`

Observed manual flow:

1. Android submitted the escort ad URL successfully.
2. Laravel completed escort ad extraction and issued an OTP challenge.
3. Laravel logged `sms.otp_dispatched` for masked phone `+421***456`.
4. Android transitioned from the ad URL step to the OTP-entry screen successfully.
5. The previous app crash on successful initiate was fixed by tolerating backend `meta: []` success envelopes.
6. After OTP entry, Android failed locally before calling `POST /api/auth/verify` because the pre-refactor `SecurityManager` required StrongBox and this emulator was below Android 9.

Observed backend evidence:

- `auth.initiate.sms_challenge_created` logged successfully
- latest OTP used during manual run: `851001`
- latest challenge id observed during manual run: `d217474b-b212-4dd1-bcd5-ac9d772e5b5d`
- no successful verify call was completed from this emulator because key generation/signing was blocked client-side

Observed Android result summary:

- initiate request: `PASS`
- OTP screen transition: `PASS`
- OTP retrieval from Laravel logs: `PASS`
- verify request dispatch from Android: `BLOCKED`
- reason: `StrongBox is required but unavailable on Android versions below 9.`
- challenge state after local verify attempt: closed until a new challenge is started

### Additional manual emulator run recorded

Execution recorded on 2026-04-04 using:

- runtime: Android Emulator
- device: `Pixel 8(AVD)`
- Android version: `14`
- API level: `34`
- backend mode: Laravel fixture-backed escort portal client
- backend base URL from emulator: `http://10.0.2.2:8000/api/`
- test ad URL: `https://portal.example.test/escort/miriam`

Observed manual flow:

1. The first API 34 attempt did not reach Laravel because cleartext HTTP to the local backend was blocked on newer Android.
2. The Android debug build was updated to allow debug-only cleartext traffic to the local backend.
3. After reinstalling the debug build, Android reached Laravel successfully and created a new OTP challenge.
4. Laravel logged `sms.otp_dispatched` and Android transitioned to the OTP-entry screen successfully.
5. After OTP entry, Android again failed locally before calling `POST /api/auth/verify`, but this time with a pre-refactor post-Android-9 failure mode: `StrongBox-backed key generation failed on this device.`

Observed Android result summary:

- initiate request on API 34 emulator: `PASS`
- OTP screen transition on API 34 emulator: `PASS`
- verify request dispatch from Android on API 34 emulator: `BLOCKED`
- reason: `StrongBox-backed key generation failed on this device.`
- interpretation: newer emulator passed the Android-version gate but still did not satisfy the old StrongBox-only hardware requirement

### Manual physical-device run recorded

Execution recorded on 2026-04-05 using:

- runtime: physical Android device
- device: `Redmi A5`
- Android version: `15`
- backend mode: Laravel fixture-backed escort portal client
- backend base URL from device during local development: `http://127.0.0.1:8000/api/` via `adb reverse`
- test ad URL: `https://portal.example.test/escort/miriam`

Observed manual flow:

1. The debug build was installed on the physical phone and reached Laravel successfully through the local USB/reverse-tunnel setup.
2. Android submitted the escort ad URL successfully and Laravel created a new SMS challenge.
3. Laravel logged `sms.otp_dispatched` and Android transitioned to the OTP-entry screen successfully.
4. After OTP entry, Android again failed locally before calling `POST /api/auth/verify` with the pre-refactor message: `StrongBox-backed key generation failed on this device.`
5. Android closed the current verification attempt and required a new SMS challenge before another try.

Observed backend evidence:

- `auth.initiate.sms_challenge_created` logged successfully for the physical-device run
- latest OTP used during the recorded physical-device run: `919043`
- latest challenge id observed during the recorded physical-device run: `77faaf8a-08e5-4636-b0cf-e4234f264ca4`
- no `POST /api/auth/verify` request reached Laravel from this device because key generation/signing was blocked client-side

Observed Android result summary:

- initiate request on physical device: `PASS`
- OTP screen transition on physical device: `PASS`
- OTP retrieval from Laravel logs on physical device: `PASS`
- verify request dispatch from Android on physical device: `BLOCKED`
- reason: `StrongBox-backed key generation failed on this device.`
- interpretation: this Android 15 physical device did not satisfy the old StrongBox-only requirement at the time of that run

## Optional Follow-up Validation

The following work is optional follow-up work and should be recorded separately if executed:

1. Optionally repeat the successful verify/bind run on an emulator for additional non-physical-runtime evidence.
2. Optionally remove or reduce temporary debug diagnostics after the Phase 1 investigation is fully closed.

Important: connected tests are now recorded as executed successfully on an emulator/device runtime.

## Laravel-side diagnostic checklist for the remaining `invalid_signature` issue

Use this section for the server-side investigation of the latest confirmed failing verify attempt.

### Latest confirmed failing verify attempt

- runtime: physical Android device
- device: `Redmi A5`
- Android version: `15`
- backend base URL from device during local development: `http://127.0.0.1:8000/api/` via `adb reverse`
- result: Laravel reached `auth.verify.attempted` but rejected with `auth.verify.rejected.invalid_signature`

### Exact Android-side values from the failing attempt

- `challenge_id`: `9683690b-e58a-4c4a-976e-66dd93c7e12b`
- `public_key_sha256`: `b0873abfc5e5c3f08caab28ab255a4f9b40813f7bc49c121ee10d127f6dde5e8`
- `canonical_payload_sha256`: `d223905b0b6e9fdc424a61380526d83defe7ef9c2c525153b261a29d951efbf8`
- `signature_sha256`: `efb63bdcb8f477e5c7800bc8a8a85ae9092339720b57ae19bcd0b807b71a4c21`
- decoded signature byte length: `70`
- decoded signature first byte: `30`
- Android local PEM re-parse self-verification result: `true`

### What Laravel should compare for this exact request

1. Confirm Laravel is verifying the same `challenge_id`:
    - expected: `9683690b-e58a-4c4a-976e-66dd93c7e12b`

2. Confirm Laravel normalizes the inbound `public_key` to the same final PEM bytes that Android signed:
    - expected normalized PEM SHA-256: `b0873abfc5e5c3f08caab28ab255a4f9b40813f7bc49c121ee10d127f6dde5e8`

3. Confirm Laravel builds the exact same canonical JSON payload before verification:
    - expected canonical payload SHA-256: `d223905b0b6e9fdc424a61380526d83defe7ef9c2c525153b261a29d951efbf8`
    - important: this payload must match the contract in `docs/BACKEND_API_CONTRACT.md`, including compact JSON field order and slash escaping produced by PHP `json_encode(...)`

4. Confirm Laravel decodes the inbound Base64 signature into the same bytes Android generated:
    - expected decoded signature SHA-256: `efb63bdcb8f477e5c7800bc8a8a85ae9092339720b57ae19bcd0b807b71a4c21`
    - expected decoded signature length: `70`
    - expected first byte: `30`

5. Confirm Laravel verifies with the same signature semantics Android used:
    - Android generated a DER-encoded ECDSA signature with `SHA256withECDSA`
    - Android can successfully re-parse the exact outbound PEM and verify the same signature locally

### Backend questions that should be answered explicitly

- Does Laravel compute the same normalized `public_key` hash as Android?
- Does Laravel compute the same canonical payload hash as Android?
- Does Laravel decode the same signature bytes as Android?
- If all three hashes match, is Laravel using the correct OpenSSL/verification API and ECDSA DER expectations?
- If one of the hashes differs, at which transformation step does Laravel diverge:
    - PEM normalization
    - canonical payload creation
    - Base64 signature decoding
    - key loading / verification call

### Current Android-side conclusion

For the latest failing request, Android has already proven locally that:

- the PEM sent to Laravel can be parsed back into a public key,
- the canonical payload created on Android verifies against that PEM,
- the generated signature is internally consistent with the Android-side key and payload.

That earlier conclusion is now outdated for the latest reproduced request.

### Confirmed Laravel-vs-Android mismatch for the latest reproduced request

For challenge id `7babb8ee-e021-4d0c-a2ec-8d4d2cbeeeaa`, Laravel-side diagnostics and the Android debug panel now prove the following:

- Android `public_key_sha256`: `a47352e460417c295672d7d901b8ffab928b6e804636f9abcb05359561d30991`
- Laravel `normalized_public_key_sha256`: `a47352e460417c295672d7d901b8ffab928b6e804636f9abcb05359561d30991`
- Android `signature_sha256`: `93e454880b1a3778fd2e7ca7ff0a05cbcdfd699a98fd268640a65191f69b1fa5`
- Laravel `signature_sha256`: `93e454880b1a3778fd2e7ca7ff0a05cbcdfd699a98fd268640a65191f69b1fa5`
- Android `signature bytes`: `71`
- Laravel `signature_length`: `71`
- Android `signature first byte`: `30`
- Laravel `signature_first_byte`: `30`

These values match.

The confirmed divergence is the canonical payload hash:

- Android `canonical_payload_sha256`: `7b126e93759c4748f7bf315ab1baecc940768fe3e25a04a1e97aae357b1ccdd0`
- Laravel `canonical_payload_sha256`: `1295ef3972c5d592053d51d6990781915b902d2f94f1797a4aa29d0b1af49b29`

Laravel also successfully loaded the public key and attempted verification for this request:

- `public_key_loaded = true`
- `openssl_key_type = 3` (EC)
- `openssl_verify_result = 0`

This means the remaining bug is not PEM transport, Base64 decoding, or OpenSSL key loading. The remaining bug is that Android is signing a different canonical payload byte sequence than Laravel is verifying.

### Note for Android Copilot

Please inspect the Android canonical payload builder for the verify request path.

The next thing to compare is the exact compact JSON string bytes used for signing on Android versus Laravel's PHP `json_encode(...)` output for the same request.

Focus on:

- field order: `challenge_id` first, `public_key` second
- exact PEM string used inside the JSON payload after Android normalization
- whether Android is including any extra whitespace or line changes in `public_key`
- whether Android is escaping forward slashes, newlines, and other characters exactly like PHP `json_encode(...)`
- whether the payload shown in Android diagnostics is generated from the exact outbound `challenge_id` and exact outbound PEM, not from a stale in-memory value

At this point, Android should log or display the exact canonical JSON string being signed for challenge id `7babb8ee-e021-4d0c-a2ec-8d4d2cbeeeaa`, then compare it byte-for-byte with Laravel's canonical payload construction in `App\Services\DeviceSignatureService::payload(...)`.

### Likely Android root cause identified on 2026-04-07

The latest Android debug payload plus Laravel diagnostics strongly suggest an Android-side PEM construction bug in `PublicKeyPemEncoder.toPem(...)`.

The previous implementation used a triple-quoted string with a multi-line interpolated Base64 body plus `trimIndent()`. That pattern can preserve hidden indentation on internal PEM lines even though the PEM still looks visually correct in the UI.

Impact:

- Android could still re-parse and self-verify the PEM/signature locally,
- but Laravel would receive a different normalized `public_key` string and therefore build a different canonical payload byte sequence.

Fix implemented in Android workspace:

- replaced the triple-quoted PEM template with explicit string concatenation in `app/src/main/java/com/example/myapplication/security/PublicKeyPemEncoder.kt`
- added regression coverage in `app/src/test/java/com/example/myapplication/security/PublicKeyPemEncoderTest.kt`
- re-ran `:app:testDebugUnitTest` and `:app:assembleDebug` successfully after the fix

Status:

- code fix implemented
- local tests passing
- confirmed on a fresh physical-device retest: Laravel now accepts the Android verify request and binds the device successfully

### Successful physical-device verify/bind run recorded on 2026-04-07

Execution recorded on 2026-04-07 using:

- runtime: physical Android device
- device: `Redmi A5`
- Android version: `15`
- backend mode: Laravel fixture-backed escort portal client
- backend base URL from device during local development: `http://127.0.0.1:8000/api/` via `adb reverse`
- test ad URL: `https://portal.example.test/escort/miriam`

Observed manual flow:

1. Android submitted the escort ad URL successfully.
2. Laravel completed escort ad extraction and issued a new OTP challenge.
3. Android displayed the debug diagnostics card showing the corrected PEM/canonical-payload path.
4. The user entered the latest OTP and submitted verification.
5. Android transitioned to the success state and displayed `Device verified`.
6. Laravel logged `auth.verify.bound`, confirming that OTP verification and device binding both succeeded.

Observed backend evidence:

- latest OTP used during the successful run: `323139`
- successful challenge id: `fff1d20f-1289-4e70-a40d-336991f65bad`
- Laravel success log: `auth.verify.bound`
- bound device id observed in Laravel log: `1`

Observed Android result summary:

- initiate request on physical device: `PASS`
- OTP screen transition on physical device: `PASS`
- OTP retrieval from Laravel logs on physical device: `PASS`
- verify request dispatch from Android on physical device: `PASS`
- device binding result on physical device: `PASS`
- final user-visible state: `Device verified`

Conclusion:

- the end-to-end Phase 1 Android-to-Laravel handshake is now working on a physical Android device
- the PEM-generation fix in `PublicKeyPemEncoder.toPem(...)` resolved the canonical-payload mismatch that had caused the previous `invalid_signature` failures

### Additional physical-device Phase 2 and Phase 3 live validation recorded on 2026-04-08

Execution recorded on 2026-04-08 using:

- runtime: physical Android device
- device: `Redmi A5`
- Android version: `15`
- backend mode: local Laravel development server
- backend base URL from device during local development: `http://127.0.0.1:8000/api/` via `adb reverse`
- Android build config during this run: `API_BASE_URL = "http://127.0.0.1:8000/api/"`

Observed Phase 2 reporting flow:

1. Android opened the reporting slice from the verified home screen successfully.
2. Android submitted a signed report for client phone number `+421900123456` with feature `no_show`.
3. Laravel accepted the report and persisted the expected `client_hash`, `reports` row, and `client_feature_levels` row.
4. Android then re-submitted the same client plus feature and correctly displayed the duplicate-report error returned by Laravel.
5. Android then submitted the same client with a different feature (`aggressive`) and Laravel accepted it as an independent Level 1 feature path.

Observed Phase 2 backend evidence:

- Laravel reached `POST /api/reports` successfully during the physical-device run.
- `sha256(+421900123456)` computed on Laravel matched the stored `clients.client_hash` value exactly: `0bd0a9af9829eb59a7a69b433a65f239efb0ef16ac289987e14affc6a622a469`
- latest `reports` evidence after the independent-feature run showed both:
    - `feature = no_show`
    - `feature = aggressive`
- latest `client_feature_levels` evidence after the independent-feature run showed both:
    - `feature = no_show`, `unique_reporter_count = 1`, `is_level_two = 0`
    - `feature = aggressive`, `unique_reporter_count = 1`, `is_level_two = 0`

Observed Phase 2 Android result summary:

- signed report creation on physical device: `PASS`
- duplicate report rejection on physical device: `PASS`
- independent feature acceptance on physical device: `PASS`
- current promotion state after the recorded run: both tested features remained `Level 1`, as expected for a single verified reporter

Observed Phase 3 query flow:

1. Android opened the query slice from the verified home screen successfully.
2. Laravel computed `sha256(+421900000001)` as `cd46747eff46383dc10018bf60878d245e47728bd05c81640f789d02d8d7d0b8` for a clean unknown target.
3. Android submitted a signed `POST /api/blacklist/check` request with that `target_hash`.
4. Laravel returned a successful empty result and Android displayed `No Level 2 features were returned for this target hash.`

Observed Phase 3 backend evidence:

- Laravel reached `POST /api/blacklist/check` successfully during the physical-device run.
- The no-match query returned the expected empty-feature result for an unknown or Level 1-only target.

Observed Phase 3 Android result summary:

- signed blacklist query dispatch on physical device: `PASS`
- no-match empty-result rendering on physical device: `PASS`
- positive Level 2 feature-return path: not yet recorded in this document because no promoted Level 2 target was used during the 2026-04-08 run

## Scope Alignment Notes

### `docs/blacklist_project_sheet.md`

Still valid as the cross-phase implementation checklist. As of 2026-04-09, its Phase 4 section has been aligned with actual Android progress: the reporting UI is already implemented from Phase 2, so Phase 4 now focuses on the Shield runtime flow and app-shell readiness rather than rebuilding reporting from scratch.

### `docs/ADRs/1_foundation+security_handshake.md`

Still valid as the accepted backend/handshake decision record. No handshake decision drift has been identified in the Android client implementation.

### `docs/BACKEND_API_CONTRACT.md`

Still valid as the Android request/response source of truth. No contract field changes are currently required from the Android side.

## Next Recommended Steps

1. Decide on the preferred dev-only backend reset/seeding approach for repeatable Phase 4 physical-phone scenarios.
2. Add focused tests for more receiver-triggered edge cases and overlay dismissal behavior across multiple ringing events.
3. Optionally record an additional physical-device no-match call scenario and any denial-path behavior for permissions or overlay capability.
4. Optionally remove or tone down temporary debug diagnostics once both Android and Laravel teams are satisfied the Phase 1 investigation is fully closed.
