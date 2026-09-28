<?php

namespace Voyager\Contracts\IOPools;

interface Pumpable
{
    public function pump(): array;
}