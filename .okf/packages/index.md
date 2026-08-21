# Packages

The five publishable `voyager/*` packages developed inside this monorepo.

* [voyager/collections](collections.md) - eager and lazy collection pipelines plus the Arr helper, ported from illuminate/collections.
* [voyager/nuts-and-bolts](nuts-and-bolts.md) - the general support package — string, number, date, and environment utilities plus the base contracts.
* [voyager/macroable](macroable.md) - the Macroable trait, letting third parties attach methods to a class at runtime.
* [voyager/conditionable](conditionable.md) - the Conditionable trait and its higher-order proxy, providing fluent when()/unless() chaining.
* [voyager/reflection](reflection.md) - Reflector, a static helper for interrogating callables, parameter types, and class attributes.

# Related

* [Monorepo with composer replace](../architecture/package-split.md) - how these packages are declared and released.
* [Known gaps](../known-gaps.md) - every one of these five manifests has at least one stale or wrong dependency.
