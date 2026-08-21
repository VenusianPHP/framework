# Update Log

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
