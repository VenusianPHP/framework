<?php

namespace Tests\Database\Fixtures\Factories\Money;

use Voyager\Database\Instrument\Factories\Factory;

class PriceFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => $this->faker->name(),
        ];
    }
}
