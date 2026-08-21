# Packages

One concept per publishable `voyager/*` package. There are **31**, matching
the root `composer.json` `replace` block. `Voyager\System` is the
composition root and is **not** a split package — no concept file, no
`composer.json`, not in `replace`. See
[package split](../architecture/package-split.md).

# NutsAndBolts family

* [voyager/collections](collections.md) - eager and lazy collection pipelines plus Arr.
* [voyager/nuts-and-bolts](nuts-and-bolts.md) - support utilities, Manager, ServiceProvider, and concrete magic aliases.
* [voyager/macroable](macroable.md) - Macroable trait.
* [voyager/conditionable](conditionable.md) - Conditionable trait and HigherOrderWhenProxy.
* [voyager/reflection](reflection.md) - Reflector and the ReflectsClosures trait.

# Contracts, container, aliases

* [voyager/contracts](contracts.md) - framework-wide interfaces (114 PHP files; the directory exists).
* [voyager/vessel](vessel.md) - service container.
* [voyager/magic-aliases](magic-aliases.md) - MagicAlias base class.

# Components

* [voyager/broadcasting](broadcasting.md) - event broadcasting; Ably and channel-auth cut.
* [voyager/bus](bus.md) - command bus and batches.
* [voyager/cache](cache.md) - cache manager and stores.
* [voyager/concurrency](concurrency.md) - concurrent process driver.
* [voyager/config](config.md) - configuration repository.
* [voyager/console](console.md) - console kernel; binary is computer.
* [voyager/database](database.md) - query builder, Instrument, migrations (landed).
* [voyager/encryption](encryption.md) - encrypter.
* [voyager/events](events.md) - event dispatcher.
* [voyager/filesystem](filesystem.md) - Flysystem manager.
* [voyager/hashing](hashing.md) - hash manager.
* [voyager/http](http.md) - HTTP client only.
* [voyager/json-schema](json-schema.md) - JSON Schema types.
* [voyager/log](log.md) - log manager and context.
* [voyager/notifications](notifications.md) - notifications; mail cut; default channel database.
* [voyager/pagination](pagination.md) - paginators; no provider.
* [voyager/pipeline](pipeline.md) - pipeline.
* [voyager/process](process.md) - process factory; no provider.
* [voyager/queue](queue.md) - queue; Beanstalkd/SQS/DynamoDB cut.
* [voyager/redis](redis.md) - Redis connections.
* [voyager/testing](testing.md) - test fakes and concerns.
* [voyager/translation](translation.md) - translator.
* [voyager/validation](validation.md) - validator; Can / HTTP exception / precognition cut.

# Related

* [Monorepo with composer replace](../architecture/package-split.md)
* [Known gaps](../known-gaps.md)
* [Overview](../overview.md)
