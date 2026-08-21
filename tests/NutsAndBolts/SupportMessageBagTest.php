<?php

use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\MessageBag;

/** A bag whose format is the bare message, as most of these cases assume. */
function plainMessageBag(array $messages = []): MessageBag
{
    return (new MessageBag($messages))->setFormat(':message');
}

describe('adding messages', function () {
    test('the same message is only stored once', function () {
        $container = new MessageBag;
        $container->add('foo', 'bar');
        $container->add('foo', 'bar');

        expect($container->getMessages()['foo'])->toEqual(['bar']);
    });

    test('messages accumulate per key', function () {
        $container = plainMessageBag();
        $container->add('foo', 'bar');
        $container->add('foo', 'baz');
        $container->add('boom', 'bust');

        $messages = $container->getMessages();

        expect($messages['foo'])->toEqual(['bar', 'baz'])
            ->and($messages['boom'])->toEqual(['bust']);
    });

    test('addIf only adds when the condition holds', function () {
        $container = plainMessageBag();

        $container->addIf(true, 'foo', 'bar');
        expect($container->has('foo'))->toBeTrue();

        $container->addIf(false, 'bar', 'biz');
        expect($container->has('bar'))->toBeFalse();
    });

    test('forget removes a key', function () {
        $container = new MessageBag(['foo' => 'bar']);
        $container->forget('foo');

        expect($container->has('foo'))->toBeFalse();
    });

    test('keys returns the keys in insertion order', function () {
        $container = plainMessageBag();
        $container->add('foo', 'bar');
        $container->add('foo', 'baz');
        $container->add('boom', 'bust');

        expect($container->keys())->toEqual(['foo', 'boom']);
    });
});

describe('construction and merging', function () {
    test('the constructor wraps bare values in arrays', function () {
        $messageBag = new MessageBag(['country' => 'Azerbaijan', 'capital' => 'Baku']);

        expect($messageBag->getMessages())->toEqual(['country' => ['Azerbaijan'], 'capital' => ['Baku']]);
    });

    test('the constructor de-duplicates exactly as add does', function () {
        $messageBag = new MessageBag(['messages' => ['first', 'second', 'third', 'third']]);

        expect($messageBag->getMessages()['messages'])->toEqual(['first', 'second', 'third']);

        $messageBag = new MessageBag;
        $messageBag->add('messages', 'first');
        $messageBag->add('messages', 'second');
        $messageBag->add('messages', 'third');
        $messageBag->add('messages', 'third');

        expect($messageBag->getMessages()['messages'])->toEqual(['first', 'second', 'third']);
    });

    test('an array of messages may be merged in', function () {
        $container = new MessageBag(['username' => ['foo']]);
        $container->merge(['username' => ['bar']]);

        expect($container->getMessages())->toEqual(['username' => ['foo', 'bar']]);
    });

    test('another message bag may be merged in', function () {
        $container = new MessageBag(['foo' => ['bar']]);
        $container->merge(new MessageBag(['foo' => ['baz'], 'bar' => ['foo']]));

        expect($container->getMessages())->toEqual(['foo' => ['bar', 'baz'], 'bar' => ['foo']]);
    });

    test('Arrayable messages are converted to arrays', function () {
        $container = new MessageBag([
            Collection::make(['foo', 'bar']),
            Collection::make(['baz', 'qux']),
        ]);

        expect($container->getMessages())->toBe([['foo', 'bar'], ['baz', 'qux']]);
    });
});

describe('reading messages', function () {
    test('get returns the messages for a key', function () {
        $container = plainMessageBag();
        $container->add('foo', 'bar');
        $container->add('foo', 'baz');

        expect($container->get('foo'))->toEqual(['bar', 'baz']);
    });

    test('get expands a wildcard key', function () {
        $container = plainMessageBag();
        $container->add('foo.1', 'bar');
        $container->add('foo.2', 'baz');

        expect($container->get('foo.*'))->toEqual(['foo.1' => ['bar'], 'foo.2' => ['baz']]);
    });

    test('first returns a single message', function () {
        $container = plainMessageBag();
        $container->add('foo', 'bar');
        $container->add('foo', 'baz');

        expect($container->first('foo'))->toBe('bar');
    });

    test('first returns an empty string when nothing matches', function () {
        expect(plainMessageBag()->first('foo'))->toBe('');
    });

    test('first resolves a wildcard over dotted keys', function () {
        $container = plainMessageBag();
        $container->add('name.first', 'jon');
        $container->add('name.last', 'snow');

        expect($container->first('name.*'))->toBe('jon');
    });

    test('first finds a message for a wildcard key', function () {
        $container = plainMessageBag();
        $container->add('foo.bar', 'baz');

        expect($container->first('foo.*'))->toBe('baz');
    });

    test('all returns every message', function () {
        $container = plainMessageBag();
        $container->add('foo', 'bar');
        $container->add('boom', 'baz');

        expect($container->all())->toEqual(['bar', 'baz']);
    });

    test('unique keeps the first occurrence of each message', function () {
        $container = plainMessageBag();
        $container->add('foo', 'bar');
        $container->add('foo2', 'bar');
        $container->add('boom', 'baz');

        expect($container->unique())->toEqual([0 => 'bar', 2 => 'baz']);
    });
});

describe('presence checks', function () {
    test('has reports on a single key', function () {
        $container = plainMessageBag();
        $container->add('foo', 'bar');

        expect($container->has('foo'))->toBeTrue()
            ->and($container->has('bar'))->toBeFalse();
    });

    test('has is false on an empty bag', function () {
        expect(plainMessageBag()->has('foo'))->toBeFalse();
    });

    test('has requires every key of an array', function () {
        $container = plainMessageBag();
        $container->add('foo', 'bar');
        $container->add('bar', 'foo');
        $container->add('boom', 'baz');

        expect($container->has(['foo', 'bar', 'boom']))->toBeTrue()
            ->and($container->has(['foo', 'bar', 'boom', 'baz']))->toBeFalse()
            ->and($container->has(['foo', 'baz']))->toBeFalse();
    });

    test('has with a null key asks whether the bag holds anything', function () {
        $container = plainMessageBag();
        $container->add('foo', 'bar');

        expect($container->has(null))->toBeTrue();
    });

    test('missing is the inverse of has, variadic or array', function () {
        $container = plainMessageBag();
        $container->add('foo', 'bar');

        expect($container->missing('foo'))->toBeFalse()
            ->and($container->missing(['foo', 'baz']))->toBeFalse()
            ->and($container->missing('foo', 'baz'))->toBeFalse()
            ->and($container->missing('baz'))->toBeTrue()
            ->and($container->missing(['baz', 'biz']))->toBeTrue()
            ->and($container->missing('baz', 'biz'))->toBeTrue();
    });

    test('hasAny requires only one key of the set', function () {
        $container = plainMessageBag();

        expect($container->hasAny())->toBeFalse();

        $container->add('foo', 'bar');
        $container->add('bar', 'foo');
        $container->add('boom', 'baz');

        expect($container->hasAny(['foo', 'bar']))->toBeTrue()
            ->and($container->hasAny('foo', 'bar'))->toBeTrue()
            ->and($container->hasAny(['boom', 'baz']))->toBeTrue()
            ->and($container->hasAny('boom', 'baz'))->toBeTrue()
            ->and($container->hasAny(['baz']))->toBeFalse()
            ->and($container->hasAny('baz'))->toBeFalse()
            ->and($container->hasAny('baz', 'biz'))->toBeFalse();
    });

    test('hasAny with a null key asks whether the bag holds anything', function () {
        $container = plainMessageBag();
        $container->add('foo', 'bar');

        expect($container->hasAny(null))->toBeTrue();
    });

    test('isEmpty is true for a fresh bag', function () {
        expect((new MessageBag)->isEmpty())->toBeTrue();
    });

    test('isEmpty is false once a message is added', function () {
        $container = new MessageBag;
        $container->add('foo.bar', 'baz');

        expect($container->isEmpty())->toBeFalse();
    });

    test('isNotEmpty is true once a message is added', function () {
        $container = new MessageBag;
        $container->add('foo.bar', 'baz');

        expect($container->isNotEmpty())->toBeTrue();
    });

    test('isNotEmpty is false for a fresh bag', function () {
        expect((new MessageBag)->isNotEmpty())->toBeFalse();
    });

    test('count counts individual messages, not keys', function () {
        $container = new MessageBag;

        expect($container)->toHaveCount(0);

        $container->add('foo', 'bar');
        $container->add('foo', 'baz');
        $container->add('boom', 'baz');

        expect($container)->toHaveCount(3);
    });

    test('a bag with one message per key counts its keys', function () {
        $container = new MessageBag;
        $container->add('foo', 'bar');
        $container->add('boom', 'baz');

        expect($container)->toHaveCount(2);
    });
});

describe('formatting', function () {
    test('the format applies to first, get and all, and may be overridden per call', function () {
        $container = new MessageBag;
        $container->setFormat('<p>:message</p>');
        $container->add('foo', 'bar');
        $container->add('boom', 'baz');

        expect($container->first('foo'))->toBe('<p>bar</p>')
            ->and($container->get('foo'))->toEqual(['<p>bar</p>'])
            ->and($container->all())->toEqual(['<p>bar</p>', '<p>baz</p>'])
            ->and($container->first('foo', ':message'))->toBe('bar')
            ->and($container->get('foo', ':message'))->toEqual(['bar'])
            ->and($container->all(':message'))->toEqual(['bar', 'baz']);

        $container->setFormat(':key :message');

        expect($container->first('foo'))->toBe('foo bar');
    });

    test('getFormat returns the format that was set', function () {
        expect(plainMessageBag()->getFormat())->toBe(':message');
    });
});

describe('serialization', function () {
    test('toArray returns the raw messages', function () {
        $container = plainMessageBag();
        $container->add('foo', 'bar');
        $container->add('boom', 'baz');

        expect($container->toArray())->toEqual(['foo' => ['bar'], 'boom' => ['baz']]);
    });

    test('toJson encodes the messages', function () {
        $container = plainMessageBag();
        $container->add('foo', 'bar');
        $container->add('boom', 'baz');

        expect($container->toJson())->toBe('{"foo":["bar"],"boom":["baz"]}');
    });

    test('toPrettyJson pretty-prints and accepts extra flags', function () {
        $container = plainMessageBag();
        $container->add('foo', 'bar');
        $container->add('boom', 'baz');
        $container->add('baz', '123');

        $results = $container->toPrettyJson();
        $expected = $container->toJson(JSON_PRETTY_PRINT);

        expect($results)->toBeJson()
            ->and($results)->toBe($expected)
            ->and($results)->toContain("\n")
            ->and($results)->toContain('    ')
            ->and($results)->toContain('"123"');

        $results = $container->toPrettyJson(JSON_NUMERIC_CHECK);

        expect($results)->toContain("\n")
            ->and($results)->toContain('    ')
            ->and($results)->toContain('123')
            ->and($results)->not->toContain('"123"');
    });

    test('casting to string encodes the messages', function () {
        $container = new MessageBag;
        $container->add('foo.bar', 'baz');

        expect((string) $container)->toBe('{"foo.bar":["baz"]}');
    });
});
