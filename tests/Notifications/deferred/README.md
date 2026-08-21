# Deferred upstream tests

`NotificationSendQueuedNotificationTest` serializes a notifiable that extends
`Instrument\Model`, so it needs Database — wave 6.

`SendQueuedNotifications` itself is ported and works for any notifiable;
its model-identifier path is the part that waits.
