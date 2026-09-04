<?php

namespace Voyager\Contracts\IOPools;

/**
 * Unsolicited mail: something the world did without being asked — an OS
 * window event, a signal, a watchdog firing. Listen on this interface to
 * hear every occurrence regardless of source.
 */
interface Occurrence extends QueuedIO
{

}
