<?php

use Tests\Cache\Fixtures\BackedEnumNamedRateLimiter;
use Tests\Cache\Fixtures\UnitEnumNamedRateLimiter;
use Voyager\Cache\ArrayStore;
use Voyager\Cache\RateLimiter;
use Voyager\Cache\RateLimiting\Limit;
use Voyager\Cache\Repository;
use Voyager\Contracts\Cache\Repository as Cache;

test('a named rate limiter registers under the normalized key', function (mixed $name, string $expected) {
    $reflectedLimitersProperty = new ReflectionProperty(RateLimiter::class, 'limiters');

    $rateLimiter = new RateLimiter($this->createMock(Cache::class));
    $rateLimiter->for($name, fn () => Limit::perMinute(100));

    $limiters = $reflectedLimitersProperty->getValue($rateLimiter);

    expect($limiters)->toHaveKey($expected);

    $limiterClosure = $rateLimiter->limiter($name);

    expect($limiterClosure)->not->toBeNull();
})->with([
    'uses BackedEnum' => [BackedEnumNamedRateLimiter::API, 'api'],
    'uses UnitEnum' => [UnitEnumNamedRateLimiter::THIRD_PARTY, 'THIRD_PARTY'],
    'uses normal string' => ['yolo', 'yolo'],
    'uses int' => [100, '100'],
]);

test('the origin key is used as a prefix when multiple limiters share the same key', function () {
    $rateLimiter = new RateLimiter(new Repository(new ArrayStore));

    $rateLimiter->for('user_limiter', fn (string $userId) => [
        Limit::perSecond(3)->by($userId),
        Limit::perMinute(5)->by($userId),
    ]);

    $userId1 = '123';
    $userId2 = '456';

    $limiterForUser1 = $rateLimiter->limiter('user_limiter')($userId1);
    $limiterForUser2 = $rateLimiter->limiter('user_limiter')($userId2);

    for ($i = 0; $i < 3; $i++) {
        expect($rateLimiter->tooManyAttempts($limiterForUser1[0]->key, $limiterForUser1[0]->maxAttempts))->toBeFalse()
            ->and($rateLimiter->tooManyAttempts($limiterForUser2[0]->key, $limiterForUser2[0]->maxAttempts))->toBeFalse();

        $rateLimiter->hit($limiterForUser1[0]->key, $limiterForUser1[0]->decaySeconds);
        $rateLimiter->hit($limiterForUser2[0]->key, $limiterForUser2[0]->decaySeconds);
    }

    expect($limiterForUser1[0]->key)->not->toBe($limiterForUser2[0]->key)
        ->and($limiterForUser1[1]->key)->not->toBe($limiterForUser2[1]->key);
});
