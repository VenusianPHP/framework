<?php

namespace Venusian\Tests\Database\Fixtures;

use Voyager\Database\Seeder;

/** Seeds one widget, for the migrate:fresh --seed and testing-trait cases. */
class WidgetSeeder extends Seeder
{
    public function run(): void
    {
        Widget::create(['name' => 'seeded']);
    }
}
