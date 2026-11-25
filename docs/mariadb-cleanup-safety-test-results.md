# Local MariaDB cleanup safety validation

Test environment: XAMPP MariaDB 10.4.32, InnoDB, PHP 8.2.12, PHPUnit 11.5.56,
127.0.0.1:3306, dedicated sai_cleanup_test. No production connection or application
.env was used. Before the first write the database had zero tables, routines and
events; loopback, local hostname and XAMPP server data-directory identity matched.

Final run on 2026-11-13: MariaDB **8 tests, 141 assertions, PASS**, 1 minute
23.569 seconds, 48 MB. Companion SQLite suite: **66 tests, 516 assertions, PASS**,
10.218 seconds, 60 MB. Syntax, Pint and whitespace checks passed. Invoking the
MariaDB test file under the SQLite runner skipped all seven test methods before
application setup; it does not silently connect to MariaDB from the normal suite.

Reproduce only on this local disposable test database:

```bash
php vendor/phpunit/phpunit/phpunit -c phpunit.cleanup-mariadb.xml --testdox
php vendor/phpunit/phpunit/phpunit -c phpunit.cleanup.xml
```

| Scenario | Result |
| --- | --- |
| Ordinary migrations on the initially empty isolated database; InnoDB metadata checks | PASS |
| All-scope dry-run produces no row/file/journal changes | PASS |
| All-scope commit removes all inventoried requests/dependencies | PASS |
| Populated settings/services/admin/holiday preservation; template config and view hashes | PASS |
| Limited command removes three requests and preserves the historical request | PASS |
| Exception after actual DELETE rolls back rows; files remain; journal retry succeeds | PASS |
| Independent connection INSERTs into requests/jobs/sequences time out under populated-table locks | PASS |
| Independent connection INSERTs time out under empty-table range locks | PASS |
| Concurrent UPDATE of an existing request times out | PASS |
| Independent insert succeeds after commit; stale approvals preserve new rows | PASS |
| Unknown transitive dependency and target-table trigger block execution | PASS |
| Missing attestations and reserved jobs block; file failure/resume succeeds | PASS |
| Baseline restored after each test without dropping schemas or resetting IDs | PASS |
| Next reference SC/2026/000001 and next file SC/2026/F000001 | PASS |

Seven deliberate second-connection writes returned MariaDB error 1205 while the
command held its actual range locks. Separate connection IDs were asserted.
Both populated and empty-table cleanup cases subsequently committed successfully.
The rollback test injected an application exception after the SQL DELETE had
executed, before transaction commit; full row hashes matched the original snapshot.

The first fixture run exposed a duplicate synthetic service name under a real
MariaDB uniqueness constraint. The fixture was corrected; production code did not
need alteration. Baseline restoration was verified after the failed fixture run.

The file-number text F00001 in the latest request differs from the established
six-digit F000001 format. Tests retain the existing implementation and prior
requirement; a five-digit format would require an explicit separate decision.

Deployment remains conditional on assembling the final working-tree files,
matching the deployed engine/schema/configuration, a reviewed backup, maintenance
on all nodes, stopped writers/workers/scheduling, and a fresh blocker-free inventory.
File deletion is nontransactional. These local tests cannot certify external
integrations, production credentials, metadata visibility or multi-node topology.
No production cleanup, commit, push or deployment was performed.
