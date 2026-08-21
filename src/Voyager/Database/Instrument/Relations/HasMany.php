<?php

namespace Voyager\Database\Instrument\Relations;

use Voyager\Database\Instrument\Collection as InstrumentCollection;

/**
 * @template TRelatedModel of \Voyager\Database\Instrument\Model
 * @template TDeclaringModel of \Voyager\Database\Instrument\Model
 *
 * @extends \Voyager\Database\Instrument\Relations\HasOneOrMany<TRelatedModel, TDeclaringModel, \Voyager\Database\Instrument\Collection<int, TRelatedModel>>
 */
class HasMany extends HasOneOrMany
{
    /**
     * Convert the relationship to a "has one" relationship.
     *
     * @return \Voyager\Database\Instrument\Relations\HasOne<TRelatedModel, TDeclaringModel>
     */
    public function one()
    {
        return HasOne::noConstraints(fn () => tap(
            new HasOne(
                $this->getQuery(),
                $this->parent,
                $this->foreignKey,
                $this->localKey
            ),
            function ($hasOne) {
                if ($inverse = $this->getInverseRelationship()) {
                    $hasOne->inverse($inverse);
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
}
