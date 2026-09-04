<?php

namespace Voyager\Queue;

use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Builder;
use Voyager\Contracts\Database\ModelIdentifier;
use Voyager\Contracts\Queue\QueueableCollection;
use Voyager\Contracts\Queue\QueueableEntity;
use Voyager\Database\Instrument\Collection as Instrument;
use Voyager\Database\Instrument\Relations\Concerns\AsPivot;
use Voyager\Database\Instrument\Relations\Pivot;
use Voyager\NutsAndBolts\Collection;

trait SerializesAndRestoresModelIdentifiers
{
    /**
     * Get the property value prepared for serialization.
     *
     * @param  mixed  $value
     * @param  bool  $withRelations
     * @return mixed
     */
    protected function getSerializedPropertyValue($value, $withRelations = true)
    {
        if ($value instanceof QueueableCollection) {
            return (new ModelIdentifier(
                $value->getQueueableClass(),
                $value->getQueueableIds(),
                $withRelations ? $value->getQueueableRelations() : [],
                $value->getQueueableConnection()
            ))->useCollectionClass(
                ($collectionClass = get_class($value)) !== Instrument::class
                    ? $collectionClass
                    : null
            );
        }

        if ($value instanceof QueueableEntity) {
            return new ModelIdentifier(
                get_class($value),
                $value->getQueueableId(),
                $withRelations ? $value->getQueueableRelations() : [],
                $value->getQueueableConnection()
            );
        }

        return $value;
    }

    /**
     * Get the restored property value after deserialization.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function getRestoredPropertyValue($value)
    {
        if (! $value instanceof ModelIdentifier) {
            return $value;
        }

        return is_array($value->id)
            ? $this->restoreCollection($value)
            : $this->restoreModel($value);
    }

    /**
     * Restore a queueable collection instance.
     *
     * @param  ModelIdentifier  $value
     * @return Collection
     */
    protected function restoreCollection($value)
    {
        $class = $value->getClass();

        if (! $class || count($value->id) === 0) {
            return ! is_null($value->collectionClass ?? null)
                ? new $value->collectionClass
                : new Instrument;
        }

        $collection = $this->getQueryForModelRestoration(
            (new $class)->setConnection($value->connection), $value->id
        )->useWritePdo()->get();

        if (is_a($class, Pivot::class, true) ||
            in_array(AsPivot::class, class_uses($class))) {
            return $collection;
        }

        $collection = $collection->keyBy->getKey();

        $collectionClass = get_class($collection);

        return (new $collectionClass(
            (new Collection($value->id))
                ->map(fn ($id) => $collection[$id] ?? null)
                ->filter()
        ))->loadMissing($value->relations ?? []);
    }

    /**
     * Restore the model from the model identifier instance.
     *
     * @param  ModelIdentifier  $value
     * @return Model
     */
    public function restoreModel($value): Model
    {
        return $this->getQueryForModelRestoration(
            (new ($value->getClass()))->setConnection($value->connection), $value->id
        )->useWritePdo()->firstOrFail()->loadMissing($value->relations ?? []);
    }

    /**
     * Get the query for model restoration.
     *
     * @template TModel of Model
     *
     * @param TModel $model
     * @param array|int $ids
     * @return Builder
     */
    protected function getQueryForModelRestoration(Model $model, array|int $ids): Builder
    {
        return $model->newQueryForRestoration($ids);
    }
}
