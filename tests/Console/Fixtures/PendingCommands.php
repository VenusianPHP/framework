<?php

namespace Venusian\Tests\Console\Fixtures;

use Voyager\Console\Command;

/** The commands Laravel's ArtisanCommandTest declares as closures, as classes. */
final class PendingCommands
{
    /** @return list<class-string<Command>> */
    public static function all(): array
    {
        return [SurveyCommand::class, SlimCommand::class, InteractionsCommand::class, ExitCommand::class, ContainsCommand::class, ZeroCommand::class, NewEnglandCommand::class];
    }
}

class SurveyCommand extends Command
{
    protected ?string $signature = 'survey';

    public function handle(): void
    {
        $name = $this->ask('What is your name?');
        $language = $this->choice('Which language do you prefer?', ['PHP', 'Ruby', 'Python']);

        $this->line("Your name is $name and you prefer $language.");
    }
}

class SlimCommand extends Command
{
    protected ?string $signature = 'slim';

    public function handle(): void
    {
        $this->line($this->ask('Who?'));
        $this->line($this->ask('What?'));
        $this->line($this->ask('Huh?'));
    }
}

class InteractionsCommand extends Command
{
    protected ?string $signature = 'interactions';

    public function handle(): void
    {
        $this->ask('What is your name?');
        $this->choice('Which language do you prefer?', ['PHP', 'PHP', 'PHP']);
        $this->table(['Name', 'Email'], [['Taylor Otwell', 'taylor@laravel.com']]);
        $this->confirm('Do you want to continue?', true);
    }
}

class ExitCommand extends Command
{
    protected ?string $signature = 'exit {code}';

    public function handle(): int
    {
        return (int) $this->argument('code');
    }
}

class ContainsCommand extends Command
{
    protected ?string $signature = 'contains';

    public function handle(): void
    {
        $this->line('My name is Taylor Otwell');
    }
}

class ZeroCommand extends Command
{
    protected ?string $signature = 'zero';

    public function handle(): void
    {
        $this->line('0');
    }
}

class NewEnglandCommand extends Command
{
    protected ?string $signature = 'new-england';

    public function handle(): void
    {
        $this->line('The region of New England consists of the following states:');

        foreach (['Connecticut', 'Maine', 'Massachusetts', 'New Hampshire', 'Rhode Island', 'Vermont'] as $state) {
            $this->info($state);
        }
    }
}
