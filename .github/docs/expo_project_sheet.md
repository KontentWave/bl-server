# Expo Project Sheet

## Phase 6: Expo Foundation and Native Bridge Setup

This document is the starting point for the new cross-platform client in `/mnt/c/Users/Marcel-PC/AndroidStudioProjects/blacklist-client`.

The Laravel backend from Phases 1-5 remains the source of truth. The Expo client must follow:

- `BACKEND_API_CONTRACT.md` for request and response behavior
- the existing BDD intent from Phases 1-4 in `blacklist_project_sheet.md`
- the current hosted backend URL used for integration work

The legacy Kotlin app is archived at:

- `/mnt/c/Users/Marcel-PC/AndroidStudioProjects/blacklist-client/legacy_android_kotlin`

It remains a reference implementation only. The new Expo app should not be a file-by-file port.

## Current Workspace State

The new client root is effectively empty except for the archived Kotlin app.

That means Phase 6 should begin with a clean Expo scaffold rather than a migration-in-place.

## Project Goal for This Slice

Establish the Expo project foundation so later slices can add:

- onboarding UI for ad URL and OTP
- hosted Laravel networking
- hardware-backed signing through a custom native module
- call interception and overlay support through Expo config plugins and native code

This first slice is not full feature parity. It is the minimal stable base for later native integration.

## Non-Negotiable Constraints

1. The backend contract is already defined. The Expo client must adapt to it, not redesign it.
2. The app requires native integrations for secure signing and call interception, so Expo Go alone is not sufficient.
3. The project should be built with Continuous Native Generation in mind from day one.
4. The current main backend base URL is:

`https://bcuszlr92817.zafo-forum.sk/api/`

5. The onboarding flow remains ad URL first, then OTP, then verified state.

## Recommended Initial Command

From the client root:

```bash
npx create-expo-app@latest . --template
```

Then choose a TypeScript template, or use the equivalent non-interactive TypeScript Expo Router setup if preferred.

After scaffold, add the router and native-development baseline dependencies needed for this project.

## Recommended Foundation Stack

- Expo SDK current stable
- Expo Router
- TypeScript
- React Native Testing Library
- Jest
- Axios
- Zod for request/response parsing if desired
- Expo Dev Client
- Expo Modules API for custom native modules

## Exact Folder Structure Target

After the initial scaffold and first cleanup, the project should converge toward this structure:

```text
blacklist-client/
  legacy_android_kotlin/
  expo_project_sheet.md
  app/
    _layout.tsx
    index.tsx
    onboarding/
      index.tsx
      otp.tsx
    verified/
      index.tsx
      reporting.tsx
      query.tsx
  src/
    api/
      client.ts
      envelopes.ts
      auth.ts
      reports.ts
      blacklist.ts
    config/
      env.ts
      urls.ts
    features/
      onboarding/
        components/
        hooks/
        state/
        types.ts
      reporting/
        components/
        hooks/
        state/
        types.ts
      blacklist/
        components/
        hooks/
        state/
        types.ts
      shield/
        components/
        hooks/
        state/
        types.ts
    modules/
      cryptovault/
        index.ts
        types.ts
      shield/
        index.ts
        types.ts
    plugins/
      withCryptoVault.ts
      withShieldAndroid.ts
      withIosCallDirectory.ts
    security/
      canonicalPayload.ts
      pem.ts
      signatures.ts
      hashes.ts
    theme/
      colors.ts
      spacing.ts
      typography.ts
      provider.tsx
    test/
      fixtures/
      utils/
  modules/
    expo-crypto-vault/
      android/
      ios/
      src/
      expo-module.config.json
    expo-shield/
      android/
      ios/
      src/
      expo-module.config.json
  assets/
  app.config.ts
  babel.config.js
  tsconfig.json
  package.json
```

## Why This Structure

### `app/`

Holds route entrypoints only. Keep route files thin.

### `src/api/`

Owns all Laravel communication and envelope parsing.

### `src/features/`

Owns feature state and feature-local UI. This keeps onboarding, reporting, blacklist querying, and shield state from collapsing into one large app folder.

### `src/modules/`

TypeScript-facing wrappers for the native modules. This is the app-side consumption layer.

### `modules/`

Actual native Expo modules and bridge code. Keep these isolated so they can evolve independently from UI code.

### `src/security/`

Critical shared logic for canonical JSON payload construction, slash escaping behavior, hashing, PEM normalization, and signature-preparation helpers.

## Phase 6 Deliverables for the First Slice

1. Expo app boots successfully.
2. Expo Router is wired.
3. Basic screens exist for:
   - ad URL input
   - OTP entry
   - verified home
   - reporting screen shell
4. Axios client is configured for Laravel.
5. Standard response envelope parsing is in place.
6. `CryptoVault` TypeScript interface is stubbed.
7. `Shield` TypeScript interface is stubbed.
8. The hosted backend URL is the default integration target.

## Networking Rules

The Expo client should mirror the legacy Kotlin data split, but in TypeScript:

- auth API client
- report API client
- blacklist query API client

At minimum, implement:

- `POST /auth/initiate`
- `POST /auth/verify`
- `POST /reports`
- `POST /blacklist/check`

The client must preserve the Laravel envelope shape:

- `success`
- `code`
- `data`
- `meta`
- `errors`

The Expo client must also preserve canonical payload behavior, including escaped forward slashes in JSON when building signed verify payloads.

## Native Bridge Plan

### CryptoVault

Goal:

- generate or access a hardware-backed signing key
- export normalized public key material in PEM form
- sign canonical payloads

TypeScript surface:

```ts
CryptoVault.ensureKeyPair(): Promise<void>
CryptoVault.getPublicKeyPem(): Promise<string>
CryptoVault.signPayload(canonicalJson: string): Promise<string>
```

### Shield

Goal:

- expose call-monitoring readiness and status
- later bridge into Android receiver and overlay logic

TypeScript surface for the foundation slice can remain minimal:

```ts
Shield.getReadiness(): Promise<ShieldReadiness>
Shield.getLiveStatus(): Promise<ShieldLiveStatus>
```

## Screen Mapping from Legacy Kotlin to Expo

These legacy screens are the feature anchors to preserve conceptually:

- onboarding: `ui/onboarding/OnboardingScreen.kt`
- verified home: `ui/home/HomeScreen.kt`
- reporting: `ui/reporting/ReportScreen.kt`
- blacklist query: `ui/query/BlacklistQueryScreen.kt`

These should be re-expressed in React Native, not mechanically translated.

## First-Slice Implementation Order

1. Scaffold Expo TypeScript app.
2. Add Expo Router and clean default template files.
3. Establish `src/config/urls.ts` with hosted backend default.
4. Build `src/api/client.ts` and envelope parsing.
5. Create route shells for onboarding, OTP, verified home, and reporting.
6. Add `CryptoVault` and `Shield` TypeScript stubs.
7. Add test harness for routing and one networking integration helper.

## Definition of Done for the Foundation Slice

The slice is done when:

- the app launches cleanly
- the route structure is in place
- the hosted backend base URL is configured centrally
- auth and blacklist API clients compile and map the Laravel envelope shape
- `CryptoVault` and `Shield` are represented by stable TypeScript interfaces
- the codebase is organized so later native work does not require major restructuring

## Copilot Handoff Prompt

Use this prompt inside the new Expo workspace:

> Copilot, we are beginning Phase 6 of our project. The Laravel backend is fully finished. We are pivoting from a legacy Kotlin app to Expo and React Native with TypeScript. Please review `expo_project_sheet.md` and `BACKEND_API_CONTRACT.md`. Our first goal is to scaffold the Expo Router foundation, set the hosted API base URL, and build the initial Axios networking layer plus screen shells for onboarding, OTP, verified home, and reporting.
