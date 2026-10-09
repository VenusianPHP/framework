<?php

namespace Venusian\Tests\Core\Fixtures;

final class AboutRobotArm
{
    /** @return array<string, string> */
    public function __invoke(): array
    {
        return ['Controller' => 'pca9685'];
    }
}
