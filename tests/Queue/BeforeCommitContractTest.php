<?php

use Voyager\Bus\Queueable;
use Voyager\Contracts\Queue\ShouldQueueAfterCommit;
use Voyager\System\Bus\Dispatchable;
use Voyager\Queue\InteractsWithQueue;

function shouldDispatchAfterCommit($job)
{
    if ($job instanceof ShouldQueueAfterCommit) {
        return ! (isset($job->afterCommit) && $job->afterCommit === false);
    }

    return isset($job->afterCommit) ? $job->afterCommit : false;
}

test('job without contract respects before commit', function () {
    $job = new class
    {
        use Dispatchable, InteractsWithQueue, Queueable;

        public function beforeCommit()
        {
            $this->afterCommit = false;

            return $this;
        }
    };

    expect(shouldDispatchAfterCommit($job))->toBeFalse();
});

test('job without contract respects after commit', function () {
    $job = new class
    {
        use Dispatchable, InteractsWithQueue, Queueable;

        public function afterCommit()
        {
            $this->afterCommit = true;

            return $this;
        }
    };

    $job->afterCommit();

    expect(shouldDispatchAfterCommit($job))->toBeTrue();
});

test('job with contract defaults to after commit', function () {
    $job = new class implements ShouldQueueAfterCommit
    {
        use Dispatchable, InteractsWithQueue, Queueable;
    };

    expect(shouldDispatchAfterCommit($job))->toBeTrue();
});

test('job with contract and after commit false respects before commit', function () {
    $job = new class implements ShouldQueueAfterCommit
    {
        use Dispatchable, InteractsWithQueue, Queueable;

        public function beforeCommit()
        {
            $this->afterCommit = false;

            return $this;
        }
    };

    $job->beforeCommit();

    expect(shouldDispatchAfterCommit($job))->toBeFalse();
});

test('job with contract and explicit after commit true still schedules after commit', function () {
    $job = new class implements ShouldQueueAfterCommit
    {
        use Dispatchable, InteractsWithQueue, Queueable;

        public function afterCommit()
        {
            $this->afterCommit = true;

            return $this;
        }
    };

    $job->afterCommit();

    expect(shouldDispatchAfterCommit($job))->toBeTrue();
});
