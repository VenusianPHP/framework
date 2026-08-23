# Architecture

* [Dependency direction](dependency-direction.md) - which package may depend on which, and the measured upward System edges.
* [Port hazards](port-hazards.md) - adding strict PHP type hints to Laravel code written for untyped parameters silently narrows behaviour.
* [Monorepo with composer replace](package-split.md) - 34 publishable `voyager/*` packages plus System as the non-split skeleton.
* [Namespace and autoloading scheme](namespace-and-autoloading.md) - `Voyager\` plus the overlapping `Voyager\NutsAndBolts\` PSR-4 prefixes.
* [Laravel lineage](laravel-lineage.md) - port of Laravel's generic non-web surface from `laravel/framework@v12.67.0`.

# Related

* [Overview](../overview.md) - what Venusian is and what is in the tree.
* [The 0.7.x reference implementation](../reference/upstream-0-7-x.md) - the Fabricate-era answer key.
* [Known gaps](../known-gaps.md) - cuts, leftovers, and retired claims.
