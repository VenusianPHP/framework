<?php

namespace Venusian\Tests\Database\Fixtures\Models\Money;

use Voyager\Database\Instrument\Factories\HasFactory;
use Voyager\Database\Instrument\Model;
use Venusian\Tests\Database\Fixtures\Factories\Money\PriceFactory;

class Price extends Model
{
    /** @use HasFactory<PriceFactory> */
    use HasFactory;

    protected $table = 'prices';

    protected static string $factory = PriceFactory::class;
}
