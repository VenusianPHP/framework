<?php

use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Voyager\Console\OutputStyle;
use Voyager\Console\ConfirmableTrait;
use Voyager\Console\View\Components\Factory;

it('draws an alert, the banner a command shows before asking to proceed', function () {
    $output = new BufferedOutput;

    (new Factory(new OutputStyle(new ArrayInput([]), $output)))->alert('Application In Production');

    expect($output->fetch())->toContain('APPLICATION IN PRODUCTION');
});

it('warns and asks before a destructive command runs in production, and cancels on no', function () {
    $output = new BufferedOutput;
    $command = new class($output) {
        use ConfirmableTrait;

        public Factory $components;

        public function __construct(BufferedOutput $output)
        {
            $this->components = new Factory(new OutputStyle(new ArrayInput([]), $output));
        }

        public function hasOption(string $name): bool
        {
            return false;
        }

        public function getVenusian(): object
        {
            return new class {
                public function environment(): string
                {
                    return 'production';
                }
            };
        }
    };
    Prompt::fake([Key::ENTER]);

    expect($command->confirmToProceed())->toBeFalse()
        ->and($output->fetch())->toContain('APPLICATION IN PRODUCTION')
        ->toContain('Command cancelled.');
});
