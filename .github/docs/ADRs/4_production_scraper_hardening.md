# ADR 4: Production Scraper Hardening for amaterky.sk

- Status: accepted
- Date: 2026-04-11
- Scope: Phase 5 Laravel scraper hardening backend

## Context

Phase 1 intentionally used a narrow scraper boundary so the OTP and device-binding flow could be implemented before the project committed to a production portal strategy. That was enough to validate the auth model, but it was not enough for a real rollout.

By Phase 5, the backend needed to fetch real escort ads through a rotating proxy, distinguish transport failures from parser failures, and parse a live portal shape without overfitting the whole scraper stack to one hard-coded regex.

An additional operational constraint emerged during validation: the chosen Webshare integration is a rotating endpoint exposed through one proxy host plus username and password, not a directly managed list of individual exit IPs. That means proxy quality has to be observed statistically rather than by pinning or blacklisting specific exits inside the Laravel app.

## Decision

The accepted Phase 5 design is a standalone scraper-hardening layer with two immediate parts:

1. a rotating-proxy configuration and telemetry path that can probe and record transport health independently of portal extraction
2. a host-aware portal-adapter boundary, starting with a dedicated `amaterky.sk` extractor and stricter Slovak E.164 normalization

This keeps the existing Phase 1 API contract intact while making the backend realistic enough for live verification work.

## Implementation Decisions

### 1. Rotating proxy configuration is centralized and reusable

Implementation:

- `config/scraping.php` stores rotating proxy and probe settings.
- `RotatingProxyConfig` exposes the effective proxy URL and timeout values.
- `HttpEscortPortalClient` and `ProxyProbeService` both consume that same configuration so live fetching and diagnostics use one source of truth.

### 2. Proxy telemetry ships before parser hardening

Why:

- Early live probe runs showed that proxy-pool quality could dominate failures.
- Without telemetry, a parser bug and a weak proxy pool would be hard to distinguish.
- The backend needs evidence about success, timeouts, transport errors, upstream server errors, and blocked responses before blaming extraction logic.

Implementation:

- `scraper_proxy_attempts` stores per-attempt transport outcomes.
- `ProxyProbeService` records `success`, `blocked`, `server_error`, `timeout`, `transport_error`, and `client_error` outcomes.
- `scraper:probe-proxy` provides a lightweight operator-facing entry point for repeated checks.

### 3. Portal parsing uses host-aware adapters instead of one generic regex path

Implementation:

- `EscortAdHtmlParser` now receives the ad URL and dispatches by host.
- `AmaterkySkPhoneExtractor` is the first portal-specific adapter.
- The previous generic extraction path remains as a fallback so older or future shapes do not require a full parser rewrite on day one.

### 4. The initial amaterky.sk extraction strategy is selector-prioritized

Implementation:

- First try `a.detail-floater-link-phone[href^="tel:"]`.
- If missing, fall back to `a.detail-floater-link-sms[href^="sms:"]`.
- If both structured links are missing, fall back to `.card.card-contact h2`.

Why:

- The structured `href` values are the cleanest source of truth on the current portal shape.
- The heading fallback preserves resilience against lighter DOM changes or partially degraded pages.

The extractor also recognizes the explicit disabled-ad heading `Vypnutý zadávateľom` and raises a distinct domain exception instead of silently falling through to a generic missing-phone result.

This is treated as ad-state classification, not as a renewal or reactivation lifecycle. In practice, the backend recognizes that the ad still exists in a meaningful portal state, but it does not proceed into phone extraction while that disabled heading is present.

### 5. Slovak normalization is broadened where the real portal needs it

Implementation:

- `EscortPhoneNumberNormalizer` now supports local `09...` input by converting it to `+421...`.
- It also normalizes `00421...` and bare `421...` inputs into the same E.164 output.

This keeps Phase 1 verification output aligned with the caller normalization behavior already needed in Phase 4.

## Rejected Alternatives

### Managing a per-IP proxy list inside Laravel

Rejected because the Webshare integration used here is a rotating endpoint, not a directly controlled list of exits. Laravel does not have stable per-exit identity to manage.

### Continuing with regex-only HTML extraction

Rejected because the live `amaterky.sk` page already exposes structured contact links, and DOM selectors are more maintainable than growing generic regexes for each portal.

### Waiting to add telemetry until after the first live parser

Rejected because the initial 100 percent `502` probe result showed that transport and parser concerns needed to be separated before drawing conclusions about either one.

## Implementation Outcome

The current Phase 5 backend includes:

- rotating proxy settings in `.env.example` and `config/scraping.php`
- `RotatingProxyConfig`
- `scraper_proxy_attempts` persistence and `ScraperProxyAttempt`
- `ProxyProbeService` and the `scraper:probe-proxy` console command
- `AmaterkySkPhoneExtractor`
- host-aware dispatch in `EscortAdHtmlParser`
- broader Slovak E.164 normalization in `EscortPhoneNumberNormalizer`
- live fetch integration through `HttpEscortPortalClient`
- fixture-backed coverage for active, SMS-only, heading-only, missing-phone, and suspended `amaterky.sk` pages

Validation completed for this ADR includes:

- passing focused proxy telemetry tests
- passing focused portal extraction and normalization tests
- repeated real rotating-proxy probe runs against `https://amaterky.sk/32116`
- a live Laravel extraction run returning `+421944493008` for that ad URL

## Consequences

Positive:

- The backend now has a realistic production scraper path without changing the Android-facing Phase 1 contract.
- Portal-specific parsing can grow incrementally instead of forcing one brittle universal parser.
- Operators have direct telemetry for debugging rotating-proxy quality.

Tradeoffs:

- The current portal-specific implementation is still narrow and focused only on `amaterky.sk`.
- The system remains sensitive to portal markup drift and rotating-proxy pool quality.
- More portals still require additional adapter work.

## Follow-up Notes

- Future ADRs can extend this adapter model to `eurogirlsescort.com`, `rosszlanyok.hu`, or any later portal.
- If scheduled probing remains useful, a later operational decision can determine whether it should stay development-only or become a regular production signal.
- The Android client should continue to treat the scraper as opaque backend behavior and rely only on the documented API envelopes.
