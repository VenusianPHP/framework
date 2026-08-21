# Architecture

* [Dependency direction](dependency-direction.md) - the layering rule governing which Venusian package may depend on which, and where the current tree stands against it.
* [Port hazards](port-hazards.md) - adding strict PHP type hints to Laravel code written for untyped parameters silently narrows behaviour; the failure is a wrong answer, not an error.
* [Monorepo with composer replace](package-split.md) - Venusian develops five voyager/* packages in one tree and declares them all in the root manifest's replace block.
* [Namespace and autoloading scheme](namespace-and-autoloading.md) - how Voyager\NutsAndBolts\* class names resolve across five physical directories via overlapping PSR-4 prefixes.
* [Laravel lineage](laravel-lineage.md) - Venusian's foundation is a deliberate namespace-rename port of illuminate/support and illuminate/collections, taken first because everything above it depends on it.

# Related

* [Overview](../overview.md) - what Venusian is and what stage it is at.
* [The 0.7.x reference implementation](../reference/upstream-0-7-x.md) - the answer key for port questions.
* [Known gaps](../known-gaps.md) - defects that follow from these choices.
