---
type: PHP Package
title: voyager/notifications
description: Notification sender and channels. Mail channel and MailMessage are cut; default channel is database.
resource: ../../src/Voyager/Notifications
tags: [php, package, voyager, notifications]
status: draft
generated: { by: agent:cursor, at: 2026-08-22T21:30:00Z }
verified: { by: agent:framework-auditor, at: 2026-08-21T22:10:00Z }
verification_key: 'agent:framework-auditor@8a8600fda67358ec3b38b579f9f13e5107bdc758'
stale_after: 2026-11-21
sources:
  - id: package-source
    resource: ../../src/Voyager/Notifications
    title: Notifications package source (21 PHP files)
  - id: package-manifest
    resource: ../../src/Voyager/Notifications/composer.json
    title: voyager/notifications composer.json
---

# Overview

21 PHP files under `src/Voyager/Notifications/`. Upstream `v12.67.0`.
`NotificationServiceProvider` is in `DefaultProviders`. Channels on disk:
Broadcast, Database. `ChannelManager::$defaultChannel = 'database'`.

No `MailChannel`, `MailMessage`, `SimpleMessage`, or Blade view. Active
tests are Pest v4.
`tests/Notifications/deferred/NotificationSendQueuedNotificationTest.php`
is Pest and still excluded.

# Related

- [Known gaps](/known-gaps.md)
- [voyager/queue](queue.md)

[^package-source]: Notifications package source (21 PHP files)
[^package-manifest]: voyager/notifications composer.json
