<?php

namespace Voyager\Contracts\Workflows;

/**
 * Marks a graph member that must be executed by an AsyncFlow.
 *
 * A synchronous Flow refuses to orchestrate these.
 */
interface AsyncRunnable extends RuntimeAware
{

}
