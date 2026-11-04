# ADR 006: Coordinated renewal and terminal offboarding

Recorded 2026-11-03 (CET). Accepted owner-approved client ADR-006 contract;
local implementation only, not hosted qualification.

The complete agreed product and wire boundary is maintained in
[BACKEND_API_CONTRACT](../BACKEND_API_CONTRACT.md#coordinated-renewal-and-offboarding).
The corresponding client decision is `.github/docs/ADRs/006-coordinated-renewal-and-offboarding.md`
at client baseline `6aae2cb499c251326c47c10a24e5530a63e254b1`.
Server baseline is `a8d2d9a91ac11d55320df434fce68b82470d4de9`.

## Decision

Device-initiated signed status/renewal reuses the existing EC key, normalized
PEM, compact PHP JSON and SHA-256. No OTP/SMS, key replacement, scheduled crawl
or automatic enrollment is involved. A 24-hour due time is a check cadence,
not a lease: overdue bindings retain query/report access indefinitely until
confirmed offboarding. Renewal never changes historical verification time.

Store nullable provenance and lifecycle metadata on bindings; do not infer
legacy ads. A key selection row provides a short database linearization point:
selection then binding precede report target locks. Future explicit OTP
verification starts a generation and supersedes previous same-key selections
without deleting bindings or community data. Pre-upgrade ambiguity retains
first-binding query/report behavior but status refuses to guess.

Renewal uses a separate shared-cache per-generation lock, never an
incoming-call authorization lock. Portal I/O occurs outside database locks.
Recheck selection, generation and version before committing a fetched result.
Offboarded generations cannot be restored by reactivation or replay.

Only supported portal selectors can establish inactivity/contact change.
Amaterky `h2.alert-heading` exact disabled notices and exact
`Vypršala platnosť` expiry notice are supported. Unsupported deletion notices,
bare 404/410, empty contacts, challenge/unrecognized HTML and transport/parser
failures are unknown. Preserve the existing protected HTTPS/DNS-pinned/TLS/
no-redirect transport; collect all supported contact candidates for renewal
without changing enrollment's primary-contact preference.

Persist fetch reservation windows/failure delays and bounded receipts.
Authenticate before reservation; all limits fail closed through the shared
limiter store. Success resets only the failure ladder, not fetch reservations.
No configured issuance allowlist change revokes a phone-only binding.

A fetch reservation also inserts a bounded pending receipt before I/O. Cache
lease duration is max120/configured portal timeout+30 seconds; the durable
claim, not that timing assumption, fences replay. Expired pending claims settle
as unavailable with the existing failure ladder and retained reservation;
duplicates never fetch and different IDs wait for eligibility. Completion
requires the exact pending receipt row/digest/deadline plus selected generation
and version. No database lock spans portal I/O. Physical cancellation of an
arbitrarily suspended worker is not promised; late results cannot commit.


The coordinated native client uses persisted network-required JobScheduler,
startup/resume/open-UI timer and foreground network-return catch-up, converging
on one operation. `RECEIVE_BOOT_COMPLETED` supports persisted jobs and
`ACCESS_NETWORK_STATE` is the necessary companion for foreground connectivity
monitoring. These are client-side additive permissions, not server permissions
or a dependency change. Incoming-call lookup never initializes or awaits renewal.
This client execution integration is parent-reported, not independently
device-qualified by this server task.

## Qualification

Use source fixtures, deterministic clocks, generated test EC keys and the
existing forced-memory-SQLite bootstrap. MariaDB overlap qualification is
allowed only through the repository-standard runner whose bootstrap proves
disposable isolation, never the normal development/hosted database.
November 4 local execution passed 248 SQLite tests / 1,509 assertions and
24 MariaDB tests / 690 assertions at each of REPEATABLE-READ and READ-COMMITTED.
See the [integration map](../plugin_integration_map.md) for inspection timestamp,
runner safeguards, defects, commands and evidence boundaries.
Local passing tests do not establish deployed behavior.

Consumed pre-upgrade challenges are not provenance. Existing testers remain
legacy/exempt unless they separately approve intentional explicit ad/OTP
verification with their retained key. Do not infer associations or require
repeat onboarding merely to reproduce accepted initial enrollment.
After offboarding is enabled, reverting to pre-lifecycle authorization would
restore access incorrectly; retain lifecycle schema/state and use a
lifecycle-aware forward fix rather than treating migration-down as a safe
operational rollback.
