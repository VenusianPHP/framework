# Cut upstream tests

Four of Laravel's six Broadcasting test files are not here.

| Upstream file | Why |
|---|---|
| `AblyBroadcasterTest` | Ably is dropped by the driver policy; Pusher is the supported hosted broadcaster. |
| `BroadcasterTest` | All 21 tests drive channel authorization and route-model binding. |
| `PusherBroadcasterTest` | All 9 tests drive `auth()`. |
| `RedisBroadcasterTest` | All 8 tests drive `auth()`. |

Authenticating an incoming request for a channel is receiving-HTTP, so
`auth()` and `validAuthenticationResponse()` are cut from the `Broadcaster`
contract and from every broadcaster, along with `BroadcastController` and the
`routes()`/`userRoutes()`/`channelRoutes()`/`socket()` methods on
`BroadcastManager`. These tests cover only that surface, so they are cut with
it rather than deferred — nothing later in the plan brings them back.

What remains — `BroadcastEventTest` and `UsePusherChannelsNamesTest` — covers
the send path, which is what this port keeps.
