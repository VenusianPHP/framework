<?php

namespace Voyager\Core\Bootstrap;

use ReflectionException;
use Voyager\Contracts\Core\FrameworkCore as Application;

/**
 * The database exists and is migrated before anything uses it.
 *
 * A build ships a fresh, migrated database that the first run copies to the user's data directory
 * (~/Library/Application Support/<app>/database). This covers what that copy does not: a local
 * run's missing database, and an upgrade, whose new migrations never reach a database copied by
 * an earlier build. With nothing pending it is one query against the migrations table.
 */
class ProvisionDatabase
{
    /**
     * @throws ReflectionException
     */
    public function bootstrap(Application $app): void
    {
        if(config('main.use_database', false))
        {
            $connection = config('database.default');
            $settings = config("database.connections.{$connection}", []);

            if (($settings['driver'] ?? null) === 'sqlite') {
                $file = $settings['database'] ?? '';
                if ($file !== '' && $file !== ':memory:' && ! is_file($file)) {
                    is_dir(dirname($file)) || mkdir(dirname($file), 0755, true);
                    touch($file);
                }
            }

            $migrator = $app->make('migrator');
            if (! $migrator->repositoryExists()) {
                $migrator->getRepository()->createRepository();
            }

            // The app's own migrations (inside the phar when built), not the data directory's copy.
            $migrator->run([$app->basePath('database/migrations')]);
        }

    }
}
