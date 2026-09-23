# IOPools

* [Event loop](event-loop.md) - The loop that waits on timers, streams, and tickables, and how `until()` borrows it.
* [Worker pools](worker-pools.md) - One `submit(): Promise` API with a process driver and a ZTS thread driver.
* [Work targets](work-targets.md) - `run(ShouldPool): Promise` with five homes; `via()` sends a disk call to one of them.
* [Async](async.md) - `$loop->async()` runs a body in a fiber so `wait()` suspends instead of borrowing.
* [Defer](defer.md) - `$loop->defer()` runs a closure on the next turn and settles a promise.
