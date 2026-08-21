<?php

namespace Tests\Validation\fixtures;

class InvokableAfterRule
{
    public function __invoke($validator)
    {
        $validator->errors()->add('invokableAfterRule', 'true');
    }
}
