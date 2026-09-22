<?php

use Venusian\Tests\IOPools\Fixtures\TestEvent;
use Voyager\Contracts\IOPools\Pumpable;
use Voyager\Contracts\IOPools\Resumable;
use Voyager\Contracts\IOPools\Tickable;
use Voyager\IOPools\ResourceNotebook;

/** A tickable that queues mail while ticking and hands it over when pumped. */
function mailingTickable(string ...$names): Tickable&Pumpable
{
    return new class($names) implements Tickable, Pumpable {
        private array $queued = [];
        private int $ticks = 0;

        public function __construct(private array $names) {}

        public function tick(): void
        {
            $this->ticks++;

            foreach ($this->names as $name) {
                $this->queued[] = new TestEvent($name, $this->ticks);
            }
        }

        public function pump(): array
        {
            $mail = $this->queued;
            $this->queued = [];

            return $mail;
        }
    };
}

/** @return array<string> the names in the bag, in arrival order */
function bagNames(ResourceNotebook $notebook): array
{
    return $notebook->mail()?->mail()->map(fn (TestEvent $e) => $e->name())->values()->all() ?? [];
}

it('pumps only the resources that have mail to give', function () {
    $notebook = new ResourceNotebook;

    $silent = new class implements Tickable {
        public function tick(): void {}
    };

    $notebook->setTickableResource('silent', $silent);
    $notebook->setTickableResource('chatty', mailingTickable('hello'));

    $notebook->tick();
    $notebook->pump();

    expect(bagNames($notebook))->toBe(['hello']);
});

it('keeps mail in arrival order across resources', function () {
    $notebook = new ResourceNotebook;

    $notebook->setTickableResource('a', mailingTickable('a1', 'a2'));
    $notebook->setTickableResource('b', mailingTickable('b1'));

    $notebook->tick();
    $notebook->pump();

    expect(bagNames($notebook))->toBe(['a1', 'a2', 'b1']);
});

it('keeps every piece of mail, repeats included, in arrival order', function () {
    $notebook = new ResourceNotebook;

    $notebook->setTickableResource('a', mailingTickable('a1'));
    $notebook->setTickableResource('b', mailingTickable('b1'));

    $notebook->tick();
    $notebook->pump();
    $notebook->tick();
    $notebook->pump();

    expect(bagNames($notebook))->toBe(['a1', 'b1', 'a1', 'b1']);
});

it('keys each piece of mail uniquely, so two of one name both survive', function () {
    $notebook = new ResourceNotebook;

    $notebook->setTickableResource('a', mailingTickable('same', 'same'));

    $notebook->tick();
    $notebook->pump();

    $mail = $notebook->mail()->mail();

    expect($mail)->toHaveCount(2)
        ->and($mail->keys()->unique()->count())->toBe(2);
});

it('offers the last-wins view without losing the pieces behind it', function () {
    $notebook = new ResourceNotebook;

    $notebook->setTickableResource('a', mailingTickable('pin'));

    $notebook->tick();
    $notebook->pump();
    $notebook->tick();
    $notebook->pump();

    $mail = $notebook->mail()->mail();

    expect($mail)->toHaveCount(2)
        ->and($mail->keyBy(fn (TestEvent $e) => $e->name()))->toHaveCount(1)
        ->and($mail->keyBy(fn (TestEvent $e) => $e->name())->get('pin')->payload)->toBe(2);
});

it('drops anything in the pump that is not an Event', function () {
    $notebook = new ResourceNotebook;

    $notebook->setTickableResource('junk', new class implements Tickable, Pumpable {
        public function tick(): void {}
        public function pump(): array { return ['a string', 42, new TestEvent('real')]; }
    });

    $notebook->tick();
    $notebook->pump();

    expect(bagNames($notebook))->toBe(['real']);
});

it('hands the mail over once, then starts empty', function () {
    $notebook = new ResourceNotebook;

    $notebook->setTickableResource('a', mailingTickable('a1'));

    $notebook->tick();
    $notebook->pump();

    expect(bagNames($notebook))->toBe(['a1'])
        ->and($notebook->mail())->toBeNull();
});

it('ticks every resource even when one throws, and keeps the first failure', function () {
    $notebook = new ResourceNotebook;

    $notebook->setTickableResource('bad', new class implements Tickable {
        public function tick(): void { throw new RuntimeException('first'); }
    });
    $notebook->setTickableResource('worse', new class implements Tickable {
        public function tick(): void { throw new RuntimeException('second'); }
    });
    $notebook->setTickableResource('good', mailingTickable('survived'));

    $notebook->tick();
    $notebook->pump();

    expect(bagNames($notebook))->toBe(['survived'])
        ->and($notebook->failure()?->getMessage())->toBe('first')
        ->and($notebook->failure())->toBeNull();          // take-once, like the mail
});

it('takes a resource last mail before forgetting it', function () {
    $notebook = new ResourceNotebook;

    $notebook->setTickableResource('leaving', mailingTickable('goodbye'));

    $notebook->tick();
    $notebook->forget('leaving');

    expect(bagNames($notebook))->toBe(['goodbye'])
        ->and($notebook->hasResources())->toBeFalse();
});

/** Reports work on its first resume() only, and counts every call. */
final class CountingResumable implements Resumable
{
    public int $calls = 0;

    public function resume(): bool
    {
        return ++$this->calls === 1;
    }
}

describe('resumables', function () {
    it('walks resumables and reports whether any ran', function () {
        $notebook = new ResourceNotebook;
        $notebook->setResumableResource('a', $a = new CountingResumable);

        expect($notebook->resume())->toBeTrue()
            ->and($notebook->resume())->toBeFalse()
            ->and($a->calls)->toBe(2);
    });

    it('does not count a resumable as work', function () {
        $notebook = new ResourceNotebook;
        $notebook->setResumableResource('a', new CountingResumable);

        expect($notebook->hasResources())->toBeFalse();
    });

    it('forgets a resumable by name', function () {
        $notebook = new ResourceNotebook;
        $notebook->setResumableResource('a', $a = new CountingResumable);
        $notebook->forget('a');

        expect($notebook->resume())->toBeFalse()
            ->and($a->calls)->toBe(0);
    });
});
