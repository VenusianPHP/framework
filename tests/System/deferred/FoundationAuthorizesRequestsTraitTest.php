<?php

use Tests\System\Stubs\FoundationAuthorizesRequestTestClass;
use Tests\System\Stubs\FoundationAuthorizesRequestTestPolicy;
use Tests\System\Stubs\FoundationTestAuthorizeTraitClass;
use Tests\System\Stubs\TestAbility;
use Voyager\Auth\Access\AuthorizationException;
use Voyager\Auth\Access\Gate;
use Voyager\Auth\Access\Response;
use Voyager\Contracts\Auth\Access\Gate as GateContract;
use Voyager\Vessel\Vessel;

/** A gate bound onto a fresh container, resolving a user with id 1. */
function basicGate(): Gate
{
    $container = Vessel::setInstance(new Vessel);

    $gate = new Gate($container, function () {
        return (object) ['id' => 1];
    });

    $container->instance(GateContract::class, $gate);

    return $gate;
}

afterEach(function () {
    Vessel::setInstance(null);
});

test('a defined ability is checked', function () {
    unset($_SERVER['_test.authorizes.trait']);

    $gate = basicGate();

    $gate->define('baz', function () {
        $_SERVER['_test.authorizes.trait'] = true;

        return true;
    });

    $response = (new FoundationTestAuthorizeTraitClass)->authorize('baz');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($_SERVER['_test.authorizes.trait'])->toBeTrue();
});

test('a backed enum is accepted as the ability', function () {
    unset($_SERVER['_test.authorizes.trait.enum']);

    $gate = basicGate();

    $gate->define('baz', function () {
        $_SERVER['_test.authorizes.trait.enum'] = true;

        return true;
    });

    $response = (new FoundationTestAuthorizeTraitClass)->authorize(TestAbility::BAZ);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($_SERVER['_test.authorizes.trait.enum'])->toBeTrue();
});

test('a failed gate check throws', function () {
    $gate = basicGate();

    $gate->define('baz', function () {
        return false;
    });

    (new FoundationTestAuthorizeTraitClass)->authorize('baz');
})->throws(AuthorizationException::class, 'This action is unauthorized.');

test('a policy is consulted for a named ability', function () {
    unset($_SERVER['_test.authorizes.trait.policy']);

    $gate = basicGate();

    $gate->policy(FoundationAuthorizesRequestTestClass::class, FoundationAuthorizesRequestTestPolicy::class);

    $response = (new FoundationTestAuthorizeTraitClass)->authorize('update', new FoundationAuthorizesRequestTestClass);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($_SERVER['_test.authorizes.trait.policy'])->toBeTrue();
});

test('the policy method is guessed when a model instance is passed', function () {
    unset($_SERVER['_test.authorizes.trait.policy']);

    $gate = basicGate();

    $gate->policy(FoundationAuthorizesRequestTestClass::class, FoundationAuthorizesRequestTestPolicy::class);

    $response = (new FoundationTestAuthorizeTraitClass)->authorize(new FoundationAuthorizesRequestTestClass);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($_SERVER['_test.authorizes.trait.policy'])->toBeTrue();
});

test('the policy method is guessed when a class name is passed', function () {
    unset($_SERVER['_test.authorizes.trait.policy']);

    $gate = basicGate();

    $gate->policy('\\'.FoundationAuthorizesRequestTestClass::class, FoundationAuthorizesRequestTestPolicy::class);

    $response = (new FoundationTestAuthorizeTraitClass)->authorize('\\'.FoundationAuthorizesRequestTestClass::class);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($_SERVER['_test.authorizes.trait.policy'])->toBeTrue();
});

test('the guessed policy method is normalized from the caller', function () {
    unset($_SERVER['_test.authorizes.trait.policy']);

    $gate = basicGate();

    $gate->policy(FoundationAuthorizesRequestTestClass::class, FoundationAuthorizesRequestTestPolicy::class);

    (new FoundationTestAuthorizeTraitClass)->store(new FoundationAuthorizesRequestTestClass);

    expect($_SERVER['_test.authorizes.trait.policy'])->toBeTrue();
});
