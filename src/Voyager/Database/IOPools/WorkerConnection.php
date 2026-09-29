<?php

declare(strict_types=1);

namespace Voyager\Database\IOPools;

use Voyager\Database\Connection;
use Voyager\Vessel\ControlPanel;

/**
 * The worker side of every database gig: the named connection, under the config the caller's
 * connection runs on, so a database changed at runtime (or built on demand) is the one the
 * worker queries. The connection is reopened only when that config changed.
 */
final class WorkerConnection
{
    /**
     * @param array<string, mixed> $config the caller's connection config
     */
    public static function resolve(string $name, array $config): Connection
    {
        $app = ControlPanel::getInstance();
        $db = $app->get('db');

        if ($app['config']->get("database.connections.{$name}") !== $config) {
            $app['config']->set("database.connections.{$name}", $config);
            $db->purge($name);
        }

        return $db->connection($name);
    }
}
