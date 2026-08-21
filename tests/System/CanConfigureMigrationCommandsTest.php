<?php

use Tests\System\Stubs\CanConfigureMigrationCommandsTestMockClass;

/** Invoke the trait's protected migrateFreshUsing() on the given object. */
function migrateFreshUsing(object $target): array
{
    return (new ReflectionMethod(get_class($target), 'migrateFreshUsing'))->invoke($target);
}

test('migrateFreshUsing drops nothing and seeds nothing by default', function () {
    $traitObject = new CanConfigureMigrationCommandsTestMockClass;

    expect(migrateFreshUsing($traitObject))->toEqual([
        '--drop-views' => false,
        '--drop-types' => false,
        '--seed' => false,
    ]);
});

test('migrateFreshUsing reflects the dropViews and dropTypes properties', function () {
    $traitObject = new CanConfigureMigrationCommandsTestMockClass;

    $traitObject->dropViews = true;

    expect(migrateFreshUsing($traitObject))->toEqual([
        '--drop-views' => true,
        '--drop-types' => false,
        '--seed' => false,
    ]);

    $traitObject->dropViews = false;
    $traitObject->dropTypes = true;

    expect(migrateFreshUsing($traitObject))->toEqual([
        '--drop-views' => false,
        '--drop-types' => true,
        '--seed' => false,
    ]);

    $traitObject->dropViews = true;
    $traitObject->dropTypes = true;

    expect(migrateFreshUsing($traitObject))->toEqual([
        '--drop-views' => true,
        '--drop-types' => true,
        '--seed' => false,
    ]);
});
