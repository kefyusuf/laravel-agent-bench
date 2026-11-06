# Support matrix

Evidence and policy for the Laravel version matrix, as of **2026-09-19**
(sources: laravel.com/docs/13.x/releases support table, laravel/laravel 12.x & 13.x
skeleton files, laravel/framework 12.x source for mechanism claims).

## Supported versions

| Version | Status (Sept 2026) | PHP | phpunit | cell id | default matrix |
| --- | --- | --- | --- | --- | --- |
| Laravel 13 | **active** (bug fixes until Q3 2027) | ^8.3 | ^12.5 | `laravel-13` (`^13.0`) | yes |
| Laravel 12 | security-only (until Feb 24 2027) | ^8.2 | ^11.5 | `laravel-12` (`^12.0`) | yes |
| Laravel 11 | **EOL** (security ended Mar 12 2026) | — | — | — | no |
| Laravel 10 | EOL | — | — | — | no |

Policy:

- New scenarios must declare **all** default-matrix cells (`["^12.0", "^13.0"]`) unless
  the defect is version-specific. The CI dynamic gate runs per declared cell.
- When Laravel 14 ships (Q1 2027) or a cell leaves security support, add/remove the cell
  in `bench.json` and re-run the full gate; scenario `laravel` lists are validated
  against the cells, so stale declarations fail structural validation immediately.
- This repository's own base skeleton mirrors the upstream 12.x skeleton, which is
  structurally identical to 13.x (`bootstrap/app.php`, `tests/TestCase.php`, `artisan`,
  migrations, `phpunit.xml`). The only per-cell differences are composer constraints
  (rendered from `bench.json` matrix cells) and the `shouldRenderJsonWhen` block in
  `bootstrap/app.php`, which exists in both 12 and 13.

## Mechanism claims used by scenarios (verified against laravel/framework 12.x)

These are load-bearing for hidden tests. If a future cell invalidates one, the affected
scenarios fail their dynamic gate — do not silence the test, update the claim and the
scenario.

| Claim | Source |
| --- | --- |
| afterCommit deferral for queued jobs lives in `Illuminate\Queue\Queue::enqueueUsing()` via the `db.transactions` manager; priority: `ShouldQueueAfterCommit` interface → job `$afterCommit` property → connection `$dispatchAfterCommit`; callbacks fire at the **outermost** transaction commit | `src/Illuminate/Queue/Queue.php`, `src/Illuminate/Database/DatabaseTransactionsManager.php` |
| `QueueFake::push()` records immediately — it does **not** route through `enqueueUsing()`, so `Queue::fake()` bypasses afterCommit deferral | `src/Illuminate/Support/Testing/Fakes/QueueFake.php` |
| `ShouldBeUnique` filtering runs at **dispatch time** in `PendingDispatch::shouldDispatch()` (`Illuminate\Bus\UniqueLock` against the cache), *before* the queue layer — so it applies even under `Queue::fake()`, and a dropped dispatch never reaches `Queue::assertPushed`. The lock (TTL = `uniqueFor()`) is released when the job is processed (`CallQueuedHandler`) and on transaction rollback (registered in `enqueueUsing()`) | `src/Illuminate/Foundation/Bus/PendingDispatch.php`, `src/Illuminate/Bus/UniqueLock.php`, `src/Illuminate/Queue/CallQueuedHandler.php` |
| `Illuminate\Bus\Dispatcher` contains no afterCommit logic — do not look there | `src/Illuminate/Bus/Dispatcher.php` |
| The HTTP kernel does **not** flush scoped container instances between requests; scoped instances are flushed by the queue worker loop and by Octane's `FlushTemporaryContainerInstances` listener only. Feature tests model the Octane flush explicitly with `$this->app->forgetScopedInstances()` between requests — this keeps `scoped()` a legitimate fix (it must pass) while a frozen `singleton()` still fails. Also: `Route::getController()` caches the controller instance across requests in a long-lived process, so constructor-injected request state freezes even under per-request bindings | `src/Illuminate/Foundation/Http/Kernel.php` (absence), `src/Illuminate/Queue/QueueServiceProvider.php`, `laravel/octane` `src/Listeners/FlushTemporaryContainerInstances.php`, `src/Illuminate/Routing/Route.php` |
| `retry_after` must exceed every job `timeout` on the connection or a still-running job is redelivered to a second worker | framework queue docs + `DatabaseQueue::isReservedButExpired()` |
| `env()` returns null for every call under a cached config (`.env` is not loaded) | framework config docs |
| `assertDatabaseCount/Has` third parameter is a **connection name**, not a message — passing a message throws `InvalidArgumentException: Database connection [...] not configured` | `src/Illuminate/Foundation/Testing/Concerns/InteractsWithDatabase.php` |
| `RefreshDatabase` wraps each test in a transaction; transaction-manager callbacks fire at the outermost commit | `src/Illuminate/Foundation/Testing/RefreshDatabase.php`, `src/Illuminate/Database/Concerns/ManagesTransactions.php` |

## Skeleton provenance (mirrored into `stubs/base`)

- `composer.json` → laravel/laravel 12.x, with `%%LARAVEL%%` / `%%PHPUNIT%%` / `%%PHP%%`
  tokens rendered per matrix cell; dev deps reduced to `phpunit` + `fakerphp/faker`
  (scenarios must not add dependencies).
- `phpunit.xml` → upstream 12.x content plus `APP_KEY` (fixed 32-byte base64) and
  `APP_URL` so workspaces run without a `.env`.
- `artisan`, `bootstrap/app.php`, `bootstrap/providers.php`, `routes/console.php`,
  `tests/TestCase.php`, `UserFactory`, and the three `0001_01_01_*` migrations →
  upstream 12.x verbatim (minus the welcome view and first-party dev packages).
