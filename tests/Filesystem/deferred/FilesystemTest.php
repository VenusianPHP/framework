<?php

use Voyager\Contracts\Filesystem\FileNotFoundException;
use Voyager\Filesystem\Filesystem;
use Voyager\NutsAndBolts\LazyCollection;
use Voyager\Testing\Assert;
use Mockery as m;

use function Orchestra\Testbench\terminate;

/**
 * @param  string  $file
 * @return int
 */
function getFilePermissions($file)
{
    $filePerms = fileperms($file);
    $filePerms = substr(sprintf('%o', $filePerms), -3);

    return (int) base_convert($filePerms, 8, 10);
}

$tempDir = null;

beforeAll(function () use (&$tempDir) {
    $tempDir = sys_get_temp_dir().'/tmp';
    mkdir($tempDir);
});

afterAll(function () use (&$tempDir) {
    $files = new Filesystem;
    $files->deleteDirectory($tempDir);
    $tempDir = null;
});

afterEach(function () use (&$tempDir) {
    $files = new Filesystem;
    $files->deleteDirectory($tempDir, $preserve = true);
});

test('get retrieves files', function () use (&$tempDir) {
    file_put_contents($tempDir.'/file.txt', 'Hello World');
    $files = new Filesystem;
    expect($files->get($tempDir.'/file.txt'))->toBe('Hello World');
});

test('put stores files', function () use (&$tempDir) {
    $files = new Filesystem;
    $files->put($tempDir.'/file.txt', 'Hello World');
    expect(file_get_contents($tempDir.'/file.txt'))->toBe('Hello World');
});

test('lines reads a file into a lazy collection of lines', function () use (&$tempDir) {
    $path = $tempDir.'/file.txt';

    $contents = ' '.PHP_EOL.' spaces around '.PHP_EOL.PHP_EOL.'Line 2'.PHP_EOL.'1 trailing empty line ->'.PHP_EOL.PHP_EOL;
    file_put_contents($path, $contents);

    $files = new Filesystem;
    expect($files->lines($path))->toBeInstanceOf(LazyCollection::class);

    expect($files->lines($path)->all())->toBe(
        [' ', ' spaces around ', '', 'Line 2', '1 trailing empty line ->', '', '']
    );

    // an empty file:
    ftruncate(fopen($path, 'w'), 0);
    expect($files->lines($path)->all())->toBe(['']);
});

test('lines throws for a nonexistent file', function () {
    (new Filesystem)->lines(__DIR__.'/unknown-file.txt');
})->throws(FileNotFoundException::class, 'File does not exist at path '.__DIR__.'/unknown-file.txt.');

test('replace creates a file', function () use (&$tempDir) {
    $tempFile = $tempDir.'/file.txt';

    $filesystem = new Filesystem;

    $filesystem->replace($tempFile, 'Hello World');
    expect(file_get_contents($tempFile))->toBe('Hello World');
});

test('replaceInFile correctly replaces text', function () use (&$tempDir) {
    $tempFile = $tempDir.'/file.txt';

    $filesystem = new Filesystem;

    $filesystem->put($tempFile, 'Hello World');
    $filesystem->replaceInFile('Hello World', 'Hello Taylor', $tempFile);
    expect(file_get_contents($tempFile))->toBe('Hello Taylor');
});

test('replace preserves permissions through a unix symlink', function () use (&$tempDir) {
    $tempFile = $tempDir.'/file.txt';
    $symlinkDir = $tempDir.'/symlink_dir';
    $symlink = "{$symlinkDir}/symlink.txt";

    mkdir($symlinkDir);
    symlink($tempFile, $symlink);

    // Prevent changes to symlink_dir
    chmod($symlinkDir, 0555);

    // Test with a weird non-standard umask.
    $umask = 0131;
    $originalUmask = umask($umask);

    $filesystem = new Filesystem;

    // Test replacing non-existent file.
    $filesystem->replace($tempFile, 'Hello World');
    expect(file_get_contents($tempFile))->toBe('Hello World');
    expect(0777 - getFilePermissions($tempFile))->toEqual($umask);

    // Test replacing existing file.
    $filesystem->replace($tempFile, 'Something Else');
    expect(file_get_contents($tempFile))->toBe('Something Else');
    expect(0777 - getFilePermissions($tempFile))->toEqual($umask);

    // Test replacing symlinked file.
    $filesystem->replace($symlink, 'Yet Something Else Again');
    expect(file_get_contents($tempFile))->toBe('Yet Something Else Again');
    expect(0777 - getFilePermissions($tempFile))->toEqual($umask);

    umask($originalUmask);

    // Reset changes to symlink_dir
    chmod($symlinkDir, 0777 - $originalUmask);
})->skip(fn () => ! in_array(PHP_OS_FAMILY, ['Linux', 'Darwin'], true), 'Requires Linux or Darwin.');

test('chmod sets file permissions', function () use (&$tempDir) {
    file_put_contents($tempDir.'/file.txt', 'Hello World');
    $files = new Filesystem;
    $files->chmod($tempDir.'/file.txt', 0755);
    $filePermission = substr(sprintf('%o', fileperms($tempDir.'/file.txt')), -4);
    $expectedPermissions = DIRECTORY_SEPARATOR === '\\' ? '0666' : '0755';
    expect($filePermission)->toEqual($expectedPermissions);
});

test('chmod gets file permissions', function () use (&$tempDir) {
    file_put_contents($tempDir.'/file.txt', 'Hello World');
    chmod($tempDir.'/file.txt', 0755);

    $files = new Filesystem;
    $filePermission = $files->chmod($tempDir.'/file.txt');
    $expectedPermissions = DIRECTORY_SEPARATOR === '\\' ? '0666' : '0755';
    expect($filePermission)->toEqual($expectedPermissions);
});

test('delete removes files', function () use (&$tempDir) {
    file_put_contents($tempDir.'/file1.txt', 'Hello World');
    file_put_contents($tempDir.'/file2.txt', 'Hello World');
    file_put_contents($tempDir.'/file3.txt', 'Hello World');

    $files = new Filesystem;
    $files->delete($tempDir.'/file1.txt');
    Assert::assertFileDoesNotExist($tempDir.'/file1.txt');

    $files->delete([$tempDir.'/file2.txt', $tempDir.'/file3.txt']);
    Assert::assertFileDoesNotExist($tempDir.'/file2.txt');
    Assert::assertFileDoesNotExist($tempDir.'/file3.txt');
});

test('prepend adds content to an existing file', function () use (&$tempDir) {
    $files = new Filesystem;
    $files->put($tempDir.'/file.txt', 'World');
    $files->prepend($tempDir.'/file.txt', 'Hello ');
    expect(file_get_contents($tempDir.'/file.txt'))->toBe('Hello World');
});

test('prepend creates a new file', function () use (&$tempDir) {
    $files = new Filesystem;
    $files->prepend($tempDir.'/file.txt', 'Hello World');
    expect(file_get_contents($tempDir.'/file.txt'))->toBe('Hello World');
});

test('missing reports a nonexistent file', function () use (&$tempDir) {
    $files = new Filesystem;
    expect($files->missing($tempDir.'/file.txt'))->toBeTrue();
});

test('deleteDirectory removes a directory and its contents', function () use (&$tempDir) {
    mkdir($tempDir.'/foo');
    file_put_contents($tempDir.'/foo/file.txt', 'Hello World');
    $files = new Filesystem;
    $files->deleteDirectory($tempDir.'/foo');
    Assert::assertDirectoryDoesNotExist($tempDir.'/foo');
    Assert::assertFileDoesNotExist($tempDir.'/foo/file.txt');
});

test('deleteDirectory returns false when the path is not a directory', function () use (&$tempDir) {
    mkdir($tempDir.'/bar');
    file_put_contents($tempDir.'/bar/file.txt', 'Hello World');
    $files = new Filesystem;
    expect($files->deleteDirectory($tempDir.'/bar/file.txt'))->toBeFalse();
});

test('cleanDirectory empties a directory without removing it', function () use (&$tempDir) {
    mkdir($tempDir.'/baz');
    file_put_contents($tempDir.'/baz/file.txt', 'Hello World');
    $files = new Filesystem;
    $files->cleanDirectory($tempDir.'/baz');
    expect($tempDir.'/baz')->toBeDirectory();
    Assert::assertFileDoesNotExist($tempDir.'/baz/file.txt');
});

test('it is macroable', function () use (&$tempDir) {
    file_put_contents($tempDir.'/foo.txt', 'Hello World');
    $files = new Filesystem;
    $files->macro('getFoo', function () use ($files, $tempDir) {
        return $files->get($tempDir.'/foo.txt');
    });
    expect($files->getFoo())->toBe('Hello World');
});

test('files lists the files in a directory', function () use (&$tempDir) {
    mkdir($tempDir.'/views');
    file_put_contents($tempDir.'/views/1.txt', '1');
    file_put_contents($tempDir.'/views/2.txt', '2');
    mkdir($tempDir.'/views/_layouts');
    $files = new Filesystem;
    $results = $files->files($tempDir.'/views');
    expect($results[0])->toBeInstanceOf(SplFileInfo::class);
    expect($results[1])->toBeInstanceOf(SplFileInfo::class);
    unset($files);
});

test('copyDirectory returns false when the source is not a directory', function () use (&$tempDir) {
    $files = new Filesystem;
    expect($files->copyDirectory($tempDir.'/breeze/boom/foo/bar/baz', $tempDir))->toBeFalse();
});

test('copyDirectory copies an entire directory', function () use (&$tempDir) {
    mkdir($tempDir.'/tmp', 0777, true);
    file_put_contents($tempDir.'/tmp/foo.txt', '');
    file_put_contents($tempDir.'/tmp/bar.txt', '');
    mkdir($tempDir.'/tmp/nested', 0777, true);
    file_put_contents($tempDir.'/tmp/nested/baz.txt', '');

    $files = new Filesystem;
    $files->copyDirectory($tempDir.'/tmp', $tempDir.'/tmp2');
    expect($tempDir.'/tmp2')->toBeDirectory();
    expect($tempDir.'/tmp2/foo.txt')->toBeFile();
    expect($tempDir.'/tmp2/bar.txt')->toBeFile();
    expect($tempDir.'/tmp2/nested')->toBeDirectory();
    expect($tempDir.'/tmp2/nested/baz.txt')->toBeFile();
});

test('moveDirectory moves an entire directory', function () use (&$tempDir) {
    mkdir($tempDir.'/tmp2', 0777, true);
    file_put_contents($tempDir.'/tmp2/foo.txt', '');
    file_put_contents($tempDir.'/tmp2/bar.txt', '');
    mkdir($tempDir.'/tmp2/nested', 0777, true);
    file_put_contents($tempDir.'/tmp2/nested/baz.txt', '');

    $files = new Filesystem;
    $files->moveDirectory($tempDir.'/tmp2', $tempDir.'/tmp3');
    expect($tempDir.'/tmp3')->toBeDirectory();
    expect($tempDir.'/tmp3/foo.txt')->toBeFile();
    expect($tempDir.'/tmp3/bar.txt')->toBeFile();
    expect($tempDir.'/tmp3/nested')->toBeDirectory();
    expect($tempDir.'/tmp3/nested/baz.txt')->toBeFile();
    Assert::assertDirectoryDoesNotExist($tempDir.'/tmp2');
});

test('moveDirectory moves an entire directory and overwrites the destination', function () use (&$tempDir) {
    mkdir($tempDir.'/tmp4', 0777, true);
    file_put_contents($tempDir.'/tmp4/foo.txt', '');
    file_put_contents($tempDir.'/tmp4/bar.txt', '');
    mkdir($tempDir.'/tmp4/nested', 0777, true);
    file_put_contents($tempDir.'/tmp4/nested/baz.txt', '');
    mkdir($tempDir.'/tmp5', 0777, true);
    file_put_contents($tempDir.'/tmp5/foo2.txt', '');
    file_put_contents($tempDir.'/tmp5/bar2.txt', '');

    $files = new Filesystem;
    $files->moveDirectory($tempDir.'/tmp4', $tempDir.'/tmp5', true);
    expect($tempDir.'/tmp5')->toBeDirectory();
    expect($tempDir.'/tmp5/foo.txt')->toBeFile();
    expect($tempDir.'/tmp5/bar.txt')->toBeFile();
    expect($tempDir.'/tmp5/nested')->toBeDirectory();
    expect($tempDir.'/tmp5/nested/baz.txt')->toBeFile();
    Assert::assertFileDoesNotExist($tempDir.'/tmp5/foo2.txt');
    Assert::assertFileDoesNotExist($tempDir.'/tmp5/bar2.txt');
    Assert::assertDirectoryDoesNotExist($tempDir.'/tmp4');
});

test('moveDirectory returns false when it cannot delete the destination directory while overwriting', function () use (&$tempDir) {
    mkdir($tempDir.'/tmp6', 0777, true);
    file_put_contents($tempDir.'/tmp6/foo.txt', '');
    mkdir($tempDir.'/tmp7', 0777, true);

    $files = m::mock(Filesystem::class)->makePartial();
    $files->shouldReceive('deleteDirectory')->once()->andReturn(false);
    expect($files->moveDirectory($tempDir.'/tmp6', $tempDir.'/tmp7', true))->toBeFalse();
});

test('get throws for a nonexistent file', function () use (&$tempDir) {
    (new Filesystem)->get($tempDir.'/unknown-file.txt');
})->throws(FileNotFoundException::class);

test('getRequire returns the required value', function () use (&$tempDir) {
    file_put_contents($tempDir.'/file.php', '<?php return "Howdy?"; ?>');
    $files = new Filesystem;
    expect($files->getRequire($tempDir.'/file.php'))->toBe('Howdy?');
});

test('getRequire throws for a nonexistent file', function () use (&$tempDir) {
    (new Filesystem)->getRequire($tempDir.'/unknown-file.txt');
})->throws(FileNotFoundException::class);

test('json returns decoded json data', function () use (&$tempDir) {
    file_put_contents($tempDir.'/file.json', '{"foo": "bar"}');
    $files = new Filesystem;
    expect($files->json($tempDir.'/file.json'))->toBe(['foo' => 'bar']);
});

test('json returns null if json data is invalid', function () use (&$tempDir) {
    file_put_contents($tempDir.'/file.json', '{"foo":');
    $files = new Filesystem;
    expect($files->json($tempDir.'/file.json'))->toBeNull();
});

test('append adds data to a file', function () use (&$tempDir) {
    file_put_contents($tempDir.'/file.txt', 'foo');
    $files = new Filesystem;
    $bytesWritten = $files->append($tempDir.'/file.txt', 'bar');
    expect($bytesWritten)->toEqual(mb_strlen('bar', '8bit'));
    expect($tempDir.'/file.txt')->toBeFile();
    expect(file_get_contents($tempDir.'/file.txt'))->toBe('foobar');
});

test('move moves files', function () use (&$tempDir) {
    file_put_contents($tempDir.'/foo.txt', 'foo');
    $files = new Filesystem;
    $files->move($tempDir.'/foo.txt', $tempDir.'/bar.txt');
    expect($tempDir.'/bar.txt')->toBeFile();
    Assert::assertFileDoesNotExist($tempDir.'/foo.txt');
});

test('name returns the filename without its extension', function () use (&$tempDir) {
    file_put_contents($tempDir.'/foobar.txt', 'foo');
    $filesystem = new Filesystem;
    expect($filesystem->name($tempDir.'/foobar.txt'))->toBe('foobar');
});

test('extension returns the file extension', function () use (&$tempDir) {
    file_put_contents($tempDir.'/foo.txt', 'foo');
    $files = new Filesystem;
    expect($files->extension($tempDir.'/foo.txt'))->toBe('txt');
});

test('basename returns the file basename', function () use (&$tempDir) {
    file_put_contents($tempDir.'/foo.txt', 'foo');
    $files = new Filesystem;
    expect($files->basename($tempDir.'/foo.txt'))->toBe('foo.txt');
});

test('dirname returns the containing directory', function () use (&$tempDir) {
    file_put_contents($tempDir.'/foo.txt', 'foo');
    $files = new Filesystem;
    expect($files->dirname($tempDir.'/foo.txt'))->toEqual($tempDir);
});

test('type identifies a file', function () use (&$tempDir) {
    file_put_contents($tempDir.'/foo.txt', 'foo');
    $files = new Filesystem;
    expect($files->type($tempDir.'/foo.txt'))->toBe('file');
});

test('type identifies a directory', function () use (&$tempDir) {
    mkdir($tempDir.'/foo-dir');
    $files = new Filesystem;
    expect($files->type($tempDir.'/foo-dir'))->toBe('dir');
});

test('size outputs the file size', function () use (&$tempDir) {
    $size = file_put_contents($tempDir.'/foo.txt', 'foo');
    $files = new Filesystem;
    expect($files->size($tempDir.'/foo.txt'))->toEqual($size);
});

test('mimeType outputs the mime type', function () use (&$tempDir) {
    file_put_contents($tempDir.'/foo.txt', 'foo');
    $files = new Filesystem;
    expect($files->mimeType($tempDir.'/foo.txt'))->toBe('text/plain');
})->skip(fn () => ! extension_loaded('fileinfo'), 'Requires the [fileinfo] extension.');

test('isWritable reports whether a file is writable', function () use (&$tempDir) {
    file_put_contents($tempDir.'/foo.txt', 'foo');
    $files = new Filesystem;
    @chmod($tempDir.'/foo.txt', 0444);
    expect($files->isWritable($tempDir.'/foo.txt'))->toBeFalse();
    @chmod($tempDir.'/foo.txt', 0777);
    expect($files->isWritable($tempDir.'/foo.txt'))->toBeTrue();
});

test('isReadable reports whether a file is readable', function () use (&$tempDir) {
    file_put_contents($tempDir.'/foo.txt', 'foo');
    $files = new Filesystem;
    // chmod is noneffective on Windows
    if (DIRECTORY_SEPARATOR === '\\') {
        expect($files->isReadable($tempDir.'/foo.txt'))->toBeTrue();
    } else {
        @chmod($tempDir.'/foo.txt', 0000);
        expect($files->isReadable($tempDir.'/foo.txt'))->toBeFalse();
        @chmod($tempDir.'/foo.txt', 0777);
        expect($files->isReadable($tempDir.'/foo.txt'))->toBeTrue();
    }
    expect($files->isReadable($tempDir.'/doesnotexist.txt'))->toBeFalse();
});

test('isEmptyDirectory reports whether a directory is empty', function () use (&$tempDir) {
    mkdir($tempDir.'/foo-dir');
    file_put_contents($tempDir.'/foo-dir/.hidden', 'foo');
    mkdir($tempDir.'/bar-dir');
    file_put_contents($tempDir.'/bar-dir/foo.txt', 'foo');
    mkdir($tempDir.'/baz-dir');
    mkdir($tempDir.'/baz-dir/.hidden');
    mkdir($tempDir.'/quz-dir');
    mkdir($tempDir.'/quz-dir/not-hidden');

    $files = new Filesystem;

    expect($files->isEmptyDirectory($tempDir.'/foo-dir', true))->toBeTrue();
    expect($files->isEmptyDirectory($tempDir.'/foo-dir'))->toBeFalse();
    expect($files->isEmptyDirectory($tempDir.'/bar-dir', true))->toBeFalse();
    expect($files->isEmptyDirectory($tempDir.'/bar-dir'))->toBeFalse();
    expect($files->isEmptyDirectory($tempDir.'/baz-dir', true))->toBeTrue();
    expect($files->isEmptyDirectory($tempDir.'/baz-dir'))->toBeFalse();
    expect($files->isEmptyDirectory($tempDir.'/quz-dir', true))->toBeFalse();
    expect($files->isEmptyDirectory($tempDir.'/quz-dir'))->toBeFalse();
});

test('glob finds files matching a pattern', function () use (&$tempDir) {
    file_put_contents($tempDir.'/foo.txt', 'foo');
    file_put_contents($tempDir.'/bar.txt', 'bar');
    $files = new Filesystem;
    $glob = $files->glob($tempDir.'/*.txt');
    expect($glob)->toContain($tempDir.'/foo.txt');
    expect($glob)->toContain($tempDir.'/bar.txt');
});

test('allFiles finds files recursively', function () use (&$tempDir) {
    file_put_contents($tempDir.'/foo.txt', 'foo');
    file_put_contents($tempDir.'/bar.txt', 'bar');
    $files = new Filesystem;
    $allFiles = [];
    foreach ($files->allFiles($tempDir) as $file) {
        $allFiles[] = $file->getFilename();
    }
    expect($allFiles)->toContain('foo.txt');
    expect($allFiles)->toContain('bar.txt');
});

test('directories finds directories', function () use (&$tempDir) {
    mkdir($tempDir.'/film');
    mkdir($tempDir.'/music');
    $files = new Filesystem;
    $directories = $files->directories($tempDir);
    expect($directories)->toContain($tempDir.DIRECTORY_SEPARATOR.'film');
    expect($directories)->toContain($tempDir.DIRECTORY_SEPARATOR.'music');
});

test('allDirectories finds directories recursively', function () use (&$tempDir) {
    mkdir($tempDir.'/film');
    mkdir($tempDir.'/music');
    mkdir($tempDir.'/music/rock');
    mkdir($tempDir.'/music/blues');

    $directories = (new Filesystem)->allDirectories($tempDir);

    expect($directories)->toContain($tempDir.DIRECTORY_SEPARATOR.'film');
    expect($directories)->toContain($tempDir.DIRECTORY_SEPARATOR.'music');
    expect($directories)->toContain($tempDir.DIRECTORY_SEPARATOR.'music'.DIRECTORY_SEPARATOR.'rock');
    expect($directories)->toContain($tempDir.DIRECTORY_SEPARATOR.'music'.DIRECTORY_SEPARATOR.'blues');
});

test('makeDirectory creates a directory', function () use (&$tempDir) {
    $files = new Filesystem;
    expect($files->makeDirectory($tempDir.'/created'))->toBeTrue();
    expect($tempDir.'/created')->toBeFile();
});

test('get and put support shared locking across processes', function () use (&$tempDir) {
    $content = str_repeat('123456', 1000000);
    $result = 1;

    posix_setpgid(0, 0);

    for ($i = 1; $i <= 20; $i++) {
        $pid = pcntl_fork();

        if (! $pid) {
            $files = new Filesystem;
            $files->put($tempDir.'/file.txt', $content, true);
            $read = $files->get($tempDir.'/file.txt', true);

            terminate($this, strlen($read) === strlen($content) ? 1 : 0);
        }
    }

    while (pcntl_waitpid(0, $status) != -1) {
        $status = pcntl_wexitstatus($status);
        $result *= $status;
    }

    expect($result)->toBe(1);
})->skip(fn () => ! in_array(PHP_OS_FAMILY, ['Linux', 'Darwin'], true), 'Requires Linux or Darwin.')
    ->skip(fn () => ! extension_loaded('pcntl'), 'Requires the [pcntl] extension.');

test('requireOnce requires a file only once per changed contents', function () use (&$tempDir) {
    $filesystem = new Filesystem;
    mkdir($tempDir.'/scripts');
    file_put_contents($tempDir.'/scripts/foo.php', '<?php function random_function_xyz(){};');
    $filesystem->requireOnce($tempDir.'/scripts/foo.php');
    file_put_contents($tempDir.'/scripts/foo.php', '<?php function random_function_xyz_changed(){};');
    $filesystem->requireOnce($tempDir.'/scripts/foo.php');
    expect(function_exists('random_function_xyz'))->toBeTrue();
    expect(function_exists('random_function_xyz_changed'))->toBeFalse();
});

test('requireOnce throws for a nonexistent file', function () {
    (new Filesystem)->requireOnce(__DIR__.'/unknown-file.txt');
})->throws(FileNotFoundException::class, 'File does not exist at path '.__DIR__.'/unknown-file.txt.');

test('copy copies a file properly', function () use (&$tempDir) {
    $filesystem = new Filesystem;
    $data = 'contents';
    mkdir($tempDir.'/text');
    file_put_contents($tempDir.'/text/foo.txt', $data);
    $filesystem->copy($tempDir.'/text/foo.txt', $tempDir.'/text/foo2.txt');
    expect($tempDir.'/text/foo2.txt')->toBeFile();
    expect(file_get_contents($tempDir.'/text/foo2.txt'))->toEqual($data);
});

test('hasSameHash checks file hashes', function () use (&$tempDir) {
    $filesystem = new Filesystem;

    mkdir($tempDir.'/text');
    file_put_contents($tempDir.'/text/foo.txt', 'contents');
    file_put_contents($tempDir.'/text/foo2.txt', 'contents');
    file_put_contents($tempDir.'/text/foo3.txt', 'invalid');

    expect($filesystem->hasSameHash($tempDir.'/text/foo.txt', $tempDir.'/text/foo2.txt'))->toBeTrue();
    expect($filesystem->hasSameHash($tempDir.'/text/foo.txt', $tempDir.'/text/foo3.txt'))->toBeFalse();
    expect($filesystem->hasSameHash($tempDir.'/text/foo4.txt', $tempDir.'/text/foo.txt'))->toBeFalse();
    expect($filesystem->hasSameHash($tempDir.'/text/foo.txt', $tempDir.'/text/foo4.txt'))->toBeFalse();
});

test('isFile checks files properly', function () use (&$tempDir) {
    $filesystem = new Filesystem;
    mkdir($tempDir.'/help');
    file_put_contents($tempDir.'/help/foo.txt', 'contents');
    expect($filesystem->isFile($tempDir.'/help/foo.txt'))->toBeTrue();
    expect($filesystem->isFile($tempDir.'./help'))->toBeFalse();
});

test('files returns SplFileInfo objects', function () use (&$tempDir) {
    mkdir($tempDir.'/objects');
    file_put_contents($tempDir.'/objects/1.txt', '1');
    file_put_contents($tempDir.'/objects/2.txt', '2');
    mkdir($tempDir.'/objects/bar');
    $files = new Filesystem;
    expect($files->files($tempDir.'/objects'))->toContainOnlyInstancesOf(SplFileInfo::class);
    unset($files);
});

test('allFiles returns SplFileInfo objects', function () use (&$tempDir) {
    file_put_contents($tempDir.'/foo.txt', 'foo');
    file_put_contents($tempDir.'/bar.txt', 'bar');
    $files = new Filesystem;
    expect($files->allFiles($tempDir))->toContainOnlyInstancesOf(SplFileInfo::class);
});

test('hash uses md5 by default', function () use (&$tempDir) {
    file_put_contents($tempDir.'/foo.txt', 'foo');
    $filesystem = new Filesystem;
    expect($filesystem->hash($tempDir.'/foo.txt'))->toBe('acbd18db4cc2f85cedef654fccc4a4d8');
});

test('hash supports different algorithms', function () use (&$tempDir) {
    file_put_contents($tempDir.'/foo.txt', 'foo');
    $filesystem = new Filesystem;
    expect($filesystem->hash($tempDir.'/foo.txt', 'sha1'))->toBe('0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33');
    expect($filesystem->hash($tempDir.'/foo.txt', 'sha3-256'))->toBe('76d3bc41c9f588f7fcd0d5bf4718f8f84b1c41b20882703100b9eb9413807c01');
});

test('lastModified returns a timestamp', function () use (&$tempDir) {
    $path = $tempDir.'/timestamp.txt';
    file_put_contents($path, 'test content');

    $filesystem = new Filesystem;
    $timestamp = $filesystem->lastModified($path);

    expect($timestamp)->toBeInt();
    expect($timestamp)->toBeGreaterThan(0);
    expect($timestamp)->toEqual(filemtime($path));
});

test('it creates a file and verifies its content', function () use (&$tempDir) {
    $files = new Filesystem;

    $testContent = 'This is a test file content';
    $filePath = $tempDir.'/test.txt';

    $files->put($filePath, $testContent);

    expect($files->exists($filePath))->toBeTrue();
    expect($files->get($filePath))->toBe($testContent);
    expect($files->size($filePath))->toEqual(strlen($testContent));
});

test('it performs directory operations with subdirectories', function () use (&$tempDir) {
    $files = new Filesystem;

    $dirPath = $tempDir.'/test_dir';
    $subDirPath = $dirPath.'/sub_dir';

    expect($files->makeDirectory($dirPath))->toBeTrue();
    expect($files->isDirectory($dirPath))->toBeTrue();

    expect($files->makeDirectory($subDirPath))->toBeTrue();
    expect($files->isDirectory($subDirPath))->toBeTrue();

    $filePath = $subDirPath.'/test.txt';
    $files->put($filePath, 'test content');

    expect($files->exists($filePath))->toBeTrue();

    $allFiles = $files->allFiles($dirPath);

    expect($allFiles)->toHaveCount(1);
    expect($allFiles[0]->getFilename())->toEqual('test.txt');
});
