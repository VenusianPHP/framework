<?php

use Voyager\Notifications\Action;

test('action is created properly', function () {
    $action = new Action('Text', 'url');

    expect($action->text)->toBe('Text')
        ->and($action->url)->toBe('url');
});
