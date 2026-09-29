<?php

namespace Voyager\Contracts\Database\Query;

use Voyager\Database\Grammar;

interface Expression
{
    /**
     * Get the value of the expression.
     *
     * @param  \Voyager\Database\Grammar  $grammar
     * @return string|int|float
     */
    public function getValue(Grammar $grammar);
}
