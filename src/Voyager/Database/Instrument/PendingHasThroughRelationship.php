<?php

namespace Voyager\Database\Instrument;

use BadMethodCallException;
use Voyager\Database\Instrument\Relations\HasMany;
use Voyager\Database\Instrument\Relations\MorphOneOrMany;
use Voyager\NutsAndBolts\DataObjects\Str;
use Voyager\NutsAndBolts\DataObjects\Stringable;

/**
 * @template TIntermediateModel of \Voyager\Database\Instrument\Model
 * @template TDeclaringModel of \Voyager\Database\Instrument\Model
 * @template TLocalRelationship of \Voyager\Database\Instrument\Relations\HasOneOrMany<TIntermediateModel, TDeclaringModel>
 */
class PendingHasThroughRelationship
{
    /**
     * The root model that the relationship exists on.
     *
     * @var TDeclaringModel
     */
    protected $rootModel;

    /**
     * The local relationship.
     *
     * @var TLocalRelationship
     */
    protected $localRelationship;

    /**
     * Create a pending has-many-through or has-one-through relationship.
     *
     * @param  TDeclaringModel  $rootModel
     * @param  TLocalRelationship  $localRelationship
     */
    public function __construct($rootModel, $localRelationship)
    {
        $this->rootModel = $rootModel;

        $this->localRelationship = $localRelationship;
    }

    /**
     * Define the distant relationship that this model has.
     *
     * @template TRelatedModel of \Voyager\Database\Instrument\Model
     *
     * @param  string|(callable(TIntermediateModel): (\Voyager\Database\Instrument\Relations\HasOne<TRelatedModel, TIntermediateModel>|\Voyager\Database\Instrument\Relations\HasMany<TRelatedModel, TIntermediateModel>|\Voyager\Database\Instrument\Relations\MorphOneOrMany<TRelatedModel, TIntermediateModel>))  $callback
     * @return (
     *     $callback is string
     *     ? \Voyager\Database\Instrument\Relations\HasManyThrough<\Voyager\Database\Instrument\Model, TIntermediateModel, TDeclaringModel>|\Voyager\Database\Instrument\Relations\HasOneThrough<\Voyager\Database\Instrument\Model, TIntermediateModel, TDeclaringModel>
     *     : (
     *         TLocalRelationship is \Voyager\Database\Instrument\Relations\HasMany<TIntermediateModel, TDeclaringModel>
     *         ? \Voyager\Database\Instrument\Relations\HasManyThrough<TRelatedModel, TIntermediateModel, TDeclaringModel>
     *         : (
     *              $callback is callable(TIntermediateModel): \Voyager\Database\Instrument\Relations\HasMany<TRelatedModel, TIntermediateModel>
     *              ? \Voyager\Database\Instrument\Relations\HasManyThrough<TRelatedModel, TIntermediateModel, TDeclaringModel>
     *              : \Voyager\Database\Instrument\Relations\HasOneThrough<TRelatedModel, TIntermediateModel, TDeclaringModel>
     *         )
     *     )
     * )
     */
    public function has($callback)
    {
        if (is_string($callback)) {
            $callback = fn () => $this->localRelationship->getRelated()->{$callback}();
        }

        $distantRelation = $callback($this->localRelationship->getRelated());

        if ($distantRelation instanceof HasMany || $this->localRelationship instanceof HasMany) {
            $returnedRelation = $this->rootModel->hasManyThrough(
                $distantRelation->getRelated()::class,
                $this->localRelationship->getRelated()::class,
                $this->localRelationship->getForeignKeyName(),
                $distantRelation->getForeignKeyName(),
                $this->localRelationship->getLocalKeyName(),
                $distantRelation->getLocalKeyName(),
            );
        } else {
            $returnedRelation = $this->rootModel->hasOneThrough(
                $distantRelation->getRelated()::class,
                $this->localRelationship->getRelated()::class,
                $this->localRelationship->getForeignKeyName(),
                $distantRelation->getForeignKeyName(),
                $this->localRelationship->getLocalKeyName(),
                $distantRelation->getLocalKeyName(),
            );
        }

        if ($this->localRelationship instanceof MorphOneOrMany) {
            $returnedRelation->where($this->localRelationship->getQualifiedMorphType(), $this->localRelationship->getMorphClass());
        }

        return $returnedRelation;
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (Str::startsWith($method, 'has')) {
            return $this->has((new Stringable($method))->after('has')->lcfirst()->toString());
        }

        throw new BadMethodCallException(sprintf(
            'Call to undefined method %s::%s()', static::class, $method
        ));
    }
}
