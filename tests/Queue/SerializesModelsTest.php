<?php

use Voyager\Queue\Attributes\WithoutRelations;
use Voyager\NutsAndBolts\Reflector;
use Venusian\Tests\Queue\Fixtures\RelationlessEvent;

it('serializes and restores a class marked #[WithoutRelations]', function () {
    $restored = unserialize(serialize(new RelationlessEvent('kept')));

    expect($restored)->toBeInstanceOf(RelationlessEvent::class)
        ->and($restored->line)->toBe('kept');
});

it('finds a class attribute, and on parents only when asked to ascend', function () {
    $child = new class('x') extends RelationlessEvent {};

    expect(Reflector::getClassAttribute(RelationlessEvent::class, WithoutRelations::class))->toBeInstanceOf(WithoutRelations::class)
        ->and(Reflector::getClassAttribute($child, WithoutRelations::class))->toBeNull()
        ->and(Reflector::getClassAttribute($child, WithoutRelations::class, ascend: true))->toBeInstanceOf(WithoutRelations::class);
});

it('declares the PHP 8.5 polyfill the Reflection component calls array_first() from', function () {
    $manifest = json_decode(file_get_contents(dirname(__DIR__, 2).'/src/Voyager/Reflection/composer.json'), true);

    expect($manifest['require'])->toHaveKey('symfony/polyfill-php85');
});

describe('on models', function () {
    beforeEach(function () {
        $this->app = \Venusian\Tests\Database\Fixtures\DatabaseApp::boot();
        $this->app['config']->set('database.default', 'sqlite');
        \Venusian\Tests\Database\Fixtures\Author::$table_name = \Venusian\Tests\Database\Fixtures\DatabaseApp::table($this->app, 'sqlite', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        \Venusian\Tests\Database\Fixtures\Book::$table_name = \Venusian\Tests\Database\Fixtures\DatabaseApp::table($this->app, 'sqlite', function ($table) {
            $table->id();
            $table->foreignId('author_id');
            $table->string('title');
            $table->timestamps();
        });
        $this->book = \Venusian\Tests\Database\Fixtures\Author::create(['name' => 'Octavia'])
            ->books()->create(['title' => 'Kindred'])->load('author');
    });
    afterEach(fn () => \Venusian\Tests\Database\Fixtures\DatabaseApp::tearDown($this->app, $this));

    /** The relations the serialized model identifier names. */
    function serializedRelations(object $carrier): array
    {
        return $carrier->__serialize()['model']->relations;
    }

    it('serializes a Queueable job that also uses SerializesModels, keeping relations without the attribute', function () {
        $carrier = new Venusian\Tests\Queue\Fixtures\ModelCarrier($this->book);

        expect(serializedRelations($carrier))->toBe(['author'])
            ->and(unserialize(serialize($carrier))->model->title)->toBe('Kindred');
    });

    it('drops relations for #[WithoutRelations] on the class itself', function () {
        expect(serializedRelations(new Venusian\Tests\Queue\Fixtures\RelationlessCarrier($this->book)))->toBe([]);
    });

    it('drops relations for #[WithoutRelations] on a parent class', function () {
        expect(serializedRelations(new Venusian\Tests\Queue\Fixtures\InheritedRelationlessCarrier($this->book)))->toBe([]);
    });

    it('keeps one SerializesModels trait', function () {
        expect(trait_exists('Voyager\Queue\Concerns\SerializesModels'))->toBeFalse();
    });
});
