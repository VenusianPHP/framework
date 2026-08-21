<?php

namespace Voyager\Database\Concerns;

use Voyager\NutsAndBolts\Collection;

trait ExplainsQueries
{
    /**
     * Explains the query.
     *
     * @return \Voyager\NutsAndBolts\Collection
     */
    public function explain()
    {
        $sql = $this->toSql();

        $bindings = $this->getBindings();

        $explanation = $this->getConnection()->select('EXPLAIN '.$sql, $bindings);

        return new Collection($explanation);
    }
}
