<?php

namespace Tests\System\Stubs;

class FoundationAuthorizesRequestTestPolicy
{
    public function create()
    {
        $_SERVER['_test.authorizes.trait.policy'] = true;

        return true;
    }

    public function update()
    {
        $_SERVER['_test.authorizes.trait.policy'] = true;

        return true;
    }

    public function testPolicyMethodMayBeGuessedPassingModelInstance()
    {
        $_SERVER['_test.authorizes.trait.policy'] = true;

        return true;
    }

    public function testPolicyMethodMayBeGuessedPassingClassName()
    {
        $_SERVER['_test.authorizes.trait.policy'] = true;

        return true;
    }
}
