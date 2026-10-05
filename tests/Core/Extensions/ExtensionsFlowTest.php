<?php

use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Voyager\Core\Extensions\ExtensionsFlow;
use Voyager\Core\Extensions\FirstPartyExtension;
use Voyager\Core\Extensions\Host;
use Voyager\Process\Factory;
use Voyager\Process\PendingProcess;
use Voyager\Workflows\SharedBag;

/*
 * The install:ext interview over a described machine. DescribedHost says what
 * is there, the Process factory's fake answers each command, Prompt::fake()
 * presses keys. No PIE, no network.
 */

const EXT_PHP = '/opt/php/bin/php';
const EXT_PIE = '/usr/local/bin/pie';
const EXT_PHP_CONFIG = '/opt/php/bin/php-config';

final class DescribedHost extends Host
{
    /** @var list<list<string>> */
    public array $commands = [];

    /** @var list<array{string, string}> */
    public array $downloads = [];

    /** @var list<string> */
    public array $removed = [];

    /**
     * @param  list<string>  $loaded
     * @param  array<string, string>  $executables
     * @param  list<array{string, int, string}>  $rules  [text in the command line, exit code, stdout]; first match wins.
     */
    public function __construct(
        public string $php = EXT_PHP,
        public string $os = 'Darwin',
        public array $loaded = [],
        public ?string $home = '/home/dev',
        public array $executables = ['pie' => EXT_PIE],
        public bool $downloads_arrive = true,
        array $rules = [],
    ) {
        $rules = [...$rules, ['--version', 0, '🥧 PHP Installer for Extensions (PIE) 1.5.1'], [EXT_PHP_CONFIG.' --php-binary', 0, EXT_PHP], ['--php-binary', 127, '']];
        $factory = new Factory;
        $factory->fake(function (PendingProcess $process) use ($rules, $factory) {
            $this->commands[] = $process->command;
            foreach ($rules as [$text, $exit, $output]) {
                if (str_contains(implode(' ', $process->command), $text)) {
                    return $factory->result($output, '', $exit);
                }
            }

            return $factory->result();
        });

        parent::__construct($factory);
    }

    public function phpBinary(): string
    {
        return $this->php;
    }

    public function osFamily(): string
    {
        return $this->os;
    }

    public function loaded(string $extension): bool
    {
        return in_array($extension, $this->loaded, true);
    }

    public function home(): ?string
    {
        return $this->home;
    }

    public function find(string $name, array $extra_directories = []): ?string
    {
        return $this->executables[$name] ?? null;
    }

    public function download(string $url, string $path): bool
    {
        $this->downloads[] = [$url, $path];

        return $this->downloads_arrive;
    }

    public function remove(string $path): void
    {
        $this->removed[] = $path;
    }
}

/**
 * @param  list<string>  $keys
 */
function interview(DescribedHost $host, array $keys = [], ?string $only = null, bool $interactive = true): SharedBag
{
    Prompt::fake($keys);
    $shared = new SharedBag;
    $shared->interactive = $interactive;
    $shared->only = $only;
    $shared->output = null;
    new ExtensionsFlow($host)->run($shared);

    return $shared;
}

it('finds a case by the name PHP loads it under', function () {
    expect(FirstPartyExtension::named('pcurl'))->toBe(FirstPartyExtension::PCURL)
        ->and(FirstPartyExtension::named('KQueue'))->toBe(FirstPartyExtension::KQUEUE)
        ->and(FirstPartyExtension::named('gd'))->toBeNull();
});

it('goes straight to the list and installs what stays selected', function () {
    $host = new DescribedHost(os: 'Darwin');

    // The list opens with every installable row selected; Enter accepts it.
    $shared = interview($host, [Key::ENTER]);

    expect($host->commands)->toBe([
        [EXT_PHP_CONFIG, '--php-binary'],
        [EXT_PHP, EXT_PIE, '--version'],
        [EXT_PHP, EXT_PIE, 'install', 'php-io-extensions/kqueue:^0.10', '--with-php-config='.EXT_PHP_CONFIG],
        [EXT_PHP, '--ri', 'kqueue'],
        [EXT_PHP, EXT_PIE, 'install', 'php-io-extensions/pcurl:^0.10', '--with-php-config='.EXT_PHP_CONFIG],
        [EXT_PHP, '--ri', 'pcurl'],
    ])
        ->and($shared->extension_results)->toBe(['kqueue' => 'installed', 'pcurl' => 'installed'])
        ->and(Prompt::strippedContent())->toContain('– epoll — event loop waiting on Linux (Linux only)');
});

it('installs the one named extension without showing the list', function () {
    $host = new DescribedHost(os: 'Linux');

    $shared = interview($host, only: 'epoll', interactive: false);

    expect($shared->extension_results)->toBe(['epoll' => 'installed'])
        ->and($host->commands)->toContain([EXT_PHP, EXT_PIE, 'install', 'php-io-extensions/epoll:^0.10', '--with-php-config='.EXT_PHP_CONFIG])
        ->and($host->commands)->not->toContain([EXT_PHP, EXT_PIE, 'install', 'php-io-extensions/pcurl:^0.10', '--with-php-config='.EXT_PHP_CONFIG])
        ->and(Prompt::strippedContent())->not->toContain('Extensions to install');
});

it('ends with the reason when the named extension cannot be installed', function (string $only, array $loaded, string $note) {
    $host = new DescribedHost(os: 'Darwin', loaded: $loaded);

    $shared = interview($host, only: $only);

    expect($shared->extensions_note)->toBe($note)
        ->and($host->commands)->toBe([]);
})->with([
    'another operating system' => ['epoll', [], 'Nothing to install: epoll (Linux only).'],
    'already loaded' => ['pcurl', ['pcurl'], 'Nothing to install: pcurl (installed).'],
    'not a first-party extension' => ['gd', [], 'Unknown extension [gd]. Choose one of: epoll, kqueue, pcurl.'],
]);

it('ends when every extension is installed or belongs to another operating system', function () {
    $host = new DescribedHost(os: 'Darwin', loaded: ['kqueue', 'pcurl']);

    $shared = interview($host);

    expect($shared->extensions_note)->toBe('Nothing to install: epoll (Linux only), kqueue (installed), pcurl (installed).')
        ->and($host->commands)->toBe([]);
});

it('asks for a name on a non-interactive run with none', function () {
    $shared = interview(new DescribedHost, interactive: false);

    expect($shared->extensions_note)->toBe('Name the extension to install on a non-interactive run: epoll, kqueue, pcurl.');
});

it('ends when nothing is selected', function () {
    $host = new DescribedHost;

    // Ctrl+A clears the list, since every installable row starts selected.
    $shared = interview($host, [Key::CTRL_A, Key::ENTER]);

    expect($shared->extensions_note)->toBe('None selected.')
        ->and(isset($shared->extension_results))->toBeFalse();
});

it('downloads PIE on a yes, has it verify itself, and installs with it', function () {
    $host = new DescribedHost(os: 'Linux', loaded: ['pcurl'], executables: []);

    $shared = interview($host, [Key::ENTER, Key::ENTER]);

    expect($host->downloads)->toBe([['https://github.com/php/pie/releases/latest/download/pie.phar', '/home/dev/.local/bin/pie']])
        ->and($host->commands)->toContain([EXT_PHP, '/home/dev/.local/bin/pie', 'self-verify'])
        ->and($shared->pie_binary)->toBe('/home/dev/.local/bin/pie')
        ->and($shared->extension_results)->toBe(['epoll' => 'installed']);
});

it('ends without PIE', function (array $host, array $keys, bool $interactive, string $note) {
    $host = new DescribedHost(...$host, executables: []);

    $shared = interview($host, $keys, 'pcurl', $interactive);

    expect($shared->extensions_note)->toBe($note)
        ->and(isset($shared->pie_binary))->toBeFalse();
})->with([
    'declined' => [[], ['n', Key::ENTER], true, 'PIE is not installed, and you declined to install it.'],
    'non-interactive: nothing is downloaded unasked' => [[], [], false, 'PIE is not installed. Run this command interactively to install it.'],
    'no home directory' => [['home' => null], [], true, 'PIE is not installed, and there is no home directory to install it into.'],
    'the download does not arrive' => [['downloads_arrive' => false], [Key::ENTER], true, 'PIE could not be downloaded from https://github.com/php/pie/releases/latest/download/pie.phar.'],
    'the download fails its own verification' => [['rules' => [['self-verify', 1, '']]], [Key::ENTER], true, 'The downloaded PIE failed its own verification and was removed.'],
]);

it('removes a downloaded PIE that fails its own verification', function () {
    $host = new DescribedHost(executables: [], rules: [['self-verify', 1, '']]);

    interview($host, [Key::ENTER], 'pcurl');

    expect($host->removed)->toBe(['/home/dev/.local/bin/pie']);
});

it('does not take a pie that is another program or does not run under this PHP', function (array $rule) {
    $host = new DescribedHost(rules: [$rule]);

    $shared = interview($host, ['n', Key::ENTER], 'pcurl');

    expect($shared->extensions_note)->toBe('PIE is not installed, and you declined to install it.');
})->with([
    'another program' => [[EXT_PIE.' --version', 0, 'Python Installs Everything 3.1']],
    'does not run' => [[EXT_PIE.' --version', 1, '']],
]);

it('finds the php-config beside a versioned PHP binary', function () {
    $host = new DescribedHost(php: '/usr/bin/php8.4', os: 'Linux', rules: [['/usr/bin/php-config8.4 --php-binary', 0, '/usr/bin/php8.4']]);

    $shared = interview($host, only: 'epoll');

    expect($shared->php_config)->toBe('/usr/bin/php-config8.4');
});

it('takes a php-config from PATH when it reports the running PHP binary', function () {
    $host = new DescribedHost(
        executables: ['pie' => EXT_PIE, 'php-config' => '/usr/local/bin/php-config'],
        rules: [[EXT_PHP_CONFIG.' --php-binary', 127, ''], ['/usr/local/bin/php-config --php-binary', 0, EXT_PHP]],
    );

    expect(interview($host, only: 'pcurl')->php_config)->toBe('/usr/local/bin/php-config');
});

it('ends when the only php-config builds for another PHP', function () {
    $host = new DescribedHost(
        executables: ['pie' => EXT_PIE, 'php-config' => '/usr/local/bin/php-config'],
        rules: [[EXT_PHP_CONFIG.' --php-binary', 127, ''], ['/usr/local/bin/php-config --php-binary', 0, '/usr/local/bin/php']],
    );

    $shared = interview($host, only: 'pcurl');

    expect($shared->extensions_note)->toBe('The php-config on this machine builds for /usr/local/bin/php, not for '.EXT_PHP.'. Install the development files for '.EXT_PHP.' first.');
});

it('leaves the php-config to PIE when the machine has none', function () {
    $host = new DescribedHost(rules: [[EXT_PHP_CONFIG.' --php-binary', 127, '']]);

    $shared = interview($host, only: 'pcurl');

    expect($shared->php_config)->toBeNull()
        ->and($host->commands)->toContain([EXT_PHP, EXT_PIE, 'install', 'php-io-extensions/pcurl:^0.10'])
        ->and($shared->extension_results)->toBe(['pcurl' => 'installed']);
});

it('reports a failed build and still installs the next extension', function () {
    $host = new DescribedHost(rules: [['install php-io-extensions/kqueue', 2, '']]);

    $shared = interview($host, [Key::ENTER]);

    expect($shared->extension_results)->toBe(['kqueue' => 'failed (PIE exit code 2)', 'pcurl' => 'installed'])
        ->and($host->commands)->not->toContain([EXT_PHP, '--ri', 'kqueue']);
});

it('reports an extension PIE installed that the PHP binary still does not load', function () {
    $host = new DescribedHost(rules: [['--ri pcurl', 1, '']]);

    $shared = interview($host, only: 'pcurl');

    expect($shared->extension_results)->toBe(['pcurl' => 'installed by PIE, but '.EXT_PHP.' does not load it']);
});
