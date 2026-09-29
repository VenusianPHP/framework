<?php

namespace Venusian\Tests\Graph\Fixtures;

use Voyager\Graph\Instrument\Model;

/**
 * A node under the label the test names. The label rides on the instance, so a worker's copy,
 * and every instance it makes from that copy, keeps it.
 */
class Person extends Model
{
    public static string $label = '';

    protected $guarded = [];

    protected $casts = ['age' => 'integer'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (static::$label !== '') {
            $this->setTable(static::$label);
        }
    }
}
