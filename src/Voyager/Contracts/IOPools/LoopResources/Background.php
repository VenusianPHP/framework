<?php

namespace Voyager\Contracts\IOPools\LoopResources;

use Voyager\Contracts\IOPools\LoopResource;

/**
 * A resource that serves the loop while it runs but never keeps it running, the way libuv's
 * unref'd handles do. A signal watcher is one: waiting for Ctrl-C is no reason not to exit.
 */
interface Background extends LoopResource
{

}