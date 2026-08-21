<?php

namespace Voyager\Queue\Concerns;

use Voyager\Contracts\Database\ModelIdentifier;
use Voyager\Contracts\Queue\QueueableCollection;
use Voyager\Contracts\Queue\QueueableEntity;
use Voyager\Database\Instrument\Collection as InstrumentCollection;
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
    protected function getSerializedPropertyValue(mixed $value, bool $withRelations = true): mixed
    {
        if ($value instanceof QueueableCollection) {
            return (new ModelIdentifier(
                $value->getQueueableClass(),
                $value->getQueueableIds(),
                $withRelations ? $value->getQueueableRelations() : [],
                $value->getQueueableConnection()
            ))->useCollectionClass(
                ($collectionClass = get_class($value)) !== InstrumentCollection::class
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
    protected function getRestoredPropertyValue(mixed $value): mixed
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
     * @param  \Voyager\Contracts\Database\ModelIdentifier  $value
     * @return \Voyager\Database\Instrument\Collection
     */
    protected function restoreCollection(ModelIdentifier $value)
    {
        $class = $value->getClass();

        if (! $class || count($value->id) === 0) {
            return ! is_null($value->collectionClass ?? null)
                ? new $value->collectionClass
                : new InstrumentCollection;
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

        return new $collectionClass(
            (new Collection($value->id))
                ->map(fn ($id) => $collection[$id] ?? null)
                ->filter()
        );
    }

    /**
     * Restore the model from the model identifier instance.
     *
     * @param  \Voyager\Contracts\Database\ModelIdentifier  $value
     * @return \Voyager\Database\Instrument\Model
     */
    public function restoreModel(ModelIdentifier $value)
    {
        return $this->getQueryForModelRestoration(
            (new ($value->getClass()))->setConnection($value->connection), $value->id
        )->useWritePdo()->firstOrFail()->loadMissing($value->relations ?? []);
    }

    /**
     * Get the query for model restoration.
     *
     * @template TModel of \Voyager\Database\Instrument\Model
     *
     * @param  TModel  $model
     * @param  array|int  $ids
     * @return \Voyager\Database\Instrument\Builder<TModel>
     */
    protected function getQueryForModelRestoration($model, mixed $ids)
    {
        return $model->newQueryForRestoration($ids);
    }
}
