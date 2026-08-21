# Deferred upstream tests

## PipelineTransactionTest.php

Binds `Orchestra\Testbench\TestCase` — a full application harness — and uses
`Event::fake()` plus database transactions. Needs Events (wave 4), Database
(wave 6) and System (wave 2) before it can run, and testbench itself is a
Laravel-app package rather than a component dependency.

Restore it once the container can boot an application, or rewrite the two cases
against a real Vessel instance instead of testbench.
