<?php

namespace Voyager\Pipeline;

use Closure;
use Voyager\Contracts\Vessel\Vessel;
use Voyager\Contracts\Pipeline\Hub as HubContract;

class Hub implements HubContract
{
    /**
     * The container implementation.
     *
     * @var \Voyager\Contracts\Vessel\Vessel|null
     */
    protected ?Vessel $vessel = null;

    /**
     * Every available pipeline.
     *
     * @var array
     */
    protected array $pipelines = [];

    /**
     * Create a new Hub instance.
     *
     * @param  \Voyager\Contracts\Vessel\Vessel|null  $vessel
     */
    public function __construct(?Vessel $vessel = null)
    {
        $this->vessel = $vessel;
    }

    /**
     * Define the default named pipeline.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function defaults(Closure $callback): void
    {
        $this->pipeline('default', $callback);
    }

    /**
     * Define a new named pipeline.
     *
     * @param  string  $name
     * @param  \Closure  $callback
     * @return void
     */
    public function pipeline($name, Closure $callback): void
    {
        $this->pipelines[$name] = $callback;
    }

    /**
     * Send an object through one of the available pipelines.
     *
     * @param  mixed  $object
     * @param  string|null  $pipeline
     * @return mixed
     */
    public function pipe($object, $pipeline = null): mixed
    {
        $pipeline = $pipeline ?: 'default';

        return call_user_func(
            $this->pipelines[$pipeline], new Pipeline($this->vessel), $object
        );
    }

    /**
     * Get the container instance used by the hub.
     *
     * @return \Voyager\Contracts\Vessel\Vessel
     */
    public function getContainer(): ?Vessel
    {
        return $this->vessel;
    }

    /**
     * Set the container instance used by the hub.
     *
     * @param  \Voyager\Contracts\Vessel\Vessel  $vessel
     * @return $this
     */
    public function setContainer(Vessel $vessel): static
    {
        $this->vessel = $vessel;

        return $this;
    }
}
