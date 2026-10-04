<?php

namespace Voyager\Core\Extensions\Nodes;

use Voyager\Console\Prompts\ChecklistPrompt;
use Voyager\Core\Extensions\FirstPartyExtension;
use Voyager\Workflows\Node;
use Voyager\Workflows\SharedBag;

class ExtensionSelectNode extends Node
{
    public function prep(SharedBag $shared): mixed
    {
        return [
            'states' => $shared->extension_states,
            'selected' => $shared->extensions_selected ?? null,
        ];
    }

    /**
     * Every extension is listed. One that cannot be installed here is a
     * disabled row with its reason; the rest start selected. An extension
     * named on the command line is the selection, and nothing is asked.
     *
     * @return list<string> The selected FirstPartyExtension values.
     */
    public function exec(mixed $prepRes): mixed
    {
        if (! is_null($prepRes['selected'])) {
            return $prepRes['selected'];
        }

        $options = [];
        foreach (array_keys($prepRes['states']) as $package) {
            $extension = FirstPartyExtension::from($package);
            $options[$package] = "{$extension->extension()} — {$extension->description()}";
        }

        $disabled = array_filter($prepRes['states'], fn (?string $reason): bool => ! is_null($reason));

        return (new ChecklistPrompt(
            label: 'Extensions to install',
            options: $options,
            disabled: $disabled,
            default: array_keys(array_diff_key($options, $disabled)),
            hint: 'Space to select, Enter to confirm.',
        ))->prompt();
    }

    public function post(SharedBag $shared, mixed $prepRes, mixed $execRes): ?string
    {
        if ($execRes === []) {
            $shared->extensions_note = 'None selected.';

            return null;
        }

        $shared->extensions_selected = $execRes;

        return 'install-extensions';
    }
}
