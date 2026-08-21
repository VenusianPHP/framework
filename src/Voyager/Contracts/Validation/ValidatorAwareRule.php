<?php

namespace Voyager\Contracts\Validation;

use Voyager\Validation\Validator;

interface ValidatorAwareRule
{
    /**
     * Set the current validator.
     *
     * @param  \Voyager\Validation\Validator  $validator
     * @return $this
     */
    public function setValidator(Validator $validator);
}
