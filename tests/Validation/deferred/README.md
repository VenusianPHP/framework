# Deferred upstream tests

Two groups, held back for different reasons.

## Waiting on Database (wave 6)

`ValidationExistsRuleTest`, `ValidationUniqueRuleTest` and
`ValidationDatabasePresenceVerifierTest` drive the `exists` and `unique` rules
through a real connection. The rules themselves are ported — they guard on
`Instrument\Model` with `instanceof`/`is_subclass_of`, which is simply false
until wave 6 lands the class — and `ValidationServiceProvider` only wires the
presence verifier when `db` is bound. These tests come back with Database.

## Waiting on a file-upload value object

`ValidationFileRuleTest`, `ValidationImageFileRuleTest` and
`ValidationDimensionsRuleTest` build their subjects with
`Illuminate\Http\UploadedFile::fake()`. `Http` is ported as the client only, so
neither `Http\UploadedFile` nor `Http\Testing\FileFactory` exists here — both
belong to *receiving* a request. The `file`, `image`, `mimes` and `dimensions`
rules themselves are ported and work against Symfony's `File`/`UploadedFile`.

Reviving these means deciding what a Venusian file-validation subject is, which
is a design question rather than a port step — see `.okf/known-gaps.md`.
