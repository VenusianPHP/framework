<?php

namespace Tests\Console\Fixtures;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Voyager\Console\Command;

class ArgumentsAndOptionsCommand extends Command
{
    public function handle()
    {
    }

    protected function getArguments()
    {
        return [
            new InputArgument('argument-one', InputArgument::REQUIRED, 'first test argument'),
            ['argument-two', InputArgument::OPTIONAL, 'a second test argument'],
            [
                'name' => 'argument-three',
                'description' => 'a third test argument',
                'mode' => InputArgument::OPTIONAL,
                'default' => 'third-argument-default',
            ],
        ];
    }

    protected function getOptions()
    {
        return [
            new InputOption('option-one', 'o', InputOption::VALUE_OPTIONAL, 'first test option'),
            ['option-two', 't', InputOption::VALUE_REQUIRED, 'second test option'],
            [
                'name' => 'option-three',
                'description' => 'a third test option',
                'mode' => InputOption::VALUE_OPTIONAL,
                'default' => 'third-option-default',
            ],
        ];
    }
}
