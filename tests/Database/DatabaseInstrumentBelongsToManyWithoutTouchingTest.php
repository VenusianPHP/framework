<?php

declare(strict_types=1);

namespace Tests\Database;

use Voyager\Database\Instrument\Builder;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\Relations\BelongsToMany;
use Voyager\Database\Query\Grammars\Grammar;
use Mockery as m;
use stdClass;

test('it will not touch related models when updating child', function () {
    /** @var Article $related */
    $related = m::mock(Article::class)->makePartial();
    $related->shouldReceive('getUpdatedAtColumn')->never();
    $related->shouldReceive('freshTimestampString')->never();

    expect($related::isIgnoringTouch())->toBeFalse();

    Model::withoutTouching(function () use ($related) {
        expect($related::isIgnoringTouch())->toBeTrue();

        $builder = m::mock(Builder::class);
        $builder->shouldReceive('join');
        $parent = m::mock(User::class);

        $parent->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $builder->shouldReceive('getModel')->andReturn($related);
        $builder->shouldReceive('where');
        $builder->shouldReceive('getQuery')->andReturn(
            m::mock(stdClass::class, ['getGrammar' => m::mock(Grammar::class, ['isExpression' => false])])
        );
        $relation = new BelongsToMany($builder, $parent, 'article_users', 'user_id', 'article_id', 'id', 'id');
        $builder->shouldReceive('update')->never();

        $relation->touch();
    });

    expect($related::isIgnoringTouch())->toBeFalse();
});

class User extends Model
{
    protected $table = 'users';
    protected $fillable = ['id', 'email'];

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_user', 'user_id', 'article_id');
    }
}

class Article extends Model
{
    protected $table = 'articles';
    protected $fillable = ['id', 'title'];
    protected $touches = ['user'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'article_user', 'article_id', 'user_id');
    }
}
