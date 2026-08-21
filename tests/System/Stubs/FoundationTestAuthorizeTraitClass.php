<?php

namespace Tests\System\Stubs;

use Voyager\System\Auth\Access\AuthorizesRequests;

class FoundationTestAuthorizeTraitClass
{
    use AuthorizesRequests;

    public function store($object)
    {
        $this->authorize($object);
    }
}
