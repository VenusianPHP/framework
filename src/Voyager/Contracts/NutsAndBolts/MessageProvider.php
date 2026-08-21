<?php

namespace Voyager\Contracts\NutsAndBolts;

interface MessageProvider
{
    /**
     * Get the messages for the instance.
     *
     * @return \Voyager\Contracts\NutsAndBolts\MessageBag
     */
    public function getMessageBag();
}
