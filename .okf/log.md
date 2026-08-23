# Update Log

## 2026-08-23
* **Verification**: Framework Auditor housekeeping against `0.8.x` HEAD
  `3e93855e843921190adc69bcfd272ecb538d94b3` ((0.8.x) - Graph [Neo4j] DBs)
  plus the commits on this pass.
  **Verification key:** `agent:framework-auditor@3e93855e843921190adc69bcfd272ecb538d94b3`.
  Concepts stay `status: draft`.
* **Correction**: Tree counts at that SHA, measured not invented:
  **35** `src/Voyager/` directories, **1110** PHP files; **34** `voyager/*`
  replace entries including `voyager/graph`; Contracts **127**; Graph **10**;
  Sketches **9**; System **115**; `config/` **11**. Overview already had
  these Graph-era counts; stamps were still on `4e2910d`.
* **Types**: `GraphServiceProvider` `resolving('db')` takes
  `DatabaseManager`. `Neo4jConnection` `$activeTransaction` is
  `?UnmanagedTransactionInterface`; write-transaction closures take
  `TransactionInterface`. Covariant returns on
  `getDefaultQueryGrammar` / `getDefaultPostProcessor` / `run`. Connection
  overrides (`select` / `statement` / …) and Instrument
  `$connection` / `$primaryKey` / `$keyType` / `$incrementing` stay
  untyped to match parent. Container closures in
  `tests/Graph/GraphPackageTest.php` take `Contracts\Vessel\Vessel`.
  `GraphServiceProvider` is still **not** on `DefaultProviders`.
* **Pest**: Default-suite leftover PHPUnit `TestCase` is **0**. No
  `MockeryPHPUnitIntegration`. `tests/Graph/GraphPackageTest.php` is 4
  Pest closures. Deferred leftover remains
  `tests/Testing/deferred/ConfigShowCommandTest.php` (Testbench).
  Fixtures `tests/System/Stubs/{CloudQueueCase,TestCaseWithTrait}.php`
  stay.
* **Update**: Local `vendor/bin/pest` on PHP 8.4: **7543 passed**, 20
  skipped, 7 deprecated, 14 notices, 23438 assertions. Graph:
  **4 passed**, 13 assertions. Delta vs the Sketches pass (7539 / 23425)
  is exactly the Graph suite. GitHub Actions run `32614669415` (first
  push): PHP 8.4 **7551 passed**, 12 skipped, 23463 assertions; PHP 8.5
  **7546 passed**, 12 skipped, 23463 assertions. Same 8 local-skip vs
  CI-pass gap as the Sketches pass.

## 2026-08-23
* **Add**: [voyager/graph](packages/graph.md) landed as an opt-in Neo4j
  companion to [voyager/database](packages/database.md). Straight port of
  0.7.x `fabricate/graph` (`Fabricate\`→`Voyager\`, `Polisher`→`Instrument`,
  `scrapyard_io`→`venusian`). `GraphServiceProvider` is **not** on
  `DefaultProviders`. Root `replace` is **34** packages. `src/Voyager/` is
  **35** directories. Root `require-dev` pins `laudis/neo4j-php-client
  ^3.3.0`. Pest `tests/Graph`: **4 passed**, 13 assertions. Live MCP
  write/read round-trip against local Neo4j succeeded. Concept stays
  `status: draft`.

## 2026-08-23
* **Verification**: Framework Auditor housekeeping against `0.8.x` HEAD
  `4e2910dd3783ff661dea9def23d1059bbb91b400` ((0.8.x) - Sketches) plus
  the commits on this pass.
  **Verification key:** `agent:framework-auditor@4e2910dd3783ff661dea9def23d1059bbb91b400`.
  Concepts stay `status: draft`.
* **Correction**: Tree counts at that SHA, measured not invented:
  **34** `src/Voyager/` directories, **1100** PHP files; **33** `voyager/*`
  replace entries including `voyager/sketches`; Contracts **127** (7 under
  `Contracts/Sketches`); Sketches **9**; System **115**; `config/` **11**
  including `sketches.php`. `packages/index.md` still said Contracts 120.
* **Correction**: `SketchesServiceProvider` is uncommented on
  `DefaultProviders`. The known-gaps "Sketches not landed" / wave-7-comment
  claim was already retired on the landing commit.
* **Fix**: CI run `32606313934` failed one test:
  `Tests\System\SketchMakeCommandTest` —
  `BindingResolutionException: Target class [config] does not exist`
  at `Vessel.php:1131`. The landing test never bound `config`. Bound a
  `Voyager\Config\Repository` like `HandleExceptionsTest`. Not a Vessel
  type-hint TypeError; closures stay `Contracts\Vessel\Vessel`.
* **Update**: Local `vendor/bin/pest` on PHP 8.4: **7539 passed**, 20
  skipped, 7 deprecated, 14 notices, 23425 assertions. Sketches +
  SketchMake: **11 passed**, 30 assertions.

## 2026-08-22
* **Add**: [voyager/sketches](packages/sketches.md) landed. Sketch is the
  unit of a Venusian app: `php runner hello-world` boots, ticks `loop()`
  until STOP, shuts down once in `finally`. `SketchRunner` is a direct
  Arduino loop — no `Flow/` tree, no `BootSketchNode` / `TickSketchNode`.
  Kernels and `handleSketch()` live in System. `voyager/sketches` does not
  `use Voyager\System\*` and does not require `voyager/workflows`.
  `SketchesServiceProvider` is uncommented on `DefaultProviders`. Computer
  `make:sketch` / `make:middleware` write under `app/Runner/`. Known-gaps
  claim "Sketches not landed" is retired. Root `replace` is **33** packages.
  Contracts **127** (7 under `Contracts/Sketches`). `config/` is **11**
  files including `sketches.php`. Concepts stay `status: draft`.
* **Update**: [voyager/workflows](packages/workflows.md) notes that Sketches
  no longer embeds a Flow copy. A sketch may still call a workflow from
  `loop()`.

## 2026-08-22
* **Verification**: Framework Auditor housekeeping against `0.8.x` HEAD
  `e4450c2d96ec2451305ce21fc13030c7a581a000` ((0.8.T) - Workflows Component
  Tests) plus the commits on this pass.
  **Verification key:** `agent:framework-auditor@e4450c2d96ec2451305ce21fc13030c7a581a000`.
  Concepts stay `status: draft` (human sign-off is Angel's).
* **Correction**: Tree counts at that SHA, measured not invented:
  **33** `src/Voyager/` directories, **1081** PHP files; **32** `voyager/*`
  replace entries including `voyager/workflows`; Contracts **120** (6 under
  `Contracts/Workflows`); Cache **55**; `config/` **10** files including
  `workflows.php`. Overview, package-split, namespace, dependency-direction,
  contracts, config, and cache concepts updated.
* **Correction**: [voyager/workflows](packages/workflows.md) did not mention
  the test suite. `tests/Workflows/` is 5 Pest v4 files. Datasets
  `async runtimes` and `overlapping async runtimes` live in `tests/Pest.php`.
  Known gaps still true against source: no sync `BatchNode`/`BatchFlow`;
  `WorkflowsServiceProvider` not in `DefaultProviders`; `AsyncRunnable`
  cannot declare `_runAsync` (`SharedBag` is in the package).
* **Correction**: Default-suite leftover PHPUnit `TestCase` is **0**. No
  `MockeryPHPUnitIntegration`. The claim "Workflows added no tests" is
  false at `e4450c2`. Deferred leftover that is a real TestCase remains
  `tests/Testing/deferred/ConfigShowCommandTest.php` (Testbench).
  `phpunit.xml` excludes 14 deferred paths; 51 deferred PHP files.
* **Fix**: First execution of the Workflows suite (the landing commit said
  it was not run) found `FiberRuntime::loop()` throwing deadlock after a
  top-level `await(delay())` fulfilled the last timer. Re-check settlement
  before treating an empty schedule as deadlock. PHP 8.5
  `SplObjectStorage::{attach,contains,detach}` in that class replaced with
  array access. `AsyncRuntimeManager::driver()` parameter left untyped —
  narrowing it against `Manager::driver($driver = null)` fatals.
* **Update**: Local `vendor/bin/pest` green: **7528 passed** on PHP 8.4,
  **7525 passed** on PHP 8.5, 20 skipped, 23395 assertions. Workflow
  unchanged; `react/async` stays `require-dev`.

## 2026-08-22
* **Update**: Converted the last leftover PHPUnit `TestCase` suites that
  were still class-wrapped after `012f303`. Survey at that SHA: default
  suite already had **0** leftover `extends TestCase` classes (Laradeps
  had converted Database / Broadcasting / Queue / Notifications). Workflows
  added no tests. Converted the five remaining deferred leftovers —
  `tests/Queue/deferred/` (4) and
  `tests/Notifications/deferred/NotificationSendQueuedNotificationTest.php`
  — to Pest v4 closures. Baseline vs converted: same 6 pre-existing
  `QueueDatabaseQueueUnitTest` failures; the Mockery-only Notifications
  case went risky → pass (Pest counts fulfilled Mockery expectations).
  Left `tests/Testing/deferred/ConfigShowCommandTest.php` as Testbench
  PHPUnit. `phpunit.xml` exclusions unchanged. See [known gaps](known-gaps.md).

## 2026-08-22
* **New**: [voyager/workflows](packages/workflows.md) — finished the async half of
  the Workflows component and made its behaviour driver-based. Added
  `Voyager\Contracts\Workflows\Awaitable` (one `then()`) and `AsyncRuntime` (five
  operations: `async`, `resolve`, `await`, `all`, `delay`), plus `RuntimeAware`,
  which `AsyncRunnable` now extends so a flow can hand its runtime to every
  member it orchestrates. `AsyncRuntimeManager` (over `NutsAndBolts\Manager`)
  resolves `sync` / `fiber` / `react` from `config('workflows.runtime')`, named
  by the `AsyncRuntimeDriver` enum; `sync` is the default so async graphs run
  with no optional package installed. Rebuilt `AsyncWorkflowLogic`, `AsyncNode`,
  and `AsyncFlow` against the contract and added `AsyncBatchNode`,
  `AsyncParallelBatchNode`, `AsyncBatchFlow`, `AsyncParallelBatchFlow`. No
  ReactPHP type appears anywhere in the package outside `Runtimes/ReactRuntime.php`
  and `Runtimes/ReactAwaitable.php`.

  Three upstream PocketFlow-PHP defects were fixed rather than ported:
  `AsyncFlow::_runAsync` now runs `prepAsync`/`postAsync` around orchestration
  (upstream skips both, unlike its own sync `Flow::_run`); batch flows put their
  param loop in `_runAsync` rather than `runAsync`, so nesting one inside another
  `AsyncFlow` no longer silently runs a single orchestration; and retry backoff
  goes through `AsyncRuntime::delay()` instead of blocking `sleep()`.
  `AsyncParallelBatchFlow` clones the nodes it visits, since params live on node
  state and concurrent branches otherwise overwrite each other.

  Added `config/workflows.php`, `WorkflowsServiceProvider` (not yet in
  `DefaultProviders`), `voyager/workflows` to the root `replace` block (31 → 32
  packages), and `react/async` to `require-dev`. Tests live in
  `tests/Workflows/` and run the same assertions once per runtime via the new
  `async runtimes` dataset in `tests/Pest.php`. **Not yet executed** — the
  working tree is on a read/write-only mount.

## 2026-08-21
* **Update**: Ported Laravel's `SupportStrTest.php` (2,004 lines, 115 test
  methods) into `tests/NutsAndBolts/StrTest.php`, merged alongside the
  existing hand-written coverage rather than replacing it (211 new tests;
  suite total 6442 → 6653 passing, still 0 failing / 0 risky / 0 warnings).
  Found and fixed seven more instances of the
  [port hazard](architecture/port-hazards.md#fifth-audit-supportstrtest-port):
  the whole `Str` search/substring family (`startsWith`, `endsWith`,
  `contains`, `is`, `isMatch`, `before`/`after`/`between` and friends,
  `excerpt`, `replaceFirst`/`replaceStart`/`replaceLast`/`replaceEnd`,
  `ascii`/`isAscii`/`slug`, `numbers`) was typed narrower than Laravel's own
  test suite exercises it, plus a sixth-shaped bug: `Str::uuid()` and its
  five siblings coerced a `Stringable`-returning factory to a plain string
  via their `UuidInterface|string` return type, losing `->toString()`.
  Widened to `mixed`. See the port-hazards doc for the full table and every
  `src/` line changed.

## 2026-08-21
* **Verification**: Framework Auditor pass against `0.8.x` HEAD after PR 1
  (`8a8600fda67358ec3b38b579f9f13e5107bdc758`).
  **Verification key:** `agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758`.
  Official OKF 0.2 field is `verified: { by, at }`; this repo also writes
  `verification_key` because Angel asked for a citeable audit token and OKF
  0.2 has no reserved name for one. Concepts stay `status: draft` (AGENTS.md:
  human sign-off is what makes `stable`). Playbook updated:
  [maintaining this knowledge bundle](playbooks/maintaining-this-bundle.md).
* **Correction**: [overview](overview.md) and root [index](index.md) still
  described a CLI/sketch-only Support foundation (26 declarations, 5
  publishable packages, `ScrapyardIO/framework`, "CLI and sketch layers not
  in this repo"). README and the tree say otherwise: windowed GUIs + hardware
  ICs; 32 directories / 1050 PHP files under `src/Voyager/`; 31 `voyager/*`
  replace entries. Evidence: `README.md`, `composer.json`,
  `find src/Voyager`.
* **Correction**: [known-gaps](known-gaps.md) said `voyager/contracts` has no
  directory and wave 6 Database had not landed. `src/Voyager/Contracts/` is
  114 PHP files; `src/Voyager/Database/` is 230 PHP files with
  `Instrument\Model` and `Capsule\Manager`. `DatabaseServiceProvider` is in
  `DefaultProviders`.
* **Correction**: Test/CI claims ("no suite", "184 tests", waves 5–6 still
  PHPUnit, `checkout@v4`, intl-only extensions, MagicAlias vs Mockery
  1.6.15). PR 1 CI run `32527823474`: **6257 passed** on PHP 8.4 and 8.5;
  `.github/workflows/tests.yml` uses `actions/checkout@v5` and
  `intl, pdo, pdo_sqlite, pdo_mysql, gmp`; leftover PHPUnit `TestCase`
  classes in the default suite are Database 119 + Queue 17 + Notifications 6
  + Broadcasting 2 (they run under Pest); `MagicAlias::shouldReceive()`
  returns `Mockery\ExpectationInterface`.
* **Correction**: `fabricate/*` requires, empty `config/`, missing Laravel
  revision, `voyager/system` in `replace`, fatal `now()`, `ReflectsClosures`
  as a class — all false at this SHA. Remaining manifest drift:
  `voyager/conditionble` typo, `voyager/collection` singular, NutsAndBolts
  PHP `^8.6`, Macroable ScrapyardIO URLs. Deliberate Queue / Broadcasting /
  Notifications / Validation cuts still match source. File-upload value
  object and Testbench deferred files still match source.
  `DatabaseBatchRepository::find()` still lacks `return null`.
* **Update**: Added package concepts for every publishable `voyager/*`
  directory that had none, and refreshed the five foundation concepts.
  [packages/index.md](packages/index.md) lists all 31; System is noted as
  the non-split skeleton.
* **Update**: Architecture concepts
  ([package-split](architecture/package-split.md),
  [dependency-direction](architecture/dependency-direction.md),
  [namespace-and-autoloading](architecture/namespace-and-autoloading.md),
  [laravel-lineage](architecture/laravel-lineage.md),
  [port-hazards](architecture/port-hazards.md)),
  [global helpers](api/global-helpers.md),
  [local development](playbooks/local-development.md), and
  [0.7.x reference](reference/upstream-0-7-x.md) rewritten against the
  measured tree. Recorded upward `use Voyager\System\` edges from Bus,
  Queue, Broadcasting, and Testing.

## 2026-08-21
* **Update**: Converted the wave 4 test directories (`tests/Bus`, `tests/Translation`,
  `tests/Concurrency`, `tests/Events`, `tests/Validation` — 48 files, including
  every `deferred/` subdirectory in both `Bus` and `Validation`) to Pest v4.
  `tests/Validation/ValidationValidatorTest.php` alone is 274 upstream test
  methods across 9,995 lines — by far the largest single file converted in any
  wave so far, ~10x the previous record (`FoundationApplicationTest.php` at 550
  lines). Verified per-file and directory-wide against the pre-conversion
  PHPUnit baseline: identical pass/fail/risky counts throughout, 0 fixed, 0
  regressed. Assertion counts rose in the usual places (Mockery expectations
  counted as assertions under Pest, continuing the wave 2 effect).
  Five of six files in `tests/Validation/deferred` were converted against
  their exact recorded pass/fail profile, following the wave 0/3 precedent for
  deferred files that cannot be green yet — see [known gaps](known-gaps.md)
  for what blocks each one. `tests/Bus/deferred/BusBatchTest.php` looked like
  a sixth such case (0/19 passing) but wasn't: its failures traced to two
  leftover `Illuminate`-era call sites — `bootEloquent()` and a
  `Voyager\MagicAliases\Facade` that never existed — that this package's own
  naming rule (`AGENTS.md`) says must not survive a port. Fixed as a naming
  correction rather than preserved as a Database blocker, which took the file
  to 16/19 passing and surfaced two real remaining `src/` bugs, left for a
  human call — see [known gaps](known-gaps.md).
* **Update**: Found and fixed a real bug in the ad hoc Python conversion
  tooling used for this and prior waves' mechanical PHPUnit-to-Pest
  transforms: its brace/paren balance tracker treated an apostrophe inside a
  `//` comment (e.g. "there's", "doesn't") as a string-literal delimiter,
  which could silently misplace a method body's boundaries. It stayed latent
  through waves 0-3 by chance — no affected comment happened to precede a real
  brace before the tracker's bogus "string" resynced on the next genuine
  quote — until `ValidationValidatorTest.php`'s `testValidateArrayKeys`
  (`// The array is valid if there's a missing key.`) finally hit the failure
  mode outright: unmatched-brace, not silent corruption. Fixed by teaching the
  tracker to skip `//`, `#` and `/* */` comments before checking for quotes.
  Every file already converted before the fix was spot-checked against this
  risk (grepped for apostrophes in comments) and re-verified against its
  baseline pass/fail count; none were affected, since a real corruption event
  is rare enough that the two prior near-misses (`ValidationRuleDoesntContainTest`,
  `ValidationEnumRuleTest`) also happened not to straddle a live brace.

## 2026-08-20
* **Update**: Wave 5 of the Laravel port — `Queue`, `Broadcasting` and
  `Notifications` from `laravel/framework@v12.67.0`. Suite: 3205 → 3424 passing,
  with the two pre-existing failures unchanged. All three registered in
  [`DefaultProviders`](../src/Voyager/System/DefaultProviders.php) and verified
  through the booted skeleton app: the sync queue runs a real closure job, the
  broadcast manager resolves its drivers, and the channel manager resolves the
  database and broadcast channels.
* **Update**: Landing Queue unparked four upstream test files that were waiting
  on it — two in `tests/Bus`, two in `tests/Events`. `tests/Events/deferred` is
  now gone entirely; `tests/Bus/deferred` holds only `BusBatchTest`, which needs
  a real database connection.
* **Update**: Ported the five `Testing\Fakes` that wave 3 left out because their
  subjects did not exist yet — `QueueFake`, `BatchFake`, `BatchRepositoryFake`,
  `PendingBatchFake`, `PendingChainFake` — plus `NotificationFake`. `Bus\Batchable`
  had a dangling import of `BatchFake` under the pre-wave-3 namespace.
* **Update**: Created three magic aliases whose subjects only now exist —
  `Context` (referenced by `Log\Context\ContextServiceProvider` since wave 2 and
  never defined), `Broadcast`, and `Notification` — and restored the five fake
  helpers on the `Queue` alias.
* **Fix**: Four more type-narrowing defects, recorded as a fourth audit in
  [port hazards](architecture/port-hazards.md). The sharpest is `Macroable::__call`,
  typed while `Manager::__call` was left untyped, which makes any class extending
  `Manager` and using `Macroable` a fatal — typing is not a per-file decision.
* **Fix**: `Notifications\ChannelManager` and `System\MaintenanceModeManager` both
  read `$this->container`, which `NutsAndBolts\Manager` renamed to `$this->vessel`.
  The second was latent since wave 2.
* **Update**: Wave 5 cuts, recorded in [known gaps](known-gaps.md) — Beanstalkd,
  SQS and DynamoDB from Queue; Ably and the whole channel-authorization surface
  from Broadcasting; the mail channel, `MailMessage`, `SimpleMessage` and the Blade
  view from Notifications, whose default channel is now `database` rather than
  `mail`.

* **Update**: Wave 4 of the Laravel port — `Translation`, `Concurrency` and
  `Validation` from `laravel/framework@v12.67.0`, with `Bus` and `Events` already
  landed as dependencies in earlier waves. Suite: 1978 → 3205 passing, with the
  two pre-existing failures unchanged. All three registered in
  [`DefaultProviders`](../src/Voyager/System/DefaultProviders.php) and verified
  through the booted skeleton app, not only through unit tests.
* **Fix**: `Arr::exists()` typed `$key` as `float|int|string` while its body
  branches on `is_null($key)` — the same narrowing hazard as waves 0 and 1, caught
  by `ValidationInArrayKeysTest`. Widened to `float|int|string|null` and recorded
  as a third audit in [port hazards](architecture/port-hazards.md).
* **Fix**: The `Concurrency` magic alias resolved the accessor `'concurrency'`,
  which nothing binds; upstream returns `ConcurrencyManager::class`. Unreachable
  until Concurrency landed, so the boot check was the first thing to hit it.
* **Fix**: `InvokeSerializedClosureCommand` carried Laravel's untyped `$signature`,
  `$description` and `$hidden`, incompatible with `Voyager\Console\Command`, which
  types them. It fatalled the whole `computer` binary at class-load — caught by the
  end-to-end boot gate rather than by any test.
* **Fix**: `config:publish` fatalled with no argument — `laravel/prompts` types
  `select()`'s `$options` against `Illuminate\Support\Collection`, which
  `Voyager\NutsAndBolts\Collection` is not. Passing `->all()` fixes it. Pre-existing,
  and invisible to the suite because the interactive path is never exercised. An
  audit of all 16 `Laravel\Prompts` call sites found no others handing a Collection
  to a vendor signature — every other one already ends in `->all()`.
* **Update**: Three deliberate cuts in `Validation`, recorded in
  [known gaps](known-gaps.md) — `Rules\Can` (Auth is out of scope), the HTTP-response
  surface of `ValidationException`, and the precognition hook in
  `ValidatesWhenResolvedTrait`. Six upstream test files are parked in
  `tests/Validation/deferred/`: three wait on Database (wave 6), three on a
  file-upload value object that the client-only `Http` port does not provide.

## 2026-08-19
* **Update**: Wave 1 of the Laravel port — `Config`, `Pipeline`, `Encryption`,
  `Hashing`, `JsonSchema`, all from `laravel/framework@v12.67.0` with their upstream
  tests. Suite: 642 → 1007 passing. The recipe now lives as a reusable script rather
  than ad-hoc edits; the wave surfaced three gaps in it (composer slug conversion
  dropping hyphens, bare `Container` identifiers left after the FQCN rewrite, and
  import collisions with global classes), all fixed in the tool before the next wave.
* **Update**: Fixed a wave-0 defect the Hashing tests caught — `Manager`, `Hub`,
  `Pipeline`, `CapsuleManagerTrait` and `Localizable` kept bare `Container` type hints
  after their imports were rewritten to `Vessel`, so the hint resolved to a class that
  does not exist.
* **Update**: Wave 0 of the Laravel port. `voyager/vessel` gained its core from
  `Illuminate\Container`; ~40 missing `Illuminate\Support` classes landed in
  NutsAndBolts (`ServiceProvider`, `Manager`, `Fluent`, `MessageBag`, `Sleep`,
  `Uri`, …) plus 15 global helpers. Suite: 184 → 642 passing.
* **Update**: `Pluralizer` is no longer a stub — `doctrine/inflector` is a declared
  `illuminate/support` requirement, so the real implementation came with the port.
  24/24 plural cases now correct. Retired that entry from [known gaps](known-gaps.md).
* **Update**: [Port hazards](architecture/port-hazards.md) gained a wave-0 audit —
  three more narrowed signatures (`Collection::__construct`, `Str::of`, and the
  `collect()` helper) plus the inverse case of typed contracts forcing return types
  onto implementations and test doubles.
* **Update**: Put the repository under git, added a 184-test Pest v4 suite and a
  GitHub Actions workflow, and rebranded the README from ScrapyardIO to Venusian.
  [Local development](playbooks/local-development.md) and [overview](overview.md)
  updated; the "no tests, no CI, no version control" entries in
  [known gaps](known-gaps.md) are retired.
* **Update**: Recorded three further defects that writing the tests surfaced —
  `Arr::first()`/`Arr::last()` typing `$default` as `?Closure`, `chunk()`
  declaring `$preserveKeys` with no default, and `LazyCollection::chunk()`
  ignoring that argument entirely. Added to [known gaps](known-gaps.md) and
  [port hazards](architecture/port-hazards.md).
* **Fix**: Repaired six runtime-verified defects in the ported foundation and recorded
  them in [known gaps](known-gaps.md) — native `\Stringable` resolution in Collections,
  thirteen `Str` signatures that silently coerced `Collection` arguments,
  `LazyCollection::make()` rejecting a `Closure`, a missing `Str::singular()`, a
  fatalling `now()`, and `ReflectsClosures` declared as a class. Verified by a
  37-check regression sweep; the repo has no test suite.
* **Creation**: Recorded the systemic cause of most of those defects in
  [port hazards](architecture/port-hazards.md) — type hints added to Laravel code
  written for untyped parameters, where PHP coerces stringable objects instead of
  rejecting them.
* **Creation**: Recorded the [0.7.x reference implementation](reference/upstream-0-7-x.md)
  — the `Fabricate`-namespaced predecessor, which settled `ReflectsClosures` as a port
  regression and explains `MagicAliases` as the facade layer.
* **Update**: Corrected [known gaps](known-gaps.md) — the `fabricate/*` dependencies in
  the sub-package manifests are stale 0.7.x package names, not typos.
* **Update**: Recorded `Pluralizer` as a deliberate stub in
  [nuts-and-bolts](packages/nuts-and-bolts.md) and [known gaps](known-gaps.md), with the
  20-of-24 divergence table. Left unfixed by design.
* **Creation**: Established the Venusian Framework knowledge bundle from the working tree at
  `venusian/framework` v0.8.0 — root [index](index.md) and [overview](overview.md).
* **Creation**: Documented the five publishable packages — [collections](packages/collections.md),
  [nuts-and-bolts](packages/nuts-and-bolts.md), [macroable](packages/macroable.md),
  [conditionable](packages/conditionable.md), [reflection](packages/reflection.md).
* **Creation**: Recorded the [package split](architecture/package-split.md),
  [namespace and autoloading scheme](architecture/namespace-and-autoloading.md), and
  [Laravel lineage](architecture/laravel-lineage.md).
* **Creation**: Captured the [global helper surface](api/global-helpers.md) and the
  [local development playbook](playbooks/local-development.md).
* **Creation**: Recorded runtime-verified defects in [known gaps](known-gaps.md).
* **Update**: Corrected the bundle's framing after maintainer input — Venusian is a
  Laravel-like framework for **sketch-based CLI workflows**, and the Support port is a
  deliberate foundation-first step rather than the whole scope. Rewrote
  [overview](overview.md) and [Laravel lineage](architecture/laravel-lineage.md),
  which was reclassified from `Reference` to `Architecture Decision`, and refreshed the
  root [index](index.md).
* **Creation**: Recorded the [dependency direction](architecture/dependency-direction.md)
  layering rule from `AGENTS.md`, with a measured audit of every cross-package import
  and the three upward edges that violate it.
* **Creation**: Recorded the bundle's own conventions in
  [maintaining this knowledge bundle](playbooks/maintaining-this-bundle.md) — one bundle
  at the package root, concepts stay `draft` until a human verifies them.
* **Update**: Added a confirmed `Stringable` name-resolution defect affecting `implode()`,
  `groupBy()`, and `where()` to [known gaps](known-gaps.md), and reframed
  `voyager/contracts` there from an oversight to planned-but-unbuilt work.
* **Update**: Corrected [package split](architecture/package-split.md) — the
  NutsAndBolts to Collections dependency is sanctioned by the layering rule, not the
  cycle it was previously described as.
* **Update**: Converted the Wave 0 tests — `tests/Vessel` and `tests/NutsAndBolts`,
  31 upstream PHPUnit classes — to Pest v4, completing step 2 of the plan's two-step
  test strategy. 637 tests green, one pre-existing skip. Inline stub classes were
  lifted into `tests/<Component>/Fixtures/`; three fixture files that PSR-4 cannot
  carry (two `functions.php`, plus `AttributeTargets.php`, whose class names end in
  `Test`) load through `autoload-dev.files`. The two deferred files were converted
  against a recorded pass/fail profile so the conversion is provably faithful even
  though they cannot be green yet. Recorded the resulting `HandleExceptionsTest`
  Mockery leak in [known gaps](known-gaps.md).
* **Correction**: Recorded that `Voyager\System` deliberately ships no sub-package
  `composer.json`, `LICENSE` or `.gitattributes` in
  [package split](architecture/package-split.md). `Illuminate\Foundation` is the one
  component of 37 upstream with none of the three and no `replace` entry — it is the
  application skeleton, not a consumable split package. Corrects an earlier reading
  of the porting recipe that treated the Collections layout as universal. The root
  `composer.json` `replace` block still carries a `voyager/system` entry that
  upstream has no counterpart for; left for the maintainer.
* **Update**: Converted the wave 1 tests (Config, Pipeline, Encryption, Hashing,
  JsonSchema — 15 files) and the wave 2 tests (System, Console, Log — 50 files) to
  Pest v4, run as two isolated worktree branches off the checkpoint and merged
  separately. Wave 1 preserved all 555 assertions exactly; wave 2 rose 534 to 698
  because Pest counts fulfilled Mockery expectations, which also retired all 26 of
  its "risky, no assertions" cases. Suite-wide assertions did not move.
* **Update**: Recorded in [known gaps](known-gaps.md) that the port left
  `Voyager\Foundation` in two `HandleExceptionsTest` regexes — an incomplete
  namespace rewrite that `grep -rn 'Illuminate'` cannot catch — and that
  `tests/System` must use `Stubs/` rather than `Fixtures/` because the dev volume is
  case-insensitive and a lowercase `fixtures/` already exists.
* **Update**: Converted all seven wave 3 test directories (`tests/Filesystem`,
  `tests/Process`, `tests/Pagination`, `tests/Http`, `tests/Cache`, `tests/Redis`,
  `tests/Testing` — ~85 files including every `deferred/` subdirectory) to Pest
  v4, run as seven parallel agents against one shared worktree (each scoped to
  its own non-overlapping `tests/<Component>/` directory, so no coordination
  was needed between them). Diffed the full suite's `--log-junit` output before
  and after: the same 46 pre-existing failures fail before and after conversion,
  0 fixed, 0 regressed. 39 previously-risky (no-assertion) Mockery-only cases
  across Cache/Redis/Http turned into honest passes, continuing the effect from
  wave 2. One deliberate exception: `tests/Testing/deferred/ConfigShowCommandTest.php`
  was left untouched — it depends on `Orchestra\Testbench`, which is not and
  will not become a dependency of this repo (see [known gaps](known-gaps.md)
  for the fuller "we are not building Laravel" note, and the 17 other
  pre-existing Testbench-dependent files found scattered across `deferred/`
  that predate this conversion and were equally left alone). Also surfaced,
  recorded in [known gaps](known-gaps.md): a converted class's helper method
  can't become a plain Pest function if it reads a `private` trait property or
  calls a `protected`/`private` `TestCase` method (PHP's visibility check is
  scope-based, not object-based); `__CLASS__` and `self::` inside a closure
  silently stop resolving the way they did under the original class wrapper,
  with no error to catch it; and a genuine, unrelated `Voyager\MagicAliases\MagicAlias`
  incompatibility with Mockery 1.6.15 that was already failing two `Testing/deferred`
  tests before any of this work started.
* **Update**: Ported `Illuminate\Cache\DatabaseStore` (the last unported
  Laravel component) to `src/Voyager/Cache/DatabaseStore.php`, plus its
  companion `DatabaseLock` (`Illuminate\Cache\DatabaseLock`), needed for
  `LockProvider::lock()` and not called out by name in the task but required
  by the interface. Wired `CacheManager::createDatabaseDriver()` the same way
  `createRedisDriver`/`createFileDriver` are wired, and turned the commented-out
  `database` store entry in `config/cache.php` live. `CacheTableCommand`
  needed no change — already correct. Kept the whole file docblock-typed
  (matching every sibling in `src/Voyager/Cache/` — `RedisStore`, `ArrayStore`,
  `Lock`, `CacheLock` — and `Voyager\Database\Query\Builder` itself, none of
  which carry scalar type hints on params/properties beyond `array`/`Closure`);
  see [port hazards](architecture/port-hazards.md) for why adding type hints
  Laravel didn't have is a live risk, not a style choice, on `Store`/`Lock`
  implementations. Faithfully converted `tests/Cache/CacheDatabaseStoreTest.php`
  to Pest (Mockery partial mocks of `DatabaseStore` via
  `Mockery::mock(DatabaseStore::class, $args)->shouldAllowMockingProtectedMethods()->makePartial()`,
  matching the pattern already in `CacheManagerTest.php`) — 13 tests, 0 risky
  (upstream's assertion-free `testItemsMayBeRemovedFromCache` was given a real
  `expect()` since Pest flags a no-assertion test risky where PHPUnit does
  not). Added `tests/Cache/CacheDatabaseStoreIntegrationTest.php` (not from
  upstream) running the store and lock against a real in-memory sqlite
  connection via `Voyager\Database\Capsule\Manager`, since the ported test
  only ever mocks the query builder and neither `CacheManager::createDatabaseDriver()`
  nor `DatabaseLock` had any coverage against real `Voyager\Database` query
  building. Suite: 6442 → 6459 passed (+17), 0 failing/risky/warnings before
  and after.
