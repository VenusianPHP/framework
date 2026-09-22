<?php

namespace Voyager\Contracts\Signals;

interface NamedSignal extends Signal
{
    public function name(): string;
}