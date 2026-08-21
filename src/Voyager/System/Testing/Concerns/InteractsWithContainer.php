<?php

namespace Voyager\System\Testing\Concerns;

use Closure;
use Voyager\NutsAndBolts\Defer\DeferredCallbackCollection;
use Voyager\NutsAndBolts\HtmlString;
use Mockery;

trait InteractsWithContainer
{


    /**
     * The original deferred callbacks collection.
     *
     * @var \Voyager\NutsAndBolts\Defer\DeferredCallbackCollection|null
     */
    protected $originalDeferredCallbacksCollection;

    /**
     * Register an instance of an object in the container.
     *
     * @template TSwap of object
     *
     * @param  string  $abstract
     * @param  TSwap  $instance
     * @return TSwap
     */
    protected function swap($abstract, $instance)
    {
        return $this->instance($abstract, $instance);
    }

    /**
     * Register an instance of an object in the container.
     *
     * @template TInstance of object
     *
     * @param  string  $abstract
     * @param  TInstance  $instance
     * @return TInstance
     */
    protected function instance($abstract, $instance)
    {
        $this->app->instance($abstract, $instance);

        return $instance;
    }

    /**
     * Mock an instance of an object in the container.
     *
     * @template TInstance of object
     *
     * @param  string|class-string<TInstance>  $abstract
     * @param  \Closure|null  $mock
     * @return ($abstract is class-string<TInstance> ? TInstance&\Mockery\MockInterface : \Mockery\MockInterface)
     */
    protected function mock($abstract, ?Closure $mock = null)
    {
        return $this->instance($abstract, Mockery::mock(...array_filter(func_get_args())));
    }

    /**
     * Mock a partial instance of an object in the container.
     *
     * @template TInstance of object
     *
     * @param  string|class-string<TInstance>  $abstract
     * @param  \Closure|null  $mock
     * @return ($abstract is class-string<TInstance> ? TInstance&\Mockery\MockInterface : \Mockery\MockInterface)
     */
    protected function partialMock($abstract, ?Closure $mock = null)
    {
        return $this->instance($abstract, Mockery::mock(...array_filter(func_get_args()))->makePartial());
    }

    /**
     * Spy an instance of an object in the container.
     *
     * @template TInstance of object
     *
     * @param  string|class-string<TInstance>  $abstract
     * @param  \Closure|null  $mock
     * @return ($abstract is class-string<TInstance> ? TInstance&\Mockery\MockInterface : \Mockery\MockInterface)
     */
    protected function spy($abstract, ?Closure $mock = null)
    {
        return $this->instance($abstract, Mockery::spy(...array_filter(func_get_args())));
    }

    /**
     * Instruct the container to forget a previously mocked / spied instance of an object.
     *
     * @param  string  $abstract
     * @return $this
     */
    protected function forgetMock($abstract)
    {
        $this->app->forgetInstance($abstract);

        return $this;
    }

    /**
     * Execute deferred functions immediately.
     *
     * @return $this
     */
    protected function withoutDefer()
    {
        if ($this->originalDeferredCallbacksCollection == null) {
            $this->originalDeferredCallbacksCollection = $this->app->make(DeferredCallbackCollection::class);
        }

        $this->swap(DeferredCallbackCollection::class, new class extends DeferredCallbackCollection
        {
            public function offsetSet(mixed $offset, mixed $value): void
            {
                $value();
            }
        });

        return $this;
    }

    /**
     * Restore deferred functions.
     *
     * @return $this
     */
    protected function withDefer()
    {
        if ($this->originalDeferredCallbacksCollection) {
            $this->app->instance(DeferredCallbackCollection::class, $this->originalDeferredCallbacksCollection);
        }

        return $this;
    }
}
