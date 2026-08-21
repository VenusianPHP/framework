<?php

namespace Voyager\Database\Instrument\Relations;

use Voyager\Database\Instrument\Collection as InstrumentCollection;

/**
 * @template TRelatedModel of \Voyager\Database\Instrument\Model
 * @template TDeclaringModel of \Voyager\Database\Instrument\Model
 *
 * @extends \Voyager\Database\Instrument\Relations\MorphOneOrMany<TRelatedModel, TDeclaringModel, \Voyager\Database\Instrument\Collection<int, TRelatedModel>>
 */
class MorphMany extends MorphOneOrMany
{
    /**
     * Convert the relationship to a "morph one" relationship.
     *
     * @return \Voyager\Database\Instrument\Relations\MorphOne<TRelatedModel, TDeclaringModel>
     */
    public function one()
    {
        return MorphOne::noConstraints(fn () => tap(
            new MorphOne(
                $this->getQuery(),
                $this->getParent(),
                $this->morphType,
                $this->foreignKey,
                $this->localKey
            ),
            function ($morphOne) {
                if ($inverse = $this->getInverseRelationship()) {
                    $morphOne->inverse($inverse);
                }
            }
        ));
    }

    /** @inheritDoc */
    public function getResults()
    {
        return ! is_null($this->getParentKey())
            ? $this->query->get()
            : $this->related->newCollection();
    }

    /** @inheritDoc */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /** @inheritDoc */
    public function match(array $models, InstrumentCollection $results, $relation)
    {
        return $this->matchMany($models, $results, $relation);
    }

    /** @inheritDoc */
    public function forceCreate(array $attributes = [])
    {
        $attributes[$this->getMorphType()] = $this->morphClass;

        return parent::forceCreate($attributes);
    }
}
