<?php

namespace Voyager\Core\Extensions;

use Voyager\Core\Extensions\Nodes\ExtensionInstallNode;
use Voyager\Core\Extensions\Nodes\ExtensionSelectNode;
use Voyager\Core\Extensions\Nodes\ExtensionStateNode;
use Voyager\Core\Extensions\Nodes\PhpConfigFinderNode;
use Voyager\Core\Extensions\Nodes\PieFinderNode;
use Voyager\Core\Extensions\Nodes\PieInstallNode;
use Voyager\Workflows\Flow;

/**
 * Installs the first-party PHP extensions through PIE, run by the PHP binary
 * running the command.
 *
 * Reads from the bag: `interactive` (bool), `only` (an extension name, or
 * unset for the list), `output` (a callable given what PIE prints when it
 * does not have the terminal). Writes `extension_results` (name => outcome),
 * or `extensions_note` when it ends without installing.
 *
 * Every transition has a name. A node stops the flow by returning null, which
 * a Flow reads as "default", and no node here has a default successor.
 */
class ExtensionsFlow extends Flow
{
    public function __construct(Host $host = new Host)
    {
        $state = new ExtensionStateNode($host);
        $php_config = new PhpConfigFinderNode($host);
        $pie_finder = new PieFinderNode($host);
        $pie_install = new PieInstallNode($host);
        $select = new ExtensionSelectNode;
        $install = new ExtensionInstallNode($host);

        $state->next($php_config, 'find-php-config');
        $php_config->next($pie_finder, 'find-pie');
        $pie_finder->next($select, 'select-extensions');
        $pie_finder->next($pie_install, 'install-pie');
        $pie_install->next($select, 'select-extensions');
        $select->next($install, 'install-extensions');

        parent::__construct($state);
    }
}
