<?php

namespace Tests\Console\Fixtures;

use Voyager\Console\Command;

class PromptingCommand extends Command
{
    public $answer;

    public function __construct(protected $prompt)
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->answer = ($this->prompt)();
    }
}
