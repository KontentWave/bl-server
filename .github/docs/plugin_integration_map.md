# Plugin and Module Integration Map

**Updated:** 2026-11-04 15:22:08 CET (UTC+01:00), verified local clock.
Current inspection: lifecycle implementation originally uncommitted on `main`
`a8d2d9a91ac11d55320df434fce68b82470d4de9`, focused corrections and executed
isolated SQLite/MariaDB qualification. Owner authorized the backend
branch/commit/push/PR/merge/return-to-main publication workflow on November 4;
this publication slice does not perform hosted operations or client distribution.
Fresh pre-publication SQLite regression passed 248 tests / 1,509 assertions;
changed/new PHP Pint and whitespace checks passed. MariaDB evidence below
remains the earlier November 4 execution, not a new publication-time rerun.
Publication identifiers are recorded in the local server-owned handoff after
completion. Last executed hosted evidence remains October 29 20:07:09
UTC, 94 runtime files matching `8d98219a06c932fd966d761a3159135832331bbd`;
this is historical evidence, not current hosted parity.

## Current ad-flow qualification - approved supervised slice

### November 4 - server review, corrections and isolated qualification

The existing lifecycle changes were preserved and inspected: additive migration,
key selection/generations, signed authorization, receipts/pruning, pending-claim
fencing, parser confidence and transport. Exact payload order, envelopes, due
tokens, metadata, budgets and client error distinctions remain unchanged.
Client-owned handoff/attachment were read only. The client handoff reports
[client PR #17](https://github.com/KontentWave/bl-client/pull/17) merged at
`70ed318491a13f4c1f7d45d5fd1c5ce29454ebc2`; client/device behavior was not rerun here.

**Demonstrated defects and fixes:**

- [RenewalAdInspector](../../app/Services/RenewalAdInspector.php) previously
  caught every `Throwable` without diagnostics. Known timeout, unavailable,
  URL-policy and extraction exceptions now have an explicit unknown path with
  integer-only retry hints. Empty/unrecognized/ambiguous parser outcomes remain
  explicit unknowns. Unexpected exceptions still cannot offboard a user, but
  emit `renewal.inspection_failed` at error level with **exception class only**:
  no exception object, message, trace, URL, contact, key or response body.
  The new regression failed before correction because no diagnostic existed.
- Partial contact sets were not necessarily confident: Amaterky phone/SMS
  anchors missing the expected href were skipped; EuroGirls could skip a
  contact item with only an unavailable/visible-prefix phone. A recognized
  partial set could therefore exclude the verified recipient incorrectly.
  Amaterky now checks every supported contact anchor; EuroGirls checks every
  contact item and phone trigger, as well as each advertised data attribute.
  Incomplete sets remain unknown, not `contact_changed`. The new regression
  initially received terminal403 instead of503. Complete multi-contact and
  encoded EuroGirls fixtures still renew and can confirm a genuine change.

**Inspected integration boundaries:** [AppServiceProvider](../../app/Providers/AppServiceProvider.php)
still binds `EscortPortalClient` from `services.escort_portal.driver`
(`fixture` or HTTP) and `SmsSender` independently; no new provider or plugin
hook. Both new POST routes retain `throttle:api`, then signed lifecycle request
caps/shared limiter locks. Status/renewal inspection is synchronous and
device-initiated, not queued or scheduled. Portal I/O remains outside
transactions; short selection/binding transactions precede report-target locks.
No incoming-call query awaits the portal or renewal cache lock. Existing
`cache.limiter`/default store and `services.escort_portal.timeout` govern the
shared limiter and lease; no hosted binding/configuration changes were made.
The nullable binding upgrade, selection row, generation ownership registry and
unique enrollment/request receipt pair remain the only new storage boundary.

**Executed evidence (PHP 8.4.12 / PHPUnit 12.5.15):**

| Selection | Result |
| --- | --- |
| `php vendor/bin/phpunit --do-not-cache-result` | 248 tests / 1,509 assertions passed |
| `php scripts/test-mariadb.php` with session-only Compose configuration | 24 tests / 690 assertions passed under REPEATABLE-READ; independently 24 / 690 under READ-COMMITTED |
| New [MariaDB lifecycle suite](../../tests/Database/LifecycleConcurrencyTest.php), focused run | 6 tests / 131 assertions at each isolation level |
| Changed/new PHP Pint, `php -l`, `git diff --check` | Passed |

The full MariaDB run has one fewer assertion than the focused lifecycle plus
older suite totals because only the first suite asserts that the initial schema
is empty. It is not a skipped test. [Lifecycle regressions](../../tests/Feature/Auth/LifecycleTest.php)
cover equality, overdue single-fetch catch-up/commit-time due, terminal state,
explicit re-enrollment, unknown failures/contact changes, request/fetch caps,
backoff, receipts/conflicts/pruning and stale-result rejection. Existing full
regressions retain signed query/report distinctions, duplicate reports,
threshold three, phone allowlist and initial enrollment.

The existing [runner](../../scripts/test-mariadb.php),
[Compose definition](../../compose.mariadb-test.yaml) and
[bootstrap](../../tests/bootstrap-mariadb.php) were inspected before execution:
local Unix-socket daemon; random project/password; loopback ephemeral port;
verified container labels/project; tmpfs-only database; cached-config refusal;
forced disposable schema, fixture portal and log-only SMS with OTP logging off.
The workstation Compose plugin was missing/broken. A missing-dependency run
failed before container creation; Ubuntu Compose 2.40.3 was then downloaded and
extracted only in session storage, used via temporary `DOCKER_CONFIG`, with no
global installation/configuration change. Run-created containers/networks were
removed by the runner. A pre-existing exited test container was left untouched.
PHP has no loaded Xdebug; deterministic regressions/process barriers supplied
runtime evidence without temporary production logging.

The new process tests prove zero transaction depth during paused fixture I/O,
overlap/cache-loss pending fencing, query completion while scraping is paused,
short selection serialization with an observed InnoDB lock wait, terminal
offboarding, re-enrollment/stale-result rejection, expired-claim rejection after
a later success, and nullable migration rollback/re-upgrade retaining keys,
verification time and community rows. They use mocked portal I/O, not live
scraping/SMS. Arbitrarily suspended external workers still cannot be physically
canceled; their late results cannot commit. No hosted/provider/device or broader
beta-readiness claim follows.

**Server-first proposal:** backend code publication to `github` is now
owner-authorized. A bounded hosted backup/preflight/migration/API rollout through
`origin` with effective shared-store/transport/TLS/worker parity checked and
existing keys, APP_KEY, data and budgets preserved remains a separately scoped
execution step, not part of this publication-only slice. Do not distribute the new
client before the server schema/API is ready. Initial validation should be
no-send and synthetic where possible; any genuine portal/binding/SMS/device
qualification needs its own explicit finite approval.

Existing testers remain legacy/exempt: consumed ad challenges cannot prove
provenance now. Do not backfill an ad, revoke a binding, reset keys, or request
repeat onboarding just to reproduce October's accepted success. Renewal can
be enabled for an existing tester only through a separately approved,
intentional supported-ad OTP verification creating a new generation with the
retained device key; the client owner must first confirm the manual entry path
preserves key/data. Remaining legacy/exempt is valid and needs no live action.
Migration-down compatibility is tested, but is **not** a safe operational
rollback after offboarding: old authorization would ignore terminal state.
Retain schema/state and forward-fix a lifecycle-aware build; separately approve
an ingress pause if containment is needed. See the current
[server handoff](client-docs/SERVER_TO_CLIENT.md) for owner action.

### November 3 - accepted coordinated renewal/offboarding; local implementation

**Updated:** 2026-11-03, CET (UTC+01:00). Server baseline
`a8d2d9a91ac11d55320df434fce68b82470d4de9`; owner documentation-only edits
integrated, not discarded. Client accepted ADR-006 at baseline
`6aae2cb499c251326c47c10a24e5530a63e254b1` owns the coordinated client work.
The ignored client `backend-docs` symlink points here. `github` is code;
`origin` is hosting, untouched. No hosted/runtime/provider qualification.

- [ADR 006](ADRs/006_coordinated_renewal_and_offboarding.md) and
  [authoritative API contract](BACKEND_API_CONTRACT.md#coordinated-renewal-and-offboarding)
  were written before runtime implementation. Device-initiated signed
  status/renewal is not SMS/OTP, a lease or scheduled crawling. Overdue bindings
  retain ordinary query/report access until confirmed offboarding.
- [Routes](../../routes/api.php), [request bounds](../../app/Http/Requests/Auth/LifecycleRequest.php),
  [controller](../../app/Http/Controllers/Api/Auth/LifecycleController.php) and
  [service](../../app/Services/AuthLifecycleService.php) implement signed
  `POST /api/auth/status` / `POST /api/auth/renew`. Existing EC key, normalized
  PEM, ordered canonical PHP JSON/SHA256 are shared in
  [signature helpers](../../app/Services/DeviceSignatureService.php). All
  lifecycle keys, nullable fields, exact due token, versions and UTC snapshots
  are explicit. Query/report terminal metadata has no request ID.
- [Nullable upgrade migration](../../database/migrations/2026_11_03_210000_add_binding_lifecycle.php)
  adds provenance/generation/state/version/timestamps and persisted fetch-window,
  failure-ladder/retry eligibility to bindings. `device_key_selections` selects
  the current binding after explicit verification; ownership hashes in
  `device_enrollment_generations` distinguish stale same-key generations from
  unknown/foreign IDs after pruning. `renewal_receipts` retains accepted renewal
  digest/outcome for48h, max8/enrollment. No inferred backfill/community change.
- [Verification](../../app/Services/AuthVerificationService.php) copies challenge
  provenance or recognizes existing `owner-enrollment` sentinel as phone-only.
  New generation supersedes prior same-key bindings, retaining rows/reports and
  verification semantics. Response adds `data.lifecycle`; renewal never
  rewrites verified_at. Allowlist remains issuance-only. Legacy ambiguity keeps
  first-binding query/report behavior; status refuses to guess provenance.
- [BindingLifecycle](../../app/Services/BindingLifecycle.php) linearizes selected
  authorization/state commits in short transactions, selection then ordered
  binding locks. [Queries](../../app/Services/BlacklistQueryService.php) and
  [reports](../../app/Services/ReportSubmissionService.php) verify signatures
  before authoritative offboarding errors; CB-11's query401 and report403/422
  remain unchanged. Report target locks follow committed binding authorization.
  Authorized operations may finish after later offboard commit. Query never
  awaits renewal's cache lock or portal I/O.
- [RenewalProtection](../../app/Services/RenewalProtection.php) reuses
  `OtpAbuseProtection::assertSharedStore`, cache limiter/default/stores/prefix
  and shared locks. Fixed request caps20/key/hour and3/IP/minute; application
  fetch cap100/86400s. Renewal lock contention returns429/Retry-After1.
  Persisted per-enrollment reservations permit4/fixed86400s from first fetch;
  failures delay900/3600/21600s, honoring longer validated Retry-After. Success
  resets failure ladder only. No SMS counter/config/key resets.
- [Inspector](../../app/Services/RenewalAdInspector.php) uses existing container
  portal binding, one fetch and supported Amaterky/EuroGirls selectors only,
  not generic fallback. Amaterky exact `h2.alert-heading` disabled notices and
  `Vypršala platnosť` expiry confirm terminal offboarding. Unsupported deletion,
  empty/ambiguous contacts, challenges, bare404/410 and transport/parser failures
  are unknown503. Active contacts match the original verified phone regardless
  order; confident exclusion offboards contact_changed. Enrollment's primary
  preference stays unchanged. [HTTP](../../app/Services/HttpEscortPortalClient.php)
  retains HTTPS/host/443, public DNS pinning, TLS/no redirects and proxy choices;
  validated Retry-After is exposed without changing enrollment failure semantics.
- Portal I/O is outside DB locks; commit rechecks selected key, binding,
  generation/version. Current terminal state overrides old active receipts;
  duplicates never fetch/advance. Fresh eligible retries use new IDs; signed
  old successful cycles remain rejected after receipt pruning.
- Executed forced-memory SQLite/no cached config using source fixtures,
  generated test EC keys, fake portal callbacks, Carbon clocks and stray-request
  guards. Initial actual failing test:1 test/1 assertion, expected200 got404,
  not compilation failure. Focused passing run:200 tests/1331 assertions
  (31 lifecycle tests plus existing enrollment/query/report/portal suites and
  eight HTTP additions). Reentrant duplicate proves429/no second fetch;
  in-fetch manual generation rejects stale commit; query succeeds with renewal
  lock held; transaction-depth check proves no portal DB lock. Query interleaving proves
  authorization before offboard commit may finish, while the next query denies. MariaDB
  bootstrap/overlap suites inspected but not executed: Docker reports no
  already-provisioned labeled isolated test container. No service provision/start,
  toolchain/config/env change, real network/SMS or Git publication.

  Executed command: `php vendor/bin/phpunit --do-not-cache-result
  tests/Feature/Auth tests/Feature/Blacklist tests/Feature/Reports
  tests/Feature/Scraper/Portals tests/Feature/Services/HttpEscortPortalClientTest.php`
  through WSL Ubuntu-22.04 in the server checkout; PHP8.4.12/PHPUnit12.5.15.
  [Lifecycle tests](../../tests/Feature/Auth/LifecycleTest.php) include isolated
  upgrade/rollback with a pre-metadata legacy row and retained community rows.
  That rollback first exposed a SQLite unique-index drop error (1 test,
  0 assertions, 1 runtime error); dropping the added unique index before
  dropping its column fixed it, and the final combined run above passes.
  Changed-PHP-only Pint check and `git diff --check` pass.
  Cache cleanup fault injection proves release failure cannot reclassify a
  protected committed renewal as unavailable: retain its outcome, log cleanup
  failure and leave the lease closed until expiry. Acquisition/budget failures
  still fail503 before portal I/O.

### Lease-expiry and early-request qualification (November 3)

The shared cache lease is `max(120, services.escort_portal.timeout + 30)`
seconds, exposed by `RenewalProtection::leaseSeconds()`. A timeout margin alone
is not a durable concurrency fence: arbitrary worker suspension can outlive it.
The new regression initially observed two portal calls for one retained request
ID after cache expiry (1 test/1 assertion/1 genuine failure).

`AuthLifecycleService` now writes an accepted `pending` receipt in the same
short transaction as the enrollment fetch reservation, before portal I/O.
The receipt's internal retry_at temporarily holds its claim deadline. Pending
receipts block other IDs even if the cache lease is lost; expired claims settle
once as unavailable, preserve lifecycle and consumed reservations, and enter
900/3600/21600s failure backoff. A retained duplicate gets that stored failure,
not another fetch. Fresh IDs blocked by pending/retry caps get no receipt.
Completion rechecks selection/generation/version and the exact receipt row ID,
digest, pending outcome and deadline under short locks. A canceled, expired,
pruned or replaced claim cannot commit, including same UUID reuse after receipt
retention. New-generation manual enrollment remains separately fenced.
No columns/tables, budgets or wire codes were added by this correction.

Accepted200 not-due/exempt requests are receipted as well. Replaying an early
ID at the due boundary remains not-due/no-fetch; changed canonical inputs
return409. A new eligible ID is required. All receipts remain bounded8/48h.

Executed standalone lifecycle file:31 tests/296 assertions passed. Final
combined focused selection above:200 tests/1331 assertions passed, comprising
161 pre-existing regressions +31 lifecycle tests +8 added HTTP cases. This is
not a repository-wide PHPUnit run. New deterministic cases cover cache loss,
same-ID expiry, different-ID backoff/stale commit, timeout200/lease230 and
receipted early-ID replay. Ordinary HTTP is bounded by configured timeout;
no guarantee of cancellation/external exactly-once execution is claimed for
an arbitrarily suspended worker. After unknown settlement/backoff a different
ID can legitimately start another paid attempt; a late old result is fenced.

### Parent-reported final native and cryptographic qualification (November 3)

Parent verified final XML totals:217 JVM tests discovered,216 passed,
1 old optional `BackendReportInteropTest` skipped,26 suites with zero
failures/errors. The actual synthetic Java EC -> PHP `DeviceSignatureService`
lifecycle interoperability test executed and passed for status, nullable due
and offset due; no genuine wire/signature mismatch. The helper uses
`wsl --exec` to preserve PHP variables without shell expansion.
Full JVM validation, instrument-test compilation and sequential debug lint
passed; instrument compilation was UP-TO-DATE after earlier source compilation,
not an executed device test. Lint:0 errors,19 warnings.
These are parent-reported local results, not independently rerun by this server
task. Cryptographic parity is not hosted, Laravel-route/provider end-to-end or
device qualification. Publication/deployment were not authorized or performed.
Server executed focused qualification remains200 tests/1331 assertions above.

### November 3 server-only local change inventory (historical)

22 changed/new files:19 PHP files (including1 additive migration and2 test
files), plus3 documentation files. HEAD remains the recorded baseline.
No client file or `SERVER_TO_CLIENT.md` change.

- `.github/docs/ADRs/006_coordinated_renewal_and_offboarding.md`
- `.github/docs/BACKEND_API_CONTRACT.md`
- `.github/docs/plugin_integration_map.md`
- `app/Http/Controllers/Api/Auth/LifecycleController.php`
- `app/Http/Controllers/Api/Auth/VerifyAuthController.php`
- `app/Http/Requests/Auth/LifecycleRequest.php`
- `app/Models/DeviceBinding.php`
- `app/Scraper/Portals/AmaterkySkPhoneExtractor.php`
- `app/Services/AuthLifecycleService.php`
- `app/Services/AuthVerificationService.php`
- `app/Services/BindingLifecycle.php`
- `app/Services/BlacklistQueryService.php`
- `app/Services/DeviceSignatureService.php`
- `app/Services/HttpEscortPortalClient.php`
- `app/Services/ReportSubmissionService.php`
- `app/Services/RenewalAdInspector.php`
- `app/Services/RenewalProtection.php`
- `bootstrap/app.php`
- `database/migrations/2026_11_03_210000_add_binding_lifecycle.php`
- `routes/api.php`
- `tests/Feature/Auth/LifecycleTest.php`
- `tests/Feature/Services/HttpEscortPortalClientTest.php`

Shared signing integration methods:
`DeviceSignatureService::lifecyclePayload(string $operation, array $data): string`,
`DeviceSignatureService::verifyLifecycle(string $operation, array $data): bool`,
and existing `normalizePublicKey(string $publicKey): string`. Canonical goldens
for status, null due, concrete due and PEM escaping are executed locally and
documented in the API contract. All lifecycle keys/nulls are present; ad due
equals verified/last-renewed plus86400s, UTC tokens use `+00:00`. No intentional
wire-shape differences from accepted ADR-006.

Prior owner-reported successful ad onboarding below remains historical evidence,
not renewal/deployment qualification. `SERVER_TO_CLIENT.md` and client files
are untouched by this server task.

**Owner-reported pass, recorded 21:57:41 CET:** tester submitted the ad URL,
received the actual SMS OTP, verified it and was onboarded. Accept this as
owner-reported/device acceptance of live ad onboarding, separate from executed
server preflight evidence; no independent provider/challenge/binding inspection
or new SMS in this update. Do not repeat successful enrollment for documentation.
Ordinary signed BlackList query/report, persistence and planned ad-off weekend
use remain separate checks. Keep the enrollment; incoming-call/overlay tests
are outside this older-phone scope. Last verified live configuration stays
enabled without a fixed cutoff at owner's request, not a new pause decision.

**Current: live ad onboarding enabled, 2026-10-29 21:07:07 CET (UTC+01:00),**
verified local clock. Owner confirmed ad reactivation/requested retry, resuming
approved preparation with no fixed cutoff. At 20:06:52 UTC, guarded
[HTTP adapter](../../app/Services/HttpEscortPortalClient.php) via Webshare
returned HTTP200 in 1.93056 seconds, verified TLS/no redirects, one supported
contact matching private expectation on first fetch. At 20:07:00 UTC, ingress
paused/403, environment atomically updated and fresh native bindings verified,
then normal rewrites restored/health200. `ESCORT_PORTAL_DRIVER=http`,
`SCRAPER_PROXY_DRIVER=webshare_rotating`, private endpoint/credentials, 5-second
proxy connect and 10-second portal timeout are now persistent beta settings.
No automatic cutoff/monitoring. Scheduled probes off; blank override/legacy
proxy; existing phone enrollment/three-entry list, SMSTools, OTP-log-off,
production/debug-off, APP_KEY, store/prefix/budgets, data and threshold preserved.
Parsed environment equality verifies no unrelated settings change.

Closeout 20:07:09 UTC: 94 runtime files match recorded deployment, zero active
global attempts (20/hour, 100/day), zero challenges, three bindings, one client,
three reports, one feature-level row, thirteen migrations. Public empty-input
initiation/query both expected JSON422, no send. No source deployment,
migration or Git publication. Private original environment retained mode600
outside public root; temporary local/remote helpers removed, sanitized proof kept.
Existing owner pause helper remains available, affecting all new HTTP but not
in-flight/provider-accepted SMS. At activation, receipt/verification/query were
unqualified; the later owner report confirms receipt/onboarding, not query/report.
Rotating exit/ad
changes can fail a later fetch; ongoing public budget exposure accepted by owner,
not a hard one-send limit or overall readiness claim.

**Current October 29 operation, 17:47:38 CET (UTC+01:00), verified local clock:**
owner requests advance preparation and no fixed cutoff; intended live ad access
would stay on until owner pause, subject to unchanged budgets/public endpoint
exposure, not a hard one-SMS cap. Activation remains gated on a genuine expected
contact match. Three new guarded Webshare fetches returned HTTP 200/verified
TLS/no redirects, each with no supported contact. Owner approved one additional
diagnostic fetch, again HTTP 200; offline inspection identified the portal's
"Vypršala platnosť" (ad expired) heading/notice, no contact selectors or advertised
number. No match/SMS/configuration activation. This ad-state failure does not
justify a parser fallback, recipient substitution or proxy/TLS workaround.
Renew/reactivate the ad before another contact qualification and activation;
four fetches total, no automatic continuation.
Hosted closeout at 16:47:38 UTC reverified all 94 runtime files, unchanged
fixture/no-directory/proxy-off, valid enabled three-phone allowlist,
SMSTools/OTP-log-off/no override/probes off/threshold three and public health200.
Global budgets remain 20/hour and 100/day with zero active recorded attempts;
business counts unchanged (zero challenges, three bindings, one client,
three reports, one feature-level row, thirteen migrations).
Fresh local regression validation: 193 tests / 1,136 assertions plus synthetic
environment escaping/exact-restore/drift refusal. No source deployment or Git
publication. Saved private page and unused local/remote helpers removed;
requested private ad/recipient input retained owner-only outside Git.
The [extractor](../../app/Scraper/Portals/AmaterkySkPhoneExtractor.php) explicitly
classifies the recognized advertiser-disabled heading, while this expired page
has no supported contact and therefore yields the existing missing-phone error
before SMS. Neither state revokes an already verified device binding.
The earlier success below is dated October 24, not current
qualification of the newly supplied private ad/contact.

**October 29 coordination / ad-off boundary:** 2026-10-29 17:19:08 CET
(UTC+01:00), verified local clock; local source inspection only, no renewed
hosted evidence. Owner plans onboarding October 29, ordinary use October 30,
ad-off October 30 evening through the weekend and reactivation next week.
Personal ad/contact supplied in conversation is not retained here, and is not
assumed identical to the earlier privately qualified input.
[Signed queries](../../app/Services/BlacklistQueryService.php) and
[reports](../../app/Services/ReportSubmissionService.php) authorize by stored
device binding/signature without fetching the ad or checking its active status.
Ad deactivation alone therefore does not revoke existing authorization.
[Amaterky extraction](../../app/Scraper/Portals/AmaterkySkPhoneExtractor.php)
rejects the recognized disabled-ad heading during fresh onboarding, before SMS.
Ordinary weekend app access is not a fresh ad/OTP enrollment test; preserve a
working enrollment unless a separate reset/re-enrollment test is intended.
Owner clarified: reset once tonight on the installed app, enroll while the ad is
on, then preserve enrollment for ordinary BlackList availability checks while
the ad is off. No weekend reset/new SMS planned. Older Android incoming-call
capture and system-blocked overlay are outside this tester's current scope;
second-phone overlay testing is tentative next week or the following week.
Actual client recovery and hosted ad-off behavior remain unverified. Last
effective portal setting observed October 24 is fixture/no directory, not
live-onboarding readiness for tonight.

**Current result: genuine extraction qualified; SMS/OTP step paused.** Owner
conditionally approved option 1 if existing Webshare credentials were available.
Private local configuration contained a complete endpoint/credential pair
(HTTP proxy hop; bounded credential-hop risk accepted in that option).
At 18:02:49 UTC, the actual safeguarded HTTP adapter fetched the consenting ad
through Webshare on attempt one of the maximum three: HTTP 200 in 2.19138 seconds,
completed target TLS/verification, no redirect, one supported contact matching
the privately supplied expected recipient. No raw page, URL, recipient or
credential in shared output; no pool-wide scan or exit-IP identity claim.
This is hosted extraction evidence, not SMS delivery or a proxy-reliability rate.

Owner was not with the recipient and explicitly paused the SMS step.
The live window was never opened: no persistent portal/proxy environment change,
SMS reservation, challenge or binding write. Prepared nine-minute restoration
controls passed synthetic escaping/exact-restore/drift-refusal checks but were
not executed on beta; unused helpers and private ad/recipient input were removed.
Sanitized session proof and the existing owner pause control remain.
Closeout at 18:14:03 UTC verified source identity, production/debug-off,
fixture/no directory, private three-phone enrollment enabled, SMSTools/OTP-log-off,
blank override, persistent proxy/probes off, normal rewrites and public health200.
Global limits still 20/hour and 100/day with zero active recorded attempts;
aggregate business counts unchanged. Next owner action is recipient readiness
for the remaining single initiation/SMS/OTP/signed verify/query/report step,
not a client rebuild or hosting-support prerequisite. No automatic resumption.

### Earlier direct-transport attempt and Webshare clarification

Owner approved the bounded operational slice and stated risks after local
inspection. Hosted read at 17:20:00 UTC verified the expected beta index target
and all 94 inventoried runtime files, unchanged lock, PHP 8.5.12/cURL pinning,
production/debug-off, valid enabled three-phone allowlist, actual SMSTools
binding/expected endpoint/key presence, OTP logging off, fixture/no directory,
blank override, no proxy/probes, database limiter and report threshold three.
Existing global limits are 20/hour and 100/day, recipient limits 3/hour and 5/day,
cooldown 60 seconds; no active recorded global attempts. These are current
counter observations, not proof of no provider-side historical expenditure.
Read counters directly without expired-cache cleanup or reservation/reset.
Business counts: zero challenges, three bindings, one client, three reports,
one feature-level row, thirteen migrations; no target/feature attribution.
Owner saved ad/expected contact privately, confirmed consent and operated the
prepared pause helper; independent public HTTPS check returned 403. The single
CLI no-SMS preflight then failed during direct fetch with
`EscortPortalTimeoutException`, before a page/contact comparison was available.
The adapter maps any Laravel `ConnectionException` to that class and discarded
the first attempt's native transport context. After explicit diagnostic and
conditional-completion approval, one further CLI fetch used existing HTTP
failure/statistics events, without changing application source or safeguards.
At 17:39:21 UTC it failed with native cURL 28 after 9.999886 seconds:
HTTP status 0, connect/appconnect/start-transfer times 0, zero downloaded bytes,
no connected address or redirect. This establishes a connection-stage timeout,
not a certificate-validation or page-parser failure; underlying hosting
egress/routing/filtering, selected destination or portal availability remains
unresolved. `ssl_verify_result=0` without a handshake does not qualify TLS.
No further fetch, proxy workaround, timeout increase or TLS change.

Post-stop hosted read at 17:40:24 UTC reverified all 94 files, same effective
fixture/no-directory/no-override configuration, zero active global attempts and
unchanged aggregate business counts. Saved public rewrites are restored;
independent HTTPS health returned 200. Phone enrollment stays enabled/unchanged.
Total: two authorized fetch attempts, no returned HTML/recipient qualification,
no SMS/challenge/binding write, persistent portal switch or source deployment.
Private local ad/recipient input and temporary local/remote helpers were removed;
only sanitized session diagnostics and the reusable owner pause helper with
private saved/deny rewrites remain. Pause affects all new HTTP, not accepted SMS.
Stop stands; unused SMS authority is not automatic permission to replay.

**Owner clarification / corrected next step:** owner reports Amaterky
anti-scraping and existing use of a 1,000-proxy Webshare pool with varying failed
exit IPs. This is owner-reported operational experience, not proof the observed
timeouts were caused by blocking. Hosting fault is not established, and support
escalation is not the first prerequisite. [ADR 4](./ADRs/4_production_scraper_hardening.md)
already records the intended single rotating-endpoint Webshare integration;
its historical live results do not qualify current beta.
[RotatingProxyConfig](../../app/Scraper/Proxy/RotatingProxyConfig.php) consumes
`SCRAPER_PROXY_DRIVER`, scheme/host/port/username/password and connect timeout.
The [HTTP adapter](../../app/Services/HttpEscortPortalClient.php) uses that
endpoint while retaining URL/public-address pinning, TLS verification and no
redirects. Its overall timeout is `ESCORT_PORTAL_TIMEOUT`, not
`SCRAPER_PROXY_TIMEOUT`; the latter is consumed by the separate probe service.
The adapter makes one fetch with no automatic rotation/retry loop or per-exit
inventory. A good preflight exit does not guarantee the next client fetch works.

That Webshare-backed slice was subsequently approved; the current result above
records one successful no-SMS fetch and the owner's pause before initiation.
Resume only with the recipient available and a current private ad/contact check,
not by automatically spending the unused SMS allowance. Do not scan the whole pool,
add unbounded retries or retry the complete OTP issuance operation.
Keep phone enrollment/budgets unchanged and scheduled probes off. Check proxy
hop security separately from target TLS; do not assume an HTTP proxy connection
protects proxy credentials in transit. The existing
[probe service](../../app/Scraper/Proxy/ProxyProbeService.php) is not equivalent
to the production fetch: it permits general valid URLs/default redirects,
treats 3xx as success, and persists target URL/raw exception text and optional
body previews. Prefer the safeguarded ad adapter with sanitized diagnostics,
not an unqualified probe loop or raw telemetry in shared documentation.

The October 23 client attachment and owner report supersede outstanding
build/install/onboarding/call-test statements in the historical records below:
installed Kotlin/Compose `bl.zafo`, code 3 / beta.3 uses the beta URL; owner
reports successful onboarding and personally attended Level 2 Aggressive warning
on one tester's device after three reporters. This is owner-observed/device
evidence, not independently inspected database counts or universally fixed Waiting.
Accepted call-UI/overlay limitations do not require repeating that test.

No source, client, schema or authentication change is identified for a first
bounded ad test. [Container binding](../../app/Providers/AppServiceProvider.php)
already selects [HTTP transport](../../app/Services/HttpEscortPortalClient.php)
for `ESCORT_PORTAL_DRIVER=http`. The completed initial attempt proposed
fixture-to-HTTP configuration with direct HTTPS (`SCRAPER_PROXY_DRIVER=none`,
blank `ESCORT_PORTAL_PROXY`); that transport choice is superseded for the next
proposal by the Webshare clarification above. Keep probes off and no recipient override. Preserve the enabled
phone allowlist, SMSTools, OTP logging off, APP_KEY, stores, budgets and data.
Recheck effective beta settings and source before applying; historical proxy
configuration is not evidence its current endpoint/credentials work on beta.

[URL policy](../../app/Services/EscortAdUrlPolicy.php) permits HTTPS/443 only on
`amaterky.sk`, `www.amaterky.sk`, `eurogirlsescort.com` and
`www.eurogirlsescort.com`, rejects credentials/non-public DNS answers and pins a
validated address. Transport verifies TLS and refuses redirects; use the final
canonical supported URL, not a redirect/TLS workaround. cURL support is required.
[Parser](../../app/Services/EscortAdHtmlParser.php) selects host-specific extractors,
not generic page text. [Amaterky](../../app/Scraper/Portals/AmaterkySkPhoneExtractor.php)
prefers contact tel, then SMS, then contact heading, rejecting disabled ads.
[EuroGirlsEscort](../../app/Scraper/Portals/EuroGirlsEscortPhoneExtractor.php)
uses contact attributes/decoded contact values, returning the first candidate;
multiple contacts/social attributes are a concrete wrong-recipient risk.
These selectors and normalization do not establish current page correctness.

The initial direct-slice proposal below is historical, not resumed authority:
before one owner-triggered initiation, privately select a consenting recipient
controlling the ad contact and compare a no-SMS fetch/extraction to the expected
number. Prefer one unambiguous SMS-capable contact; stop on mismatch, multiple
conflicting contacts, blocked/changed markup or failed direct fetch. Proposal:
at most two GETs of that one URL (preflight plus initiation), no retry/probe,
one SMS attempt, no resend, at most ten minutes with HTTP enabled; restore
fixture/no-directory immediately after initiation or on stop. Owner enters the
received OTP privately; existing signed verify/query/report remain unchanged.
Same-phone verification can replace that phone's binding key; do not promise key
continuity from the client's owner-tested Reset onboarding action.

The phone allowlist applies only to owner-phone issuance, not ad initiation.
The public ad route can send to other extracted contacts within shared budgets;
the one-send operator allowance is not an endpoint-wide cap. Review actual limits
and remaining capacity without resetting counters. Owner accepted the stated
short-window exposure, historical non-TLS DB/shared-account filesystem risks and
untested recovery for this slice; those risks are not remediated. Do not extend
that acceptance to unrestricted reopening or require a new coordinator.
Owner-operated emergency pause: save beta's actual document-root `.htaccess`,
replace it with `Require all denied`, verify public denial, restore the saved
file only deliberately. Qualify owner access/control before sending. This pauses
all new beta HTTP requests (phone/query/report too), not already-admitted work,
CLI producers or provider-accepted SMS. Do not use log-SMS as a successful hold.
Unknown acceptance/delivery means stop, preserve challenge/budget state and
investigate privately without replay, refund, reset or database rollback.

**Executed local evidence:** 193 tests / 1,136 assertions, PHP 8.4.12, isolated
in-memory SQLite with fake SMS/mocked HTTP: HTTP/URL protections, both extractors,
extraction job, SMSTools acceptance/failure, ad/allowlisted-phone auth, signed
verification/report/query. VS Code runner found no PHP tests; direct existing
PHPUnit runner passed. No live page, delivery or hosted-worker qualification.

## Current closed-beta direction - existing phone screen with a private allowlist

Owner selected the existing owner-phone input and SMS OTP flow for the owner and
two testers, each receiving the code on their own phone. The earlier local-only
invitation design was superseded and removed completely: no invitation endpoint,
command, model, migration, feature flag, extra identity or new client screen.

[OtpChallengeService::issueForOwner](../../app/Services/OtpChallengeService.php)
now accepts a private comma-separated `OWNER_PHONE_NUMBERS` list from
[security configuration](../../config/security.php) and the blank
[example](../../.env.example). `OWNER_ENROLLMENT_ENABLED=false` remains the
default. A nonempty list supersedes `OWNER_PHONE_NUMBER`; unset/exactly empty
list preserves legacy single-number behavior. Entry-edge whitespace is trimmed;
all entries must be canonical E.164. Malformed/non-string configuration fails
closed before any SMS reservation, with the existing sanitized error-level 503.
Disabled/non-eligible requests retain 403 `owner_enrollment_unavailable`.

The existing [request](../../app/Http/Requests/Auth/InitiateOwnerAuthRequest.php),
[controller](../../app/Http/Controllers/Api/Auth/InitiateOwnerAuthController.php)
and [route](../../routes/api.php) are unchanged:
`POST /api/auth/owner/initiate`, `phone_number`, shared global/initiation IP
limiters, 201 `auth.sms_initiated` with challenge/masked recipient/expiry and
object metadata. No new container binding, queue, provider or external dependency.
After exact membership validation, synchronous SMS goes to the submitted allowed
phone through the existing `SmsSender` binding, never to a common first entry.
The inspected [SMSTools adapter](../../app/Services/SmstoolsSmsSender.php) puts that
argument in `data.recipients[0].phonenr` and checks acceptance for the same
recipient; this is source inspection, not a provider call or delivery check.
Normal shared SMS budgets, challenge locking/replacement/expiry and no-refund
unknown-send handling are unchanged.

[Verification](../../app/Services/AuthVerificationService.php) and
[signatures](../../app/Services/DeviceSignatureService.php) are unchanged. The
existing unique-phone `otp_challenges` and `device_bindings` tables keep each
tester independent. No seeded binding or migration: OTP and possession of the
device private key are still required. Same-phone re-enrollment replaces only
that phone's key. Removing an allowed number or disabling issuance does not
revoke issued challenges or existing verified bindings.

[Report submission](../../app/Services/ReportSubmissionService.php) retains the
bound-phone hash identity, duplicate rules and threshold three per target/feature;
[signed queries](../../app/Services/BlacklistQueryService.php) still expose only
stored Level 2 features. Three individually verified testers can contribute
independently, with no invented reports or changed thresholds.

The [API contract](./BACKEND_API_CONTRACT.md#post-apiauthownerinitiate) and
[README](../../README.md#closed-beta-reuse-the-existing-phone-screen) explain
reuse of the installed phone/OTP screens and unchanged signed verification.
Client label "Beta tester phone" is optional. The latest client handoff reports
beta.3 installed with `https://beta.zafo-forum.sk/api/` and source published via
client PR #15; the earlier uncommitted-source/build blocker is superseded.
No Android checkout is inspected here and no new client build is needed for
existing ad onboarding. Preserve app data/keys; use the owner-tested Reset
onboarding action when needed, not Clear app data/uninstall. Its storage/key
effects remain uninspected.

**Publication and activation:** owner authorized branch/commit/push/PR merge/pull
and beta deployment/configuration/SMS activation. [PR #22](https://github.com/KontentWave/bl-server/pull/22),
reviewed head `2514566bc534df0cead552d248ad75d1df9fe3c5`, merged at
20:01:17 UTC; local main pulled merge `8d98219a06c932fd966d761a3159135832331bbd`.
Reviewed/merge trees match; exact-head merge, no bypass, GitHub CLEAN/mergeable
with no checks listed (not CI-pass evidence). Seven-file scope, ignored handoff
and real recipients excluded, co-author trailer verified. No origin push.

Host result at 20:09:04 UTC: `PASS_BETA_ACTIVATED`. New private release
`~/apps/bl-beta/releases/8d98219a-20261020/app/` is the beta index target; previous
release retained. Bundle SHA-256
`cc73b7662673a619f70f614ffbbc88e672646976e32cae1f70ae103e9fba8ba0`;
unchanged lock SHA-256
`4374bada085a68ad96a86e6e2da0b8e1a9e81cc404fb261609e2ec19a8f58c00`.
Exported source inventory verified; initial inventory erroneously included
`.github` files excluded by Git attributes and stopped before configuration.
Corrected inventory follows the actual archive. Existing production vendor was
copied without downloads, hooks, dependency changes or autoload regeneration;
candidate autoload resolution verified.

Private environment preserves beta APP_KEY/database/cache/budgets and unrelated
settings. Owner enrollment is enabled with exactly three privately supplied
canonical phones; singular fallback blank, no embedded values. SMS driver/binding
is SMSTools using the existing working installation's private provider key/sender,
expected HTTPS endpoint, bounded timeouts and OTP logging off. Development
recipient override is blank. Portal remains fixture/no fixture directory; use
phone enrollment for this beta, not the unqualified ad/scraper path.

Native production/debug-off PHP 8.5.10 kernel checks passed health200,
missing-owner-input422, ineligible synthetic phone403 and unsigned query401.
Actual allowed-list count, adapter binding, threshold three, shared database
limiter and encryption roundtrip verified. OTP/binding/client/report/feature and
migration row counts unchanged. No seed, migration, key generation, flush or SMS.
Smoke limiter/log writes remain; no counters were reset or refunded.

After successful native checks, index symlink switched atomically and directory
deny-all replaced with the candidate's standard Laravel rewrites. Public HTTPS
checks at 20:09:41 UTC returned health200 and expected JSON 422/403/401 envelopes
with object metadata, normal TLS verification and no redirects/proxy bypass.
Zero SMS attempts; no genuine-recipient enrollment/delivery, signed device or
incoming-call qualification follows. Uploaded phone input/archive/inventory/helper
removed; private release/configuration retained. Old installation/source untouched.
Independent read-only closeout at 20:15:40 UTC confirms active index/rewrites,
mode-700 release/mode-600 environment, preserved beta key and matching private SMS
key, exactly three allowed entries, all 6,824 copied vendor files byte-identical,
no standard config cache, previous release retained and temporary inputs absent.

Earlier read-only hosted checks on October 20, 20:57-21:02 CEST, before activation:
the client's
`bcuszlr92817.zafo-forum.sk` entrypoint still points to revision `a1e36535...`,
with inspected source anchors matching HEAD, no owner route and no standard
config/route caches. Beta points to release `934bb9d2-20261017`, matching inspected
local source anchors, with directory deny-all, owner disabled/no configured owner
phone and declared log-only SMS. Both on-disk thresholds are three; these are not
all-worker/FPM attestations. Existing access records for October 20 show three
owner-initiation 404s and eight blacklist-check 200s on the client hostname,
plus ad initiation/verification success statuses. Response Content-Type/bodies,
the requesting device and ringing-versus-manual origin are not established.
Target-specific database inspection was explicitly deferred; no current
target counts or Level 2 claim. Those earlier read-only checks made no changes;
the separately approved activation above supersedes beta's denied/log-only state.

Local isolated allowlist and affected auth/report/query tests pass
**111 tests / 946 assertions** (PHP 8.4.12, in-memory SQLite, mocked SMS/portal and
stray-network prevention). [Owner-route tests](../../tests/Feature/Auth/InitiateOwnerAuthTest.php)
cover fail-closed malformed lists, precedence/legacy fallback, exact independent
recipients, resend isolation, three signed phone bindings, replay rejection and
ordinary reporter identities/duplicates/thresholds/queries, alongside existing
auth regressions. No real sends or phones in test fixtures, no hosted activation
or genuine concurrency/Android execution established.
Scoped Pint and `git diff --check` pass. Shared report/query/signature/route/console
files match baseline again after removal of the invitation implementation.

## Historical October 17 deployment evidence

**Updated:** 2026-10-17 14:47:07 CEST (UTC+02:00), verified local clock for owner-approved documentation-only publication of saved post-deployment evidence. Documentation baseline main/local github/main `ed3e4b084ab5177e51d879019a3a15d653bf0d7a`; deployed candidate remains `934bb9d2d8f91467e738ed27934e8782ed4ab353`, source tree `6f5588bb4e4135eb82b5dec6b024c062953b75f7`. Executed deployment closeout was verified at 14:32:07, with no new hosted action in this publication. Local inspection covers auth/signature/query/report/shared abuse boundaries and production autoload; earlier 80 targeted auth tests / 614 assertions and scoped Pint retain their scope. Earlier October 16 migration/runtime/FPM findings retain their original candidate/timestamps/scopes, not reassigned to this new revision. Only this map/deployment plan are being published; ignored handoff/editor files stay outside Git.

**Executed release: PASS_CLOSED_OWNER_DEPLOYMENT.** [PR #19](https://github.com/KontentWave/bl-server/pull/19) reviewed head `bef6c5fab327b997cd2725942c28ae46446ca1aa`, guarded merge/main `934bb9d2d8f91467e738ed27934e8782ed4ab353`, exact tree identity verified; returned to main. Release `~/apps/bl-beta/releases/934bb9d2-20261017/app/` now targets the public index behind unchanged directory denial. Local execution 14:31:29-14:31:34 CEST, host PHP 12:31:34-12:31:36 UTC, distinct clocks. Bundle SHA-256 `882a0242870674268b0c03d6c7049baf35d56eb0049026d688897411071cbf21`; same lock/83 production packages/31 dev exclusions. Verified vendor reused without downloads; only Composer classmap/static autoload regenerated with scripts/plugins disabled for new classes. Both candidate file inventories verified; old release retained.

**Configuration/runtime contract:** copied old active private `.env` and inactive draft byte-for-byte, independently verified without printing/hash-recording key material. Same APP_KEY/database/cache prefix/bindings, no migrations/seed/flush. Native production/debug-off HTTP kernel on PHP CLI 8.5.10 verified health200, owner-validation422, disabled-owner403 `owner_enrollment_unavailable`/retryable false and blacklist-validation422. Owner config false/no recipient; log-SMS/OTP logging off, fixture/no directory/probes off. API=3/initiate minute=2/hour=2 plus timers created six exact synthetic cache rows, verified and removed; all non-ledger tables empty and 13 ledger entries unchanged. DB session still non-TLS. Public `.htaccess` never relaxed; HTTPS 403 before/after atomic index switch.

**Persistent effects/limits:** private generated `.env`, package/service manifests and warning log, plus release-root draft copy. Upload helper/inventory removed; private checks contains only mode-600 result, exact entry temporary absent/config cache absent. Sanitized local/hosted evidence and final Android contract are in the [server handoff](./client-docs/SERVER_TO_CLIENT.md). This is new-revision private HTTP-kernel/source-switch qualification, not new-revision FPM/public-API/SMS delivery. No owner recipient/enablement, SMS attempt, provider/scraping, Android/distribution or live-use exception. Authority consumed; both beta gates OPEN/BLOCKED, public denial/no-send retained.

## Optional owner enrollment - local source only, not deployed

**Status correction, 2026-10-17 14:35:39 CEST (UTC+02:00), verified local clock:** original heading retained for existing links; the local-only state is superseded by the executed closed release above. Owner route is now published/deployed, still disabled with no configured owner recipient. No actual owner/SMS activation or new-revision FPM/public-API claim.

Owner selected OTP-based enrollment without a portal ad on October 17. [New route](../../routes/api.php) `POST /api/auth/owner/initiate` uses [bounded E.164 validation](../../app/Http/Requests/Auth/InitiateOwnerAuthRequest.php), a [thin controller](../../app/Http/Controllers/Api/Auth/InitiateOwnerAuthController.php) and [OtpChallengeService::issueForOwner](../../app/Services/OtpChallengeService.php). [Security configuration](../../config/security.php) adds `OWNER_ENROLLMENT_ENABLED=false` and private `OWNER_PHONE_NUMBER` (blank in the [example](../../.env.example)); no real owner phone in source/tests/docs. Enabled enrollment requires exact canonical configured-number equality. Disabled/ineligible returns logged 403 `owner_enrollment_unavailable`; malformed enabled configuration returns sanitized error-level 503 with the same code, not a silent fallback. Validation failures retain 422 `validation_failed`; owner-unavailable metadata says `retryable=false`. [README](../../README.md#optional-owner-phone-enrollment) records the additive contract and lifecycle.

Both initiation routes use shared `throttle:api` and `throttle:auth-initiate` keys from the unchanged [provider](../../app/Providers/AppServiceProvider.php); changing routes cannot obtain a fresh IP allowance. No new service-container binding, queue, provider adapter or dependency. Owner issuance bypasses URL validation/portal extraction only, then shares private issuance logic: existing recipient/global SMS reservations/lock, random six-digit hashed OTP, 15-minute challenge, unique-phone transaction/replacement, one synchronous `SmsSender` call after commit. Literal `ad_url=owner-enrollment` records provenance, not a fake scraped URL. Owner and ad enrollment for the same phone share replacement and budgets; no refund/reset/replay on failed or unknown sends.

[Existing signed verification](../../app/Services/AuthVerificationService.php) and [signature payloads](../../app/Services/DeviceSignatureService.php) are unchanged. No phone-only binding, fixed OTP, automatic seed/migration or administrative role. [Device bindings](../../app/Models/DeviceBinding.php) already have no automatic expiry; [report](../../app/Services/ReportSubmissionService.php) thresholds/duplicates and [query](../../app/Services/BlacklistQueryService.php) signatures remain ordinary. Re-enrolling the same phone may replace its key; disabling owner issuance does not revoke existing bindings or already-issued challenges. No permanent/irrevocable-access promise.

Android coordination is required: explicit owner-phone initiation UI calling the new route, then reuse existing OTP/signature/enrollment-state flow without embedding the owner number or clearing device keys. Actual client-owned messages and shared contract remain read-only/unmodified. Local PHP 8.4.12 regressions pass **80 targeted auth tests / 614 assertions**, scoped Pint and whitespace/documentation-link checks, using in-memory SQLite, synthetic numbers and fake SMS/HTTP. [Owner tests](../../tests/Feature/Auth/InitiateOwnerAuthTest.php) cover eligibility, no portal call, OTP/hash/expiry/attempts, shared budgets/IP limits, failure/resend persistence, signed verification/query/report and normal duplicate/threshold behavior; [existing initiation](../../tests/Feature/Auth/InitiateAuthTest.php) and [verification](../../tests/Feature/Auth/VerifyAuthTest.php) regressions pass. Hosted private-kernel disabled-owner acceptance is recorded above; no real-phone/SMS or new-revision FPM/public-API qualification. Candidate `934bb9d2d8f91467e738ed27934e8782ed4ab353` deployed as a new release, not mutation of `c4e16a4a393f56c887e747465cdd1e161c2dc558`. Beta remains closed/no-send and both gates OPEN/BLOCKED; full client task/evidence in the [server handoff](./client-docs/SERVER_TO_CLIENT.md).

## Closed hosted qualification - unchanged fixed candidate

**Current actual-web result for the unchanged deployed candidate: PASS_GUARDED_WEB.** Owner-approved temporary source-IP/one-time-header access at `https://beta.zafo-forum.sk` used the fixed candidate's [public rewrite rules](../../public/.htaccess) and unchanged [entrypoint](../../public/index.php). Successful operation local 2026-10-16 23:14:14-23:14:19 CEST; host coordinator 21:14:16-21:14:20 UTC (distinct clocks). Native web probe observed PHP 8.5.10 / `fpm-fcgi`, production/debug-off, active-key encryption/decryption, [log-SMS/fixture bindings](../../app/Providers/AppServiceProvider.php), disabled OTP logging/fixture directory/probes, selected database and database cache/limiter/lock configuration. FPM's database transport negotiated no TLS. HTTPS certificate/hostname verification remained enabled; no redirect/proxy or verification bypass.

**Actual web contracts/cleanup:** the same nine health/API assertions described below passed over HTTPS, including a correctly signed synthetic empty-result query and tampered-signature rejection. Fourteen HTTPS requests total include closed/header-denial and probe/source-correction checks. SSH and HTTP peers differed; Apache diagnostic response headers proved receipt of the one-time header and identified the observed public HTTPS peer, used for one narrow IP correction without dropping the IP/header restriction or trusting forwarded headers. All [named middleware counters](../../app/Providers/AppServiceProvider.php) matched API=8, initiate minute/hour=1 each, verify=2, with four timers. Admission was reclosed before exact removal of one synthetic binding/eight limiter rows; all non-ledger tables empty. Original source, active configuration/key, inactive draft and index link unchanged. Public rules restored exactly to `Require all denied`, then HTTPS returned 403 even with the previous valid header. Temporary rewrite/no-store/diagnostic rules, probe and uploaded helpers removed; no public routing left enabled.

**Provenance/scope:** two prior tasks stopped at the guard before seeding/API calls and restored denial. The first local evidence-save bug and initially incorrect UTC-prefix task lookup were corrected; closeout recovered all three remote results, each now the sole mode-600 file in its mode-700 task directory. [Server handoff](./client-docs/SERVER_TO_CLIENT.md) records precise evidence and the corrected earlier status report. No SMS attempt, provider/scraping, migration, cache flush, old-state or Android action. This qualifies the observed FPM/HTTPS slice, not all workers/ingress, genuine onboarding/report lifecycle or SMS delivery. Non-TLS/shared-filesystem findings stand, no live-use waiver; BETA-BACKEND-001 and BETA-SMS-001 remain OPEN/BLOCKED.

**Completed private runtime result: PASS_PRIVATE_RUNTIME.** One owner-approved SSH operation at local 22:40:41-22:40:44 CEST (host PHP 20:40:42-20:40:45 UTC / 2.619 seconds, distinct clocks) reverified the fixed 6,936-file inventory, beta database identity/empty non-ledger tables and public denial/index link. It exclusively created private mode-600 `app/.env` from the corrected inactive draft with a fresh APP_KEY, then natively bootstrapped production/debug-off and verified encryption/decryption, log-only SMS/OTP logging off, fixture/no fixture directory, probes off and database cache/limiter/locks. Persistent generated files are `.env`, `bootstrap/cache/packages.php`, `bootstrap/cache/services.php`, `storage/logs/laravel.log`; no config cache or compiled health view. Original candidate files/draft unchanged.

**Verified HTTP boundary:** temporary `127.0.0.1` PHP CLI 8.5.10 server used unchanged [entrypoint](../../public/index.php), [routing](../../routes/api.php) and [exception envelopes](../../bootstrap/app.php). Nine requests passed: JSON health 200; four validation 422s; unknown challenge 422 `challenge_not_found`; unsigned query 401; correctly signed synthetic query 200 `blacklist.checked` with empty features; tampered signature 401. [Signature normalization/canonical payload](../../app/Services/DeviceSignatureService.php) must also be applied to the directly seeded synthetic public key before [binding lookup](../../app/Services/BlacklistQueryService.php); this was synthetic authorization, not real enrollment. Fixed framework `ThrottleRequests` hashes limiter name plus [provider-defined key](../../app/Providers/AppServiceProvider.php) with MD5: exact observed counters API=8, initiate minute/hour=1 each, verify=2, with four timer rows. After confirmed server termination, the one exact binding and eight exact cache rows were removed; all non-ledger tables empty. No valid initiation, SMS attempt, scraping/provider request, public HTTP, migration or flush. Helper removed, private result retained as described in the [server handoff](./client-docs/SERVER_TO_CLIENT.md).

**Scope after the separate web check:** CLI-server/native configuration and the observed FPM/HTTPS path are now qualified for these requests only; complete ingress/all-worker coverage, genuine onboarding/report lifecycle, Android and SMS remain separate. Health requested JSON, so its HTML template's external font/script resources were neither rendered nor fetched. Earlier non-TLS DB transport/shared-filesystem findings and preparation-only exception stand; no real-user risk waiver. Both beta gates remain OPEN/BLOCKED; this execution authority is consumed. Do not repeat database/key setup.

**Completed database result, before runtime activation:** initial owner-approved SSH task at local 21:48:22-21:48:25 CEST returned from Migrator but stopped at a helper table-set guard; no synthetic work or automatic retry followed. Owner separately approved [inspection and conditional continuation](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#held-schema-inspection-and-conditional-synthetic-continuation---pending-approval), completed locally 21:57:53-21:58:07 CEST (host PHP 19:57:56-19:58:11 UTC / 14.563 seconds, distinct clocks). It confirmed the first stop was only SQL/PHP sorting, accepted 15 InnoDB tables/expected columns-indexes-FKs/13 exact batch-1 entries and reran no DDL/migrations. Shared cache CRUD/add, 100 overlapping-worker increments with unique 1-100/final 100 and stable expiry, real three-second TTL, limiter threshold/expiry/reset, cross-process exclusion/release/expired-lease/stale-owner tests passed. Three PHP processes/connections, at most five cache/two lock rows, 130 synthetic DML statements. Both workers exited, all seven task keys absent and non-ledger tables empty; source/inactive draft/blank APP_KEY/absent active `.env`/public denial/index link unchanged. Both tasks' helper/manifests/runtime directories removed; private original/success result records retained. No HTTP/provider/SMS/old-state/Android/publication operation. All three MariaDB 11.4.13 / REPEATABLE-READ sessions negotiated no TLS. CLI-only qualification, lock pruning disabled process-locally; web-worker parity/application lifecycle/ingress and live-use exposures remain unqualified. Both beta gates OPEN/BLOCKED; both database approvals consumed.

**Initialization decision update:** 2026-10-13 20:27:28 CEST (UTC+02:00), verified local clock (Europe/Prague). Owner selected separate fresh no-import beta, accepting absent historical authorization/community results and intact old state in the existing installation. Design choice only; no budget reset, new capacity, provider/recipient-history waiver or operational authority. Local main/HEAD/local github/main `a70d150325db6fe6b67f86a10c45e875accc347b`, tree `4b57b2f77403609b7b89ad3e5927e9d36903d290`, empty index/clean tracked worktree before edits. Current inspection scope: existing decision/plan/review/map and actual instructions/handoffs only; no new source/runtime/hosted inspection or tests. Prior source findings and timestamps below remain historical. Fixed candidate unchanged. Next is the separately approved local review of one owner-identified environment's supplied capability evidence.

**Current target/preparation update:** 2026-10-13 21:02:45 CEST (UTC+02:00), verified local clock (Europe/Prague). Owner identifies a separate fresh installation on the existing hosting account and proposes `beta.zafo-forum.sk`; no allocation/availability verification. No capability bundle supplied, so assessment paused. Owner explicitly selects preparation of the [minimal closed, empty, SMS-disabled setup approval](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#minimal-fresh-beta-setup-approval-package), not execution. Scope: actual instructions/exchange/handoffs and existing plan/review/map/relevant decisions; no new source or operational inspection. Local main/HEAD/local github/main `e1bb2e9efe2991726f14e439a1b8a115687b9eb1`, tree `39eb293827c94cee3c56d530512847782f4f76aa`; empty index/clean tracked worktree before edits. Earlier inspection timestamps/baselines retain their historical scope.

**Directory access evidence update:** 2026-10-15 20:04:59 CEST (UTC+02:00), verified local clock (Europe/Prague). Owner reports preparation done and identifies beta directory. Newly approved single read-only SSH metadata check at **20:04:49 CEST** confirms `~/zafo-forum.sk/sub/beta` and old `~/zafo-forum.sk/sub/bcuszlr92817` resolve as expected, both mode 755 with owner/group 5496453/5496453; domain/sub parents same mode/owner, home 700. ACL metadata unavailable (`getfacl` absent), no fallback. No contents, source/runtime/process inspection, DB/cache/provider access, tests or mutations. Effective per-site execution identity/confinement and DB grants remain unverified: same-owner chmod alone cannot isolate apps running under that owner. [Current finding/next boundary](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#minimal-fresh-beta-setup-approval-package) supersedes any inference that separate directories establish access separation. Local baseline unchanged; existing three-document/editor changes preserved and index empty. No deployment, sending or readiness claim.

**Incomplete cross-site test:** 2026-10-15 20:15:31 CEST (UTC+02:00), verified local clock. Fresh owner approval allowed a scoped temporary canary/checker test, not application deployment. Setup and cleanup SSH returned 255 without captured cause; no HTTPS/PHP test executed. A separately approved cleanup-only connection at **20:15:17 CEST** verified both exact temporary files already absent. No retry or existing application/data/configuration/permission change. Cross-site filesystem access remains unverified; [plan records the failed attempt and cleanup boundary](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#minimal-fresh-beta-setup-approval-package). Previous metadata evidence retains its original scope; no beta-readiness claim.

**Current cross-site result:** 2026-10-15 20:20:37 CEST (UTC+02:00), verified local clock. Newly approved bounded retry created a beta owner-only mode-600 canary and standalone old-site checker at **20:19:56 CEST**; one authenticated HTTPS POST returned HTTP 200/`read_succeeded` with the expected random content hash. Both exact files hash-checked, removed and absence verified **20:19:57 CEST**. No Laravel/bootstrap/configuration/secret/record reads, DB/provider/SMS or existing permission changes. This proves old-site web PHP read access to the tested beta file: the required filesystem read boundary is **not met**. It does not prove beta write/DB access or PHP-version/all-worker identity. [Plan's current stop/next boundary](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#minimal-fresh-beta-setup-approval-package): identify a supported enforced per-site restriction before separately approved change/requalification, not chmod 700 or silent risk waiver. Old state untouched apart from removed synthetic checker; both beta requests OPEN/BLOCKED, no opening/deployment/readiness claim.

**Official-documentation result:** 2026-10-15 20:26:51 CEST (UTC+02:00), verified local clock. New explicit approval limited research to Websupport public official documentation, read via article pages/public WordPress REST content; no account/SSH/support access, tests or changes. [Plan's source/provenance record](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#minimal-fresh-beta-setup-approval-package) distinguishes per-directory PHP/Apache settings and HTTP password protection from direct filesystem isolation. Generic FPM separate-user guidance does not establish availability on this hosting account. Supported same-account remedy remains unverified, not proven absent. Next is one owner-relayed account-specific control/scope/old-site-impact answer, no activation or assumed VPS requirement. Earlier canary failure of read separation remains operative; other qualifications and both OPEN/BLOCKED gates unchanged.

**Current bounded acceptance:** 2026-10-15 20:32:40 CEST (UTC+02:00), verified local clock. Owner explicitly accepts shared-filesystem exposure for **continued closed no-send preparation only**, pending tomorrow's provider answer. This removes isolation as a blocker to that preparation, not as an observed failure or as a live-use gate: no independence claim or authorization for new hosting operations, live credentials/real-user data, SMS/opening. Old compromise could expose readable beta material; no reported breach does not negate this risk. Fresh no-import/old-state preservation unchanged. [Current plan](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#minimal-fresh-beta-setup-approval-package) prepares the next isolated fixed-candidate production-dependency bundle approval; local Git lock/count verification only, no build/bootstrap/download. Both beta requests and other actual acceptance gates remain OPEN/BLOCKED.

**Provider fence intake:** 2026-10-10 18:58:10 CEST (UTC+02:00) - owner-relayed helpdesk identifies target `.htaccess` `Require all denied` candidate; original observation/authority retained.

**Prior helpdesk intake:** 2026-10-10 16:41:04 CEST (UTC+02:00) - reference-platform scope and post-completion access-log semantics; no active-request/drain control identified. Historical reply limits preserved.

**Reference intake:** 2026-10-10 16:27:13 CEST (UTC+02:00) - one bounded read of owner-linked reference page reports PHP 8.5.10/FPM and selected extensions for that page only. No backend/SSH access, test run or setting change.

**Owner screenshot intake:** 2026-10-09 21:58:14 CEST (UTC+02:00) - Apache 2.4 / PHP 8.5 selected for the matching hostname; existing compatibility-test success owner-reported, not shown in result output. This remains panel-selection/report evidence, not all-worker qualification.

**Preflight update:** 2026-10-09 21:19:34 CEST (UTC+02:00) - bounded runtime/configuration/hold preflight stopped at unavailable effective web/worker/control evidence. On-disk Git/artifact/layout metadata only; source-level framework maintenance coupling below is not a qualified hosted fence/drain. No service requests/connections, config/PHP execution, operational changes, tests/audits or live SMS. Earlier database metadata retains its 20:37:31 timestamp and structural limits.

**Planning update:** 2026-10-09 20:49:03 CEST (UTC+02:00) - local source/evidence-only [deployment/configuration/rollback plan](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md); no new hosted access, tests/audits, settings or operational approval. Historical observation timestamps below are preserved.

**Qualification completion:** 2026-10-09 20:26:35 CEST (UTC+02:00) - current hosting and client-evidence boundaries below; prior inspection/audit/test timestamps preserved.

**Prior decision inspection:** 2026-10-06 10:15:42 CEST (UTC+02:00) - privacy/operator/restore/recovery source boundaries and owner decision package; documentation only, without hosted validation.

**Prior remediation update:** 2026-10-05 17:36:42 CEST (UTC+02:00) - locally remediate locked PHP advisories and verify the resolved graph, approved PHP 8.4.1 minimum, mocked SQLite and genuine combined MariaDB concurrency. The original 17:10:01 dependency/operator/privacy review is retained in the linked review/handoff history; no hosted validation or operational implementation.

**Publication preparation:** 2026-10-06 10:50:29 CEST (UTC+02:00) - owner explicitly approved branch/commit/push/PR and guarded reviewed-head merge for the six manifest/lock/documentation files. Fresh locked production/complete audits are zero, full pinned SQLite passes 157/680 and combined MariaDB passes 18/560 per isolation. No application/test/API source change or operational approval.

**Source baseline:** Local isolation assessment verifies `main`, HEAD and local `github/main` at `ad4cefb115c0eb148c0d2212cff49c0037f1f2da`, main tracks github/main, empty index/clean tracked worktree before edits. Local handoff records PR #13 documentation merged with reviewed head `9bcc9fe3356981222582f30238f3c13c949b1231`; local merged/reviewed trees equal `9037d679458669ad77cbbfbe9acacfc9c50d2fc0`, no live GitHub query/fetch. October 12 ingress/completion assessment used `4c9eede9ad5d77d4bcc084d0b8d75c4f5e86cbf6` (PR #12); prior owner-evidence intake used `81669f762b97039ed8fbef431ad4851d949a99e8` (PR #11); prior preflight used `90a86b0c27ffd7ef088aecd3bf2d130f40fd26a2` (PR #10). Fixed candidate remains `c4e16a4a393f56c887e747465cdd1e161c2dc558`, not moving main; application/runtime/manifests/lock/assets compare equal to candidate and inspection baseline `924c80256ecd4b24b568175895d2d789b9d17ba7`. Separately approved 21:42:51 hosted bare/serving HEAD and manifest/lock checksums still match historical `a1e36535f781e0a0fd51847e738576ac5eccbd12`; worktree cleanliness was not rechecked then. Prior 21:19:15 tracked-clean observation (untracked paths not enumerated) and earlier 20:35:14 observation retain their timestamps. Historical hosted source is 18 commits behind fixed candidate by local calculation, 16 behind inspection baseline. All 13 migrations/database configuration remain identical across historical-hosted-source/candidate comparison. October 6 publication preparation used `38f088db7f626efe57ac51a060f4dc1c8d278448`; results remain historical. Publication deployed nothing.

**Current scope:** Local-only isolation assessment at actual instructions/handoffs/plan/review/map and needed owner-decision sections; API/bootstrap/front controller/container registration, OTP/binding/report/query/signature/SMS/probe lifecycles and application/database/cache/session/queue/files/log/probe configuration. Morph unavailable; targeted reads only, no broad exploration/discovery rerun. October 12 failure-test reads and prior owner screenshots/test report retain original scope/unknown capture time and worker coverage. October 9 21:42:51 identity reads and unsuccessful 21:48:55-21:48:56 PHP-token/lsof discovery remain historical only. No hosting/provider/support access, runtime/bootstrap execution, test/audit, client inspection, source change or instrumentation implementation.

**Reference scope:** October 10 single HTTPS read of the exact owner-linked [PHP information URL](https://it20.phpinfo.eu/85/), with bounded in-memory extraction of selected runtime rows/extension sections only; no raw environment/configuration persisted or emitted. Page issuer/generation time/provider affiliation and mapping to this backend are unverified. No backend/hosting account connection, runtime execution, request to providers/databases/cache, source exploration or client inspection. Local baseline and fixed candidate above unchanged; yesterday's uncommitted documentation preserved.

**Helpdesk scope:** Owner-relayed provider helpdesk screenshot received October 10 approximately 16:40 CEST; reply/capture time unknown, no independently authenticated contact. Reply identifies reference page as provider-platform build/extension information, not exact subdomain configuration; explains post-completion access-log entries. No runtime/all-worker attestation, existing ingress control or active-request indicator supplied. Suggested temporary phpinfo file is new executable/public diagnostics outside current authority; not created/requested. No hosting/log access or support contact by assistant.

**Prior preflight scope:** October 9 preflight inspected actual handoffs/docs and local auth/provider/routes/cache/database/dependency/runtime/ADR plus local installed Laravel 13.34.0 maintenance source. One explicitly approved origin SSH session at 21:19:15 used existing Git/checksum/existence/fixed-string reads on known target paths only; no PHP/helper/config execution or process/account crawl. Front-controller textual autoload/bootstrap references matched; autoload/development PHPUnit directory present, installed JSON checksum only; target `.user.ini` and standard config cache absent. No effective-web mechanism, adequate serving-worker visibility or supplied ingress/drain attestation: stop. Prior 20:22-20:24 hosting metadata/one health GET, 20:35 anchors and 20:36:59-20:37:01 standalone selected DB metadata retain dated limits, not repeated qualification. No hosted raw environment/credentials/application records, service connections, requests, writes/locks or operational actions in that preflight. October 5/6 tests/audits not rerun. Effective web/configuration, loaded artifact/process parity, functional stores, hold/drain and live/client acceptance remain unverified.

The inspected application uses Laravel service-container bindings, routes and named rate limiters, rather than an application plugin hook registry. "Plugin" here includes provider adapters and cooperating modules. No custom event listeners or model observers were found in the inspected application registration paths. Android behavior is outside this source inspection.

**Fence-review scope:** October 10 local reads of [public rewrite template](../../public/.htaccess), [index](../../public/index.php), [bootstrap](../../bootstrap/app.php) and API/web/console routes; entrypoints compare equal to fixed candidate, historical rewrite template same. Known hosted index is separate and references backend autoload/bootstrap; no fresh hosted file inspection. [Placement/coverage review](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#local-review---apache-fence-placement-and-conditional-coverage) describes standalone directory authorization, not rewrite-only/method-limited blocking. Conditional scope includes all API/root/static/direct-index/health requests with expected non-contract 403 outage; no Laravel maintenance exemption bypasses effective Apache denial. Host overrides/alternate mappings/upstream cache and all-worker coverage remain unverified; synchronous admitted work/CLI producers not drained. No hosting/functional test or control action.

**Current bounded scope:** October 10 local state/instructions/actual handoff/plan/review/map and relevant operator boundaries inspected; targeted [console registration](../../routes/console.php), [bootstrap](../../bootstrap/app.php), [proxy-probe implementation](../../app/Scraper/Proxy/ProxyProbeService.php) and schedule/dispatch registration search only, not a repeat broad exploration. [Exact optional hosting scope](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#bounded-review---exact-read-only-hosting-scope-and-missing-evidence) is limited to known origin serving-directory/adjacent `.htaccess` path equality, numeric file metadata and selected directive names/safe literal authorization tokens. No raw operands or configuration crawl. No exact safe effective-vhost/backend/cache/aggregate drain source identified; provider-scoped evidence still needed. Local baseline/candidate above unchanged, empty index/prior documentation/editor/handoff changes preserved; no new hosting connection, tests/audits, runtime execution or client operation.

**Latest provider-boundary scope:** October 12 owner-pasted Websupport reply; original reply time and account-specific/all-backend attestation unknown. [Provider qualification](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#later-qualification---websupport-cache-and-completion-evidence-reply): cached responses can bypass Apache/PHP; no WebAdmin manual purge, provider recommends disabling HTTP Cache, effect/propagation/API applicability untested. Customers lack pre-cache infrastructure configuration access; external Cloudflare suggestion is not deployed/selected and would require origin-bypass coverage review. SMS history/DB timestamps/error logs cannot establish aggregate drain; new access-log wording differs from earlier explanation, neither proves zero active work. [Completed local design](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#local-only-design-assessment---ingress-and-completion) prefers cache-disable/Apache candidate but finds A ingress and B completion UNKNOWN/BLOCKED. Prospective durable atomic admission/completion records cannot bootstrap proof of uninstrumented historical drain. Any local implementation/isolated qualification requires new source/schema/test approval; hosting controls/adoption/access remain distinct. No provider/DB/log access or changes; fixed candidate and both beta gates unchanged.

## Registration and Entry Points

**API-only decision / inspection:** 2026-10-15 21:05:52 CEST (UTC+02:00), verified local clock. Owner selects Android API-only beta, no public welcome page or JS build; later exposure limited to the four API endpoints, restricted health/diagnostic access and no unnecessary static routes. Initial deployment stays closed to testers and sending. Direct reads of fixed-candidate [web routes](../../routes/web.php) and [Apache template](../../public/.htaccess) confirm the welcome route and existing-file/directory rewrite bypass remain: neither source implements this new access policy. Exact hosted mapping and target-specific admission controls need separately approved preflight/change/qualification. Local source/production-vendor bundle validation is recorded in the [deployment plan](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#candidate-artifact-specification), not hosted/runtime acceptance. Inspection baseline: fixed candidate `c4e16a4a393f56c887e747465cdd1e161c2dc558`; documentation worktree baseline `75c82c8bc52c7acaa72852048f0ef76acd81066a`. No source change, application execution, hosted access or tests in this decision batch; prior source/runtime findings retain their dates and limits.

**Predeployment path observation:** executed 2026-10-15 21:09:42 CEST (UTC+02:00), recorded 21:09:59 CEST using verified local clock. Beta/domain/sub metadata mode 755, owner/group 5496453/5496453; beta then contained only the original mode-644 `index.php` (6,593 bytes, digest in [plan](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md)), no local access/public/application/storage files. This is original-index provenance, not current layout, web mapping or exposure evidence. The subsequent private/public mapping and denial result below supersede that layout.

**Current closed mapping / qualification:** 2026-10-15 21:21:38 CEST (UTC+02:00), verified local clock. Newly approved closed file deployment executed 21:21:16-21:21:20 CEST, superseding the preflight only for file layout/access-file presence. Canonical private `~/apps/bl-beta` is owner-group 5496453/5496453, mode 755; new release parent/release/backup directories mode 700. Fixed checksum/inventory-verified source/vendor resides at `~/apps/bl-beta/releases/c4e16a4a-20261015/app/`. Public beta `index.php` links only to that release's `app/public/index.php`; original public index preserved privately at release `backup/index.php`, digest verified. New public `.htaccess` contains `Require all denied`. One random static canary returned exact HTTP 200 content before denial and HTTP 403 after it; canary hash-checked/removed and absence verified, denial retained. No application execution, config/secrets/logs/stores/providers/process/old-site access or migrations; no live credentials/user data installed. This establishes files/link metadata and one static denial path, not effective PHP symlink handling, all-ingress/cache coverage, API-only rewrite/access enforcement, functional stores or runtime/container-binding activation. Shared-filesystem preparation-only exception remains unchanged. [Deployment result and remaining scope](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md) retain exact source/bundle and pending runtime/store qualification; both beta requests remain OPEN/BLOCKED.

| Boundary              | Registration or caller                                                                        | Implementation and coupling                                                                                                                                                                                                                                                                                                                |
| --------------------- | --------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Portal transport      | [AppServiceProvider](../../app/Providers/AppServiceProvider.php), `EscortPortalClient` binding   | `services.escort_portal.driver=fixture` selects [FixtureEscortPortalClient](../../app/Services/FixtureEscortPortalClient.php); other values select [HttpEscortPortalClient](../../app/Services/HttpEscortPortalClient.php).                                                                                                                      |
| SMS provider          | [AppServiceProvider](../../app/Providers/AppServiceProvider.php), `SmsSender` binding            | `services.sms.driver=smstools` selects [SmstoolsSmsSender](../../app/Services/SmstoolsSmsSender.php), `vonage` selects [VonageSmsSender](../../app/Services/VonageSmsSender.php); other values select [LogSmsSender](../../app/Services/LogSmsSender.php). A misspelled driver can therefore silently select logging instead of SMS delivery.       |
| API throttles | [AppServiceProvider](../../app/Providers/AppServiceProvider.php), `RateLimiter::for` | Named `api`, `auth-initiate` and `auth-verify` limiters are consumed by [API routes](../../routes/api.php). All five endpoints in the new closed deployed candidate use `throttle:api`; both initiation routes share `auth-initiate`, and verification adds `auth-verify`. The previous candidate had four endpoints. |
| Maintenance boundary | [Bootstrap](../../bootstrap/app.php), [application configuration](../../config/app.php), [front controller](../../public/index.php) | Local Laravel 13.34.0 default global maintenance middleware can fence new API requests; file-based state and pre-rendered entrypoint check exist. Bootstrap excepts `/up`; optional maintenance secret/cookie bypass requires review. No application-specific sending-hold switch or hosted control/operator/drain coverage established; requests already past the gate can still write/send synchronously. Framework source is local generated vendor evidence, not loaded-worker qualification. |
| Prospective admission/completion boundary | [Local design only](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#local-only-design-assessment---ingress-and-completion), not a container binding/control/table currently implemented | Durable serialized hold/admission plus non-expiring execution/uncertainty records would need enrollment before API limiter writes/extraction and at applicable CLI producer entrances, extending through final lifecycle effects. Existing OTP cache locks are not execution/drain records. Crash/unknown commit/send outcomes stay blocking; no replay/TTL-based completion. Requires qualified shared durability and all-worker/producer adoption; cannot cover cache/static outage or historical uninstrumented work. |
| OTP initiation        | `POST /api/auth/initiate`                                                                     | [OtpChallengeService](../../app/Services/OtpChallengeService.php) validates the URL, invokes extraction synchronously through `app()->call`, reserves SMS capacity, replaces the challenge in a transaction with a `phone_number` row lock, then calls `SmsSender` after commit. Despite its name, [ExtractPhoneFromAdJob](../../app/Jobs/ExtractPhoneFromAdJob.php) is not dispatched to a queue by this path. |
| Device verification   | `POST /api/auth/verify`                                                                       | [AuthVerificationService](../../app/Services/AuthVerificationService.php) reserves the attempt before starting its transaction, then locks by the same phone index as resend, rechecks the challenge UUID, validates OTP/signature and atomically binds/consumes. Both lifecycle transactions allow at most three attempts for database concurrency errors; SMS dispatch and verification-attempt reservation are not repeated by those retries.                                                                                                                                                  |
| Reporting and queries | `POST /api/reports`, `POST /api/blacklist/check`                                              | [ReportSubmissionService](../../app/Services/ReportSubmissionService.php) and [BlacklistQueryService](../../app/Services/BlacklistQueryService.php) both authorize against device bindings and reuse [DeviceSignatureService](../../app/Services/DeviceSignatureService.php).                                                                       |
| Proxy operations      | [Console routes](../../routes/console.php), `scraper:probe-proxy`                                | [ProxyProbeService](../../app/Scraper/Proxy/ProxyProbeService.php) shares [RotatingProxyConfig](../../app/Scraper/Proxy/RotatingProxyConfig.php) with portal transport but performs separate HTTP and `scraper_proxy_attempts` writes. Manual CLI and optional fifteen-minute schedule (`scraping.probe.enabled`) are independent of Apache ingress. Current hosted execution/provider-managed or external producers and completion visibility remain unknown; historical disabled/cron observations are not renewed. |
| Error contract        | [Exception renderers](../../bootstrap/app.php) and [ApiResponse](../../app/Support/ApiResponse.php) | Domain errors and throttles share the Android-facing envelope. The authoritative wire reference is [BACKEND_API_CONTRACT](./BACKEND_API_CONTRACT.md).                                                                                                                                                                        |

### Isolated environment boundary

- [Local feasibility assessment](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#local-only-feasibility-assessment---isolated-beta-deployment): owner selected fresh no-import design on October 13 at 20:27:28 CEST; absent old authorization/community results accepted, old state remains intact outside beta. Target now owner-identified as a separate installation on the existing hosting account, proposed `beta.zafo-forum.sk`; no provisioning or sending allocation approved/qualified. Historical application drain is avoided only by real independence/no import; shared/imported state retains fencing/drain/reconciliation. Capability review paused without supplied evidence; owner instead approved preparing the [minimal setup approval](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#minimal-fresh-beta-setup-approval-package), not hosting discovery/execution. Exact candidate stays fixed; no new coordinator selected/implemented.
- [APP_KEY](../../config/app.php) drives encryption and [recipient-budget HMAC](../../app/Services/OtpAbuseProtection.php); [cache/limiter/lock selection and prefix](../../config/cache.php) identify counter authority. New key/prefix/empty store is not unexpired-budget continuity. Canonical [signatures](../../app/Services/DeviceSignatureService.php) contain no hostname/realm/APP_KEY: copied bindings can authorize matching report/query requests in both realms; copied challenges can permit dual consumption. No contract change proposed.
- [SMS settings](../../config/services.php) select backend credentials/adapter/timeouts but supply no established independent account allocation or recipient partition. Different keys can share funds; separate accounts can still message the same phone. Old accepted/ambiguous sends and external producers remain relevant. The [single synchronous adapter attempt](../../app/Services/SmstoolsSmsSender.php) is not cancellation/delivery evidence; never replay ambiguity.
- [Files](../../config/filesystems.php), [logs](../../config/logging.php), [sessions](../../config/session.php), [queues](../../config/queue.php), bootstrap/config/maintenance paths and backups must not bridge old/beta access or replay. Prefer no unneeded beta probe/scheduler/worker; manual [probe](../../routes/console.php) remains an independent HTTP/telemetry producer. One controlled runtime with qualified exhaustive completion plus an initially closed owner pause could suffice for later beta updates; process restart/kill/wait alone does not, and these facilities remain unqualified.

## Shared Persistence

These are application table names from migrations; configured cache stores can use different backing tables or Redis instead.

**Fresh beta initialized / bounded CLI qualification:** October 16 pre-write inspection established an empty selected beta schema. Owner selected database cache/limiter and matching `mysql` application/cache/lock connections to MariaDB. [Initialization and separately approved continuation](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#closed-database-initialization-and-synthetic-checks---single-pending-approval) now establish exactly 15 InnoDB tables/13 exact batch-1 ledger entries with column/index/FK checks, without migration rerun after the helper-only ordering stop. Cache/lock key primaries and expiration indexes match [cache migration](../../database/migrations/0001_01_01_000001_create_cache_table.php). Initial OTP migration already creates `otp_challenges`; historical-name compatibility migrations need no import. Both client FKs stay in the selected schema with delete cascade/update restrict; no cross-schema references. Bounded CLI functional results are above; domain tables remain empty and real-user/application behavior remains unqualified.

**Cache/framework coupling inspected locally:** fixed Laravel `DatabaseStore::increment` uses a row-locking transaction and preserves expiration; RateLimiter uses two physical rows (counter and `:timer`). `DatabaseLock` uses owner/expiry-conditional acquisition/release; its default lottery can prune expired locks across the table, not just the test namespace. The package disables that lottery process-locally (`cache.stores.database.lock_lottery=[0,100]`) so cleanup is limited to five exact synthetic cache keys/two lock keys. This is an explicit diagnostic deviation, not changed deployed configuration or qualification of routine pruning. Existing [OTP database tests](../../tests/Database/OtpConcurrencyTest.php) exercise invalid-attempt persistence with domain data/root lock observation; they are not approved hosted test runners. [Pipe-based worker pattern](../../tests/Support/MariaDbWorker.php) supports bounded independent-process checks without additional coordination tables.

**Bootstrap/file boundary:** [application bootstrap](../../bootstrap/app.php), [provider](../../app/Providers/AppServiceProvider.php) and [console registration](../../routes/console.php) supported the executed console bootstrap/Migrator/cache-only slice without HTTP, SMS/extraction or starting queue workers/schedulers. Configuration was process-local from the inactive release-root draft, never active `app/.env`; APP_KEY stayed blank and no OTP/HMAC/encryption lifecycle was exercised. Package/service manifests were generated only in private task directories via `APP_PACKAGES_CACHE`/`APP_SERVICES_CACHE` and then removed; config/routes/events caches remained absent and no storage data file was authorized/generated. Full limits and measurable expiry/ownership results are in the linked package. Executed evidence qualifies only these CLI mechanisms/settings, not web-worker parity.

**Corrected transport sequencing:** October 16 successful PDO TCP inspection did not negotiate TLS; authentication is resolved. The newer server handoff removes provider TLS research as a prerequisite for this closed no-send preparation, without reversing that observation or granting live-use acceptance. Shared-filesystem exposure likewise has preparation-only acceptance. Resolve exposure or obtain separate informed bounded acceptance before real-user use; neither finding authorizes another general hosting questionnaire. Both beta requests remain OPEN/BLOCKED.

| Table or store           | Writers and readers                                                             | Constraints and integration consequences                                                                                                                                                                                                                                                                                                                                                                                         |
| ------------------------ | ------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `otp_challenges`         | Initiation writes/replaces; verification reads/deletes                          | Unique phone number; nullable unique challenge UUID added by a later migration. Stores ad URL, OTP hash and expiry. Resend and verification use database transactions and row locks to serialize existing-challenge replacement versus consumption; actual guarantees depend on the deployed engine/isolation. Historical `auth_challenges`/`password_hash` names have compatibility rename migrations. See [initial schema](../../database/migrations/2026_03_31_120100_create_auth_challenges_table.php) and [challenge fields](../../database/migrations/2026_04_01_201500_add_challenge_fields_to_auth_challenges_table.php). |
| `device_bindings`        | Verification writes; reporting and queries read                                 | [Unique phone number](../../database/migrations/2026_03_31_123100_create_device_bindings_table.php), public key and verification time. Reverification can replace the key for that phone, affecting both signed APIs.                                                                                                                                                                                                               |
| `clients`                | Reporting creates/locks; queries read                                            | [Unique SHA-256 client hash](../../database/migrations/2026_04_07_170000_create_clients_table.php). A no-op upsert serializes existing/first creation before snapshot reads, preserving existing timestamps; an explicit row lock serializes reports for this client across features, not globally. Both modules retain the same normalization/hashing semantics. |
| `reports`                | Reporting writes and counts                                                     | [Unique client/reporter/feature tuple](../../database/migrations/2026_04_07_170100_create_reports_table.php); client foreign key cascades. Reporter hash remains derived from the bound phone, not the key. Under the client lock, current locking duplicate/count reads precede atomic insertion/promotion completion. Racing duplicates return the existing 422 domain envelope, not a caught generic SQL error. |
| `client_feature_levels`  | Reporting recomputes; queries read promoted rows                                 | [Unique client/feature tuple](../../database/migrations/2026_04_07_170200_create_client_feature_levels_table.php), count, promotion flag/time; client foreign key cascades. First creation is protected by the client lock; current reads avoid older REPEATABLE-READ snapshots. Report/count/promotion commit or roll back together; first promotion time is retained. [Query index](../../database/migrations/2026_04_08_090000_add_blacklist_query_indexes.php) covers client/promotion lookup. |
| Limiter cache and locks  | API middleware and [OtpAbuseProtection](../../app/Services/OtpAbuseProtection.php) | Database defaults use [cache and cache_locks](../../database/migrations/0001_01_01_000001_create_cache_table.php); null lock connection falls back to cache/default and null lock table to `cache_locks`. SMS capacity uses `otp:sms:budget`; verification uses per-challenge 10-second locks, with attempts outside the lifecycle transaction so failures do not refund them. October 16 hosted synthetic CLI checks passed sharing, atomic increments, TTL/limiter expiry and cross-process ownership/lease behavior without using real OTP/budget keys. Lock pruning was disabled only for those processes. Effective web-worker parity, complete OTP critical-section duration and real budgets remain outside this result; [plan](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#3-database-shared-cache-and-abuse-budget-continuity) retains opening/continuity gates. |
| `scraper_proxy_attempts` | Proxy probes write diagnostics                                                  | [Telemetry schema](../../database/migrations/2026_04_10_090000_create_scraper_proxy_attempts_table.php) stores target URL, proxy endpoint, outcome, error text and optional body preview. The production ad fetcher does not call this telemetry service in the inspected path.                                                                                                                                                     |

## Configuration Dependencies

**Resolved parsing / beta database boundary:** 2026-10-16 20:42:09 CEST (UTC+02:00), verified local clock. No-connection diagnostic established unquoted-password/comment-marker transformation. After owner quoted the intended password and aligned cache/lock connection names, one approved PDO TCP metadata-only inspection at host PHP 18:41:32 UTC (20:41:32 CEST) authenticated successfully and matched selected database to inactive draft. [Database configuration](../../config/database.php) supports MySQL/MariaDB connection names via PDO MySQL; changing the connection name alone was not the password fix. MariaDB `11.4.13-MariaDB-ubu2204-log`, inspection-session `REPEATABLE-READ`, no tables in selected schema; schema/ledger/cache/cache_locks still require approved initialization. Applicable privilege metadata declares CRUD/broad DDL/index/routine/trigger/event/lock capabilities without grant option, global USAGE only; not functional-write or other-identity separation evidence.

Inspection `Ssl_cipher`/`Ssl_version` empty: this PDO TCP connection did not negotiate TLS. No verified DB transport/CA/server identity claimed; no TLS relaxation. Next is separately approved verified transport qualification, then bounded fresh-schema and synthetic shared-cache/limiter/lock tests. No application/cache records, writes/migrations/grants, draft edit/activation, old-site access, Laravel bootstrap, public-denial change or SMS in the inspection. Draft remains inactive/APP_KEY blank; preparation-only shared-filesystem exception unchanged. [Plan](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md) and handoff retain exact evidence; earlier failed-attempt observations below remain historical, not a continuing authentication blocker.

**Resolved diagnostic provenance:** 2026-10-16 20:47:38 CEST (UTC+02:00), verified local clock before publication preparation. PDO TCP attempts at 19:41:06 and 20:21:33-20:21:34 CEST failed `HY000` / `1045`; owner supplied successful manual TCP evidence. Socket diagnostic at 20:26:04 CEST stopped before connecting; subsequent owner shell evidence confirmed the proposed socket absent. Dotenv transformation identified at 20:36:10 CEST and corrected authentication at 20:41:32 CEST supersede those troubleshooting blockers. Original sanitized evidence remains separately in session records referenced by the server handoff, not in Git. No further credential reset/socket discovery required.

**Selected beta shared store:** decision 2026-10-16 19:24:35 CEST; CLI/draft evidence recorded 19:30:57 CEST (UTC+02:00). Owner selects separate beta MariaDB for cache, limiter and locks. Fixed-candidate [cache configuration](../../config/cache.php) exposes `CACHE_STORE`, `CACHE_LIMITER`, `DB_CACHE_CONNECTION`, `DB_CACHE_TABLE`, `DB_CACHE_LOCK_CONNECTION`, `DB_CACHE_LOCK_TABLE` and `CACHE_PREFIX`; [database configuration](../../config/database.php) supports MySQL/MariaDB connection names. Current inactive draft uses `mysql` with matching cache/lock names; choices must agree across actual serving processes. Mode-600 private release-root `.env.preparation` stays outside application root, no active `app/.env`, APP_KEY blank; owner supplied DB credentials privately. Preparation settings: database cache/limiter/cache_locks, distinct beta prefix, log SMS/OTP logging off, fixture portal/no fixture directory, probes off. [Provider bindings](../../app/Providers/AppServiceProvider.php) and [services configuration](../../config/services.php) establish source semantics only, not active runtime bindings or complete egress control.

Approved hosted CLI snapshot: PHP 8.5.10; fixed release inventory/public denial/index-link reverified, generated Composer platform check executed without app bootstrap; 119 local Composer-constraint/application-extension checks passed against hosted CLI values. Native cURL/DOM/mbstring/OpenSSL/PDO MySQL/sodium and `CURLOPT_CONNECT_TO` available; generated runtime API basis remains local build Composer. These are CLI checks, not effective web/runtime or functional store evidence. Later DB metadata/transport findings are above; atomic counters/expiration/cross-process locks and actual worker consistency remain pending. Exact scope/evidence in [deployment plan](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md); fixed source baseline `c4e16a4a393f56c887e747465cdd1e161c2dc558`, preparation-only shared-filesystem exception/public denial unchanged.

Keys/behavior below describe current source. October 9 hosting inspection emitted only allowlisted static flags, driver names and credential-presence booleans; no credentials or effective web configuration were exposed. See hosted observations below.

| Configuration owner                                               | Keys and environment selectors                                                                                                                                                                                            | Shared dependency                                                                                                                                                                                                                |
| ----------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [services.php](../../config/services.php), portal                    | `services.escort_portal.{driver,fixture_directory,development_phone_override,proxy,timeout,user_agent}`; `ESCORT_PORTAL_*`                                                                                                | Initiation, extraction and HTTP/fixture adapters. Recipient override applies outside production when configured; it is not permission to use it for genuine beta identity verification.                                          |
| [services.php](../../config/services.php), SMS                       | `services.sms.{driver,from,log_otp_in_non_production}`; `SMS_DRIVER`, `SMS_FROM`, `SMS_LOG_OTP_IN_NON_PRODUCTION`                                                                                                         | All SMS adapters. `SMS_FROM` falls back to `VONAGE_SMS_FROM`, then the configured default label, so legacy configuration can affect SMSTools too.                                                                                |
| [services.php](../../config/services.php), providers                 | `services.sms.smstools.{api_key,endpoint,connect_timeout,timeout}` and `services.sms.vonage.{key,secret}`; `SMSTOOLS_*`, `VONAGE_API_*`                                                                                   | Provider credentials and timeouts belong on the backend only. Adapter selection does not prove provider acceptance or recipient delivery.                                                                                        |
| [scraping.php](../../config/scraping.php)                            | `scraping.proxy.{driver,scheme,host,port,username,password,connect_timeout,timeout}`; `SCRAPER_PROXY_*`                                                                                                                   | Rotating proxy configuration used by both ad fetching and probes. Rotating proxy selection takes precedence over `services.escort_portal.proxy` in the fetcher.                                                                  |
| [scraping.php](../../config/scraping.php), probes                    | `scraping.probe.{enabled,url,attempts_per_run,capture_body_preview}`                                                                                                                                                      | Operator probe command and telemetry; not the ad URL policy.                                                                                                                                                                     |
| [cache.php](../../config/cache.php) and [app.php](../../config/app.php) | `cache.limiter`, `cache.default`, `cache.prefix`, `cache.stores.*`, `app.key`; `CACHE_LIMITER`, `CACHE_STORE`, `CACHE_PREFIX`, `APP_KEY`                                                                                  | Workers must share limiter storage, prefix and app key. Recipient budget keys use app-key HMAC; challenge counter keys use SHA-256. Cache clearing/eviction or inconsistent app keys can invalidate recipient budget continuity. |
| [security.php](../../config/security.php)                            | `security.{api_per_minute,initiate_per_minute,initiate_per_hour,verify_per_minute,otp_verification_attempts,resend_cooldown_seconds,sms_recipient_per_hour,sms_recipient_per_day,sms_global_per_hour,sms_global_per_day}` | Limits affect all APIs, OTP verification and SMS reservations. Environment variables/defaults are listed in the API contract and [.env.example](../../.env.example); windows are fixed TTLs, not monetary accounting.               |
| [reporting.php](../../config/reporting.php)                          | `reporting.threshold`, `reporting.features`                                                                                                                                                                               | Request validation, report promotion and query labels share feature keys. Threshold is currently 3 in source; changes do not automatically rebuild existing promotion rows.                                                      |

## External and Cross-Module Dependencies

- [composer.json](../../composer.json) declares Laravel, Vonage, Symfony DOM/CSS parsing and development test tools. Approved root PHP is now `^8.4.1`, matching updated Symfony 8.1's minimum; hosted CLI 8.5.10 meets it, but web PHP remains unverified. [composer.lock](../../composer.lock) has 83 production / 31 development entries after 16 same-major upgrades and one required polyfill addition. Prior October 5/6 local locked production and complete audits report **zero advisories / zero abandoned packages**, down from the original 39 records / 12 packages / 38 distinct upstream issues. Advisory blocking/ignores are unchanged. Guzzle 7.15.5 requires promises 2.5.3 and PSR-7 2.13.1; Laravel 13.34.0 requires updated PHP 8.4/8.5 polyfills and the new PHP 8.6 polyfill; HttpKernel 8.1.8 requires VarDumper 8.1+. Unaffected/development entries are unchanged; ten unaffected Symfony 8.0 component entries remain on an unmaintained branch, a lifecycle concern distinct from the cleared known advisories. See the [remediation evidence](./BETA_READINESS_REVIEW_2026-10-05.md#local-php-dependency-remediation) for the complete delta, upstream maintenance sources and command results.
- [package.json](../../package.json) has no tracked/present JavaScript lockfile; `npm audit` fails with `ENOLOCK`. Vite/Axios can be included in served welcome-page assets despite development classification. Reproducible/audited asset scope or an explicit qualified API-only artifact remains a decision.
- SMSTools and portal adapters use Laravel's HTTP client. The ad fetcher requires PHP cURL with `CURLOPT_CONNECT_TO`, public DNS answers, valid TLS and compatible proxy tunneling; missing pinning support fails closed.
- [EscortAdUrlPolicy](../../app/Services/EscortAdUrlPolicy.php) owns exact HTTPS host validation and public-address filtering. [EscortAdHtmlParser](../../app/Services/EscortAdHtmlParser.php) delegates supported hosts to dedicated portal extractors without unrelated visible-phone fallback.
- [EscortPhoneNumberNormalizer](../../app/Services/EscortPhoneNumberNormalizer.php) is reused by extraction/reporting; changing it can change recipient identity, reporter identity and client hashes.
- [DeviceSignatureService](../../app/Services/DeviceSignatureService.php) is shared by verification, reporting and queries. Signature payloads and PEM normalization are client/server contracts, not private backend implementation details.
- Outside local/testing, abuse protection requires database or Redis cache plus Laravel `LockProvider` support. [PHPUnit configuration](../../phpunit.xml) and [test bootstrap](../../tests/bootstrap.php) pin tests to in-memory SQLite and clear `DB_URL`, even when inherited database variables are set. The bootstrap rejects cached application configuration before it can select a different database. This safe setup and SQLite tests do not certify deployed multi-worker behavior.

### Database engine compatibility assessment

- User-reported local topology remains SQLite in Docker, not freshly qualified. October 9 direct hosted selected PDO connection now verifies **MariaDB 11.4.13 / REPEATABLE-READ**; this is not effective web-session parity. A separate [test Compose file](../../compose.mariadb-test.yaml) provisions disposable MariaDB only; prior local Docker/image verification and genuine concurrency tests remain historical.
- [Database configuration](../../config/database.php) already defines `pgsql`, selected through `DB_CONNECTION`, with `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_URL` and `DB_SSLMODE`. PostgreSQL additionally requires PHP `pdo_pgsql` in each actual application runtime. Host provisioning, extension availability and TLS requirements remain unverified.
- All 13 [migrations](../../database/migrations/) use Laravel Schema abstractions; inspected auth/report/query services use Eloquent and Laravel transactions rather than engine-specific application SQL. This suggests a bounded portability task, not verified PostgreSQL compatibility. Validate UUID types, unsigned-column semantics, uniqueness/collation, timestamps, foreign keys, generated IDs and sequence state when importing existing data.
- The default [test bootstrap](../../tests/bootstrap.php) still forces in-memory SQLite. The separate [MariaDB configuration](../../phpunit.mariadb.xml), [guarded bootstrap](../../tests/bootstrap-mariadb.php) and [runner](../../scripts/test-mariadb.php) verify Docker project/service labels, loopback port and tmpfs database storage before migrations. Runtime-generated credentials, fixed synthetic database/user, uncached testing configuration, fixture extraction, log-only SMS and stray-HTTP rejection prevent using existing databases or live providers. Each run removes only its unique test container/network.
- [MariaDB OTP coverage](../../tests/Database/OtpConcurrencyTest.php) runs all migrations without resetting any existing database, checks InnoDB tables and actual isolation, and uses [independent PHP workers](../../tests/Support/MariaDbWorker.php) to exercise genuine overlap. Root observer access exists only in the disposable test container to query InnoDB lock waits; application operations use the restricted test user. Worker payloads stay in process pipes; only sanitized control outcomes are returned.
- Database-backed cache/locks follow [cache connection configuration](../../config/cache.php), including `DB_CACHE_CONNECTION` and `DB_CACHE_LOCK_CONNECTION`. A database cutover must account for limiter continuity, workers, sessions and queued jobs as applicable, not only business tables.
- PostgreSQL adoption has not been approved or implemented. Existing device bindings, report identities and community records must be preserved in any separately approved migration, with import/constraint/sequence verification, backups, a write-cutover plan and rollback. Changing engines alone does not close CB-06 or CB-07; genuine concurrency tests must target the engine/isolation ultimately selected for beta.

## Current Hosted Observations

**Observed:** prior hosting qualification 2026-10-09 20:22:36-20:24:45 CEST (UTC+02:00); fresh source/config anchors 20:35:14 and selected database metadata 20:36:59-20:37:01 CEST. Existing `origin` SSH only, strict host-key checking; standalone PHP emitted UTC, no web timezone evidence. Prior [hosting evidence](./BETA_READINESS_REVIEW_2026-10-05.md#october-9-bounded-hosted-qualification), fresh [database qualification](./BETA_READINESS_REVIEW_2026-10-05.md#october-9-hosted-databaseschema-metadata-qualification) and ignored [server handoff](./client-docs/SERVER_TO_CLIENT.md) distinguish timestamps/authority.

| Boundary | Fresh metadata observation | Qualification limit / coupling |
| --- | --- | --- |
| Serving source/layout | Clean serving worktree and bare deployment repository at `a1e36535f781e0a0fd51847e738576ac5eccbd12`; inspected source digest matches local historical revision. Account-relative `zafo-forum.sk/sub/bcuszlr92817/index.php` requires `repos/blacklist-backend/{vendor/autoload.php,bootstrap/app.php}`; adjacent `.htaccess` rewrites to index. | 16 commits behind current main; on-disk relationship is not active-vhost/OPcache attestation. SMSTools hardening, abuse/URL controls, OTP/report concurrency corrections and dependency remediation are not deployed. |
| PHP/transport | Historical `/usr/bin/php` CLI 8.5.10, cURL/OpenSSL/PDO MySQL/required extension names, libcurl 7.81.0/OpenSSL 3.0.2 and pinning/proxy/DNS capability observations retain their original scope; Redis extension absent then. Later owner-supplied WebAdmin screenshot shows Apache 2.4 / PHP 8.5 selected for the matching hostname; owner reports existing PHP 8.5 compatibility-test success. | Panel selection is not exact effective patch/handler/pool/ini/OPcache or all-worker evidence; test screenshot shows selection/header only, not result/scope/artifact. Capture/test time unknown. No effective extension inventory, locked production-platform check or DNS/TLS/proxy/portal acceptance. HTTP Cache/HTTPS-redirection toggles appear enabled, but saved/effective state and API cache applicability are unknown; no requests or setting changes in intake. |
| Reference PHP runtime | Owner-linked page read October 10 approximately 16:27 CEST reports PHP 8.5.10/FPM-FastCGI, cURL enabled/libcurl 7.81.0/OpenSSL 3.0.2 and PDO enabled/mysql; sections present for all listed lock extension names, curl, pdo_mysql and Zend OPcache. Owner-relayed helpdesk confirms provider-platform build scope. | Helpdesk explicitly excludes exact subdomain settings; backend/pool/all-worker mapping still unknown. Apache can forward to FPM; actual backend model unknown. No locked-platform/functional transport acceptance. Temporary phpinfo suggestion is not approved; no diagnostic created. |
| Completed requests / hold visibility | Owner-relayed cache-before-Apache and cache-disable advice; no aggregate completion source. Local lifecycle assessment identifies synchronous post-commit SMS, binding/report writes, HTTP limiter writes and independent probe loops. | Cache-disable + directory denial is preferred but coverage/propagation/direct-origin/all-backend behavior unqualified. No existing drain/all-path lifetime bound; logs/timestamps are not proof. Future durable enrollment cannot retroactively cover old admitted work; crashes/ambiguous sends retain blockers, accepted SMS may deliver later. No external WAF selected, activation, implementation, access or test. |
| Installed graph | 113 installed entries, exact names/version/dist references match 82 production / 31 development historical lock entries; development installed. Manifest/lock/source hashes and content hash consistent; Laravel 13.2.0, Guzzle 7.10.0. | Older graph, root `^8.3`, not current remediated 83/31 graph. Prior local zero-advisory audits do not apply; no new advisory audit or vendor-content integrity check. |
| Static auth/provider flags | `.env`: staging, debug false, portal HTTP, SMSTools, OTP logging false; development recipient override present. Credential/key/sender present, values never emitted. Standard config cache absent, no alternate cache path/inherited CLI selection override found. | Effective web configuration and live worker state unverified. Provider-approved sender/funding/scope/delivery unverified; staging override blocks genuine scraped-recipient qualification. |
| Selected database | Fresh standalone PDO MySQL connection actually reaches MariaDB 11.4.13, session REPEATABLE-READ. Direct grants fully parsed: SELECT/INSERT/UPDATE/DELETE/CREATE/ALTER/INDEX/DROP/REFERENCES/LOCK TABLES/TRIGGER all true on scoped tables. All 13 migration identifiers present, batch 1. | No global isolation/web-session parity or write privilege exercised. Ledger is not proof of execution/data correctness; no pending migration delta between deployed/current source. Broad DDL/lock/trigger capabilities are not ordinary CRUD requirements; no grants altered. |
| Required schema | Seven application/cache/lock tables plus ledger are InnoDB base tables, utf8mb4_unicode_ci. Required columns/types/nullability and full-column BTREE PK/unique/query indexes match deployed/current migrations; both report/feature FKs to clients.id use DELETE CASCADE / UPDATE RESTRICT. | Structurally compatible with current OTP/report locking assumptions, not deployed corrective code or functional concurrency/data validity. Legacy auth_challenges absent as expected; users/session/jobs/probe schemas outside scope. [Full comparison](./BETA_READINESS_REVIEW_2026-10-05.md#scoped-columns-indexes-and-foreign-keys). |
| Cache/locks | Fresh default table/connection overrides absent. Existing cache key PK/value mediumtext/expiration bigint and lock key PK/owner varchar(255)/expiration bigint, expiration indexes, all required columns: structural database-store candidate. Prior static intent remains file cache/session, sync queue; limiter absent in old source. | No cache connection/content/counter/lock test or configuration change. Current [shared-store guard](../../app/Services/OtpAbuseProtection.php) rejects unchanged file cache outside local/testing. [Cache configuration](../../config/cache.php) selects default DB connections when overrides absent; shared limiter/store, app-key/prefix and budget continuity/rollback require an approved plan. |
| Operational metadata | Zero target crontab entries and no scoped account-owned target queue/scheduler process observed. Probe/preview flags false; proxy driver non-none with endpoint/credentials present, unrecognized driver label redacted. | Auth extraction/SMS synchronous; do not invent a queue requirement. Managed jobs/hidden services, reverse-proxy/body limits, alerts/rotation/backups and proxy functionality remain unverified. |
| Assets/health | No hosted Vite manifest/hot marker or JS lockfiles found. Exactly one HTTPS `/up` GET at 20:24:04 CEST returned 200, TLS verification 0, no redirects. | Not complete API-only artifact approval, DB/cache/auth readiness, live SMS or overall beta certification. |

## Risky Couplings and Open Gates

1. **OTP local concurrency is verified and the corrective source is published; it is not deployed at the inspected host.** MariaDB tests reproduced a verification/resend deadlock from different index lock orders and rolled-back attempt counters from reserving inside the transaction. The corrected source locks both paths by phone, revalidates the UUID and reserves attempts outside the retried transaction. Genuine duplicate verification, both overlap orders and binding/consumption rollback pass locally under two isolation levels. [Backend PR #5](https://github.com/KontentWave/bl-server/pull/5) is merged into the source baseline above. Confirm deployment and effective hosted database/cache/worker settings before claiming CB-06 closed for beta.
2. **Allowed send failure can invalidate a previous challenge.** Initiation persists replacement before SMS dispatch. A blocked reservation leaves the previous challenge untouched; a failed allowed send does not refund capacity or restore the old challenge. Avoid automatic ambiguous-send retries.
3. **CB-07 source is reviewed, tested and merged; it is not deployed at the inspected host.** [PR #6](https://github.com/KontentWave/bl-server/pull/6) was separately approved and merged with complete tree equality to reviewed head `841e7a5ee5af97976482acadf40c4cb48a568ac2`. Baseline independent HTTP workers reproduced stale count two and duplicate HTTP 500. [ReportSubmissionService](../../app/Services/ReportSubmissionService.php) serializes client creation/reporting, uses current reads and atomically persists insertion/count/promotion, with three bounded database-only concurrency attempts. Signature/binding checks and wire responses are preserved. [ADR 2](./ADRs/2_threshold_logic+backend_database.md) records the locking tradeoff; fresh InnoDB/schema/direct-session isolation is compatible, but effective web settings and functional hosted concurrency remain separate.
4. **Query output depends on materialized promotion state.** Queries return only `is_level_two` rows and map labels through current config. Feature-key/threshold changes need an explicit data migration/recomputation decision.
5. **Probe safety differs from API ad fetching.** `ProxyProbeService` checks only generic URL validity, does not use the ad allowlist/DNS pinning or explicit no-redirect controls, and persists exception text/optional body previews. Treat it as operator-only; do not expose it as an arbitrary-URL public endpoint. Review telemetry access/retention and sanitization separately.
6. **Identity/privacy assumptions are shared.** OTP/binding tables retain raw phone numbers; report identities use unsalted phone hashes, which are enumerable. Successful signatures demonstrate key possession, not backend-verified Android hardware attestation. Historical "zero-knowledge" or "hardware-bound" wording is not certification of these properties.
7. **Generic throttles are intentionally ambiguous.** `rate_limited` does not identify lock, IP, SMS or challenge-exhaustion scope. Empty error arrays can differ from older object examples. Android must follow the current contract without inferring scope or replaying requests automatically.
8. **Fresh design chosen, not deployed or qualified.** October 9 on-disk old-source/development-dependency observations and unqualified loaded-worker/effective-config findings remain historical. The [runtime/configuration/hold matrix](./BETA_READINESS_REVIEW_2026-10-05.md#october-9-web-runtimeconfiguration-and-hold-feasibility-preflight) and [ingress/completion design](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#local-only-design-assessment---ingress-and-completion) still leave historical continuity blocked; cache/Apache coverage and drain are unqualified. Owner now accepts no inherited authorization/community results, retaining old state in place. [Fresh decision/next package](./BETA_DEPLOYMENT_CONFIGURATION_ROLLBACK_PLAN.md#smallest-next-task-and-exact-separate-approval-boundary) avoids old application handover only with independent files/secrets/stores and no import; sharing/importing would restore that dependency. Next is a local review of one owner-identified environment's supplied evidence; no target/facility invented, no discovery or implementation. Sending allocation/history and recipient interference remain unresolved, not reset by a new key/prefix/cache. Qualified single-runtime producer completion and owner pause may suffice for later updates; no mandatory coordinator, WAF, HA or deputy. Log-only SMS is not admission closure; ambiguous sends never replay/refund/restore a challenge and accepted messages may arrive during pause. Database cache remains structural only; all four non-local routes reject unchanged file cache. Preserve subsequent beta authorization/budgets across updates; no stale restore or unrestricted old-source reopening. Public obscurity is no control. Minimum privacy/provider/contact/retention/recovery, runtime/stores/transport, genuine SMS and signed phone gates remain blocked; no additional cost/outage/loss exception inferred or publisher-custody gate reopened.
9. **Client progress is reported, not device certification.** The October 9 [client-owned handoff](./client-docs/CLIENT_TO_SERVER.md) reports native Kotlin/Compose HEAD `2a7e726a0fb6750c2c398ab6502ca936d27a9d97`, not independently verified here. Earlier verification of merged [client PR #5](https://github.com/KontentWave/bl-client/pull/5) at `4b50be5009ddb52688cbdda0b361b47f65986e6d` is historical, not current-main evidence. Prior 147 JVM tests / 21 suites and pure-service contract vectors retain their client-reported limits. Publisher-key storage is owner-reported complete and accepted for this beta; recovery untested, not a signing prerequisite. Backend readiness first, then signed APK/install-update and physical-phone qualification. No wire change or client rewrite; preserve client-owned uncommitted documentation. Sole-owner operation may use an agreed pause policy; specific private contact/pause/alert arrangements remain pending.

## Verification Anchors

- [October 6 owner privacy/operator decision package](./OWNER_PRIVACY_OPERATOR_DECISIONS_2026-10-06.md): source-grounded purposes/retention, incident ownership, RPO/RTO, isolated restore criteria and recovery/revocation options. Owner approved primary role `admin` (sole owner/developer) only; backup coverage explicitly remains pending. No retention values, operational permissions or implementation scope approved. Documentation links/whitespace are the only new validation scope; dependency and application-test evidence below retains October 5 timestamps.

- [Local dependency/operator/privacy review and remediation](./BETA_READINESS_REVIEW_2026-10-05.md): the original 39-record audit and 26-test mocked review retain their original timestamps. Fresh remediation passes **155 targeted SQLite tests / 678 assertions**, **157 full SQLite tests / 680 assertions**, and **18 combined OTP/report MariaDB tests / 560 assertions per isolation** with independent workers and observed lock waits. An initial READ-COMMITTED container startup exited before tests; project cleanup and a fresh unchanged-runner retry passed both levels. Production/complete locked audits are now zero; manifest/platform/strict autoload/reproducible-install checks pass locally on PHP 8.4.12. The unprovisioned MariaDB bootstrap still fails closed (expected exit 2); runner-owned containers/networks are removed. No PHP application/test source, wire contract, existing application settings/data or client files changed; scripts/plugins were disabled. npm lock coverage, unaffected Symfony 8.0 lifecycle, retention/logging/revocation/backup/recovery and hosted/client/live-SMS qualification remain distinct gates.

- [Initiation tests](../../tests/Feature/Auth/InitiateAuthTest.php): blocked resend, SMS budgets, shared-store reservations, failed-send lifecycle and request bounds.
- [Verification tests](../../tests/Feature/Auth/VerifyAuthTest.php): signed verification, per-challenge budget, sequential replay rejection, transaction rollback after simulated challenge-consumption failure and resend ordering; SQLite is not deployed concurrency certification.
- [MariaDB OTP tests](../../tests/Database/OtpConcurrencyTest.php): competing signing keys yield one success; both resend/verification overlap orders preserve the replacement; failed consumption restores an existing binding; invalid attempts persist in database cache. Each overlap test requires an observed InnoDB lock wait and checks the successful state. These are concurrent local service tests, not parallel hosted HTTP requests.
- [MariaDB report tests](../../tests/Database/ReportConcurrencyTest.php): independent signed HTTP workers verify threshold crossing, exact duplicate 422 envelope, concurrent first client/feature creation and independent feature/client state. Shared [lock observations](../../tests/Support/AssertsMariaDbLockWait.php) require the expected blocking/waiting connections; a different client completes while the first is paused. Query tests hide uncommitted promotion and expose it after commit. Trigger failures verify rollback and bounded retry classification/exhaustion; an outer-snapshot test verifies current reads. Synthetic trigger errors are not natural deadlock evidence.
- [SQLite report tests](../../tests/Feature/Reports/StoreReportTest.php): existing success/persistence semantics plus thresholds 1/2/4, stable promotion/client timestamps, normalized signing, bound-phone identity after key rebinding, pre-write rejection and persistence rollback. SQLite in-memory results do not establish cross-process SQLite concurrency.
- [HTTP portal tests](../../tests/Feature/Services/HttpEscortPortalClientTest.php) and [extraction tests](../../tests/Feature/Jobs/ExtractPhoneFromAdJobTest.php): unsafe URLs/DNS, TLS/pinning options, redirects and supported-portal parsing.
- Historical published CB-06 evidence remains in its original checkpoints: full SQLite 150 tests / 609 assertions; MariaDB OTP 5 tests / 99 assertions per isolation, three repetitions each plus fresh pre-publication passes. Final CB-07 implementation validation: targeted SQLite report/query **15 tests / 120 assertions**; full pinned SQLite **157 tests / 680 assertions**; standalone MariaDB report **13 tests / 462 assertions per isolation**; combined OTP/report **18 tests / 560 assertions per isolation**, repeated three times each (**108 test executions / 3360 assertions**); fresh OTP-only **5 tests / 99 assertions per isolation**. Fresh pre-publication reruns additionally pass full SQLite **157 tests / 680 assertions** and combined MariaDB **18 tests / 560 assertions under each isolation level**. All final runs pass under REPEATABLE-READ and READ-COMMITTED. Six report contention scenarios per run require observed waits against the expected blocker; independent-client commit and uncommitted-promotion visibility are also tested. Scoped Pint, whitespace, 80 related documentation links, eleven-file secret-marker checks and the unprovisioned-bootstrap fail-closed check pass. The marker check is not a dependency-advisory or full security audit. Initial prerequisite/startup/fixture failures and their corrections are retained in the [server handoff](./client-docs/SERVER_TO_CLIENT.md), not counted as passes. Implementation and pre-publication test projects were cleaned. At publication preparation, existing Docker Compose v5.5.1 works against the local Unix-socket daemon; no package/configuration restoration is needed or performed. These are local results, not hosted deployment, Redis, Android device or beta certification.

## Privacy, Restore and Recovery Boundaries

- [OTP issuance](../../app/Services/OtpChallengeService.php) overwrites the existing phone row on resend; [verification](../../app/Services/AuthVerificationService.php) deletes consumed challenges. [Model expiry](../../app/Models/OtpChallenge.php) is strictly later than `expires_at`, not deletion. No expired-row pruning/archive or restore-consumption ledger is registered in [console routes](../../routes/console.php). An older snapshot can resurrect a still-time-valid challenge; later cleanup must coordinate expired-ID error classification and preserve phone-lock/attempt-budget semantics.
- [Binding schema](../../database/migrations/2026_03_31_123100_create_device_bindings_table.php) has unique phone, not unique public key, and no expiry/revocation/inactive-state fields. Fresh OTP plus submitted-key signature may replace the key without the old key. Report/query authorization reads current key matches; no administrative recovery/denylist check exists. Report authorization precedes its transaction, so a future immediate revocation cutoff requires explicit in-flight race semantics/testing.
- [Reporting](../../app/Services/ReportSubmissionService.php) derives reporter identity from phone, not key; replacement/deletion of a binding does not erase historical reports or duplicate identity. Removing contributions must deliberately reconcile [materialized levels](../../app/Models/ClientFeatureLevel.php), threshold/promotion history and [query visibility](../../app/Services/BlacklistQueryService.php) under the CB-07 client lock. Restores also need durable post-snapshot revocation/recovery/removal authority, not just a business-table snapshot.
- [Cache/locks](../../config/cache.php), [queues](../../config/queue.php), [sessions](../../config/session.php) and [files](../../config/filesystems.php) may live separately from business tables; default configuration is not effective hosted evidence. Restore design must preserve SMS/IP/verification budget continuity and app-key/prefix alignment, fence stale writers/locks and quarantine queued/scheduled side effects. Extraction and SMS are synchronous in current initiation; `ShouldQueue` is not evidence that onboarding payloads are queued.
- [Probe telemetry](../../app/Scraper/Proxy/ProxyProbeService.php) retains URL/error text even with preview off; [logs](../../config/logging.php) lack default single-channel rotation bounds. Masking does not qualify access/error logs or provider retention. No approved backup runbook/drill was found in the inspected scope. The linked decision package supplies options and acceptance criteria, not backup/prune/revoke permissions or legal approval.

## Historical Decisions and Maintenance

Historical rationale already lives in ADRs; do not duplicate those narratives here or treat old deployment notes as current facts:

- [ADR 1: security handshake](./ADRs/1_foundation+security_handshake.md): identity, OTP and adapter boundaries.
- [ADR 2: reporting database](./ADRs/2_threshold_logic+backend_database.md): reporter identity, feature keys, duplicates and promotion.
- [ADR 3: query API](./ADRs/3_real-time_query_API.md): bound-key authorization and Level 2 disclosure.
- [ADR 4: scraper hardening](./ADRs/4_production_scraper_hardening.md): rotating proxies, telemetry and host-aware parsing.
- [ADR 5: historical hosted testing](./ADRs/5_hosted_backend_integration_testing.md): Vonage trial/override decisions, not evidence of the current deployed driver or beta approval.

After each relevant integration inspection, replace affected rows and risks with current findings, update the inspection timestamp/baseline and retain source links. Record newly agreed architectural decisions in the existing ADR directory when rationale or trade-offs need durable history. Keep this map about current integration facts, not an append-only session log. Never include credentials, OTPs, private keys, personal recipients or sensitive provider payloads.
