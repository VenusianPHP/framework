<?php

namespace Voyager\Database\Instrument\Concerns;

use Voyager\Database\Instrument\ModelNotFoundException;

trait HasUniqueStringIds
{
    /**
     * Generate a new unique key for the model.
     *
     * @return mixed
     */
    abstract public function newUniqueId();

    /**
     * Determine if given key is valid.
     *
     * @param  mixed  $value
     * @return bool
     */
    abstract protected function isValidUniqueId($value): bool;

    /**
     * Initialize the trait.
     *
     * @return void
     */
    public function initializeHasUniqueStringIds()
    {
        $this->usesUniqueIds = true;
    }

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array
     */
    public function uniqueIds()
    {
        return $this->usesUniqueIds() ? [$this->getKeyName()] : parent::uniqueIds();
    }


    // The route-binding override goes with the block cut from Model.


    /**
     * Get the auto-incrementing key type.
     *
     * @return string
     */
    public function getKeyType()
    {
        if (in_array($this->getKeyName(), $this->uniqueIds())) {
            return 'string';
        }

        return parent::getKeyType();
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        if (in_array($this->getKeyName(), $this->uniqueIds())) {
            return false;
        }

        return parent::getIncrementing();
    }

    /**
     * Throw an exception for the given invalid unique ID.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return never
     *
     * @throws \Voyager\Database\Instrument\ModelNotFoundException
     */
    protected function handleInvalidUniqueId($value, $field)
    {
        throw (new ModelNotFoundException)->setModel(get_class($this), $value);
    }
}
