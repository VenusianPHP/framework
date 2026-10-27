<?php

use Voyager\NutsAndBolts\DataObjects\Str;
use Venusian\Tests\Log\Fixtures\LogApp;

beforeEach(function () {
    $this->app = LogApp::boot();
    $this->app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    $this->app['config']->set('app.cipher', 'AES-256-CBC');
});
afterEach(fn () => LogApp::tearDown($this->app, $this));

it('round-trips a string through the app encrypter', function (bool $serialize) {
    $encrypted = Str::of('secret')->encrypt($serialize);

    expect((string) $encrypted)->not->toBe('secret')
        ->and((string) $encrypted->decrypt($serialize))->toBe('secret');
})->with(['raw' => false, 'serialized' => true]);
