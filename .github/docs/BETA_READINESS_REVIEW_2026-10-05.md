# Local Dependency and Operator/Privacy Readiness Review

**Recorded:** 2026-10-05 17:10:01 CEST (UTC+02:00)

**Updated:** 2026-10-05 17:13:49 CEST (UTC+02:00) - advisory-ledger completeness, documentation references, whitespace and unchanged application scope verified.

**Remediation update:** 2026-10-05 17:36:42 CEST (UTC+02:00) - authorized local PHP dependency changes and isolated validation below. Original review timestamps, audit ledger and operational findings are preserved as historical evidence.

**Decision-package update:** 2026-10-06 10:15:42 CEST (UTC+02:00) - fresh local source inspection and owner role decisions in the [privacy/operator decision package](./OWNER_PRIVACY_OPERATOR_DECISIONS_2026-10-06.md). No dependency audit/test rerun or operational implementation; historical results below are unchanged.

**Source:** backend `main` and local `github/main` tracking ref at `38f088db7f626efe57ac51a060f4dc1c8d278448`.

**Disposition:** BLOCKED for real-user closed beta. The known locked PHP advisory gate is locally remediated by the follow-up below; JavaScript reproducibility, dependency lifecycle, operator/privacy and hosted/client/live qualification remain separate. CB-06/CB-07/CB-10 source completion and local dependency success do not clear these gates.

## Scope and authority

Reviewed backend manifests/lockfile, routes, scheduler, persistence schemas/models, authentication/SMS/probe logging, cache/session/queue/filesystem configuration and existing coordination/architecture documents. Morph was unavailable; targeted source reads/searches were used. This is a dependency and engineering-readiness review, not an exploit assessment, legal-compliance certification or full Git-history secret audit.

Executed read-only package/advisory checks and isolated mocked regressions. Only public package/advisory metadata was requested externally; no application/provider/hosted request or existing database connection was made. No dependency update/install, Laravel Artisan command, migration, cache clearing, pruning, backup, restore, key/binding mutation or live SMS was performed.

No policy, retention duration, RPO/RTO, operator appointment or production setting is approved by this document. Implementation/publication/deployment and live SMS require their own approvals. Client-owned messages and attached snapshots are read-only.

## Executed dependency checks

```sh
composer audit --locked --format=json --abandoned=report \
  --no-interaction --no-plugins --no-scripts
composer audit --locked --no-dev --format=summary --abandoned=report \
  --no-interaction --no-plugins --no-scripts
composer validate --strict --no-check-publish \
  --no-interaction --no-plugins --no-scripts
composer check-platform-reqs --lock --no-dev \
  --no-interaction --no-plugins --no-scripts
npm audit --json --ignore-scripts
```

| Check | Executed result | Boundary |
| --- | --- | --- |
| Composer locked audit, production plus development | Exit 1; 39 advisory records affecting 12 packages; no abandoned packages reported | 82 production and 31 development lock entries; every affected package belongs to production |
| Composer production-only audit | Exit 1; the same 39 records / 12 packages | Not merely a development-tool issue |
| Composer manifest/lock validation | Passed | Does not establish dependency safety |
| Production lock platform requirements | Passed on local PHP 8.4.12 / Composer 2.9.5 | Root PHP constraint is `^8.3`, but locked Symfony components require PHP `>=8.4`; hosted PHP is unverified |
| Local extensions/cURL capability | `pdo_sqlite`, `pdo_mysql`, cURL, OpenSSL and `CURLOPT_CONNECT_TO` available | Local CLI only; not hosted web-worker/proxy qualification |
| npm audit, npm 11.19.0 / Node 24.21.0 | Exit 1, `ENOLOCK` | No tracked/present JavaScript lockfile; no graph was audited or generated |

Raw advisory severity totals are **13 high, 21 medium, 4 low, 1 unrated**. There are **38 distinct upstream identifiers**, not 39 independent vulnerabilities. `PKSA-3r5d-mb8f-1qw9` and `PKSA-mdq4-51ck-6kdq` both refer to `GHSA-5vg9-5847-vvmq`; the second record has no severity. No installed application exploitability or hosted exposure was established.

### Affected production packages

Versions are from [composer.lock](../../composer.lock). Counts include the duplicate record above; advisory severity is upstream metadata, not a project-specific exploit ranking.

| Package | Locked version | Records | Upstream severity counts |
| --- | --- | --- | --- |
| `guzzlehttp/guzzle` | 7.10.0 | 9 | 1 high / 8 medium |
| `guzzlehttp/psr7` | 2.9.0 | 4 | 4 medium |
| `laravel/framework` | 13.2.0 | 4 | 1 high / 1 medium / 1 low / 1 unrated duplicate |
| `league/commonmark` | 2.8.2 | 12 | 9 high / 3 medium |
| `league/flysystem` | 3.33.0 | 1 | 1 low |
| `symfony/dom-crawler` | 8.0.8 | 1 | 1 low |
| `symfony/http-foundation` | 8.0.7 | 1 | 1 medium |
| `symfony/http-kernel` | 8.0.7 | 1 | 1 high |
| `symfony/mailer` | 8.0.6 | 1 | 1 medium |
| `symfony/mime` | 8.0.7 | 2 | 1 high / 1 medium |
| `symfony/polyfill-intl-idn` | 1.33.0 | 1 | 1 low |
| `symfony/routing` | 8.0.6 | 2 | 2 medium |

[Laravel HTTP transport](../../app/Services/HttpEscortPortalClient.php), [SMSTools](../../app/Services/SmstoolsSmsSender.php) and [operator probes](../../app/Scraper/Proxy/ProxyProbeService.php) use the HTTP stack; portal parsing uses Symfony DOM/CSS components. This makes transport/parsing upgrades a concrete compatibility surface, without proving any specific advisory is exploitable here.

Upstream pages were additionally checked for Guzzle host validation (patched in 7.15.2/8.0.1), Laravel email validation (13.10.0+ for that issue) and DomCrawler XML parsing (8.0.12 on the 8.0 branch). These are issue-specific fixes, not sufficient target versions for clearing all 39 records. In particular, other Laravel records cover later fixes. Dependency resolution and all-advisory clearance still require a separately approved update and fresh audit/regressions.

[package.json](../../package.json) lists only public development dependencies, but that does not mean generated assets are non-production: [the JS entry](../../resources/js/app.js) imports [Axios bootstrap](../../resources/js/bootstrap.js), and [the welcome view](../../resources/views/welcome.blade.php) loads Vite assets when a manifest/hot file exists. Either create/review a reproducible lockfile and audit the complete build graph, or explicitly qualify an API-only artifact that does not build/serve these assets. No Android/Gradle dependency audit was performed; Expo is not the active client.

## Privacy inventory and retention boundaries

This inventory describes possible stored fields from source, not records inspected in an existing database. Hashing/masking and model serialization exclusions do not establish encryption at rest or anonymization.

| Surface | Inspected data/control | Readiness gap or required decision |
| --- | --- | --- |
| `otp_challenges` | Raw worker phone, full ad URL, challenge UUID, OTP hash, expiry and timestamps; [schema additions](../../database/migrations/2026_04_01_201500_add_challenge_fields_to_auth_challenges_table.php), [model](../../app/Models/OtpChallenge.php) | Fifteen-minute validation lifetime is not deletion. Successful verification deletes the row; resend replaces it. No expired-challenge pruning is registered. Approve expiry/grace/retention policy and race-safe isolated implementation |
| `device_bindings` | Raw phone, public key, verification time; [schema](../../database/migrations/2026_03_31_123100_create_device_bindings_table.php), [model](../../app/Models/DeviceBinding.php) | No expiry/revocation field or first-class operator revocation endpoint/command. Define active/inactive binding retention, identity checks and lost-device recovery |
| `clients`, `reports`, `client_feature_levels` | Unsalted phone-derived SHA-256 identifiers, feature reports, counts and timestamps; [clients](../../database/migrations/2026_04_07_170000_create_clients_table.php), [reports](../../database/migrations/2026_04_07_170100_create_reports_table.php), [levels](../../database/migrations/2026_04_07_170200_create_client_feature_levels_table.php) | Pseudonymous/enumerable identifiers, not anonymous data. Reporter identity can be associated with binding phones. Approve report retention/removal/dispute policy; deleting reports must preserve or deliberately recompute counts/promotion |
| Probe telemetry | Full target URL, proxy endpoint metadata, exception text, optional 255-byte response preview; [schema](../../database/migrations/2026_04_10_090000_create_scraper_proxy_attempts_table.php), [writer](../../app/Scraper/Proxy/ProxyProbeService.php) | Preview defaults off, but URL/error fields still persist. No application pruning found. Approve operator-only use, redaction/minimization and retention; do not schedule probes merely for this review |
| Logs | Challenge IDs, masked phone metadata, hashed ad URLs, provider IDs; non-production verification fingerprints/diagnostics | Correlation data still needs access/retention controls. Generic uncaught exceptions/framework/server logs were not certified safe for full request/SQL/transport data |
| Cache/locks | SMS HMAC recipient keys use `APP_KEY`; verification/IP identifiers are hashed and budgets have TTLs; [abuse protection](../../app/Services/OtpAbuseProtection.php), [cache config](../../config/cache.php) | TTL semantics are not a complete backing-store retention policy. Preserve shared-store/key-prefix/key continuity and attempt budgets; arbitrary flushes/key rotation are not harmless recovery steps |
| Sessions/user scaffolding | User/email/password-hash fields, reset-token storage, sessions with IP/user-agent/payload; [schema](../../database/migrations/0001_01_01_000000_create_users_table.php) | Four signed API routes are not session-based onboarding. Data may exist if other web features are used; actual population unknown. Session lifetime 120 minutes and 2/100 sweep lottery do not define whole-database retention |
| Queue/batch/failure scaffolding | Payloads, failed exceptions and batch options; [schema](../../database/migrations/0001_01_01_000002_create_jobs_table.php), [config](../../config/queue.php) | Current extraction is invoked synchronously despite `ShouldQueue`; do not invent queue persistence for this flow. Define pruning/redaction if queues are used. Default failed-job storage is database-backed |
| Files/config/backups | Private/public disks; ignored environment/key/build files; [filesystem config](../../config/filesystems.php), [.gitignore](../../.gitignore) | Git ignoring is not storage encryption, access restriction or backup exclusion. No repository-managed backup/restore policy/drill found; host-level facilities remain unverified |
| Historical documentation | [ADR 5](./ADRs/5_hosted_backend_integration_testing.md) retains identifiable historical test recipients and old trial/override behavior | No identifiers copied here. Sanitize personal identifiers before redistribution while preserving dated historical meaning; do not treat old deployment statements as current settings |

### Logging controls: verified source versus unresolved operation

- [SMSTools](../../app/Services/SmstoolsSmsSender.php) and [Vonage](../../app/Services/VonageSmsSender.php) success logs mask recipients; inspected SMSTools failures omit raw provider bodies/notes/transport messages. No automatic SMS retry was added.
- [LogSmsSender](../../app/Services/LogSmsSender.php) can include plaintext OTP outside production when its flag is enabled. [Service configuration](../../config/services.php) defaults `SMS_LOG_OTP_IN_NON_PRODUCTION` to true; beta operation must explicitly verify it is false, including staging. Production blocks this branch, but no effective hosted flags were checked.
- [Provider selection](../../app/Providers/AppServiceProvider.php) falls back to the log sender for unknown SMS drivers. Verify effective driver/class, not only intended environment text; cached configuration and web/CLI parity are separate hosted checks.
- [Logging configuration](../../config/logging.php) defaults to stack/single with no application rotation limit. The daily channel has a configurable 14-day default only when selected; this is not evidence of an approved policy or active host rotation.
- Domain logs and masked/hash identifiers remain linkable. Collect only minimal incident metadata; exclude OTPs, full request bodies, ad URLs, keys/signatures, credentials, raw SQL bindings and provider bodies from operator evidence bundles. Access/error logs, monitoring, third-party log destinations and provider retention require separate verification.

## Operator readiness findings

At the October 5 review, no approved, current named-operator/backup-restore/revocation runbook or executed restore/recovery evidence was found in the inspected repository/coordination scope. External facilities may exist; they were not inspected. [The scheduler](../../routes/console.php) registers an optional proxy probe, not challenge/report/probe pruning or backup jobs. Roadmap revocation language is a future design, not an implemented control.

**October 6 decision checkpoint:** Owner approved `admin` (the sole owner/developer) as primary operator for privacy decisions, incident triage and tester escalation only. Backup coverage was explicitly left pending; contact, access/alert routing, retention values, RPO/RTO and administrative recovery/revocation semantics remain unapproved. This role label is not an access grant or implementation approval. The [decision package](./OWNER_PRIVACY_OPERATOR_DECISIONS_2026-10-06.md#decision-tables) separates source facts/options, prerequisites and beta priorities, including restore-safe consumed-challenge/key state, report-count consistency and limiter continuity. Ordinary fresh OTP plus submitted-key signature can replace a phone binding without an old-key signature; that is not administrative recovery. Resend overwrites the phone's existing challenge row, not an application archive. No data was inspected or changed.

The smallest proposed next slice is expired-only OTP retention after separate owner approval of cutoff/grace/cadence, operational scope and Android error-classification coordination. Deleting an expired challenge changes its later error from `otp_invalid_or_expired` to existing `challenge_not_found`; unchanged envelope fields do not remove that coordination requirement. No slice is authorized by planning completion, and real-user beta remains BLOCKED.

| Gate | Minimum evidence needed before real beta identities |
| --- | --- |
| Ownership and incident response | Named primary/backup operator, tester contact, alert/triage ownership, least-privilege host/DB/log access and a minimal redacted incident-evidence format |
| Retention/privacy notice | Approved purposes and retention periods for each category above; provider data-handling/notice review; documented deletion/dispute process; separately tested cleanup and backup-expiry rules |
| Backup/restore | Approved RPO/RTO, consistent engine-appropriate backup, encryption/access/off-host storage and key custody, restore drill in a disposable isolated environment, application/schema/count/identity verification and recorded outcome |
| Restore/revocation safety | Restoring a snapshot can resurrect consumed challenges, old bindings or revoked keys. Define explicit reconciliation/invalidation and revocation records before re-opening writes. Do not replay queued work or send SMS during drills |
| Binding recovery | Distinguish deliberate device-key replacement from administrative revocation. Existing signed endpoints only check the current binding; no revocation workflow is implemented. Do not assume deleting a phone binding removes its historical report contribution |
| Release/rollback | Identify source/APK/config/schema revisions, approved maintenance/write-cutover and rollback boundaries, database compatibility and worker refresh. Source rollback is not automatic data rollback |
| Cache/key continuity | Account for rate-limit/SMS budget state, key/prefix/store continuity and lock ownership across restart/restore. Do not casually flush cache or rotate `APP_KEY` as a repair |

These are release evidence requirements, not authorization or runnable production procedures. RPO/RTO and retention values remain owner decisions. No backup, restore, delete, prune, revoke, key rotation or deployment was executed.

## Safe validation performed

```sh
DB_CONNECTION=sqlite DB_DATABASE=:memory: SMS_DRIVER=log \
SMS_LOG_OTP_IN_NON_PRODUCTION=false vendor/bin/phpunit --no-progress \
tests/Feature/Services/SmstoolsSmsSenderTest.php \
tests/Feature/Services/VonageSmsSenderTest.php \
tests/Feature/Scraper/Proxy/ProxyProbeServiceTest.php \
tests/Feature/Console/ProbeScraperProxyCommandTest.php
```

**26 tests / 129 assertions passed**, using isolated SQLite and mocked provider/probe interactions. These verify existing behavior, not a new privacy redaction implementation or recovery/restore exercise. No application dependency/source changed. Prior CB-07 full SQLite/MariaDB evidence retains its original timestamps; those complete suites were not rerun for this documentation review.

Documentation checks verified all 38 distinct upstream advisory identifiers against the executed audit and 118 local references across this report, the affected map and README. New-report ASCII/whitespace and `git diff --check` passed. Audit scratch output and the specific failed npm-audit debug log were removed; unrelated logs/caches/data were untouched.

Do not use `composer setup` or `composer test` as a harmless audit command: [composer scripts](../../composer.json) include application mutations such as key generation/migrations or configuration-cache clearing. The executed audit commands disabled scripts/plugins and did not invoke them.

## Recommended bounded follow-up

1. Obtain approval for local PHP dependency remediation within reviewed constraints; resolve and review the full changed graph, rerun the locked production/development audits and affected scraper/SMS/auth/report/query plus concurrency regressions. Do not suppress advisories to make the gate green.
2. Resolve the JavaScript reproducibility/audit gap or explicitly approve and verify API-only asset scope.
3. Have the owner approve operator roles, category-specific retention, recovery/revocation semantics and RPO/RTO before implementing pruning or conducting a disposable restore drill. Logging/probe minimization and historical-document sanitization are separate surgical follow-ups.
4. Keep hosted source/PHP/migrations/database/cache/locks/workers, cURL/DNS/proxies, signed APK/install-update, Android process-restart/Keystore/call/device/OEM/backup and consenting-recipient real SMS/candidate onboarding as separate approval/evidence gates.

No wire-contract or retention-policy change was implemented. BETA-SMS-001 and BETA-BACKEND-001 remain OPEN/BLOCKED.

## Local PHP dependency remediation

**Recorded:** 2026-10-05 17:36:42 CEST (UTC+02:00)

**Authority:** User approved local PHP dependency changes and isolated tests only. After exact resolved-graph inspection revealed Symfony 8.1 requires PHP 8.4.1 rather than 8.4.0, work paused and the user separately approved retaining that graph and minimum. The initial install preceded that patch-floor approval; this sequencing error is explicit in the [server-owned handoff](./client-docs/SERVER_TO_CLIENT.md), not represented as prior approval. Root PHP is now `^8.4.1`; hosted PHP remains unverified. No major framework migration or API change.

Verified unchanged `main`, HEAD and local `github/main` at the source baseline above, both remote roles and expected dirty/untracked/ignored files before changes. Preserved all prior work and client-owned/read-only files. No applicable repository/ancestor AGENTS.md was found. Morph was unavailable; focused source/test inspection was used.

### Resolution and exact graph

Both fresh pre-update locked audits reproduce **39 records / 12 production packages / 38 distinct upstream issues**, severity **13 high / 21 medium / 4 low / 1 unrated**, with no abandoned packages. No newly affected development package. An initial deduplication helper omitted its input argument (exit 255); the corrected link/CVE-based check confirms 38 upstream issues, including the duplicate Laravel record.

Two dry-runs preceded the package update. The default supported candidate stays within existing package major constraints. A smaller candidate constrained the six affected Symfony components to `8.0.*`; it resolves but was rejected because [Symfony 8.0 is no longer maintained](https://symfony.com/releases/8.0), while [Symfony 8.1 is current stable](https://symfony.com/releases/8.1). No advisory blocking was disabled, no ignore was added and no functionality was removed. Dry-run audit messages describe the old on-disk lock, not the proposed graph.

| Production package | Before | After | Reason |
| --- | --- | --- | --- |
| `guzzlehttp/guzzle` | 7.10.0 | 7.15.5 | Nine advisory records; same-major HTTP transport update |
| `guzzlehttp/psr7` | 2.9.0 | 2.13.1 | Four records; required by new Guzzle |
| `laravel/framework` | 13.2.0 | 13.34.0 | Four records including one duplicate; retain Laravel 13 |
| `league/commonmark` | 2.8.2 | 2.10.3 | Twelve records; same-major update |
| `league/flysystem` | 3.33.0 | 3.36.0 | One record; preserve filesystem adapter |
| `symfony/dom-crawler` | 8.0.8 | 8.1.5 | One record; maintained minor and parsing regressions |
| `symfony/http-foundation` | 8.0.7 | 8.1.8 | One record; maintained minor |
| `symfony/http-kernel` | 8.0.7 | 8.1.8 | One record; maintained minor |
| `symfony/mailer` | 8.0.6 | 8.1.7 | One record; maintained minor |
| `symfony/mime` | 8.0.7 | 8.1.7 | Two records; maintained minor |
| `symfony/polyfill-intl-idn` | 1.33.0 | 1.43.0 | One record |
| `symfony/routing` | 8.0.6 | 8.1.8 | Two records; maintained minor |
| `guzzlehttp/promises` | 2.3.0 | 2.5.3 | Guzzle now requires `^2.5.3` |
| `symfony/polyfill-php84` | 1.33.0 | 1.43.0 | Laravel now requires `^1.36` |
| `symfony/polyfill-php85` | 1.33.0 | 1.43.0 | Laravel now requires `^1.36` |
| `symfony/polyfill-php86` | absent | 1.43.0 | Laravel now requires `^1.36`; polyfill does not require PHP 8.6 |
| `symfony/var-dumper` | 8.0.6 | 8.1.7 | HttpKernel 8.1 conflicts with VarDumper `<8.1` |

Exactly **16 upgrades / 1 addition / 0 removals**, **83 production / 31 development entries**. All development entries and unaffected production entries are structurally unchanged. Installed name/version graph exactly matches all **114** lock entries. Top-level lock changes are the content hash and approved platform PHP constraint only. Package reference/metadata/requirement deltas were inspected; no plugin policy, script, repository, advisory-ignore or application feature change.

Ten unaffected Symfony 8.0 components remain unchanged under the bounded advisory-targeted scope: clock, console, CSS selector, error handler, event dispatcher, finder, process, string, translation and UID. Zero advisories is not a maintenance guarantee for these unmaintained entries. Their lifecycle remains a separate follow-up concern; this task does not silently modernize the entire graph.

### Exact dependency and validation commands

All Composer commands below use `--no-interaction --no-plugins --no-scripts`; hooks were inspected, not executed. Setup/create hooks can generate keys/migrate, the test script clears configuration, post-autoload discovers packages/clears compiled manifests and post-update publishes assets. None is a harmless dependency check.

```sh
# Before and after: each audit executed separately, production and complete graph.
composer audit --locked --no-dev --format=json --abandoned=report --no-interaction --no-plugins --no-scripts
composer audit --locked --format=json --abandoned=report --no-interaction --no-plugins --no-scripts

# First dry-run; then the same command without --dry-run applied the chosen graph.
composer update guzzlehttp/guzzle guzzlehttp/psr7 laravel/framework \
  league/commonmark league/flysystem symfony/dom-crawler \
  symfony/http-foundation symfony/http-kernel symfony/mailer symfony/mime \
  symfony/polyfill-intl-idn symfony/routing \
  --with-all-dependencies --minimal-changes --dry-run \
  --no-interaction --no-plugins --no-scripts
# Second dry-run added these temporary resolver constraints to the command above:
# --with='symfony/dom-crawler:8.0.*' --with='symfony/http-foundation:8.0.*'
# --with='symfony/http-kernel:8.0.*' --with='symfony/mailer:8.0.*'
# --with='symfony/mime:8.0.*' --with='symfony/routing:8.0.*'

# After approved root PHP alignment; no further package-version changes.
composer update --lock --no-install --no-interaction --no-plugins --no-scripts
composer install --no-interaction --no-plugins --no-scripts
composer validate --strict --no-check-publish --no-interaction --no-plugins --no-scripts
composer check-platform-reqs --lock --no-dev --no-interaction --no-plugins --no-scripts
composer check-platform-reqs --lock --no-interaction --no-plugins --no-scripts
composer install --dry-run --no-interaction --no-plugins --no-scripts
composer dump-autoload --optimize --strict-psr --strict-ambiguous --no-interaction --no-plugins --no-scripts

DB_CONNECTION=sqlite DB_DATABASE=:memory: SMS_DRIVER=log \
SMS_LOG_OTP_IN_NON_PRODUCTION=false vendor/bin/phpunit --no-progress \
--filter 'HttpEscortPortalClientTest|SmstoolsSmsSenderTest|VonageSmsSenderTest|ProxyProbeServiceTest|ProbeScraperProxyCommandTest|AmaterkySkPhoneExtractorTest|EuroGirlsEscortPhoneExtractorTest|ExtractPhoneFromAdJobTest|EscortPhoneNumberNormalizerTest|InitiateAuthTest|VerifyAuthTest|StoreReportTest|CheckBlacklistTest'

DB_CONNECTION=sqlite DB_DATABASE=:memory: SMS_DRIVER=log \
SMS_LOG_OTP_IN_NON_PRODUCTION=false vendor/bin/phpunit --no-progress

php scripts/test-mariadb.php
# Expected rejection without runner-provisioned isolation:
vendor/bin/phpunit --configuration phpunit.mariadb.xml --no-progress
git diff --check
```

| Executed check | Result |
| --- | --- |
| Fresh final locked production audit | Exit 0; `advisories: []`, `abandoned: []` |
| Fresh final locked complete graph audit | Exit 0; `advisories: []`, `abandoned: []` |
| Strict manifest/lock validation | Passed |
| Production and complete lock platform checks | Passed on local PHP 8.4.12 / Composer 2.9.5; effective/root minimum 8.4.1 |
| Lock install and subsequent dry-run | Passed; nothing further to install/update/remove |
| Optimized strict PSR/ambiguity autoload build | Passed; 7226 classes |
| Affected isolated/mock SQLite regressions | Passed, **155 tests / 678 assertions** |
| Full pinned SQLite suite | Passed, **157 tests / 680 assertions** |
| First guarded MariaDB run | REPEATABLE-READ passed **18 / 560**; READ-COMMITTED container startup exited 1 before tests; runner exited 255 and cleaned its project |
| Fresh unchanged-runner retry | Passed **18 tests / 560 assertions per isolation**, both REPEATABLE-READ and READ-COMMITTED |
| Unprovisioned MariaDB bootstrap | Expected exit **2**, refused before migration/connection |
| Scoped Pint | Not applicable: no PHP application/test source changed |

The failed container was `bl-otp-test-1195693ec769`; cleanup removed it before logs could be inspected, so no startup root cause is asserted. The retry required no source/configuration/tool change. No runner-owned containers or the failed project network remain. Genuine independent worker overlap and observed InnoDB lock waits were retained, not replaced by sequential replay. Restricted application user, loopback ports, tmpfs, private worker pipes, bounded waits and project-scoped cleanup remain unchanged.

Auth/signature/normalization, configured feature thresholds, success/duplicate envelopes, atomic OTP consumption/rollback/attempt accounting, report count/promotion/rollback and query visibility regressions pass without compatibility fixes. CB-06, CB-07 and CB-10 source/guards are preserved. No wire-contract change; required Android action: none. Kotlin/Compose remains active; Expo is cancelled.

**Remaining boundaries / next beta gate:** obtain owner-approved privacy/retention, named operator, backup/restore and recovery/revocation scope; do not implement these from this approval. Separately resolve JavaScript reproducibility/API-only artifact and remaining dependency lifecycle. Hosted revision/PHP 8.4.1+/migrations/database/cache/locks/workers and cURL/DNS/proxy qualification, signed APK/install-update, Android process-restart/Keystore/call/device/OEM/backup, and explicitly approved real SMS/candidate-APK onboarding remain unverified. Prior Android 147 JVM tests / 21 suites is client-reported evidence, not rerun here.

Only local manifests/lock/vendor and related documentation changed. No scripts/plugins, application/shared/hosted database access, external test networking, provider send, production setting, recipient override, key/binding reset, retention/recovery implementation, branch, commit, push, PR, merge or deployment. Fresh advisory clearance is not an exploitability assessment, hosted certification or overall beta readiness.

**Final verification:** 2026-10-05 17:38:47 CEST (UTC+02:00). Repeated production and complete locked audits again return exit 0 with empty advisory/abandoned lists. Strict manifest validation and `git diff --check` pass; **122 local documentation references** and changed-document whitespace pass, including the untracked review. No runner-owned containers/networks remain; HEAD/tracking ref are unchanged, index is empty and pre-existing editor work is preserved. README clarification distinguishes the root constraint from runtime qualification; no dependency/source/test change followed passing suites. Known audit/lock-inspection scratch files are removed at final cleanup; no unrelated caches/logs/data are removed.

## October 6 approved publication preparation

**Recorded:** 2026-10-06 10:50:29 CEST (UTC+02:00).

Owner explicitly approved publishing all six manifest/lock/documentation paths through branch, commit, `github` push, PR and guarded reviewed-head merge. Scope includes this review and the [completed owner decision package](./OWNER_PRIVACY_OPERATOR_DECISIONS_2026-10-06.md), preventing broken new-document links. Ignored handoff/editor files, client-owned messages and attachments are excluded. Hosting `origin`, deployment, operational implementation and live SMS remain unapproved.

Fresh checks on the exact dependency candidate: production and complete locked audits exit 0 with no advisory/abandoned entries; strict manifest validation, production/complete platform checks, no-op install dry-run and strict optimized autoload (7226 classes) pass. Installed graph matches all 114 lock entries; 16 production upgrades / one addition / zero removals and development entries unchanged.

Fresh full pinned SQLite passes **157 tests / 680 assertions**. First `php scripts/test-mariadb.php` attempt exits 255 before provisioning because `docker compose` is unavailable (`unknown flag: --project-name`; up/down each exit 125). The system Compose plugin symlink target is unavailable. After this missing-tool failure, Ubuntu `docker-compose-v2=2.40.3+ds1-0ubuntu1~22.04.1` was downloaded and extracted only into session scratch storage, without system installation or shared Docker configuration changes. An isolated `DOCKER_CONFIG` registers that native plugin, with `DOCKER_HOST=unix:///var/run/docker.sock`; the unchanged guarded runner then passes **18 tests / 560 assertions under each isolation level**, REPEATABLE-READ and READ-COMMITTED. Unprovisioned PHPUnit MariaDB still fails closed with expected exit 2.

Both newly provisioned test projects are removed. A pre-existing stopped container/network for project `bl-otp-test-2ac4e57878b9` was present before the successful run and remains untouched; this checkpoint does not claim the entire shared environment is empty. Publication source needs no compatibility fix or scoped Pint because no PHP application/test source changed. Link/whitespace and bounded private-key/recipient-marker checks pass; marker checks are not a full secret/security audit. Prior results and failures retain their original timestamps.

This is publication preparation, not a merge/deployment event. Actual commit/PR/merge identifiers will be recorded in the ignored server-owned handoff. All privacy/operator, dependency-lifecycle/JavaScript, hosted/runtime/transport, candidate APK/device and real-SMS gates remain separate; beta is still BLOCKED.

## Upstream advisory ledger

These links are from executed Composer advisory metadata. Each distinct upstream identifier is listed once; only the Laravel email issue has two Composer records. Titles/categories are abbreviated. This ledger is not proof of application reachability or a substitute for a fresh audit after an update.

- `guzzlehttp/guzzle`: [host validation](https://github.com/advisories/GHSA-v5mv-p594-2x33), [cookie subdomain scope](https://github.com/advisories/GHSA-f7vp-7xgx-4w4r), [fragment Referer disclosure](https://github.com/advisories/GHSA-h95v-h523-3mw8), [host-only cookies](https://github.com/advisories/GHSA-wm3w-8rrp-j577), [response-cookie bounds](https://github.com/advisories/GHSA-f283-ghqc-fg79), [IP cookie domains](https://github.com/advisories/GHSA-g446-98w2-8p5w), [proxy authorization](https://github.com/advisories/GHSA-94pj-82f3-465w), [dot-only domains](https://github.com/guzzle/guzzle/security/advisories/GHSA-cwxw-98qj-8qjx), [HTTPS proxy downgrade](https://github.com/guzzle/guzzle/security/advisories/GHSA-wpwq-4j6v-78m3).
- `guzzlehttp/psr7`: [host validation](https://github.com/advisories/GHSA-c2w2-prh8-qm98), [start-line serialization](https://github.com/guzzle/psr7/security/advisories/GHSA-vm85-hxw5-5432), [host CRLF](https://github.com/guzzle/psr7/security/advisories/GHSA-hq7v-mx3g-29hw), [authority reinterpretation](https://github.com/guzzle/psr7/security/advisories/GHSA-34xg-wgjx-8xph).
- `laravel/framework`: [debug-page XSS](https://github.com/advisories/GHSA-jh5r-qr3c-85q8), [signed-URL path](https://github.com/advisories/GHSA-crmm-hgp2-wgrp), [email validation](https://github.com/advisories/GHSA-5vg9-5847-vvmq).
- `league/commonmark`: [raw HTML](https://github.com/advisories/GHSA-97jj-33gv-5xf9), [table scanning](https://github.com/advisories/GHSA-3q6v-r5mr-hxv8), [distinct attributes](https://github.com/advisories/GHSA-8rr7-cvq3-gmfh), [SmartPunct/attributes](https://github.com/advisories/GHSA-jjv6-8j6v-6j52), [event-handler filtering](https://github.com/advisories/GHSA-f8fg-pg57-v4j8), [crafted delimiters](https://github.com/advisories/GHSA-j8pm-gj4c-rq4x), [nested XML output](https://github.com/advisories/GHSA-mj63-m3rc-8ppr), [heading slugs](https://github.com/advisories/GHSA-mh25-x5hq-wrqp), [footnote definitions](https://github.com/advisories/GHSA-jfm3-95jq-q3rf), [adjacent attributes](https://github.com/advisories/GHSA-g2gp-3wwq-f4ph), [crafted Markdown](https://github.com/advisories/GHSA-2q4p-g7hv-5rgv), [unsafe-link filtering](https://github.com/advisories/GHSA-29pj-957v-52mc).
- `league/flysystem`: [path normalization](https://github.com/advisories/GHSA-cxf4-7mrp-vvpr).
- `symfony/dom-crawler`: [XML parsing](https://symfony.com/cve-2026-45071).
- `symfony/http-foundation`: [private subnet forms](https://symfony.com/cve-2026-48736).
- `symfony/http-kernel`: [HEAD method filtering](https://symfony.com/cve-2026-45075).
- `symfony/mailer`: [sendmail arguments](https://symfony.com/cve-2026-45068).
- `symfony/mime`: [parameter names](https://symfony.com/cve-2026-45070), [address CRLF](https://symfony.com/cve-2026-45067).
- `symfony/polyfill-intl-idn`: [Punycode equivalence](https://symfony.com/cve-2026-46644).
- `symfony/routing`: [dot segments](https://symfony.com/cve-2026-48784), [route requirements](https://symfony.com/cve-2026-45065).
