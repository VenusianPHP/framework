<?php

namespace Voyager\Contracts\IOPools;

/** Thrown into a fiber at its suspend point: by cancel(), by stop(), or because the loop ran out of work. */
class CancelledException extends EventLoopException
{

}
