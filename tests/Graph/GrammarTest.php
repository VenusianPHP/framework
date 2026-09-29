<?php

use Voyager\Database\Query\Builder;
use Voyager\Database\Query\Processors\Processor;
use Voyager\Database\SQLiteConnection;
use Voyager\Graph\Database\Query\Grammars\Neo4jGrammar;

/** A builder on the Neo4j grammar. Compiling needs no server, so any connection carries it. */
function cypherQuery(string $label = 'Person', string $prefix = ''): Builder
{
    $connection = new SQLiteConnection(fn () => new PDO('sqlite::memory:'), '', $prefix);

    return (new Builder($connection, new Neo4jGrammar($connection), new Processor))->from($label);
}

function namedCypher(Builder $query): array
{
    return $query->getGrammar()->convertParametersToNamed($query->toSql(), $query->getBindings());
}

it('compiles a select as a match on the label', function () {
    $query = cypherQuery()->where('name', 'Ada')->where('age', '>', 30)->orderByDesc('age')->offset(5)->limit(10);

    expect($query->toSql())->toBe('MATCH (n:`Person`) where n.`name` = ? and n.`age` > ? RETURN n ORDER BY n.`age` desc SKIP 5 LIMIT 10')
        ->and($query->getBindings())->toBe(['Ada', 30]);
});

it('projects columns under their property names, qualified or not', function () {
    expect(cypherQuery()->select(['Person.id', 'name', 'age as years'])->toSql())
        ->toBe('MATCH (n:`Person`) RETURN n.`id` AS `id`, n.`name` AS `name`, n.`age` AS `years`')
        ->and(cypherQuery()->distinct()->select('name')->toSql())
        ->toBe('MATCH (n:`Person`) RETURN DISTINCT n.`name` AS `name`');
});

it('compiles aggregates as one aggregate column', function () {
    $count = cypherQuery()->where('age', '>', 1);
    $count->aggregate = ['function' => 'count', 'columns' => ['*']];
    $sum = cypherQuery();
    $sum->aggregate = ['function' => 'sum', 'columns' => ['age']];
    $distinct = cypherQuery()->distinct();
    $distinct->aggregate = ['function' => 'count', 'columns' => ['name']];

    expect($count->toSql())->toBe('MATCH (n:`Person`) where n.`age` > ? RETURN count(n) AS aggregate')
        ->and($sum->toSql())->toBe('MATCH (n:`Person`) RETURN sum(n.`age`) AS aggregate')
        ->and($distinct->toSql())->toBe('MATCH (n:`Person`) RETURN count(DISTINCT n.`name`) AS aggregate');
});

it('compiles in, null, between, column and nested wheres', function () {
    $query = cypherQuery()
        ->whereIn('id', ['a', 'b'])
        ->whereNotIn('id', ['c'])
        ->whereNull('deleted_at')
        ->orWhereNotNull('born')
        ->whereBetween('age', [18, 65])
        ->whereNotBetween('age', [30, 40])
        ->whereColumn('updated_at', '>', 'created_at')
        ->where(fn (Builder $nested) => $nested->where('a', 1)->orWhere('b', 2))
        ->whereIn('id', []);

    expect($query->toSql())->toBe(
        'MATCH (n:`Person`) where n.`id` IN [?, ?] and NOT n.`id` IN [?] and n.`deleted_at` IS NULL or n.`born` IS NOT NULL'
        .' and (? <= n.`age` <= ?) and NOT (? <= n.`age` <= ?) and n.`updated_at` > n.`created_at`'
        .' and (n.`a` = ? or n.`b` = ?) and false RETURN n'
    )->and($query->getBindings())->toBe(['a', 'b', 'c', 18, 65, 30, 40, 1, 2]);
});

it('compiles like as a regex over the bound pattern, escaping the pattern\'s regex characters', function () {
    $query = cypherQuery()->where('name', 'like', 'A%')->whereNotLike('name', '%x_', caseSensitive: true);

    expect($query->toSql())->toContain("n.`name` =~ '(?i)' + replace(replace(")
        ->toContain("NOT (n.`name` =~ replace(replace(")
        ->toContain("'%', '.*'), '_', '.')")
        ->and($query->getBindings())->toBe(['A%', '%x_']);
});

it('names placeholders, passing over any ? inside a string or a backticked name', function () {
    $query = cypherQuery('Why?')->where('name', 'like', 'Who?%')->where('age', 3);

    [$cypher, $named] = namedCypher($query);

    expect($cypher)->toStartWith('MATCH (n:`Why?`) where n.`name` =~ \'(?i)\' + ')
        ->toContain("replace(\$p0, '\\\\', '\\\\\\\\')")
        ->toContain("'?', '\\\\?'")
        ->toEndWith('and n.`age` = $p1 RETURN n')
        ->and($named)->toBe(['p0' => 'Who?%', 'p1' => 3]);
});

it('compiles writes that report the nodes they touched', function () {
    $insert = cypherQuery()->getGrammar()->compileInsert(cypherQuery(), [['age' => 1, 'name' => 'Ada'], ['age' => 2, 'name' => 'Bob']]);
    $update = cypherQuery()->where('id', 'a');
    $updateSql = $update->getGrammar()->compileUpdate($update, ['name' => 'Ada', 'tags' => ['x', 'y']]);
    $updateBindings = $update->getGrammar()->prepareBindingsForUpdate($update->getRawBindings(), ['name' => 'Ada', 'tags' => ['x', 'y']]);
    $windowed = cypherQuery()->orderBy('age')->limit(2);

    expect($insert)->toBe('UNWIND [{`age`: ?, `name`: ?}, {`age`: ?, `name`: ?}] AS row CREATE (n:`Person`) SET n = row')
        ->and($updateSql)->toBe('MATCH (n:`Person`) where n.`id` = ? SET n.`name` = ?, n.`tags` = ? RETURN count(n) AS affected')
        ->and($updateBindings)->toBe(['a', 'Ada', ['x', 'y']])
        ->and($windowed->getGrammar()->compileDelete($windowed))
        ->toBe('MATCH (n:`Person`) WITH n ORDER BY n.`age` asc LIMIT 2 DETACH DELETE n RETURN count(n) AS affected')
        ->and(cypherQuery()->getGrammar()->compileTruncate(cypherQuery()))->toBe(['MATCH (n:`Person`) DETACH DELETE n' => []]);
});

it('compiles an upsert as a merge on the unique properties', function () {
    $query = cypherQuery();

    expect($query->getGrammar()->compileUpsert($query, [['id' => 'a', 'name' => 'Ada']], ['id'], ['name']))
        ->toBe('UNWIND [{`id`: ?, `name`: ?}] AS row MERGE (n:`Person` {`id`: row.`id`}) ON CREATE SET n = row ON MATCH SET n.`name` = row.`name` RETURN count(n) AS affected');
});

it('compiles exists and random order', function () {
    expect(cypherQuery()->where('id', 'a')->getGrammar()->compileExists(cypherQuery()->where('id', 'a')))
        ->toBe('CALL { MATCH (n:`Person`) where n.`id` = ? RETURN n } RETURN count(*) > 0 AS `exists`')
        ->and(cypherQuery()->inRandomOrder()->toSql())->toBe('MATCH (n:`Person`) RETURN n ORDER BY rand()');
});

it('prefixes the label with the connection\'s prefix', function () {
    expect(cypherQuery('Person', 'Test')->toSql())->toBe('MATCH (n:`TestPerson`) RETURN n');
});

it('refuses clauses a label match has no form for, by name', function (Closure $build, string $message) {
    expect(fn () => $build(cypherQuery())->toSql())->toThrow(LogicException::class, $message);
})->with([
    'join' => [fn (Builder $q) => $q->join('Other', 'Person.id', '=', 'Other.id'), 'join() has no Cypher form'],
    'groupBy' => [fn (Builder $q) => $q->groupBy('name'), 'groupBy() has no Cypher form'],
    'having' => [fn (Builder $q) => $q->having('age', '>', 1), 'having() has no Cypher form'],
    'lock' => [fn (Builder $q) => $q->lockForUpdate(), 'lock() has no Cypher form'],
    'whereDate' => [fn (Builder $q) => $q->whereDate('born', '2020-01-01'), 'whereDate() has no Cypher form'],
    'whereExists' => [fn (Builder $q) => $q->whereExists(fn ($s) => $s->from('Other')), 'whereExists() has no Cypher form'],
]);

it('refuses an operator Cypher has no spelling for', function () {
    expect(fn () => cypherQuery()->where('name', 'regexp', 'A.*')->toSql())
        ->toThrow(InvalidArgumentException::class, 'The regexp operator has no Cypher form');
});
