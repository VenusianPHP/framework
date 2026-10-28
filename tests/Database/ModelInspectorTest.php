<?php

use Voyager\Database\Instrument\JsonEncodingException;
use Voyager\Database\Instrument\ModelInspector;
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

it('inspects a model without reporting a JSON resource Venusian does not have', function () {
    $info = $this->app->make(ModelInspector::class)->inspect(Bookmark::class);

    expect($info['class'])->toBe(Bookmark::class)
        ->and($info)->not->toHaveKey('resource');
});

it('has no resource-encoding exception, since there is no JSON resource class', function () {
    expect(method_exists(JsonEncodingException::class, 'forResource'))->toBeFalse();
});
