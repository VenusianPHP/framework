<?php

use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Voyager\Console\Prompts\ChecklistPrompt;

function checklist(array $keys, array $disabled, array $default = []): array
{
    Prompt::fake($keys);

    return (new ChecklistPrompt(
        'Extensions to install',
        ['epoll' => 'epoll', 'kqueue' => 'kqueue', 'multicurl' => 'multicurl'],
        $disabled,
        $default,
    ))->prompt();
}

it('opens on the first enabled row and steps over a disabled row in both directions', function () {
    // Opens on kqueue; Down to multicurl; Down wraps past epoll to kqueue; Up wraps past epoll to multicurl.
    $picked = checklist([Key::SPACE, Key::DOWN, Key::DOWN, Key::UP, Key::SPACE, Key::ENTER], ['epoll' => 'Linux only']);

    expect($picked)->toBe(['kqueue', 'multicurl']);
});

it('moves Home off a disabled first row and End off a disabled last row', function () {
    expect(checklist([Key::DOWN, Key::HOME[0], Key::SPACE, Key::ENTER], ['epoll' => 'Linux only']))->toBe(['kqueue'])
        ->and(checklist([Key::END[0], Key::SPACE, Key::ENTER], ['multicurl' => 'installed']))->toBe(['kqueue']);
});

it('selects and clears only the enabled rows with Ctrl+A', function () {
    expect(checklist([Key::CTRL_A, Key::ENTER], ['epoll' => 'Linux only']))->toBe(['kqueue', 'multicurl'])
        ->and(checklist([Key::CTRL_A, Key::CTRL_A, Key::ENTER], ['epoll' => 'Linux only']))->toBe([]);
});

it('never toggles a disabled row, even when every row is disabled', function () {
    $everything = ['epoll' => 'Linux only', 'kqueue' => 'installed', 'multicurl' => 'installed'];

    expect(checklist([Key::SPACE, Key::DOWN, Key::SPACE, Key::ENTER], $everything))->toBe([]);
});

it('drops disabled values from the defaults', function () {
    expect(checklist([Key::ENTER], ['epoll' => 'Linux only'], ['epoll', 'multicurl']))->toBe(['multicurl']);
});

it('draws a disabled row with a dash and its reason, and an enabled row with a checkbox', function () {
    checklist([Key::ENTER], ['epoll' => 'Linux only', 'multicurl' => 'installed']);

    $frame = Prompt::strippedContent();

    expect($frame)->toContain('  – epoll (Linux only)')
        ->and($frame)->toContain('› ◻ kqueue')
        ->and($frame)->toContain('  – multicurl (installed)');
});

it('strikes every row through when the prompt is cancelled', function () {
    Prompt::fake([Key::CTRL_C]);
    $prompt = new ChecklistPrompt('Extensions to install', ['epoll' => 'epoll', 'kqueue' => 'kqueue'], ['epoll' => 'Linux only']);
    $prompt->prompt();

    expect($prompt->state)->toBe('cancel')
        ->and(Prompt::content())->toContain("\e[9mepoll (Linux only)\e[29m")
        ->and(Prompt::content())->toContain("\e[9mkqueue\e[29m");
});
