# Android Local Backend Connections

The Android client now defaults to the hosted backend for the current Phase 5 integration flow:

```text
https://bcuszlr92817.zafo-forum.sk/api/
```

Use the local backend base URLs below only for development, diagnostics, or fallback testing against a machine running Laravel locally.

## 1. Android emulator -> local backend on the development machine

Use:

```text
http://10.0.2.2:8000/api/
```

When:

- running the Android emulator
- Laravel is running locally on the development machine
- no `adb reverse` is involved

Why:

- `10.0.2.2` is the Android emulator alias for the host machine

## 2. Physical Android device -> local backend over the same LAN

Use:

```text
http://<HOST_LAN_IP>:8000/api/
```

Example:

```text
http://192.168.100.2:8000/api/
```

When:

- using a real phone
- phone and development machine are on the same Wi-Fi/LAN
- Laravel is reachable from the network

Requirements:

- Laravel should listen on `0.0.0.0:8000`
- Windows/macOS/Linux firewall must allow inbound traffic on port `8000`
- the phone must be able to reach the host LAN IP

## 3. Physical Android device -> local backend over USB with `adb reverse`

Use:

```text
http://127.0.0.1:8000/api/
```

When:

- using a real phone over USB debugging
- `adb reverse tcp:8000 tcp:8000` is active
- the phone should tunnel local port `8000` back to the development machine

Important:

- `127.0.0.1` works for a physical device only when `adb reverse` is active
- `127.0.0.1` does not point to the development machine by default

## Quick rules

- emulator: use `10.0.2.2`
- physical phone on LAN: use host LAN IP
- physical phone over `adb reverse`: use `127.0.0.1`

## Current project default

`app/build.gradle.kts` currently defaults to the hosted backend:

```kotlin
buildConfigField("String", "API_BASE_URL", "\"https://bcuszlr92817.zafo-forum.sk/api/\"")
```

Hosted integration notes:

- keep Android request/response parsing unchanged; the API contract remains the same
- OTP delivery is currently routed through the temporary hosted Vonage-trial integration mode documented in `docs/ADRs/5_hosted_backend_integration_testing.md`
- Android should not branch on that temporary OTP routing detail

## Temporary switch-back to local development

If you need to test against a local Laravel server, temporarily replace the hosted `API_BASE_URL` in `app/build.gradle.kts`, rebuild the app, and use one of these values:

- emulator -> `http://10.0.2.2:8000/api/`
- physical phone on LAN -> `http://<HOST_LAN_IP>:8000/api/`
- physical phone with `adb reverse` -> `http://127.0.0.1:8000/api/`

After local testing, switch the value back to the hosted URL so the main Android flow stays aligned with the current project phase.

