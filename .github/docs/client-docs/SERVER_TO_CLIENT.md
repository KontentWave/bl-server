# Server to Client Copilot

## BETA-SMS-001 - SMSTools Implementation and Hosting Check

**Date:** 2026-10-02

**Status:** BLOCKED - local hardening and mocked verification complete; approved live delivery and Android verification pending.

### Verified facts

- The existing `SmsSender` abstraction selects `SmstoolsSmsSender` when `SMS_DRIVER=smstools`. Vonage removal is deferred; no broad authentication refactor was performed.
- The adapter posts JSON to `https://api.smstools.sk/3/send_batch` using `SMSTOOLS_API_KEY` and the shared `SMS_FROM` setting. `SMS_FROM` should be explicitly configured with a provider-approved sender; its historical Vonage fallback is not proof of approval.
- New optional settings are `SMSTOOLS_CONNECT_TIMEOUT` (default 5 seconds) and `SMSTOOLS_TIMEOUT` (default 10 seconds). Values are bounded to at least one second.
- Provider success requires HTTP success, `id: OK`, a valid batch identifier, and a matching intended recipient with a valid message identifier. Accepted international digits with or without a leading plus are matched. Provider acceptance is not delivery evidence: the provider documentation explicitly distinguishes accepted messages from later sending/delivery states.
- Transport failures become the existing `sms_dispatch_failed` domain error. Provider notes, raw response bodies and transport exception messages are not copied into error responses or failure logs. Existing successful-dispatch logs contain masked recipient metadata and provider identifiers, not an OTP or API key.
- There is no automatic send retry. A transport timeout or malformed response can mean an SMS was already accepted; retrying automatically risks duplicate charges and messages.

### Effective environment checks

Checks were read-only; no configuration, cache, deployment or data was changed.

| Environment              | Effective selection                        | Runtime flags                                                                            | Credential/sender evidence                                                                           |
| ------------------------ | ------------------------------------------ | ---------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Local normal CLI         | `SMS_DRIVER=log`, `LogSmsSender`           | production; debug disabled; no cached configuration; no development recipient override   | SMSTools key absent; sender present but approval unverified                                          |
| Isolated automated tests | `SMS_DRIVER=smstools`, `SmstoolsSmsSender` | testing; SQLite in-memory database; fixture ad source; fake HTTP; OTP logging disabled   | Dummy test key and test sender only; no real provider call                                           |
| Accessible hosted CLI    | `SMS_DRIVER=smstools`, `SmstoolsSmsSender` | staging; debug disabled; no cached configuration; development recipient override present | SMSTools key present; sender present; account funding, sender approval and delivery scope unverified |

The documented public entrypoint loads the inspected hosted backend. At the read-only hosting check, local HEAD and hosted source HEAD were both `a1e36535f781e0a0fd51847e738576ac5eccbd12`. The hardening changes have not been deployed; GitHub publication and merge do not change the hosted source. The read-only health request to `https://bcuszlr92817.zafo-forum.sk/up` returned HTTP 200. This does not prove OTP delivery, database correctness, or web-worker configuration parity; the effective flags above were inspected through hosted CLI, not a public configuration endpoint.

SMSTools is therefore already selected in the accessible hosted non-production deployment, but the newly hardened adapter has only mocked test evidence and has not been deployed. Real delivery remains unvalidated. No credential was copied from the host into the local environment, and no real key was supplied for an isolated local provider test.

### Changed paths and verification

- `app/Services/SmstoolsSmsSender.php`: explicit timeouts, transport exception mapping, intended-recipient acceptance and safe provider identifiers; removal of raw provider failure-note logging.
- `config/services.php` and `.env.example`: documented SMSTools timeout settings; the normal default driver remains `log` to avoid accidental live sends.
- `tests/Feature/Services/SmstoolsSmsSenderTest.php`: success, provider/HTTP rejection, connection failure, configured timeouts, malformed/partial responses, intended-recipient matching, safe contexts and single-attempt behavior.
- `tests/Feature/Auth/InitiateAuthTest.php`: SMSTools-backed HTTP initiation success and domain failure envelopes, plus unchanged challenge replacement behavior after failed sends.
- `tests/Feature/Auth/VerifyAuthTest.php`: successful signed verification and challenge consumption after mocked SMSTools acceptance.
- This server-owned handoff records the evidence; client-owned messages are unchanged and excluded from this change. The reviewed source revision is identified by the GitHub commit/PR history; publication is separate from the approval-gated deployment below.

Focused command:

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: SMS_DRIVER=smstools \
SMSTOOLS_API_KEY=test-api-key SMS_FROM=Blacklist \
SMS_LOG_OTP_IN_NON_PRODUCTION=false vendor/bin/phpunit --no-progress \
--filter 'SmstoolsSmsSenderTest|InitiateAuthTest|VerifyAuthTest'
```

Result: **36 tests, 261 assertions passed**. The key in this command is a dummy, not a credential.

The same isolated environment without the filter passed the complete suite: **81 tests, 362 assertions**. Scoped `vendor/bin/pint --test` passed for the adapter, service configuration and three changed test files; `git diff --check` passed. These tests are not live-SMS evidence or Android hardware/device evidence, and SQLite does not establish MariaDB concurrency safety.

### Local server and Android test rerun

The requested local server/client tests were run on 2026-10-02. The backend again passed **81 tests, 362 assertions**, scoped Pint and the whitespace check, using the isolated environment above.

The active Kotlin/Compose project at `legacy_android_kotlin/` was tested using its existing Windows Android SDK and Android Studio JBR, not the cancelled Expo project. From that project directory:

```bat
gradlew.bat --no-daemon --console=plain :app:testDebugUnitTest --rerun :app:assembleDebugAndroidTest
```

- **27 JVM tests passed across nine suites**, with zero failures, errors or skipped tests, confirmed from Gradle XML reports. Coverage includes auth/report/query repositories, canonical payloads, PEM encoding, number normalization, incoming-call processing and shield readiness.
- Repository tests use local `MockWebServer`; incoming-call tests use a fake query repository. No hosted API or SMS-provider request was made by these tests.
- The instrumentation-test APK compiled and packaged successfully. Instrumented tests were **not executed** on an emulator or physical device; packaging is not UI execution or hardware-backed signing evidence.
- The initial offline Gradle attempt stopped on an uncached `foojay-resolver` dependency before running tests. The successful retry allowed build-dependency downloads and forced fresh unit-test execution.
- Client tracked files remained unchanged. No APK was installed, hosted configuration changed, live SMS sent, or deployment performed. These are separate mocked suites, not an end-to-end Android-to-Laravel test; both beta requests remain BLOCKED on the outstanding gates below.

### API compatibility and Android actions

**Required Android provider-specific actions: none.** Continue using the Kotlin/Compose client and the existing Laravel endpoints. Never embed SMS credentials or call SMSTools from Android.

- Initiation success remains HTTP 201 with `auth.sms_initiated`, challenge identifier, masked phone metadata, expiry and object-shaped `meta`.
- SMS failure remains HTTP 503 with `sms_dispatch_failed` and `meta.retryable: true`. A retryability flag does not establish that the first SMS was not sent; do not automatically resend an ambiguous request. Any necessary change to client retry behavior should be coordinated separately.
- Verification remains the existing OTP, PEM normalization, canonical JSON/slash escaping and signature contract.
- Existing lifecycle semantics are deliberately unchanged: a challenge is created/replaced before sending. If sending fails, that challenge remains stored and an earlier challenge is invalidated; the error response does not return the new identifier. There is no new rollback/deletion of challenge data in this change.
- **Existing contract mismatch:** empty error fields serialize as `errors: []`, although `BACKEND_API_CONTRACT.md` specifies an object. This comes from `ApiResponse::error`, not SMSTools. The SMS slice preserves the existing wire behavior; coordinate a separate envelope correction and client compatibility test before claiming full contract conformance.

### Deployment and rollback plan - approval required

1. Approve the release and provision the SMSTools key through the hosting secret/configuration mechanism, never through shared notes or chat. Confirm provider-approved `SMS_FROM`, account credit, sender restrictions and intended country/carrier scope.
2. Record the previous release and environment settings in the secure deployment system. Do not put credentials, recipient identities, provider payloads or environment-file snapshots in this handoff.
3. Deploy the reviewed adapter/configuration changes. Select `SMS_DRIVER=smstools`, the documented HTTPS endpoint, and the approved sender; choose the explicit timeout settings. No database migration or data deletion is required for this SMS slice.
4. With explicit approval, genuine beta onboarding must use `APP_ENV=production`, `APP_DEBUG=false`, the live HTTP scraper, and no `ESCORT_PORTAL_DEVELOPMENT_PHONE_OVERRIDE`. The current hosted staging override is not genuine beta identity verification. An approved isolated staging delivery test must likewise target the consenting ad owner's actual scraped recipient, not an unrelated override.
5. In the approved deployment directory, using the deployment PHP binary, run `php artisan config:cache`. Recheck the effective adapter, environment/debug flags, override absence and key/sender presence with a sanitized runtime check; never dump `config`, environment files or secrets.
6. If queue workers are used, run `php artisan queue:restart` and confirm workers are supervised/restarted correctly. Refresh other actually deployed long-lived application workers and hosted PHP/OPcache according to the hosting procedure; do not assume an unused worker framework is installed.
7. Check `/up`, then perform only the separately approved live test described below. Monitor sanitized SMS domain failures and provider credit/delivery status. Do not blindly replay failed sends.
8. Roll back by restoring the previous reviewed release and its securely recorded settings, rebuilding the configuration cache and refreshing relevant workers. The prior hosted driver is already SMSTools, so rollback must not silently switch back to Vonage or the log sender. Do not revert migrations, clear community tables, or resend messages as a rollback step.

### Live-test readiness and pending approvals

**Physical-device OTP test: blocked pending approval/setup, not passed.** Required inputs are explicit live-send approval, recipient consent, an approved sender/funded account, an isolated test target with an active supported ad, and an approved deployment of the hardening changes. Real-recipient configuration changes require separate approval. Never provide the key or recipient details through this handoff.

After approval, perform one controlled Android ad URL -> initiate -> received SMS -> entered OTP -> hardware-signed verify flow. Record provider acceptance separately from physical receipt, then confirm HTTP 200 `auth.verified` and the Android verified state. Coordinate retries explicitly if receipt is delayed; do not log or publish the OTP, recipient, key, public request payload or private signing material. Record only sanitized outcome/date/runtime evidence. Keep BETA-SMS-001 open until actual delivery and Android verification are observed.

## BETA-BACKEND-001 - Beta Readiness Response

**Date:** 2026-10-02

**Status:** BLOCKED - provider selection confirmed, but genuine beta identity settings, live verification and remaining hardening gates are incomplete.

- Intended documented API base URL remains `https://bcuszlr92817.zafo-forum.sk/api/`. `/up` is reachable. Provider replacement does not change auth/report/query request fields; the existing empty-error serialization mismatch above remains a separate compatibility issue.
- Hosted production mode: **no** (staging). Development recipient override removed: **no** (present). Debug enabled: **no**. These are verified CLI facts, not historical Vonage assumptions.
- Delivery to real participants: **unverified**. SMSTools is configured, but key presence and accepted messages do not prove account funding, approval, delivery or country/carrier coverage.
- Source has `amaterky.sk` and `eurogirlsescort.com` adapters with fixture coverage; live portal reliability must be rechecked for the consenting beta ad. Number normalization supports Slovak local formats and E.164-shaped international inputs, not blanket worldwide SMS certification.
- OTP lifetime remains 15 minutes; current tests preserve validity at exactly the expiry timestamp and rejection afterwards. No new resend, challenge-attempt, query or report limits were introduced. The earlier missing-throttling/OTP-attempt-limit finding remains open.
- Other earlier beta gates remain open: supported-portal/SSRF restrictions, atomic OTP consumption and reporting concurrency, dependency advisories, sensitive identity retention/access/backup protections, and clarification of server-side hardware-attestation guarantees. This SMS change does not close them.
- For Level 2 match/no-match and duplicate-report scenarios, use a separate approved non-production database and synthetic records/test identities. Three distinct verified reporter identities are required for promotion; a repeat report must not increase the count. Do not seed, reset, delete or reuse real community records to create a test result. Automated backend tests already cover these behaviors, but not a new physical hosted call scenario.
- No live-provider balance/delivery monitoring, database restoration drill, deployment rollback execution, latency certification, or operational contact/on-call owner was validated here. Designate the operator and approved rollback/contact procedure before live beta.

No production changes, live/billable SMS, data deletion, or Android physical-device verification were performed for either request.
