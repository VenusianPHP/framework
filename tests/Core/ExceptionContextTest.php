<?php

use Venusian\Tests\Log\Fixtures\LogApp;
use Voyager\Log\Signals\MessageLogged;
use Voyager\Contracts\Debug\ExceptionHandler;

beforeEach(function () {
    $this->app = LogApp::boot();
    $this->path = LogApp::channel($this->app, 'reports');
    $this->app['config']->set('logging.default', 'reports');
    $this->handler = $this->app->make(ExceptionHandler::class);
    $this->logged = [];
    $this->app['signals']->listen(MessageLogged::class, fn (MessageLogged $logged) => $this->logged[] = $logged->context);
});
afterEach(fn () => LogApp::tearDown($this->app, $this));

/** The context of the one record the report logged. */
function reportedContext(array $logged): array
{
    expect($logged)->toHaveCount(1);

    return $logged[0];
}

it('adds the context a registered callback builds to the report', function () {
    $this->handler->buildContextUsing(fn (Throwable $e) => ['tenant' => 'acme', 'seen' => $e->getMessage()]);

    $this->handler->report(new RuntimeException('boom'));

    expect(reportedContext($this->logged))->toMatchArray(['tenant' => 'acme', 'seen' => 'boom']);
});

it('reports with the other context and a marker when a callback throws', function () {
    $this->handler->buildContextUsing(fn () => throw new LogicException('no user here'));
    $this->handler->buildContextUsing(fn () => ['tenant' => 'acme']);

    $this->handler->report(new RuntimeException('boom'));

    $context = reportedContext($this->logged);

    expect($context['exception']->getMessage())->toBe('boom')
        ->and($context['tenant'])->toBe('acme')
        ->and($context['context_callback_failed'])->toHaveCount(1)
        ->and(array_values($context['context_callback_failed'])[0])->toBe('LogicException: no user here')
        ->and(array_key_first($context['context_callback_failed']))->toStartWith('Closure@'.__FILE__.':');
});

it('adds nothing of its own by default', function () {
    $this->handler->report(new RuntimeException('boom'));

    expect(reportedContext($this->logged))->toHaveKey('exception')
        ->and(reportedContext($this->logged))->not->toHaveKey('userId');
});
