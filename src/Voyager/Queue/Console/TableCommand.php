<?php

namespace Voyager\Queue\Console;

use Voyager\Console\MigrationGeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;

use function Voyager\Filesystem\join_paths;

#[AsCommand(name: 'make:queue-table', aliases: ['queue:table'])]
class TableCommand extends MigrationGeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected ?string $name = 'make:queue-table';

    /**
     * The console command name aliases.
     *
     * @var array
     */
    protected ?array $aliases = ['queue:table'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a migration for the queue jobs database table';

    /**
     * Get the migration table name.
     *
     * @return string
     */
    protected function migrationTableName(): string
    {
        return $this->venusian['config']['queue.connections.database.table'];
    }

    /**
     * Get the path to the migration stub file.
     *
     * @return string
     */
    protected function migrationStubFile(): string
    {
        return __DIR__.'/stubs/jobs.stub';
    }

    /**
     * Determine whether a migration for the table already exists.
     *
     * @param  string  $table
     * @return bool
     */
    protected function migrationExists($table): bool
    {
        if ($table !== 'jobs') {
            return parent::migrationExists($table);
        }

        foreach ([
            join_paths($this->venusian->databasePath('migrations'), '*_*_*_*_create_'.$table.'_table.php'),
            join_paths($this->venusian->databasePath('migrations'), '0001_01_01_000002_create_jobs_table.php'),
        ] as $path) {
            if (count($this->files->glob($path)) !== 0) {
                return true;
            }
        }

        return false;
    }
}
