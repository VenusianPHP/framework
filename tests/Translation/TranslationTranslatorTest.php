<?php

use Tests\Translation\Fixtures\Enums\Bar;
use Tests\Translation\Fixtures\Enums\Baz;
use Tests\Translation\Fixtures\Enums\Foo;
use Voyager\Contracts\Translation\Loader;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\Translation\MessageSelector;
use Voyager\Translation\Translator;

function translatorLoader()
{
    return Mockery::mock(Loader::class);
}

test('has method returns false when returned translation is null', function () {
    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get'])->setConstructorArgs([translatorLoader(), 'en'])->getMock();
    $t->expects($this->once())->method('get')->with($this->equalTo('foo'), $this->equalTo([]), $this->equalTo('bar'))->willReturn('foo');
    expect($t->has('foo', 'bar'))->toBeFalse();

    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get'])->setConstructorArgs([translatorLoader(), 'en', 'sp'])->getMock();
    $t->expects($this->once())->method('get')->with($this->equalTo('foo'), $this->equalTo([]), $this->equalTo('bar'))->willReturn('bar');
    expect($t->has('foo', 'bar'))->toBeTrue();

    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get'])->setConstructorArgs([translatorLoader(), 'en'])->getMock();
    $t->expects($this->once())->method('get')->with($this->equalTo('foo'), $this->equalTo([]), $this->equalTo('bar'), false)->willReturn('bar');
    expect($t->hasForLocale('foo', 'bar'))->toBeTrue();

    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get'])->setConstructorArgs([translatorLoader(), 'en'])->getMock();
    $t->expects($this->once())->method('get')->with($this->equalTo('foo'), $this->equalTo([]), $this->equalTo('bar'), false)->willReturn('foo');
    expect($t->hasForLocale('foo', 'bar'))->toBeFalse();

    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'foo', '*')->andReturn(['foo' => 'bar']);
    expect($t->hasForLocale('foo'))->toBeTrue();

    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'foo', '*')->andReturn([]);
    expect($t->hasForLocale('foo'))->toBeFalse();
});

test('get method properly loads and retrieves item', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'bar', 'foo')->andReturn(['foo' => 'foo', 'baz' => 'breeze :foo', 'qux' => ['tree :foo', 'breeze :foo']]);

    expect($t->get('foo::bar.qux', ['foo' => 'bar'], 'en'))->toEqual(['tree bar', 'breeze bar'])
        ->and($t->get('foo::bar.baz', ['foo' => 'bar'], 'en'))->toBe('breeze bar')
        ->and($t->get('foo::bar.foo'))->toBe('foo');
});

test('get method properly loads and retrieves array item', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'bar', 'foo')->andReturn(['foo' => 'foo', 'baz' => 'breeze :foo', 'qux' => ['tree :foo', 'breeze :foo', 'beep' => ['rock' => 'tree :foo']]]);

    expect($t->get('foo::bar', ['foo' => 'bar'], 'en'))->toEqual(['foo' => 'foo', 'baz' => 'breeze bar', 'qux' => ['tree bar', 'breeze bar', 'beep' => ['rock' => 'tree bar']]])
        ->and($t->get('foo::bar.baz', ['foo' => 'bar'], 'en'))->toBe('breeze bar')
        ->and($t->get('foo::bar.foo'))->toBe('foo');
});

test('get method for non existing returns same key', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'bar', 'foo')->andReturn(['foo' => 'foo', 'baz' => 'breeze :foo', 'qux' => ['tree :foo', 'breeze :foo']]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'unknown', 'foo')->andReturn([]);

    expect($t->get('foo::unknown', ['foo' => 'bar'], 'en'))->toBe('foo::unknown')
        ->and($t->get('foo::bar.unknown', ['foo' => 'bar'], 'en'))->toBe('foo::bar.unknown')
        ->and($t->get('foo::unknown.bar'))->toBe('foo::unknown.bar');
});

test('trans method properly loads and retrieves item with HTML in the message', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'foo', '*')->andReturn(['bar' => 'breeze <p>test</p>']);

    expect($t->get('foo.bar', [], 'en'))->toBe('breeze <p>test</p>');
});

test('get method properly loads and retrieves item with capitalization', function () {
    $t = $this->getMockBuilder(Translator::class)->onlyMethods([])->setConstructorArgs([translatorLoader(), 'en'])->getMock();
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'bar', 'foo')->andReturn(['foo' => 'foo', 'baz' => 'breeze :0 :Foo :BAR']);

    expect($t->get('foo::bar.baz', ['john', 'foo' => 'bar', 'bar' => 'foo'], 'en'))->toBe('breeze john Bar FOO')
        ->and($t->get('foo::bar.foo'))->toBe('foo');
});

test('get method properly loads and retrieves item with longest replacements first', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'bar', 'foo')->andReturn(['foo' => 'foo', 'baz' => 'breeze :foo :foobar']);

    expect($t->get('foo::bar.baz', ['foo' => 'bar', 'foobar' => 'taylor'], 'en'))->toBe('breeze bar taylor')
        ->and($t->get('foo::bar.baz', ['foo' => 'foo bar baz', 'foobar' => 'taylor'], 'en'))->toBe('breeze foo bar baz taylor')
        ->and($t->get('foo::bar.foo'))->toBe('foo');
});

test('get method properly loads and retrieves item for fallback', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->setFallback('lv');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'bar', 'foo')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('lv', 'bar', 'foo')->andReturn(['foo' => 'foo', 'baz' => 'breeze :foo']);

    expect($t->get('foo::bar.baz', ['foo' => 'bar'], 'en'))->toBe('breeze bar')
        ->and($t->get('foo::bar.foo'))->toBe('foo');
});

test('get does not call getLine twice for missing key when locale matches fallback', function () {
    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['getLine'])->setConstructorArgs([translatorLoader(), 'en'])->getMock();
    $t->setFallback('en');
    $t->getLoader()->shouldReceive('load')->with('en', '*', '*')->andReturn([]);

    $t->expects($this->once())->method('getLine')->with('*', 'messages', 'en', 'test', [])->willReturn(null);

    $t->get('messages.test', [], 'en');
});

test('get method properly loads and retrieves item for global namespace', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'foo', '*')->andReturn(['bar' => 'breeze :foo']);

    expect($t->get('foo.bar', ['foo' => 'bar']))->toBe('breeze bar');
});

test('choice method properly loads and retrieves item for an int', function () {
    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get', 'localeForChoice'])->setConstructorArgs([translatorLoader(), 'en'])->getMock();
    $t->expects($this->once())->method('get')->with($this->equalTo('foo'), $this->equalTo([]), $this->equalTo('en'))->willReturn('line');
    $t->expects($this->once())->method('localeForChoice')->with($this->equalTo('foo'), $this->equalTo(null))->willReturn('en');
    $t->setSelector($selector = Mockery::mock(MessageSelector::class));
    $selector->shouldReceive('choose')->once()->with('line', 10, 'en')->andReturn('choiced');

    $t->choice('foo', 10, ['replace']);
});

test('choice method properly loads and retrieves item for a float', function () {
    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get', 'localeForChoice'])->setConstructorArgs([translatorLoader(), 'en'])->getMock();
    $t->expects($this->once())->method('get')->with($this->equalTo('foo'), $this->equalTo([]), $this->equalTo('en'))->willReturn('line');
    $t->expects($this->once())->method('localeForChoice')->with($this->equalTo('foo'), $this->equalTo(null))->willReturn('en');
    $t->setSelector($selector = Mockery::mock(MessageSelector::class));
    $selector->shouldReceive('choose')->once()->with('line', 1.2, 'en')->andReturn('choiced');

    $t->choice('foo', 1.2, ['replace']);
});

test('choice method properly counts collections and loads and retrieves item', function () {
    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get', 'localeForChoice'])->setConstructorArgs([translatorLoader(), 'en'])->getMock();
    $t->expects($this->exactly(2))->method('get')->with($this->equalTo('foo'), $this->equalTo([]), $this->equalTo('en'))->willReturn('line');
    $t->expects($this->exactly(2))->method('localeForChoice')->with($this->equalTo('foo'), $this->equalTo(null))->willReturn('en');
    $t->setSelector($selector = Mockery::mock(MessageSelector::class));
    $selector->shouldReceive('choose')->twice()->with('line', 3, 'en')->andReturn('choiced');

    $values = ['foo', 'bar', 'baz'];
    $t->choice('foo', $values, ['replace']);

    $values = new Collection(['foo', 'bar', 'baz']);
    $t->choice('foo', $values, ['replace']);
});

test('choice method properly selects locale for choose', function () {
    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get', 'hasForLocale'])->setConstructorArgs([translatorLoader(), 'cs'])->getMock();
    $t->setFallback('en');
    $t->expects($this->once())->method('get')->with($this->equalTo('foo'), $this->equalTo([]), $this->equalTo('en'))->willReturn('line');
    $t->expects($this->once())->method('hasForLocale')->with($this->equalTo('foo'), $this->equalTo('cs'))->willReturn(false);
    $t->setSelector($selector = Mockery::mock(MessageSelector::class));
    $selector->shouldReceive('choose')->once()->with('line', 10, 'en')->andReturn('choiced');

    $t->choice('foo', 10, ['replace']);
});

test('choice method properly uses custom count replacement', function () {
    $t = $this->getMockBuilder(Translator::class)->onlyMethods(['get', 'localeForChoice'])->setConstructorArgs([translatorLoader(), 'en'])->getMock();
    $t->expects($this->once())->method('get')->with($this->equalTo(':count foos'), $this->equalTo([]), $this->equalTo('en'))->willReturn('{1} :count foos|[2,*] :count foos');
    $t->expects($this->once())->method('localeForChoice')->with($this->equalTo(':count foos'), $this->equalTo(null))->willReturn('en');
    $t->setSelector($selector = Mockery::mock(MessageSelector::class));
    $selector->shouldReceive('choose')->once()->with('{1} :count foos|[2,*] :count foos', 1234, 'en')->andReturn(':count foos');

    expect($t->choice(':count foos', 1234, ['count' => '1,234']))->toEqual('1,234 foos');
});

test('get json', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn(['foo' => 'one']);

    expect($t->get('foo'))->toBe('one');
});

test('get json replaces', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn(['foo :i:c :u' => 'bar :i:c :u']);

    expect($t->get('foo :i:c :u', ['i' => 'one', 'c' => 'two', 'u' => 'three']))->toBe('bar onetwo three');
});

test('get json has atomic replacements', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn(['Hello :foo!' => 'Hello :foo!']);

    expect($t->get('Hello :foo!', ['foo' => 'baz:bar', 'bar' => 'abcdef']))->toBe('Hello baz:bar!');
});

test('get json replaces for associative input', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn(['foo :i :c' => 'bar :i :c']);

    expect($t->get('foo :i :c', ['i' => 'eye', 'c' => 'see']))->toBe('bar eye see');
});

test('get json preserves order', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn(['to :name I give :greeting' => ':greeting :name']);

    expect($t->get('to :name I give :greeting', ['name' => 'David', 'greeting' => 'Greetings']))->toBe('Greetings David');
});

test('get json for non existing json key looks for regular keys', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'foo', '*')->andReturn(['bar' => 'one']);

    expect($t->get('foo.bar'))->toBe('one');
});

test('get json for non existing json key looks for regular keys and replace', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'foo', '*')->andReturn(['bar' => 'one :message']);

    expect($t->get('foo.bar', ['message' => 'two']))->toBe('one two');
});

test('get json for non existing returns same key', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'Foo that bar', '*')->andReturn([]);

    expect($t->get('Foo that bar'))->toBe('Foo that bar');
});

test('get json for non existing returns same key and replaces', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'foo :message', '*')->andReturn([]);

    expect($t->get('foo :message', ['message' => 'baz']))->toBe('foo baz');
});

test('empty fallbacks', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'foo :message', '*')->andReturn([]);

    expect($t->get('foo :message', ['message' => null]))->toBe('foo ');
});

test('get json replaces with stringable', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()
        ->shouldReceive('load')
        ->once()
        ->with('en', '*', '*')
        ->andReturn(['test' => 'the date is :date']);

    $date = Carbon::createFromTimestamp(0);

    expect($t->get('test', ['date' => $date]))->toBe('the date is 1970-01-01 00:00:00');

    $t->stringable(function (Carbon $carbon) {
        return $carbon->format('jS M Y');
    });

    expect($t->get('test', ['date' => $date]))->toBe('the date is 1st Jan 1970');
});

test('get json replaces with enums', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->getLoader()
        ->shouldReceive('load')
        ->once()
        ->with('en', '*', '*')
        ->andReturn([
            'string_backed_enum' => 'Laravel 12 was released in :month 2025',
            'int_backed_enum' => 'Stay tuned for Laravel v:version',
            'unit_enum' => ':person gets excited about every new Laravel release',
        ]);

    expect($t->get('string_backed_enum', ['month' => Baz::February]))->toBe('Laravel 12 was released in February 2025')
        ->and($t->get('int_backed_enum', ['version' => Bar::Thirteen]))->toBe('Stay tuned for Laravel v13')
        ->and($t->get('unit_enum', ['person' => Foo::Hosni]))->toBe('Hosni gets excited about every new Laravel release');
});

test('tag replacements', function () {
    $t = new Translator(translatorLoader(), 'en');

    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'We have some nice <docs-link>documentation</docs-link>', '*')->andReturn([]);

    expect($t->get(
        'We have some nice <docs-link>documentation</docs-link>',
        [
            'docs-link' => fn ($children) => "<a href=\"https://laravel.com/docs\">$children</a>",
        ]
    ))->toBe('We have some nice <a href="https://laravel.com/docs">documentation</a>');
});

test('tag replacements handle multiple of same tag', function () {
    $t = new Translator(translatorLoader(), 'en');

    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', '<bold-this>bold</bold-this> something else <bold-this>also bold</bold-this>', '*')->andReturn([]);

    expect($t->get(
        '<bold-this>bold</bold-this> something else <bold-this>also bold</bold-this>',
        [
            'bold-this' => fn ($children) => "<b>$children</b>",
        ]
    ))->toBe('<b>bold</b> something else <b>also bold</b>');
});

test('determine locales using method', function () {
    $t = new Translator(translatorLoader(), 'en');
    $t->determineLocalesUsing(function ($locales) {
        expect($locales)->toBe(['en']);

        return ['en', 'lz'];
    });
    $t->getLoader()->shouldReceive('load')->once()->with('en', '*', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('en', 'foo', '*')->andReturn([]);
    $t->getLoader()->shouldReceive('load')->once()->with('lz', 'foo', '*')->andReturn([]);

    expect($t->get('foo'))->toBe('foo');
});
