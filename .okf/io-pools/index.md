# IOPools

* [Event loop](event-loop.md) - One turn waits once, then fires wakes, deadlines, ticks, follow-ons and mail; `until()` borrows or suspends.
* [Resources and waiters](resources.md) - Resource kinds by interface, the one-sleeper succession, background resources, kqueue/epoll/select backends.
* [Loop mail](mail.md) - `post()` and pumpables collect mail; the `signal` or `sketch` mail handler receives it.
* [Worker pools](worker-pools.md) - `submit(ShouldPool)` on a named pool, process or thread; gigs cross serialized.
* [Async](async.md) - `$loop->async()` runs a body in a fiber so `wait()` suspends instead of borrowing.
* [Defer](defer.md) - `$loop->defer()` runs a closure on the next turn and settles a promise.
