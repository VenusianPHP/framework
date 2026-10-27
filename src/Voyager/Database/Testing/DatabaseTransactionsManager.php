<?php

namespace Voyager\Database\Testing;

use Voyager\Database\DatabaseTransactionsManager as BaseManager;

/**
 * The transactions manager a test's wrapping transactions run under: the outer transaction per
 * connection is the test's own, so after-commit callbacks fire as if it were not there.
 */
class DatabaseTransactionsManager extends BaseManager
{
    /**
     * The connections the test wraps in a transaction.
     *
     * @var array<int, string|null>
     */
    protected array $connectionsTransacting;

    /**
     * @param  array<int, string|null>  $connectionsTransacting
     */
    public function __construct(array $connectionsTransacting)
    {
        parent::__construct();

        $this->connectionsTransacting = $connectionsTransacting;
    }

    /**
     * Run the callback now when only the test's wrapping transactions are open; otherwise after the commit.
     *
     * @param  callable  $callback
     * @return mixed
     */
    public function addCallback($callback)
    {
        if ($this->callbackApplicableTransactions()->isEmpty()) {
            return $callback();
        }

        $this->pendingTransactions->last()->addCallback($callback);
    }

    /**
     * The transactions callbacks apply to: all but the test's wrapping ones.
     *
     * @return \Voyager\NutsAndBolts\Collection
     */
    public function callbackApplicableTransactions()
    {
        return $this->pendingTransactions->skip(count($this->connectionsTransacting))->values();
    }

    /**
     * After-commit callbacks run when the commit returns to the test's own level.
     *
     * @param  int  $level
     * @return bool
     */
    public function afterCommitCallbacksShouldBeExecuted($level)
    {
        return $level === 1;
    }
}
