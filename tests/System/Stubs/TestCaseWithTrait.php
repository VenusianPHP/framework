<?php

namespace Tests\System\Stubs;

use Orchestra\Testbench\Concerns\CreatesApplication;
use Voyager\System\Testing\TestCase as FoundationTestCase;

class TestCaseWithTrait extends FoundationTestCase
{
    use CreatesApplication;
    use TestTrait;
}
