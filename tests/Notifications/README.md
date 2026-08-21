# Cut upstream tests

`NotificationMailMessageTest` and `NotificationMessageTest` are not here. Both
cover `Messages\MailMessage` and its `Messages\SimpleMessage` base, which are
cut along with the mail channel — Mail is out of scope for this port because
mailables render through Blade.

`ChannelManager`'s default channel is `database` rather than Laravel's `mail`
for the same reason.
