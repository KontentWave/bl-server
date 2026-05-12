# CryptoVault Contract

This document freezes the current CryptoVault bridge contract for the Expo client.

It is based on the already validated architecture from BKP Step 3, but adapted to this app's Laravel verification flow.

## Scope

CryptoVault is responsible only for hardware-backed key lifecycle and payload signing.

CryptoVault is not responsible for:

- building the canonical Laravel JSON payload
- deciding endpoint-specific request shapes
- storing challenge state
- storing OTP state

Those concerns remain in TypeScript.

## Module Name

- Expo native module name: `ExpoCryptoVault`
- App-side TypeScript wrapper: `src/modules/cryptovault/index.ts`
- Local Expo module package: `modules/expo-crypto-vault/`

## Key Alias Policy

- Default alias or application tag: `phase1_device_key`
- TypeScript may optionally pass `keyAlias`
- Android stores the key in Android Keystore under the given alias
- iOS stores the key in Keychain or Secure Enclave storage under the same alias as the application tag
- The alias is stable per device install unless explicitly deleted

## Curve And Key Type

- Curve: `secp256r1`
- Android implementation target: Android Keystore or KeyMint EC P-256 signing key
- iOS implementation target: Secure Enclave EC P-256 signing key on physical devices

## Public Key Format

- Public keys exposed to TypeScript must be PEM-encoded SubjectPublicKeyInfo strings
- PEM header: `-----BEGIN PUBLIC KEY-----`
- PEM footer: `-----END PUBLIC KEY-----`
- PEM line wrapping: 64-character Base64 lines
- Line endings normalized to `\n`
- TypeScript normalizes PEM again before canonical payload generation

## Signature Format

- Input to native signing: UTF-8 bytes of the canonical JSON string built in TypeScript
- Algorithm: SHA-256 with ECDSA
- Native output before encoding: DER-encoded ECDSA signature bytes
- TypeScript-facing output: Base64 string without extra wrapping or line breaks

## Canonical Payload Boundary

TypeScript builds the payload and native code signs it as-is.

For auth verify, the current payload shape is:

```json
{
  "challenge_id": "uuid",
  "public_key": "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"
}
```

Important Laravel compatibility note:

- forward slashes must remain escaped as `\/`
- field order must remain stable
- public key normalization must happen before signing

## Availability Semantics

### Android

- Physical device with secure hardware: available
- Emulator or software-backed store:
  - available only if `requireHardwareBacked=false`
  - treated as unacceptable for production if `requireHardwareBacked=true`

### iOS

- Physical iPhone with Secure Enclave: available
- iOS Simulator: unavailable for hardware-backed signing

## TypeScript Contract

Current TypeScript surface:

```ts
type CryptoVaultAvailability = {
  isAvailable: boolean;
  isHardwareBacked: boolean;
  description: string;
};

type CryptoVaultEnsureKeyPairOptions = {
  keyAlias?: string;
  requireHardwareBacked?: boolean;
};

type CryptoVaultKeyPairStatus = {
  keyAlias: string;
  publicKeyPem: string;
  isHardwareBacked: boolean;
  description: string;
};
```

Module methods:

```ts
ensureKeyPair(options?): Promise<CryptoVaultKeyPairStatus>
getPublicKeyPem(): Promise<string>
signPayload(canonicalJson: string): Promise<string>
getAvailability(): Promise<CryptoVaultAvailability>
deleteKeyPair(options?): Promise<void>
```

## Dev Client And Prebuild Notes

- Expo Go is not sufficient for this module
- local native module linking requires a development build
- root app should include `expo-dev-client`
- use `expo prebuild` to materialize native projects when needed
- after adding or changing iOS native files, run pod install on macOS

Recommended commands:

```bash
npm install
npx expo prebuild
npx expo run:android
npx expo run:ios
```

For a clean regeneration of native projects:

```bash
npx expo prebuild --clean
```
