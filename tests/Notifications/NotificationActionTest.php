<?php

namespace Tests\Notifications;

use Voyager\Notifications\Action;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class NotificationActionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testActionIsCreatedProperly()
    {
        $action = new Action('Text', 'url');

        $this->assertSame('Text', $action->text);
        $this->assertSame('url', $action->url);
    }
}
