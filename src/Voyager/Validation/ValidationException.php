<?php

namespace Voyager\Validation;

use Exception;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\NutsAndBolts\MagicAliases\Validator as ValidatorFacade;

class ValidationException extends Exception
{
    /**
     * The validator instance.
     *
     * @var \Voyager\Contracts\Validation\Validator
     */
    public $validator;

    /**
     * The name of the error bag.
     *
     * @var string
     */
    public $errorBag;

    /**
     * Create a new exception instance.
     *
     * @param  \Voyager\Contracts\Validation\Validator  $validator
     * @param  string  $errorBag
     */
    public function __construct($validator, $errorBag = 'default')
    {
        parent::__construct(static::summarize($validator));

        $this->errorBag = $errorBag;
        $this->validator = $validator;
    }

    /**
     * Create a new validation exception from a plain array of messages.
     *
     * @param  array  $messages
     * @return static
     */
    public static function withMessages(array $messages)
    {
        return new static(tap(ValidatorFacade::make([], []), function ($validator) use ($messages) {
            foreach ($messages as $key => $value) {
                foreach (Arr::wrap($value) as $message) {
                    $validator->errors()->add($key, $message);
                }
            }
        }));
    }

    /**
     * Create an error message summary from the validation errors.
     *
     * @param  \Voyager\Contracts\Validation\Validator  $validator
     * @return string
     */
    protected static function summarize($validator)
    {
        $messages = $validator->errors()->all();

        if (! count($messages) || ! is_string($messages[0])) {
            return $validator->getTranslator()->get('The given data was invalid.');
        }

        $message = array_shift($messages);

        if ($count = count($messages)) {
            $pluralized = $count === 1 ? 'error' : 'errors';

            $message .= ' '.$validator->getTranslator()->choice("(and :count more $pluralized)", $count, compact('count'));
        }

        return $message;
    }

    /**
     * Get all of the validation error messages.
     *
     * @return array
     */
    public function errors()
    {
        return $this->validator->errors()->messages();
    }

    /**
     * Set the error bag on the exception.
     *
     * @param  string  $errorBag
     * @return $this
     */
    public function errorBag($errorBag)
    {
        $this->errorBag = $errorBag;

        return $this;
    }
}
