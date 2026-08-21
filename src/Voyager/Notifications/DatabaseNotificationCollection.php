<?php

namespace Voyager\Notifications;

use Voyager\Database\Instrument\Collection as InstrumentCollection;

/**
 * @template TKey of array-key
 * @template TModel of DatabaseNotification
 *
 * @extends \Voyager\Database\Instrument\Collection<TKey, TModel>
 */
class DatabaseNotificationCollection extends InstrumentCollection
{
    /**
     * Mark all notifications as read.
     *
     * @return void
     */
    public function markAsRead()
    {
        $this->each->markAsRead();
    }

    /**
     * Mark all notifications as unread.
     *
     * @return void
     */
    public function markAsUnread()
    {
        $this->each->markAsUnread();
    }
}
