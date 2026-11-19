# One-time full-scope customer test-data cleanup

Execution is restricted to the owner-confirmed references
`SC/2026/000001` through `SC/2026/000019` and an inventory of exactly 20
private files. A fresh execution requires the entire request table to match
that scope. If any listed reference belongs to a real customer, stop. For a
mixed dataset, stop and obtain a separate, independently reviewed cleanup plan.

The limited `sai:cleanup-production-test-data` command remains inventory-only.
A retry requires the signed recovery journal created for this 19-request scope.
Before execution, verify a restorable database and private-storage backup under the operator backup policy. The signed recovery journal is an ID/path/hash manifest,
not a backup of customer rows or document contents.

## Scope and approval

Every existing request is inventoried, without filtering references or years.
Dry-run reports every request ID/reference, table counts, file counters, private
file path SHA-256 fingerprints/counts/bytes, missing/orphan/shared files and blockers. All private paths are
shown by hash. Customer contact details, credentials and queue payloads are not
printed. The stable fingerprint covers rows, queue payloads, files and schema.

Dry-run writes no rows, files, locks, caches or journals. A blocked inventory exits
nonzero and cannot execute. Review every reference and approve the exact snapshot.
New or changed requests/dependencies invalidate approval. The completed journal
prevents any later cleanup from including newly created real requests.

The scope covers payments/submissions/proofs, billing, revenue entries/documents,
final documents/delivery audits, processing/checklists, assignment/contact/status
histories, dispatch/proofs/history, request notification events/deliveries and
queued/failed notifications/admin emails. Foreign keys and the application-managed
notification source_type/source_id links are inspected. Unknown request-linked
tables/polymorphic relations, orphan database links, inconsistent ownership and
shared appointment events block execution.

Appointments and their notifications/jobs, admin/staff accounts, credentials,
service/document/work-scope/charge masters, website/payment/notification settings,
templates, branding/QR assets and unrelated jobs are preserved. Only request file
counter rows for 2026 reset. All other-year counters are preserved, including
years represented by deleted test requests. Primary-key
counters and unrelated numbering conventions remain unchanged.

## Private files

Inventory uses database paths and private customer-requests/{request_id}/,
payment-proofs/{request_id}/ and legacy request-dispatch-proofs/{dispatch_id}/.
Untracked uploads within inventoried owner directories are included as orphans.
Files without known owners block cleanup; ownership is never guessed. Shared
files, settings-referenced files, symlinks, traversal, unreadable files and paths
outside private storage block execution. Whole directories are never deleted.

## Commands for later operator use

```bash
php artisan sai:cleanup-all-test-requests
```

After completing the preparation below, verify that the dry run lists only the
19 confirmed references. Match the 20 recorded file hashes and their present or
missing states against the approved inventory. Use the fingerprint from that run.

```bash
php artisan sai:cleanup-all-test-requests --execute --confirm=DELETE-ALL-INVENTORIED-TEST-REQUESTS --approve="PASTE_DRY_RUN_FINGERPRINT" --workers-stopped --scheduler-stopped
```

The all-scope command requires its own confirmation token. No migration, seeder,
truncation, credential change or environment edit is required.
The queue inspector refuses chained work, including nested mailable chains and
chain failure callbacks. It checks ownership for request-linked admin mail and
failed jobs. The limited command's inventory refuses when the all-scope journal
exists, including an incomplete or invalid journal.

## Production preparation

1. Verify deployment/schema and target identities. Cleanup tables must use InnoDB
   with foreign keys enabled. Relevant triggers and cross-database dependencies
   are refused. A DBA must verify metadata visibility across referencing schemas
   and verify database scheduler events with an account that can see them.
   Validate the MySQL path in a disposable MySQL environment before production.
2. Put every application node into maintenance. Stop workers, schedulers, retries,
   imports and all other DB/storage writers, including relevant database events.
   Drain already-started HTTP requests, CLI tasks, scheduled runs, after-commit
   callbacks and inline notification sends across all nodes. Verify that any
   outstanding provider operations have settled. The flags attest that both
   stopping and draining are complete. If either cannot be verified, do not
   attest it or execute cleanup. Keep writers stopped through file recovery.
3. The default queue may use `sync` or `database`. The database driver requires
   both queue tables. The sync driver inspects every existing local queue table
   and can proceed when one or both are absent. It verifies actual absence and
   rejects pending or unreviewed notification deliveries regardless of queue
   table presence or contents. Reconcile unresolved deliveries before approval.
   It also checks batch storage, Laravel schedules and enabled
   database events. Keep the deployed queue settings. Stop external scheduling,
   retries, workers and schema changes and confirm the deployment has no custom
   asynchronous integrations before running the cleanup.
   Redis/SQS, alternate tables/connections, reserved/encrypted/malformed jobs,
   unknown request jobs and target batch jobs require review and are refused.
   Never clear the entire queue to bypass a refusal.
4. Protect private storage and maintenance/ from untrusted writes. Retain the same
   APP_KEY, database identity and storage for recovery. A legacy journal at
   maintenance/test-data-cleanup-2026.json blocks the new scope and requires
   explicit reconciliation; do not delete it to bypass the guard.
5. Repeat dry-run with writers stopped and use its exact fingerprint. SQL ID
   operations are chunked, but the full snapshot/report still uses memory.
   Large datasets require a staging capacity and timing check.
   Sync queue configuration, inspected table presence and the reviewed schedule
   enter the fingerprint. A sync journal from an older release without that
   runtime snapshot requires manual reconciliation before a retry.

## Local audit findings and deployment review

The checkout initially contained staged work that replaced the limited command.
The committed limited command targets only SC/2026/000001 through 000003 and
refuses other 2026 rows or a noncontiguous counter. That explains the reported
000019 / next 000020 and file counter 15 refusal; those production values were
provided by the owner and have not been independently queried here. Its original
queue matching used payload text patterns and left notification jobs behind in
the positive regression test; the shared safe inspector fixes this. Its reports
also exposed private paths. Its original scope and execution token remain intact;
private paths are now hashed and the all-scope journal blocks the legacy command.
Use the separate all-scope command for this task, with its stronger safeguards.

The audit covered CustomerRequest relationships, migration foreign keys,
CleanupSafety's explicit child-before-parent table list, model file columns,
RequestWorkflowService, FileNumberService, ReferenceNumberService, upload services,
both delivery jobs and the queued admin mailable. The reference generator derives
its next suffix from the latest ID created in the application year. It has no
separate reference counter. FileNumberService increments a per-year counter under
a shared cache lock and row lock. Removing all request rows and only the 2026
counter therefore yields the required first values without resetting IDs.
Assigned file numbers require a counter at least as high as their maximum;
consumed gaps are allowed because every request is explicitly inventoried.

Before deploying, run the isolated tests below and review the release diff.
Deploy the reviewed changes through the normal release process. This change
requires no schema migration.
Keep the existing APP_KEY, environment and storage. Do not copy local test config
to production. Validate two-connection insert blocking and rollback on disposable
MySQL/InnoDB with the deployed engine/version before authorizing production use.
Do not infer MySQL concurrency guarantees from SQLite tests.

On every application node, enter maintenance with `php artisan down`. Stop the
actual worker/scheduler services through the deployment's process manager and
disable cron/external writers; `queue:restart` alone does not stop supervised
workers. There is no universal safe service-stop command for this repository.
Obtain a fresh blocker-free dry-run after all writers have stopped. Protect its
report and signed journal; the journal contains private paths even though logs
contain only hashes. Never publish or paste the journal into logs.

Before restoring access, repeat `php artisan sai:cleanup-all-test-requests`.
It must report zero requests, zero affected dependency/job rows, zero present
private request files, zero blockers and recovery mode. Review the signed journal
locally to verify `completed` is true without printing its paths. Its absent
2026 counter and empty requests imply next SC/2026/F000001 and SC/2026/000001;
the reference preview must confirm the latter. Compare preserved table counts
and approved checksums against preflight, including appointments and their
notifications, users/authentication, services, settings, calendars, templates
and migrations. Verify admin login and public read-only pages without submitting
a request. Then restore the previously stopped services and run `php artisan up`
on every node. Retain backups and the completed journal under access control.

Unknown application links stored under arbitrary names or opaque JSON cannot be
proven absent by schema metadata alone; deployment review must confirm no custom
integrations/archive stores were added. Writers must remain stopped through file
cleanup and verification, because file operations cannot be transactionally locked
with the database. A failed recovery requires manual reconciliation or restoration
of the matched database/storage backup; never delete a journal to bypass refusal.

## Transaction, numbering and recovery

The signed journal is storage/app/private/maintenance/test-data-cleanup-all-v2.json.
It is written before deletion and retained after success. MySQL execution uses a
serializable transaction and range locks across request/dependency/queue/counter
rows. Approval is checked again under locks; exact inventoried IDs are deleted
child-before-parent, and deletion counts are checked. DB errors roll back the DB.

Before commit, requests must be empty and ReferenceNumberService must produce
SC/2026/000001. The existing convention uses the application year. Cleanup refuses
execution outside 2026; the first subsequent request must also be created in 2026
for that exact prefix. The next assigned file number is SC/2026/F000001. Unique
constraints stay enabled and primary-key auto-increment values are not reset.

File deletion starts only after database commit. **File deletion cannot be rolled
back with the database.** Failures exit nonzero and retain the journal. Keep all
writers stopped and maintenance enabled. Inspect dry-run and rerun execution to
retry only journal-listed files. Missing/already-deleted files are harmless.
Changed content, new files/requests/links, reused IDs or invalid journals block
further action. A database failure leaves rows/files intact, apart from the
journal; a post-commit failure may leave rows deleted and some files remaining.
The journal cannot restore deleted data. Do not remove the completed journal.

Already sent emails/WhatsApp messages, provider logs, customer downloads, generic
server/application logs and session/cache contents are outside this reset.
Verify zero target rows/files/jobs, the completed journal and preserved records
before restoring workers/scheduling/access. Do not create another production test
request to check numbering: it would consume the reference and may queue messages.

## Isolated tests

```bash
php vendor/phpunit/phpunit/phpunit -c phpunit.cleanup.xml --filter 'approved_scope|sync_missing|sync_still|unsupported_queue'
```

Execution tests use 19 synthetic requests and 20 private files, exercise both
queue drivers, and verify transactional rollback and retry. Out-of-scope fixtures
exercise inventory and execution refusal.
The dedicated runner avoids .env/deployed config cache, forces SQLite :memory:,
removes real connections before RefreshDatabase, fakes storage/mail and blocks
HTTP. Production guards are simulated only. Tests cover multiple years, more than
three requests, dependencies, preservation, stale/concurrent snapshots, blockers,
orphan/shared files, polymorphic links, rollback and recovery. SQLite results do
not establish MySQL locking behavior; disposable MySQL validation is still needed.
Additional regression coverage checks each execution guard without changing rows,
files or journals, queue chains in both ownership directions in jobs/failed_jobs,
nested mailable chains, batch/callback metadata, legacy-command refusal and
scope preservation, legacy refusal for completed/incomplete/invalid all-scope
journals, and preserved-counter changes during recovery. Preservation fixtures
include populated holidays, notification settings, active/inactive services and
admin users. Notification templates live in configuration and Blade files rather
than a database template table; configuration values and view-file hashes are
verified unchanged after the all-scope cleanup.

Exclude desktop.ini, local environment and config caches, fake-storage output,
and journals from the release.

## Dedicated local MariaDB/InnoDB validation

```bash
php vendor/phpunit/phpunit/phpunit -c phpunit.cleanup-mariadb.xml --testdox
```

This separate runner accepts local XAMPP MariaDB 10.4.32 or 10.11.16 at
127.0.0.1:3306 and sai_cleanup_test. It checks database name, server version,
local hostname and XAMPP data-directory identity before loading Laravel. It never
loads .env or deployed config caches, never discovers other database credentials,
and removes all alternate Laravel connections. It uses the local test account,
temporary fake storage, array cache/mail and blocked HTTP. Do not deploy or invoke
this local-specific runner as a production operation.

The first run refuses any existing schema objects/data and initializes only the
verified empty test database using ordinary migrate. No migrate:fresh, schema wipe,
foreign-key bypass, truncation or primary-key reset is used. A local temporary
baseline manifest records table/column and row hashes immediately after migrations.
Repeat runs require exact baseline equality and no routines/events/triggers;
unknown data causes a refusal before migrations or fixture writes. Every test
removes only its recorded synthetic IDs and asserts full baseline restoration.
Never delete the manifest or alter its hashes to bypass a mismatch. Investigate
the dedicated test database locally; no automatic wiping is provided.

MariaDB coverage includes genuine top-level transactions and an exception after
DELETE to prove rollback, independent PDO connections probing insert/update range
locks on populated and empty tables, stale approval, unknown transitive foreign
keys, trigger refusal, reserved jobs, guarded execution, file failure/resume, both
commands, populated preservation and next request/file numbers. The environment
guard is simulated in-process while the database stays pinned to sai_cleanup_test.
The existing six-digit business format is SC/2026/F000001; F00001 has only five
digits and is not the application's numbering convention.

See [the local MariaDB results](mariadb-cleanup-safety-test-results.md). These tests
validate this MariaDB/InnoDB version, not an unverified production engine/config,
multi-node cache/storage topology or external writer shutdown. Production preflight
and maintenance attestations remain mandatory. Temporary manifests, fake storage,
reports and desktop.ini are excluded from runtime deployment.

Allow 15–30 minutes for a modest approved cleanup and verification, within a
30–60 minute maintenance window. Large datasets or blockers can take longer.
