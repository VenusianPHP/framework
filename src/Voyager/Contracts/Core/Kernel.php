<?php

namespace Voyager\Contracts\Core;

interface Kernel
{
    public function bootstrap(): void;

    public function handle(array $argv): int;

    public function terminate(array $argv, int $status): void;
}