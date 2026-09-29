<?php

namespace Voyager\Hashing;

use Voyager\Contracts\Hashing\Hasher;
use Voyager\Vessel\ControlPanel;

/** The hasher a hashing gig runs on: the worker app's, under the caller's config when it sent one. */
trait HashesInWorkers
{
    protected function hasher(): Hasher
    {
        $app = ControlPanel::getInstance();
        $manager = $app->get('hash');

        if (! is_null($this->config)) {
            $app['config']->set('hashing', $this->config);
            // Drivers built under the worker's earlier config would hash with its options.
            $manager->forgetDrivers();
        }

        return $manager->driver($this->driver);
    }
}
