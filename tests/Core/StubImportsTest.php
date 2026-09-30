<?php

// A stub that imports a class the framework does not have generates code that cannot
// load. Tests\ belongs to the application the stub is written into, not the framework.
it('imports only classes the framework has, in every stub', function () {
    $missing = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src'));

    foreach ($files as $file) {
        if (! str_ends_with($file->getFilename(), '.stub')) {
            continue;
        }

        preg_match_all('/^use ([A-Z][\w\\\\]+);/m', file_get_contents($file->getPathname()), $imports);

        foreach ($imports[1] as $import) {
            if (str_starts_with($import, 'Tests\\')) {
                continue;
            }

            if (! class_exists($import) && ! interface_exists($import) && ! trait_exists($import) && ! enum_exists($import)) {
                $missing[] = $file->getFilename().': '.$import;
            }
        }
    }

    expect($missing)->toBe([]);
});
