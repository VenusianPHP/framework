<?php

use Voyager\NutsAndBolts\MessageBag;
use Voyager\NutsAndBolts\ViewErrorBag;

describe('hasBag', function () {
    test('is true once a bag has been put', function () {
        $viewErrorBag = new ViewErrorBag;
        $viewErrorBag->put('default', new MessageBag(['msg1', 'msg2']));

        expect($viewErrorBag->hasBag())->toBeTrue();
    });

    test('is false while empty', function () {
        expect((new ViewErrorBag)->hasBag())->toBeFalse();
    });
});

describe('bags', function () {
    test('getBag returns the bag that was put', function () {
        $messageBag = new MessageBag;
        $viewErrorBag = (new ViewErrorBag)->put('default', $messageBag);

        expect($viewErrorBag->getBag('default'))->toEqual($messageBag);
    });

    test('getBag creates an empty bag for an unknown key', function () {
        expect((new ViewErrorBag)->getBag('default'))->toBeInstanceOf(MessageBag::class);
    });

    test('getBags returns every bag', function () {
        $messageBag1 = new MessageBag;
        $messageBag2 = new MessageBag;
        $viewErrorBag = new ViewErrorBag;
        $viewErrorBag->put('default', $messageBag1);
        $viewErrorBag->put('default2', $messageBag2);

        expect($viewErrorBag->getBags())->toEqual([
            'default' => $messageBag1,
            'default2' => $messageBag2,
        ]);
    });

    test('put returns the bag collection', function () {
        $messageBag = new MessageBag;
        $viewErrorBag = (new ViewErrorBag)->put('default', $messageBag);

        expect($viewErrorBag->getBags())->toEqual(['default' => $messageBag]);
    });
});

describe('any', function () {
    test('is true when a bag holds messages', function () {
        $viewErrorBag = new ViewErrorBag;
        $viewErrorBag->put('default', new MessageBag(['message']));

        expect($viewErrorBag->any())->toBeTrue();
    });

    test('is false when the bag is empty', function () {
        $viewErrorBag = new ViewErrorBag;
        $viewErrorBag->put('default', new MessageBag);

        expect($viewErrorBag->any())->toBeFalse();
    });

    test('is false when there are no bags at all', function () {
        expect((new ViewErrorBag)->any())->toBeFalse();
    });
});

describe('count', function () {
    test('counts the messages in the bags', function () {
        $viewErrorBag = new ViewErrorBag;
        $viewErrorBag->put('default', new MessageBag(['message', 'second']));

        expect($viewErrorBag)->toHaveCount(2);
    });

    test('is zero for an empty message bag', function () {
        $viewErrorBag = new ViewErrorBag;
        $viewErrorBag->put('default', new MessageBag);

        expect($viewErrorBag)->toHaveCount(0);
    });

    test('is zero when there are no bags at all', function () {
        expect(new ViewErrorBag)->toHaveCount(0);
    });
});

describe('dynamic access', function () {
    test('an unknown method call is forwarded to the default bag', function () {
        $viewErrorBag = new ViewErrorBag;
        $viewErrorBag->put('default', new MessageBag(['message', 'second']));

        expect($viewErrorBag->all())->toEqual(['message', 'second']);
    });

    test('a bag may be read as a property', function () {
        $messageBag = new MessageBag;
        $viewErrorBag = (new ViewErrorBag)->put('default', $messageBag);

        expect($viewErrorBag->default)->toEqual($messageBag);
    });

    test('a bag may be written as a property', function () {
        $messageBag = new MessageBag;
        $viewErrorBag = new ViewErrorBag;
        $viewErrorBag->default2 = $messageBag;

        expect($viewErrorBag->getBags())->toEqual(['default2' => $messageBag]);
    });
});

test('casting to string renders the default bag as json', function () {
    $viewErrorBag = (new ViewErrorBag)->put('default', new MessageBag(['message' => 'content']));

    expect((string) $viewErrorBag)->toBe('{"message":["content"]}');
});
