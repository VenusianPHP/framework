<?php

namespace Tests\Vessel\Fixtures;

use Voyager\Contracts\Vessel\SelfBuilding;

class RequestDto implements SelfBuilding
{
    public function __construct(
        public readonly int $userId,
        public readonly string $email,
    ) {
    }

    public static function newInstance(RequestDtoDependencyContract $dependency): self
    {
        return new self(
            $dependency->userId,
            $_SERVER['__withFactory.email'],
        );
    }
}
