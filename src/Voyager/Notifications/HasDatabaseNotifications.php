<?php

namespace Voyager\Notifications;

trait HasDatabaseNotifications
{
    /**
     * Get the entity's notifications.
     *
     * @return \Voyager\Database\Instrument\Relations\MorphMany<DatabaseNotification, $this>
     */
    public function notifications()
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')->latest();
    }

    /**
     * Get the entity's read notifications.
     *
     * @return \Voyager\Database\Instrument\Relations\MorphMany<DatabaseNotification, $this>
     */
    public function readNotifications()
    {
        return $this->notifications()->read();
    }

    /**
     * Get the entity's unread notifications.
     *
     * @return \Voyager\Database\Instrument\Relations\MorphMany<DatabaseNotification, $this>
     */
    public function unreadNotifications()
    {
        return $this->notifications()->unread();
    }
}
