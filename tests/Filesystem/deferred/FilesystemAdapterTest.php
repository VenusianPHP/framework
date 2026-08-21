<?php

use Carbon\Carbon;
use GuzzleHttp\Psr7\Stream;
use League\Flysystem\Filesystem;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use Mockery as m;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Filesystem\Fixtures\TemporaryUploadUrlLocalFilesystemAdapter;
use Tests\Filesystem\Fixtures\TemporaryUrlLocalFilesystemAdapter;
use Voyager\Contracts\Debug\ExceptionHandler;
use Voyager\Filesystem\FilesystemAdapter;
use Voyager\Filesystem\FilesystemManager;
use Voyager\Http\UploadedFile;
use Voyager\System\Application;
use Voyager\Testing\Assert;
use Voyager\Vessel\Vessel;

beforeEach(function () {
    $this->tempDir = __DIR__.'/tmp';
    $this->filesystem = new Filesystem(
        $this->adapter = new LocalFilesystemAdapter($this->tempDir)
    );
});

afterEach(function () {
    $filesystem = new Filesystem(
        $this->adapter = new LocalFilesystemAdapter(dirname($this->tempDir))
    );
    $filesystem->deleteDirectory(basename($this->tempDir));

    unset($this->tempDir, $this->filesystem, $this->adapter);
});

describe('response', function () {
    test('it returns a streamed response', function () {
        $this->filesystem->write('file.txt', 'Hello World');
        $files = new FilesystemAdapter($this->filesystem, $this->adapter);
        $response = $files->response('file.txt');

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        expect($response)->toBeInstanceOf(StreamedResponse::class);
        expect($content)->toBe('Hello World');
        expect($response->headers->get('content-disposition'))->toBe('inline; filename=file.txt');
    });

    test('mimeType is not called when already provided to response', function () {
        $this->filesystem->write('file.txt', 'Hello World');

        $files = m::mock(FilesystemAdapter::class, [$this->filesystem, $this->adapter])->makePartial();
        $files->shouldReceive('mimeType')->never();

        $files->response('file.txt', null, [
            'Content-Type' => 'text/x-custom',
        ]);
    });

    test('size is not called when already provided to response', function () {
        $this->filesystem->write('file.txt', 'Hello World');

        $files = m::mock(FilesystemAdapter::class, [$this->filesystem, $this->adapter])->makePartial();
        $files->shouldReceive('size')->never();

        $files->response('file.txt', null, [
            'Content-Length' => 11,
        ]);
    });

    test('fallbackName is not called when already provided to response', function () {
        $this->filesystem->write('file.txt', 'Hello World');

        $files = m::mock(FilesystemAdapter::class, [$this->filesystem, $this->adapter])
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();
        $files->shouldReceive('fallbackName')->never();

        $files->response('file.txt', null, [
            'Content-Disposition' => 'attachment',
        ]);
    });
});

describe('download', function () {
    test('it can download a file with a given name', function () {
        $this->filesystem->write('file.txt', 'Hello World');
        $files = new FilesystemAdapter($this->filesystem, $this->adapter);
        $response = $files->download('file.txt', 'hello.txt');
        expect($response)->toBeInstanceOf(StreamedResponse::class);
        expect($response->headers->get('content-disposition'))->toBe('attachment; filename=hello.txt');
    });

    test('it can download a file with a non ascii filename', function () {
        $this->filesystem->write('file.txt', 'Hello World');
        $files = new FilesystemAdapter($this->filesystem, $this->adapter);
        $response = $files->download('file.txt', 'привет.txt');
        expect($response)->toBeInstanceOf(StreamedResponse::class);
        expect($response->headers->get('content-disposition'))->toBe("attachment; filename=privet.txt; filename*=utf-8''%D0%BF%D1%80%D0%B8%D0%B2%D0%B5%D1%82.txt");
    });

    test('it can download a file with a non ascii filename and no given name', function () {
        $this->filesystem->write('привет.txt', 'Hello World');
        $files = new FilesystemAdapter($this->filesystem, $this->adapter);
        $response = $files->download('привет.txt');
        expect($response)->toBeInstanceOf(StreamedResponse::class);
        expect($response->headers->get('content-disposition'))->toBe('attachment; filename=privet.txt; filename*=utf-8\'\'%D0%BF%D1%80%D0%B8%D0%B2%D0%B5%D1%82.txt');
    });

    test('it can download a file with a percent sign in its filename', function () {
        $this->filesystem->write('Hello%World.txt', 'Hello World');
        $files = new FilesystemAdapter($this->filesystem, $this->adapter);
        $response = $files->download('Hello%World.txt', 'Hello%World.txt');
        expect($response)->toBeInstanceOf(StreamedResponse::class);
        expect($response->headers->get('content-disposition'))->toBe('attachment; filename=HelloWorld.txt; filename*=utf-8\'\'Hello%25World.txt');
    });
});

test('it can check if a file exists', function () {
    $this->filesystem->write('file.txt', 'Hello World');
    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    expect($filesystemAdapter->exists('file.txt'))->toBeTrue();
    expect($filesystemAdapter->fileExists('file.txt'))->toBeTrue();
});

test('it can check if a file is missing', function () {
    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    expect($filesystemAdapter->missing('file.txt'))->toBeTrue();
    expect($filesystemAdapter->fileMissing('file.txt'))->toBeTrue();
});

test('it can check if a directory exists', function () {
    $this->filesystem->write('/foo/bar/file.txt', 'Hello World');
    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    expect($filesystemAdapter->directoryExists('/foo/bar'))->toBeTrue();
});

test('it can check if a directory is missing', function () {
    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    expect($filesystemAdapter->directoryMissing('/foo/bar'))->toBeTrue();
});

test('it can get the full path', function () {
    $this->filesystem->write('file.txt', 'Hello World');
    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter, [
        'root' => $this->tempDir.DIRECTORY_SEPARATOR,
    ]);
    expect($filesystemAdapter->path('file.txt'))->toEqual($this->tempDir.DIRECTORY_SEPARATOR.'file.txt');
});

test('it can get file contents', function () {
    $this->filesystem->write('file.txt', 'Hello World');
    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    expect($filesystemAdapter->get('file.txt'))->toBe('Hello World');
});

test('it returns null when getting a missing file', function () {
    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    expect($filesystemAdapter->get('file.txt'))->toBeNull();
});

test('json returns decoded json data', function () {
    $this->filesystem->write('file.json', '{"foo": "bar"}');
    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    expect($filesystemAdapter->json('file.json'))->toBe(['foo' => 'bar']);
});

test('json returns null if json data is invalid', function () {
    $this->filesystem->write('file.json', '{"foo":');
    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    expect($filesystemAdapter->json('file.json'))->toBeNull();
});

test('mimeType returns false when it cannot be detected', function () {
    $this->filesystem->write('unknown.mime-type', '');
    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    expect($filesystemAdapter->mimeType('unknown.mime-type'))->toBeFalse();
});

test('it can put file contents', function () {
    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    $filesystemAdapter->put('file.txt', 'Something inside');
    expect(file_get_contents($this->tempDir.'/file.txt'))->toBe('Something inside');
});

test('it can prepend content to a file', function () {
    file_put_contents($this->tempDir.'/file.txt', 'World');
    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    $filesystemAdapter->prepend('file.txt', 'Hello ');
    expect(file_get_contents($this->tempDir.'/file.txt'))->toBe('Hello '.PHP_EOL.'World');
});

test('it can append content to a file', function () {
    file_put_contents($this->tempDir.'/file.txt', 'Hello ');
    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    $filesystemAdapter->append('file.txt', 'Moon');
    expect(file_get_contents($this->tempDir.'/file.txt'))->toBe('Hello '.PHP_EOL.'Moon');
});

test('it can delete a file', function () {
    file_put_contents($this->tempDir.'/file.txt', 'Hello World');
    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    expect($filesystemAdapter->delete('file.txt'))->toBeTrue();
    Assert::assertFileDoesNotExist($this->tempDir.'/file.txt');
});

test('delete returns true when the file is not found', function () {
    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    expect($filesystemAdapter->delete('file.txt'))->toBeTrue();
});

test('it can copy a file', function () {
    $data = '33232';
    mkdir($this->tempDir.'/foo');
    file_put_contents($this->tempDir.'/foo/foo.txt', $data);

    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    $filesystemAdapter->copy('/foo/foo.txt', '/foo/foo2.txt');

    expect($this->tempDir.'/foo/foo.txt')->toBeFile();
    expect(file_get_contents($this->tempDir.'/foo/foo.txt'))->toEqual($data);

    expect($this->tempDir.'/foo/foo2.txt')->toBeFile();
    expect(file_get_contents($this->tempDir.'/foo/foo2.txt'))->toEqual($data);
});

test('it can move a file', function () {
    $data = '33232';
    mkdir($this->tempDir.'/foo');
    file_put_contents($this->tempDir.'/foo/foo.txt', $data);

    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    $filesystemAdapter->move('/foo/foo.txt', '/foo/foo2.txt');

    Assert::assertFileDoesNotExist($this->tempDir.'/foo/foo.txt');

    expect($this->tempDir.'/foo/foo2.txt')->toBeFile();
    expect(file_get_contents($this->tempDir.'/foo/foo2.txt'))->toEqual($data);
});

describe('streams', function () {
    test('it can stream a file to a new location', function () {
        $this->filesystem->write('file.txt', $original_content = 'Hello World');
        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
        $readStream = $filesystemAdapter->readStream('file.txt');
        $filesystemAdapter->writeStream('copy.txt', $readStream);
        expect($filesystemAdapter->get('copy.txt'))->toEqual($original_content);
    });

    test('it can stream a file between filesystems', function () {
        $secondFilesystem = new Filesystem(new LocalFilesystemAdapter($this->tempDir.'/second'));
        $this->filesystem->write('file.txt', $original_content = 'Hello World');
        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
        $secondFilesystemAdapter = new FilesystemAdapter($secondFilesystem, $this->adapter);
        $readStream = $filesystemAdapter->readStream('file.txt');
        $secondFilesystemAdapter->writeStream('copy.txt', $readStream);
        expect($secondFilesystemAdapter->get('copy.txt'))->toEqual($original_content);
    });

    test('streaming to an existing file overwrites it', function () {
        $this->filesystem->write('file.txt', 'Hello World');
        $this->filesystem->write('existing.txt', 'Dear Kate');
        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
        $readStream = $filesystemAdapter->readStream('file.txt');
        $filesystemAdapter->writeStream('existing.txt', $readStream);
        expect($filesystemAdapter->read('existing.txt'))->toBe('Hello World');
    });

    test('readStream returns null for a nonexistent file', function () {
        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
        expect($filesystemAdapter->readStream('nonexistent.txt'))->toBeNull();
    });

    test('writeStream throws when given an invalid resource', function () {
        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
        $filesystemAdapter->writeStream('file.txt', 'foo bar');
    })->throws(InvalidArgumentException::class);
});

test('it can put a PSR-7 stream', function () {
    file_put_contents($this->tempDir.'/foo.txt', 'some-data');

    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    $stream = fopen($this->tempDir.'/foo.txt', 'r');
    $guzzleStream = new Stream($stream);
    $filesystemAdapter->put('bar.txt', $guzzleStream);
    fclose($stream);

    expect($filesystemAdapter->get('bar.txt'))->toBe('some-data');
});

describe('putFile', function () {
    test('it can put a file with a given name', function () {
        file_put_contents($filePath = $this->tempDir.'/foo.txt', 'uploaded file content');

        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);

        $uploadedFile = new UploadedFile($filePath, 'org.txt', null, null, true);

        $storagePath = $filesystemAdapter->putFileAs('/', $uploadedFile, 'new.txt');

        expect($storagePath)->toBe('new.txt');

        expect($filePath)->toBeFile();

        $filesystemAdapter->assertExists($storagePath);

        expect($filesystemAdapter->read($storagePath))->toBe('uploaded file content');

        $filesystemAdapter->assertExists(
            $storagePath,
            'uploaded file content'
        );
    });

    test('it can put a file with a given name from an absolute file path', function () {
        file_put_contents($filePath = $this->tempDir.'/foo.txt', 'normal file content');

        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);

        $storagePath = $filesystemAdapter->putFileAs('/', $filePath, 'new.txt');

        expect($filesystemAdapter->read($storagePath))->toBe('normal file content');
    });

    test('it can put a file with a given name without a target path', function () {
        file_put_contents($filePath = $this->tempDir.'/foo.txt', 'normal file content');

        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);

        $storagePath = $filesystemAdapter->putFileAs($filePath, 'new.txt');

        expect($filesystemAdapter->read($storagePath))->toBe('normal file content');
    });

    test('it can put a file with a generated name', function () {
        file_put_contents($filePath = $this->tempDir.'/foo.txt', 'uploaded file content');

        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);

        $uploadedFile = new UploadedFile($filePath, 'org.txt', null, null, true);

        $storagePath = $filesystemAdapter->putFile('/', $uploadedFile);

        expect(strlen($storagePath))->toBe(44); // random 40 characters + ".txt"

        expect($filePath)->toBeFile();

        $filesystemAdapter->assertExists($storagePath);

        $filesystemAdapter->assertExists(
            $storagePath,
            'uploaded file content'
        );
    });

    test('it can put a file with a generated name from an absolute file path', function () {
        file_put_contents($filePath = $this->tempDir.'/foo.txt', 'uploaded file content');

        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);

        $storagePath = $filesystemAdapter->putFile('/', $filePath);

        expect(strlen($storagePath))->toBe(44); // random 40 characters + ".txt"

        $filesystemAdapter->assertExists($storagePath);

        $filesystemAdapter->assertExists(
            $storagePath,
            'uploaded file content'
        );
    });

    test('it can put a file with a generated name without a target path', function () {
        file_put_contents($filePath = $this->tempDir.'/foo.txt', 'normal file content');

        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);

        $storagePath = $filesystemAdapter->putFile($filePath);

        expect($filesystemAdapter->read($storagePath))->toBe('normal file content');
    });
});

test('it can create an ftp driver', function () {
    $filesystem = new FilesystemManager(new Application);

    $driver = $filesystem->createFtpDriver([
        'host' => 'ftp.example.com',
        'username' => 'admin',
        'permPublic' => 0700,
        'unsupportedParam' => true,
    ]);

    expect($driver->getAdapter())->toBeInstanceOf(FtpAdapter::class);

    $config = $driver->getConfig();
    expect($config['permPublic'])->toEqual(0700);
    expect($config['host'])->toBe('ftp.example.com');
    expect($config['username'])->toBe('admin');
})->skip(fn () => ! extension_loaded('ftp'), 'Requires the [ftp] extension.');

test('it is macroable', function () {
    $this->filesystem->write('foo.txt', 'Hello World');

    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    $filesystemAdapter->macro('getFoo', function () {
        return $this->get('foo.txt');
    });

    expect($filesystemAdapter->getFoo())->toBe('Hello World');
});

describe('temporary urls', function () {
    test('it can build a temporary url with a custom callback', function () {
        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);

        $filesystemAdapter->buildTemporaryUrlsUsing(function ($path, Carbon $expiration, $options) {
            return $path.$expiration->toString().implode('', $options);
        });

        $path = 'foo';
        $expiration = Carbon::create(2021, 18, 12, 13);
        $options = ['bar' => 'baz'];

        expect($filesystemAdapter->temporaryUrl($path, $expiration, $options))->toBe(
            $path.$expiration->toString().implode('', $options)
        );
    });

    test('it reports that it provides temporary urls when the adapter supports them', function () {
        $localAdapter = new TemporaryUrlLocalFilesystemAdapter($this->tempDir);
        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $localAdapter);

        expect($filesystemAdapter->providesTemporaryUrls())->toBeTrue();
    });

    test('it reports that it provides temporary urls when a custom callback is set', function () {
        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);

        $filesystemAdapter->buildTemporaryUrlsUsing(function ($path, Carbon $expiration, $options) {
            return $path.$expiration->toString().implode('', $options);
        });

        expect($filesystemAdapter->providesTemporaryUrls())->toBeTrue();
    });

    test('it reports that it does not provide temporary urls when unsupported', function () {
        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);

        expect($filesystemAdapter->providesTemporaryUrls())->toBeFalse();
    });
});

describe('s3 adapter', function () {
    test('the s3 adapter provides temporary urls', function () {
        $filesystem = new FilesystemManager(new Application);
        $filesystemAdapter = $filesystem->createS3Driver([
            'region' => 'us-west-1',
            'bucket' => 'laravel',
        ]);

        expect($filesystemAdapter->providesTemporaryUrls())->toBeTrue();
    });

    test('s3 paths use the right separator', function () {
        $filesystem = new FilesystemManager(new Application);
        $filesystemAdapter = $filesystem->createS3Driver([
            'region' => 'us-west-1',
            'bucket' => 'laravel',
            'root' => 'something',
            'directory_separator' => '\\',
        ]);

        $path = $filesystemAdapter->path('different');
        expect($path)->toContain('/');
        expect($path)->not->toContain('\\');
    });

    test('s3 paths use the right separator without double prefixing', function () {
        $filesystem = new FilesystemManager(new Application);
        $filesystemAdapter = $filesystem->createS3Driver([
            'region' => 'us-west-1',
            'bucket' => 'laravel',
            'root' => 'my-root',
            'prefix' => 'someprefix',
            'directory_separator' => '\\',
        ]);

        $path = $filesystemAdapter->path('different');
        expect($path)->toEqual('my-root/someprefix/different');
    });
});

test('it prefixes urls', function () {
    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter, ['url' => 'https://example.org/', 'prefix' => 'images']);

    expect($filesystemAdapter->url('picture.jpeg'))->toEqual('https://example.org/images/picture.jpeg');
});

test('it can get a checksum', function () {
    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);
    $filesystemAdapter->write('path.txt', 'contents of file');

    expect($filesystemAdapter->checksum('path.txt'))->toEqual('730bed78bccf58c2cfe44c29b71e5e6b');
    expect($filesystemAdapter->checksum('path.txt', ['checksum_algo' => 'crc32']))->toEqual('a5c3556d');
});

describe('temporary upload urls', function () {
    test('it can build a temporary upload url with a custom callback', function () {
        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);

        $filesystemAdapter->buildTemporaryUploadUrlsUsing(function ($path, Carbon $expiration, $options) {
            return [
                'url' => $path.$expiration->toString().implode('', $options),
                'headers' => ['X-Custom' => 'header'],
            ];
        });

        $path = 'foo';
        $expiration = Carbon::create(2021, 18, 12, 13);
        $options = ['bar' => 'baz'];

        $result = $filesystemAdapter->temporaryUploadUrl($path, $expiration, $options);

        expect($result['url'])->toBe($path.$expiration->toString().implode('', $options));
        expect($result['headers'])->toBe(['X-Custom' => 'header']);
    });

    test('it reports that it provides temporary upload urls when the adapter supports them', function () {
        $localAdapter = new TemporaryUploadUrlLocalFilesystemAdapter($this->tempDir);
        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $localAdapter);

        expect($filesystemAdapter->providesTemporaryUploadUrls())->toBeTrue();
    });

    test('it reports that it provides temporary upload urls when a custom callback is set', function () {
        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);

        $filesystemAdapter->buildTemporaryUploadUrlsUsing(function ($path, Carbon $expiration, $options) {
            return [
                'url' => $path.$expiration->toString().implode('', $options),
                'headers' => [],
            ];
        });

        expect($filesystemAdapter->providesTemporaryUploadUrls())->toBeTrue();
    });

    test('it reports that it does not provide temporary upload urls when unsupported', function () {
        $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);

        expect($filesystemAdapter->providesTemporaryUploadUrls())->toBeFalse();
    });
});

test('it can get all files', function () {
    $this->filesystem->write('body.txt', 'Hello World');
    $this->filesystem->write('file1.txt', 'Hello World');
    $this->filesystem->write('file.txt', 'Hello World');
    $this->filesystem->write('existing.txt', 'Dear Kate');

    $filesystemAdapter = new FilesystemAdapter($this->filesystem, $this->adapter);

    expect($filesystemAdapter->files())->toBe(['body.txt', 'existing.txt', 'file.txt', 'file1.txt']);
});

describe('throwing exceptions', function () {
    test('get throws when configured to throw exceptions', function () {
        $adapter = new FilesystemAdapter($this->filesystem, $this->adapter, ['throw' => true]);

        $adapter->get('/foo.txt');
    })->throws(UnableToReadFile::class);

    test('readStream throws when configured to throw exceptions', function () {
        $adapter = new FilesystemAdapter($this->filesystem, $this->adapter, ['throw' => true]);

        $adapter->readStream('/foo.txt');
    })->throws(UnableToReadFile::class);

    test('put throws when configured to throw exceptions', function () {
        $this->filesystem->write('foo.txt', 'Hello World');

        chmod(__DIR__.'/tmp/foo.txt', 0400);

        $adapter = new FilesystemAdapter($this->filesystem, $this->adapter, ['throw' => true]);

        try {
            $adapter->put('/foo.txt', 'Hello World!');
        } finally {
            chmod(__DIR__.'/tmp/foo.txt', 0600);
        }
    })->throws(UnableToWriteFile::class);

    test('mimeType throws when configured to throw exceptions', function () {
        $this->filesystem->write('unknown.mime-type', '');

        $adapter = new FilesystemAdapter($this->filesystem, $this->adapter, ['throw' => true]);

        $adapter->mimeType('unknown.mime-type');
    })->throws(UnableToRetrieveMetadata::class);
});

describe('reporting exceptions', function () {
    test('get reports exceptions when configured to report', function () {
        $vessel = Vessel::getInstance();

        $exceptionHandler = m::mock(ExceptionHandler::class);

        $exceptionHandler->shouldReceive('report')
            ->once()
            ->andReturnUsing(function (UnableToReadFile $e) {
                expect($e->getMessage())->toContain('Unable to read file from location: foo.txt.');
            });

        $vessel->bind(ExceptionHandler::class, function () use ($exceptionHandler) {
            return $exceptionHandler;
        });

        $adapter = new FilesystemAdapter($this->filesystem, $this->adapter, ['report' => true]);

        try {
            $adapter->get('/foo.txt');
        } catch (UnableToReadFile) {
            $this->fail('Exception was thrown.');
        }
    });

    test('readStream reports exceptions when configured to report', function () {
        $vessel = Vessel::getInstance();

        $exceptionHandler = m::mock(ExceptionHandler::class);

        $exceptionHandler->shouldReceive('report')
            ->once()
            ->andReturnUsing(function (UnableToReadFile $e) {
                expect($e->getMessage())->toContain('Unable to read file from location: foo.txt.');
            });

        $vessel->bind(ExceptionHandler::class, function () use ($exceptionHandler) {
            return $exceptionHandler;
        });

        $adapter = new FilesystemAdapter($this->filesystem, $this->adapter, ['report' => true], $exceptionHandler);

        try {
            $adapter->readStream('/foo.txt');
        } catch (UnableToReadFile) {
            $this->fail('Exception was thrown.');
        }
    });

    test('put reports exceptions when configured to report', function () {
        $vessel = Vessel::getInstance();

        $exceptionHandler = m::mock(ExceptionHandler::class);

        $exceptionHandler->shouldReceive('report')
            ->once()
            ->andReturnUsing(function (UnableToWriteFile $e) {
                expect($e->getMessage())->toContain('Unable to write file at location: foo.txt.');
            });

        $vessel->bind(ExceptionHandler::class, function () use ($exceptionHandler) {
            return $exceptionHandler;
        });

        $this->filesystem->write('foo.txt', 'Hello World');

        chmod(__DIR__.'/tmp/foo.txt', 0400);

        $adapter = new FilesystemAdapter($this->filesystem, $this->adapter, ['report' => true], $exceptionHandler);

        try {
            $adapter->put('/foo.txt', 'Hello World!');
        } catch (UnableToWriteFile) {
            $this->fail('Exception was thrown.');
        } finally {
            chmod(__DIR__.'/tmp/foo.txt', 0600);
        }
    });

    test('mimeType reports exceptions when configured to report', function () {
        $vessel = Vessel::getInstance();

        $exceptionHandler = m::mock(ExceptionHandler::class);

        $exceptionHandler->shouldReceive('report')
            ->once()
            ->andReturnUsing(function (UnableToRetrieveMetadata $e) {
                expect($e->getMessage())->toContain('Unable to retrieve the mime_type for file at location: unknown.mime-type.');
            });

        $vessel->bind(ExceptionHandler::class, function () use ($exceptionHandler) {
            return $exceptionHandler;
        });

        $this->filesystem->write('unknown.mime-type', '');

        $adapter = new FilesystemAdapter($this->filesystem, $this->adapter, ['report' => true], $exceptionHandler);

        try {
            $adapter->mimeType('unknown.mime-type');
        } catch (UnableToRetrieveMetadata) {
            $this->fail('Exception was thrown.');
        }
    });
});
