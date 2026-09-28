<?php

namespace Voyager\Contracts\IOPools;

/**
 * Where a turn's mail goes. The loop hands over everything its resources pumped or
 * had posted since the last delivery, oldest first, once per non-quiet turn.
 */
interface MailHandler
{
    /**
     * @param list<object> $mail
     */
    public function handOff(array $mail, Loop $loop): void;
}
