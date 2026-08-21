<?php

namespace Voyager\Database\Instrument;

use Voyager\Database\RecordsNotFoundException;
use Voyager\NutsAndBolts\DataObjects\Arr;

use function Voyager\NutsAndBolts\Helpers\enum_value;

/**
 * @template TModel of \Voyager\Database\Instrument\Model
 */
class ModelNotFoundException extends RecordsNotFoundException
{
    /**
     * Name of the affected Instrument model.
     *
     * @var class-string<TModel>
     */
    protected $model;

    /**
     * The affected model IDs.
     *
     * @var array<int, int|string>
     */
    protected $ids;

    /**
     * Set the affected Instrument model and instance ids.
     *
     * @param  class-string<TModel>  $model
     * @param  array<int, int|string>|int|string  $ids
     * @return $this
     */
    public function setModel($model, $ids = [])
    {
        $this->model = $model;

        $this->ids = array_map(enum_value(...), Arr::wrap($ids));

        $this->message = "No query results for model [{$model}]";

        if (count($this->ids) > 0) {
            $this->message .= ' '.implode(', ', $this->ids);
        } else {
            $this->message .= '.';
        }

        return $this;
    }

    /**
     * Get the affected Instrument model.
     *
     * @return class-string<TModel>
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Get the affected Instrument model IDs.
     *
     * @return array<int, int|string>
     */
    public function getIds()
    {
        return $this->ids;
    }
}
