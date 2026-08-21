<?php

namespace Voyager\Validation;

use Voyager\Contracts\NutsAndBolts\Arrayable;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\NutsAndBolts\Concerns\Macroable;
use Voyager\Validation\Rules\AnyOf;
use Voyager\Validation\Rules\ArrayRule;
use Voyager\Validation\Rules\Date;
use Voyager\Validation\Rules\Dimensions;
use Voyager\Validation\Rules\Email;
use Voyager\Validation\Rules\Enum;
use Voyager\Validation\Rules\ExcludeIf;
use Voyager\Validation\Rules\ExcludeUnless;
use Voyager\Validation\Rules\Exists;
use Voyager\Validation\Rules\File;
use Voyager\Validation\Rules\ImageFile;
use Voyager\Validation\Rules\In;
use Voyager\Validation\Rules\NotIn;
use Voyager\Validation\Rules\Numeric;
use Voyager\Validation\Rules\ProhibitedIf;
use Voyager\Validation\Rules\ProhibitedUnless;
use Voyager\Validation\Rules\RequiredIf;
use Voyager\Validation\Rules\RequiredUnless;
use Voyager\Validation\Rules\StringRule;
use Voyager\Validation\Rules\Unique;

class Rule
{
    use Macroable;

    /**
     * Apply the given rules if the given condition is truthy.
     *
     * @param  callable|bool  $condition
     * @param  \Voyager\Contracts\Validation\ValidationRule|\Voyager\Contracts\Validation\InvokableRule|\Voyager\Contracts\Validation\Rule|\Closure|array|string  $rules
     * @param  \Voyager\Contracts\Validation\ValidationRule|\Voyager\Contracts\Validation\InvokableRule|\Voyager\Contracts\Validation\Rule|\Closure|array|string  $defaultRules
     * @return \Voyager\Validation\ConditionalRules
     */
    public static function when($condition, $rules, $defaultRules = [])
    {
        return new ConditionalRules($condition, $rules, $defaultRules);
    }

    /**
     * Apply the given rules if the given condition is falsy.
     *
     * @param  callable|bool  $condition
     * @param  \Voyager\Contracts\Validation\ValidationRule|\Voyager\Contracts\Validation\InvokableRule|\Voyager\Contracts\Validation\Rule|\Closure|array|string  $rules
     * @param  \Voyager\Contracts\Validation\ValidationRule|\Voyager\Contracts\Validation\InvokableRule|\Voyager\Contracts\Validation\Rule|\Closure|array|string  $defaultRules
     * @return \Voyager\Validation\ConditionalRules
     */
    public static function unless($condition, $rules, $defaultRules = [])
    {
        return new ConditionalRules($condition, $defaultRules, $rules);
    }

    /**
     * Get an array rule builder instance.
     *
     * @param  array|null  $keys
     * @return \Voyager\Validation\Rules\ArrayRule
     */
    public static function array($keys = null)
    {
        return new ArrayRule(...func_get_args());
    }

    /**
     * Create a new nested rule set.
     *
     * @param  callable  $callback
     * @return \Voyager\Validation\NestedRules
     */
    public static function forEach($callback)
    {
        return new NestedRules($callback);
    }

    /**
     * Get a unique constraint builder instance.
     *
     * @param  string  $table
     * @param  string  $column
     * @return \Voyager\Validation\Rules\Unique
     */
    public static function unique($table, $column = 'NULL')
    {
        return new Unique($table, $column);
    }

    /**
     * Get an exists constraint builder instance.
     *
     * @param  string  $table
     * @param  string  $column
     * @return \Voyager\Validation\Rules\Exists
     */
    public static function exists($table, $column = 'NULL')
    {
        return new Exists($table, $column);
    }

    /**
     * Get an in rule builder instance.
     *
     * @param  \Voyager\Contracts\NutsAndBolts\Arrayable|\UnitEnum|array|string  $values
     * @return \Voyager\Validation\Rules\In
     */
    public static function in($values)
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        return new In(is_array($values) ? $values : func_get_args());
    }

    /**
     * Get a not_in rule builder instance.
     *
     * @param  \Voyager\Contracts\NutsAndBolts\Arrayable|\UnitEnum|array|string  $values
     * @return \Voyager\Validation\Rules\NotIn
     */
    public static function notIn($values)
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        return new NotIn(is_array($values) ? $values : func_get_args());
    }

    /**
     * Get a required_if rule builder instance.
     *
     * @param  (\Closure(): bool)|bool  $callback
     * @return \Voyager\Validation\Rules\RequiredIf
     */
    public static function requiredIf($callback)
    {
        return new RequiredIf($callback);
    }

    /**
     * Get a required_unless rule builder instance.
     *
     * @param  (\Closure(): bool)|bool  $callback
     * @return \Voyager\Validation\Rules\RequiredUnless
     */
    public static function requiredUnless($callback)
    {
        return new RequiredUnless($callback);
    }

    /**
     * Get a exclude_if rule builder instance.
     *
     * @param  (\Closure(): bool)|bool  $callback
     * @return \Voyager\Validation\Rules\ExcludeIf
     */
    public static function excludeIf($callback)
    {
        return new ExcludeIf($callback);
    }

    /**
     * Get a exclude_unless rule builder instance.
     *
     * @param  (\Closure(): bool)|bool  $callback
     * @return \Voyager\Validation\Rules\ExcludeUnless
     */
    public static function excludeUnless($callback)
    {
        return new ExcludeUnless($callback);
    }

    /**
     * Get a prohibited_if rule builder instance.
     *
     * @param  (\Closure(): bool)|bool  $callback
     * @return \Voyager\Validation\Rules\ProhibitedIf
     */
    public static function prohibitedIf($callback)
    {
        return new ProhibitedIf($callback);
    }

    /**
     * Get a prohibited_unless rule builder instance.
     *
     * @param  (\Closure(): bool)|bool  $callback
     * @return \Voyager\Validation\Rules\ProhibitedUnless
     */
    public static function prohibitedUnless($callback)
    {
        return new ProhibitedUnless($callback);
    }

    /**
     * Get a date rule builder instance.
     *
     * @return \Voyager\Validation\Rules\Date
     */
    public static function date()
    {
        return new Date;
    }

    /**
     * Get a datetime rule builder instance.
     */
    public static function dateTime(): Date
    {
        return (new Date)->format('Y-m-d H:i:s');
    }

    /**
     * Get an email rule builder instance.
     *
     * @return \Voyager\Validation\Rules\Email
     */
    public static function email()
    {
        return new Email;
    }

    /**
     * Get an enum rule builder instance.
     *
     * @param  class-string  $type
     * @return \Voyager\Validation\Rules\Enum
     */
    public static function enum($type)
    {
        return new Enum($type);
    }

    /**
     * Get a file rule builder instance.
     *
     * @return \Voyager\Validation\Rules\File
     */
    public static function file()
    {
        return new File;
    }

    /**
     * Get an image file rule builder instance.
     *
     * @param  bool  $allowSvg
     * @return \Voyager\Validation\Rules\ImageFile
     */
    public static function imageFile($allowSvg = false)
    {
        return new ImageFile($allowSvg);
    }

    /**
     * Get a dimensions rule builder instance.
     *
     * @param  array  $constraints
     * @return \Voyager\Validation\Rules\Dimensions
     */
    public static function dimensions(array $constraints = [])
    {
        return new Dimensions($constraints);
    }

    /**
     * Get a string rule builder instance.
     *
     * @return \Voyager\Validation\Rules\StringRule
     */
    public static function string()
    {
        return new StringRule;
    }

    /**
     * Get a numeric rule builder instance.
     *
     * @return \Voyager\Validation\Rules\Numeric
     */
    public static function numeric()
    {
        return new Numeric;
    }

    /**
     * Get an "any of" rule builder instance.
     *
     * @param  array  $rules
     * @return \Voyager\Validation\Rules\AnyOf
     *
     * @throws \InvalidArgumentException
     */
    public static function anyOf($rules)
    {
        return new AnyOf($rules);
    }

    /**
     * Get a contains rule builder instance.
     *
     * @param  \Voyager\Contracts\NutsAndBolts\Arrayable|\UnitEnum|array|string  $values
     * @return \Voyager\Validation\Rules\Contains
     */
    public static function contains($values)
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        return new Rules\Contains(is_array($values) ? $values : func_get_args());
    }

    /**
     * Get a "does not contain" rule builder instance.
     *
     * @param  \Voyager\Contracts\NutsAndBolts\Arrayable|\UnitEnum|array|string  $values
     * @return \Voyager\Validation\Rules\DoesntContain
     */
    public static function doesntContain($values)
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        return new Rules\DoesntContain(is_array($values) ? $values : func_get_args());
    }

    /**
     * Compile a set of rules for an attribute.
     *
     * @param  string  $attribute
     * @param  array  $rules
     * @param  array|null  $data
     * @return object|\stdClass
     */
    public static function compile($attribute, $rules, $data = null)
    {
        $parser = new ValidationRuleParser(
            Arr::undot(Arr::wrap($data))
        );

        if (is_array($rules) && ! array_is_list($rules)) {
            $nested = [];

            foreach ($rules as $key => $rule) {
                $nested[$attribute.'.'.$key] = $rule;
            }

            $rules = $nested;
        } else {
            $rules = [$attribute => $rules];
        }

        return $parser->explode(ValidationRuleParser::filterConditionalRules($rules, $data));
    }
}
