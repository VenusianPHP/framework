<?php

namespace Voyager\Contracts\IOPools;

interface LoopTimer
{
    public function fire(): void;
    public function cancel(): void;
    public function dueAt(): ?float;
    public function cancelled(): bool;
    public function interval(): ?float;
    public function setDueAt(float $due_at): void;
}