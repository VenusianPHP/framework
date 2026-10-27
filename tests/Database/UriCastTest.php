<?php

use Voyager\NutsAndBolts\Uri;
use Voyager\Database\Schema\Blueprint;
use Venusian\Tests\Database\Fixtures\Bookmark;
use Venusian\Tests\Database\Fixtures\DatabaseApp;

beforeEach(function () {
    $this->app = DatabaseApp::boot();
    $this->app['db']->connection('sqlite')->getSchemaBuilder()->create('bookmarks', function (Blueprint $table) {
        $table->id();
        $table->string('url')->nullable();
    });
    $this->app['config']->set('database.default', 'sqlite');
});
afterEach(fn () => DatabaseApp::tearDown($this->app, $this));

it('reads an AsUri attribute back as a Uri and stores it as its string', function () {
    $bookmark = Bookmark::create(['url' => Uri::of('https://venusian.dev/docs?page=2')]);

    $fresh = Bookmark::find($bookmark->id);

    expect($fresh->url)->toBeInstanceOf(Uri::class)
        ->and($fresh->url->host())->toBe('venusian.dev')
        ->and($fresh->url->query()->get('page'))->toBe('2')
        ->and($fresh->getRawOriginal('url'))->toBe('https://venusian.dev/docs?page=2');
});

it('keeps a null AsUri attribute null', function () {
    $bookmark = Bookmark::create(['url' => null]);

    expect(Bookmark::find($bookmark->id)->url)->toBeNull();
});
