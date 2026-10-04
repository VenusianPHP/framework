<?php

namespace Voyager\Core\Extensions\Nodes;

use Voyager\Core\Extensions\FirstPartyExtension;
use Voyager\Core\Extensions\Host;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

/**
 * Works out what can be installed here. With one extension named, that one
 * is the selection and the list is never shown.
 */
class ExtensionStateNode extends Node
{
    public function __construct(
        private Host $host = new Host,
    ) {
        parent::__construct();
    }

    public function prep(SharedBag $shared): mixed
    {
        $states = [];
        foreach (FirstPartyExtension::cases() as $extension) {
            $states[$extension->value] = $extension->unsupportedOn($this->host->osFamily())
                ?? ($this->host->loaded($extension->extension()) ? 'installed' : null);
        }

        return [
            'states' => $states,
            'only' => $shared->only ?? null,
            'interactive' => $shared->interactive ?? false,
        ];
    }

    /**
     * @return array{note: ?string, selected: ?list<string>}
     */
    public function exec(mixed $prepRes): mixed
    {
        $names = array_map(fn (FirstPartyExtension $extension): string => $extension->extension(), FirstPartyExtension::cases());

        if (! is_null($prepRes['only'])) {
            $extension = FirstPartyExtension::named($prepRes['only']);

            if (is_null($extension)) {
                return ['note' => "Unknown extension [{$prepRes['only']}]. Choose one of: ".implode(', ', $names).'.', 'selected' => null];
            }

            $reason = $prepRes['states'][$extension->value];

            return is_null($reason)
                ? ['note' => null, 'selected' => [$extension->value]]
                : ['note' => "Nothing to install: {$extension->extension()} ({$reason}).", 'selected' => null];
        }

        if (! in_array(null, $prepRes['states'], true)) {
            $reasons = [];
            foreach ($prepRes['states'] as $package => $reason) {
                $reasons[] = FirstPartyExtension::from($package)->extension()." ({$reason})";
            }

            return ['note' => 'Nothing to install: '.implode(', ', $reasons).'.', 'selected' => null];
        }

        if (! $prepRes['interactive']) {
            return ['note' => 'Name the extension to install on a non-interactive run: '.implode(', ', $names).'.', 'selected' => null];
        }

        return ['note' => null, 'selected' => null];
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        $shared->extension_states = $prepRes['states'];

        if (! is_null($execRes['note'])) {
            $shared->extensions_note = $execRes['note'];

            return null;
        }

        if (! is_null($execRes['selected'])) {
            $shared->extensions_selected = $execRes['selected'];
        }

        return 'find-php-config';
    }
}
