<?php

namespace Voyager\Database\Capsule;

use Voyager\Vessel\Vessel;
use Voyager\Contracts\Events\Dispatcher;
use Voyager\Database\Connectors\ConnectionFactory;
use Voyager\Database\DatabaseManager;
use Voyager\Database\Instrument\Model as Instrument;
use Voyager\NutsAndBolts\Concerns\CapsuleManagerTrait;
use PDO;

class Manager
{
    use CapsuleManagerTrait;

    /**
     * The database manager instance.
     *
     * @var \Voyager\Database\DatabaseManager
     */
    protected $manager;

    /**
     * Create a new database capsule manager.
     *
     * @param  \Voyager\Vessel\Vessel|null  $container
     */
    public function __construct(?Vessel $container = null)
    {
        $this->setupContainer($container ?: new Vessel);

        // Once we have the container setup, we will setup the default configuration
        // options in the container "config" binding. This will make the database
        // manager work correctly out of the box without extreme configuration.
        $this->setupDefaultConfiguration();

        $this->setupManager();
    }

    /**
     * Setup the default database configuration options.
     *
     * @return void
     */
    protected function setupDefaultConfiguration()
    {
        $this->vessel['config']['database.fetch'] = PDO::FETCH_OBJ;

        $this->vessel['config']['database.default'] = 'default';
    }

    /**
     * Build the database manager instance.
     *
     * @return void
     */
    protected function setupManager()
    {
        $factory = new ConnectionFactory($this->vessel);

        $this->manager = new DatabaseManager($this->vessel, $factory);
    }

    /**
     * Get a connection instance from the global manager.
     *
     * @param  string|null  $connection
     * @return \Voyager\Database\Connection
     */
    public static function connection($connection = null)
    {
        return static::$instance->getConnection($connection);
    }

    /**
     * Get a fluent query builder instance.
     *
     * @param  \Closure|\Voyager\Database\Query\Builder|string  $table
     * @param  string|null  $as
     * @param  string|null  $connection
     * @return \Voyager\Database\Query\Builder
     */
    public static function table($table, $as = null, $connection = null)
    {
        return static::$instance->connection($connection)->table($table, $as);
    }

    /**
     * Get a schema builder instance.
     *
     * @param  string|null  $connection
     * @return \Voyager\Database\Schema\Builder
     */
    public static function schema($connection = null)
    {
        return static::$instance->connection($connection)->getSchemaBuilder();
    }

    /**
     * Get a registered connection instance.
     *
     * @param  string|null  $name
     * @return \Voyager\Database\Connection
     */
    public function getConnection($name = null)
    {
        return $this->manager->connection($name);
    }

    /**
     * Register a connection with the manager.
     *
     * @param  array  $config
     * @param  string  $name
     * @return void
     */
    public function addConnection(array $config, $name = 'default')
    {
        $connections = $this->vessel['config']['database.connections'];

        $connections[$name] = $config;

        $this->vessel['config']['database.connections'] = $connections;
    }

    /**
     * Bootstrap Instrument so it is ready for usage.
     *
     * @return void
     */
    public function bootInstrument()
    {
        Instrument::setConnectionResolver($this->manager);

        // If we have an event dispatcher instance, we will go ahead and register it
        // with the Instrument ORM, allowing for model callbacks while creating and
        // updating "model" instances; however, it is not necessary to operate.
        if ($dispatcher = $this->getEventDispatcher()) {
            Instrument::setEventDispatcher($dispatcher);
        }
    }

    /**
     * Set the fetch mode for the database connections.
     *
     * @param  int  $fetchMode
     * @return $this
     */
    public function setFetchMode($fetchMode)
    {
        $this->vessel['config']['database.fetch'] = $fetchMode;

        return $this;
    }

    /**
     * Get the database manager instance.
     *
     * @return \Voyager\Database\DatabaseManager
     */
    public function getDatabaseManager()
    {
        return $this->manager;
    }

    /**
     * Get the current event dispatcher instance.
     *
     * @return \Voyager\Contracts\Events\Dispatcher|null
     */
    public function getEventDispatcher()
    {
        if ($this->vessel->bound('events')) {
            return $this->vessel['events'];
        }
    }

    /**
     * Set the event dispatcher instance to be used by connections.
     *
     * @param  \Voyager\Contracts\Events\Dispatcher  $dispatcher
     * @return void
     */
    public function setEventDispatcher(Dispatcher $dispatcher)
    {
        $this->vessel->instance('events', $dispatcher);
    }

    /**
     * Dynamically pass methods to the default connection.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return static::connection()->$method(...$parameters);
    }
}
