<?php

namespace Voyager\Database\Instrument;

interface Scope
{
    /**
     * Apply the scope to a given Instrument query builder.
     *
     * @template TModel of \Voyager\Database\Instrument\Model
     *
     * @param  \Voyager\Database\Instrument\Builder<TModel>  $builder
     * @param  TModel  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model);
}
