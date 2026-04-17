# ADR 5: Hosted Backend Integration Testing with Vonage Trial SMS

- Status: accepted
- Date: 2026-04-17
- Scope: Android-to-Laravel hosted integration testing during Phase 5

## Context

By mid-Phase 5, the Laravel backend was already deployed to `https://bcuszlr92817.zafo-forum.sk`, live scraper extraction through the rotating proxy was validated, and the remaining gap was real OTP delivery for Android integration work.

The backend gained a real Vonage SMS transport, but the available Vonage account is still a trial account. That creates two operational constraints:

- SMS can only be delivered to the registered phone number and up to four additional verified test numbers.
- Trial messages append the suffix `[FREE SMS DEMO, TEST MESSAGE]`.

At the same time, the Android client needs to stop talking to a local Laravel instance and begin using the hosted backend URL so onboarding, verification, and later signed query/report flows are exercised against the real deployed server.

The problem is that the normal production identity path scrapes a real ad phone number, while the trial Vonage account cannot send to arbitrary real scraped recipients.

## Decision

The accepted short-term decision is to expose the hosted backend to Android integration work through a temporary hosted testing mode.

That mode keeps the real deployed backend URL and real Laravel runtime, but temporarily alters OTP routing so trial Vonage delivery can succeed against verified test numbers.

## Implementation Decisions

### 1. Android should integrate against the hosted backend URL now

Implementation:

- Android should target `https://bcuszlr92817.zafo-forum.sk` for the current integration slice.
- The hosted Laravel API remains the source of truth for challenge IDs, masked phone metadata, OTP expiry, verification, and signed blacklist queries.

Why:

- This moves Android off the local-only backend assumption.
- It surfaces deployment-specific issues earlier, including TLS, hosted config, and live server behavior.

### 2. OTP delivery is temporarily redirected through the existing non-production override path

Implementation:

- `ESCORT_PORTAL_DEVELOPMENT_PHONE_OVERRIDE` is set to a verified Vonage test number.
- The hosted app is temporarily run outside `production` so `ExtractPhoneFromAdJob` applies that override.
- The override was validated first with `+421903223183` and then switched to `+421917047260` for continued testing.

Why:

- Vonage trial accounts cannot send OTPs to arbitrary scraped ad numbers.
- The backend already had a controlled non-production override path, so no parallel testing-only controller or route was needed.

### 3. The backend SMS path remains the real Vonage transport

Implementation:

- `SmsSender` now supports a Vonage-backed implementation.
- The hosted backend uses `SMS_DRIVER=vonage` during this slice.
- Real trial-account delivery was validated by receiving a hosted OTP SMS with the expected verification-code message body.

Why:

- Android integration should exercise the real outbound SMS transport, not the old log-only sender.
- This de-risks the later paid-account cutover because the transport is already in place.

### 4. This hosted mode is explicitly not final production behavior

Implementation:

- Documentation must call this a temporary hosted integration mode.
- Before real rollout, Laravel must return to `APP_ENV=production`.
- `ESCORT_PORTAL_DEVELOPMENT_PHONE_OVERRIDE` must be removed before claiming live production identity verification.

Why:

- Android developers need a clear warning that OTPs are currently routed to a verified test number rather than the true scraped recipient.
- Without that warning, later test evidence could be misread as proof of full production readiness.

## Rejected Alternatives

### Keeping Android pointed at a local Laravel backend until paid SMS is available

Rejected because it delays discovery of hosted-environment issues and weakens confidence in the real deployment.

### Building a special-case testing API just for Android onboarding

Rejected because the existing override path already provides the needed behavior with less code and less API drift.

### Claiming the hosted flow is already production-ready

Rejected because the trial Vonage restrictions and the active phone override mean the current hosted OTP path is still a controlled integration environment.

## Implementation Outcome

The current hosted integration state includes:

- deployed Laravel backend at `https://bcuszlr92817.zafo-forum.sk`
- real Vonage SMS transport configured on the backend
- temporary non-production hosted mode so the override path can apply
- verified-number OTP routing through `ESCORT_PORTAL_DEVELOPMENT_PHONE_OVERRIDE`
- successful public `POST /api/auth/initiate` responses through the hosted domain
- successful receipt of a real Vonage trial OTP SMS

The latest validated hosted initiate flow returned masked phone metadata for `+421917047260`, confirming that Android can now integrate against the deployed backend while the trial-SMS limitation remains in place.

## Consequences

Positive:

- Android can begin real hosted integration immediately.
- OTP delivery now exercises the real Vonage path instead of the former log sender.
- The deployed Laravel environment is part of the test loop.

Tradeoffs:

- The hosted backend is temporarily not in strict production mode.
- OTP identity routing is currently a test harness around verified numbers, not the final live scraped-recipient path.
- Documentation must remain explicit so the team does not overstate production readiness.

## Follow-up Notes

- Once Android integration is stable, revert Laravel to `APP_ENV=production`.
- Remove `ESCORT_PORTAL_DEVELOPMENT_PHONE_OVERRIDE` before final production sign-off.
- After the Vonage account is upgraded, retest the exact same hosted onboarding path without the override and against a real scraped recipient.
