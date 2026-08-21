<?php

namespace Tests\Events\Fixtures;

class DeclarativeSubscriber
{
    public static $string = '';

    public function subscribe()
    {
        return [
            'myEvent1' => [
                self::class.'@listener1',
                self::class.'@listener2',
            ],
            'myEvent2' => [
                self::class.'@listener3',
            ],
        ];
    }

    public function listener1()
    {
        self::$string .= 'L1_';
    }

    public function listener2()
    {
        self::$string .= 'L2_';
    }

    public function listener3()
    {
        self::$string .= 'L3';
    }
}
