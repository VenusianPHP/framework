<?php

use Monolog\Formatter\HtmlFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\LogEntriesHandler;
use Monolog\Handler\NewRelicHandler;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Level;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use Monolog\Processor\UidProcessor;
use Venusian\Tests\Log\Fixtures\CustomChannelFactory;
use Venusian\Tests\Log\Fixtures\CustomizeFormatter;
use Venusian\Tests\Log\Fixtures\LoggerSpy;
use Venusian\Tests\Log\Fixtures\ThrowingEmergencyLogManager;
use Voyager\Config\Repository;
use Voyager\Core\RenderedInstance;
use Voyager\Log\Logger;
use Voyager\Log\LogManager;
use Voyager\Signals\SignalDispatcher;

/** The two bindings LogManager reads, on a bare app: no bootstrappers, no providers. */
if (! function_exists('logApp')) {
function logApp(array $logging = []): RenderedInstance
{
    $app = new RenderedInstance(sys_get_temp_dir());
    $app->registerInstance('config', new Repository(['logging' => $logging + [
        'default' => 'single',
        'channels' => ['single' => ['driver' => 'single', 'path' => sys_get_temp_dir().'/vf-log-test.log']],
    ]]));
    $app->registerInstance('signals', new SignalDispatcher($app));
    $app->registerInstance('log', new LogManager($app));

    return $app;
}
}

if (! function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $base = sys_get_temp_dir().'/vf-log-storage';
        if (! is_dir($base.'/logs')) {
            mkdir($base.'/logs', 0777, true);
        }

        return $path === '' ? $base : $base.'/'.ltrim($path, '/');
    }
}

/** Read a private property off an object. */
function logManagerProperty(object $target, string $property): mixed
{
    return (new ReflectionProperty(get_class($target), $property))->getValue($target);
}

test('resolved channels are cached', function () {
    $app = logApp();
    $manager = new LogManager($app);

    $logger1 = $manager->channel('single')->getLogger();
    $logger2 = $manager->channel('single')->getLogger();

    expect($logger1)->toBe($logger2);
});

test('the default driver is used when no channel is named', function () {
    $app = logApp();
    $app['config']->set('logging.default', 'single');

    $manager = new LogManager($app);

    expect($manager->getChannels())->toBeEmpty();

    // we don't specify any channel name
    $manager->channel();

    expect($manager->getChannels())->toHaveCount(1)
        ->and($manager->getDefaultDriver())->toEqual('single');
});

test('a stack channel composes the handlers of its members', function () {
    $app = logApp();
    $config = $app['config'];

    $config->set('logging.channels.stack', [
        'driver' => 'stack',
        'channels' => ['stderr', 'stdout'],
    ]);

    $config->set('logging.channels.stderr', [
        'driver' => 'monolog',
        'handler' => StreamHandler::class,
        'level' => 'notice',
        'with' => [
            'stream' => 'php://stderr',
            'bubble' => false,
        ],
        'processors' => [PsrLogMessageProcessor::class],
    ]);

    $config->set('logging.channels.stdout', [
        'driver' => 'monolog',
        'handler' => StreamHandler::class,
        'level' => 'info',
        'with' => [
            'stream' => 'php://stdout',
            'bubble' => true,
        ],
    ]);

    $manager = new LogManager($app);

    // create logger with handler specified from configuration
    $logger = $manager->channel('stack');
    $handlers = $logger->getLogger()->getHandlers();

    expect($logger)->toBeInstanceOf(Logger::class)
        ->and($handlers)->toHaveCount(2)
        ->and($handlers[0])->toBeInstanceOf(StreamHandler::class)
        ->and($logger->getLogger()->getProcessors()[2])->toBeInstanceOf(PsrLogMessageProcessor::class)
        ->and($handlers[1])->toBeInstanceOf(StreamHandler::class)
        ->and($handlers[0]->getLevel())->toEqual(Level::Notice)
        ->and($handlers[1]->getLevel())->toEqual(Level::Info)
        ->and($handlers[0]->getBubble())->toBeFalse()
        ->and($handlers[1]->getBubble())->toBeTrue();
});

test('a stack channel list can be given as a comma separated string', function () {
    $app = logApp();
    $app['config']->set('logging.channels.daily', [
        'driver' => 'daily',
        'path' => storage_path('logs/venusian.log'),
    ]);
    $app['config']->set('logging.channels.stderr', [
        'driver' => 'monolog',
        'handler' => StreamHandler::class,
        'with' => ['stream' => 'php://stderr'],
    ]);
    $app['config']->set('logging.channels.stack', [
        'driver' => 'stack',
        'channels' => 'single, daily, stderr',
    ]);

    $manager = new LogManager($app);

    $manager->channel('stack');

    expect(array_keys($manager->getChannels()))->toBe(['single', 'daily', 'stderr', 'stack']);
});

test('a configured monolog handler is built with its constructor arguments', function () {
    $app = logApp();
    $config = $app['config'];
    $config->set('logging.channels.nonbubblingstream', [
        'driver' => 'monolog',
        'name' => 'foobar',
        'handler' => StreamHandler::class,
        'level' => 'notice',
        'with' => [
            'stream' => 'php://stderr',
            'bubble' => false,
        ],
    ]);

    $manager = new LogManager($app);

    // create logger with handler specified from configuration
    $logger = $manager->channel('nonbubblingstream');
    $handlers = $logger->getLogger()->getHandlers();

    expect($logger)->toBeInstanceOf(Logger::class)
        ->and($logger->getName())->toBe('foobar')
        ->and($handlers)->toHaveCount(1)
        ->and($handlers[0])->toBeInstanceOf(StreamHandler::class)
        ->and($handlers[0]->getLevel())->toEqual(Level::Notice)
        ->and($handlers[0]->getBubble())->toBeFalse()
        ->and(logManagerProperty($handlers[0], 'url'))->toBe('php://stderr');

    $config->set('logging.channels.logentries', [
        'driver' => 'monolog',
        'name' => 'le',
        'handler' => LogEntriesHandler::class,
        'with' => [
            'token' => '123456789',
        ],
    ]);

    $logger = $manager->channel('logentries');
    $handlers = $logger->getLogger()->getHandlers();

    expect($handlers[0])->toBeInstanceOf(LogEntriesHandler::class)
        ->and(logManagerProperty($handlers[0], 'logToken'))->toBe('123456789');
});

test('a configured monolog handler takes the configured formatter', function () {
    $app = logApp();
    $config = $app['config'];
    $config->set('logging.channels.newrelic', [
        'driver' => 'monolog',
        'name' => 'nr',
        'handler' => NewRelicHandler::class,
        'formatter' => 'default',
    ]);

    $manager = new LogManager($app);

    // create logger with handler specified from configuration
    $logger = $manager->channel('newrelic');
    $handler = $logger->getLogger()->getHandlers()[0];

    expect($handler)->toBeInstanceOf(NewRelicHandler::class)
        ->and($handler->getFormatter())->toBeInstanceOf(NormalizerFormatter::class);

    $config->set('logging.channels.newrelic2', [
        'driver' => 'monolog',
        'name' => 'nr',
        'handler' => NewRelicHandler::class,
        'formatter' => HtmlFormatter::class,
        'formatter_with' => [
            'dateFormat' => 'Y/m/d--test',
        ],
    ]);

    $logger = $manager->channel('newrelic2');
    $handler = $logger->getLogger()->getHandlers()[0];
    $formatter = $handler->getFormatter();

    expect($handler)->toBeInstanceOf(NewRelicHandler::class)
        ->and($formatter)->toBeInstanceOf(HtmlFormatter::class)
        ->and(logManagerProperty($formatter, 'dateFormat'))->toBe('Y/m/d--test');
});

test('a null handler is built with or without a formatter', function () {
    $app = logApp();
    $config = $app->make('config');
    $config->set('logging.channels.null', [
        'driver' => 'monolog',
        'handler' => NullHandler::class,
        'formatter' => HtmlFormatter::class,
    ]);

    $manager = new LogManager($app);

    // create logger with handler specified from configuration
    $logger = $manager->channel('null');
    $handler = $logger->getLogger()->getHandlers()[0];

    expect($handler)->toBeInstanceOf(NullHandler::class);

    $config->set('logging.channels.null2', [
        'driver' => 'monolog',
        'handler' => NullHandler::class,
    ]);

    $logger = $manager->channel('null2');
    $handler = $logger->getLogger()->getHandlers()[0];

    expect($handler)->toBeInstanceOf(NullHandler::class);
});

test('configured processors are pushed onto the logger', function () {
    $app = logApp();
    $config = $app->make('config');
    $config->set('logging.channels.memory', [
        'driver' => 'monolog',
        'name' => 'memory',
        'handler' => StreamHandler::class,
        'with' => [
            'stream' => 'php://stderr',
        ],
        'processors' => [
            MemoryUsageProcessor::class,
            ['processor' => PsrLogMessageProcessor::class, 'with' => ['removeUsedContextFields' => true]],
        ],
    ]);

    $manager = new LogManager($app);

    // create logger with handler specified from configuration
    $logger = $manager->channel('memory');
    $handler = $logger->getLogger()->getHandlers()[0];
    $processors = $logger->getLogger()->getProcessors();

    expect($handler)->toBeInstanceOf(StreamHandler::class)
        ->and($processors[1])->toBeInstanceOf(MemoryUsageProcessor::class)
        ->and($processors[2])->toBeInstanceOf(PsrLogMessageProcessor::class)
        ->and(logManagerProperty($processors[2], 'removeUsedContextFields'))->toBeTrue();
});

test('the null driver is used during tests, and the emergency logger only in production', function () {
    $app = logApp();
    $app['env'] = 'testing';
    $config = $app->make('config');
    $config->set('logging.default', null);
    $config->set('logging.channels.null', [
        'driver' => 'monolog',
        'handler' => NullHandler::class,
    ]);
    $manager = new ThrowingEmergencyLogManager($app);

    // In tests, this should not need to create the emergency logger...
    $manager->info('message');

    // we should also be able to forget the null channel...
    expect($manager->getChannels())->toHaveCount(1);
    $manager->forgetChannel();
    expect($manager->getChannels())->toHaveCount(0);

    // However in production we want it to fallback to the emergency logger...
    $app['env'] = 'production';

    try {
        $manager->info('message');

        $this->fail('Emergency logger was not created as expected.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Emergency logger was created.');
    }
});

test('the single driver takes the configured formatter', function () {
    $app = logApp();
    $config = $app['config'];
    $config->set('logging.channels.defaultsingle', [
        'driver' => 'single',
        'name' => 'ds',
        'path' => storage_path('logs/venusian.log'),
        'replace_placeholders' => true,
    ]);

    $manager = new LogManager($app);

    // create logger with handler specified from configuration
    $logger = $manager->channel('defaultsingle');
    $handler = $logger->getLogger()->getHandlers()[0];
    $formatter = $handler->getFormatter();

    expect($handler)->toBeInstanceOf(StreamHandler::class)
        ->and($formatter)->toBeInstanceOf(LineFormatter::class)
        ->and($logger->getLogger()->getProcessors()[1])->toBeInstanceOf(PsrLogMessageProcessor::class);

    $config->set('logging.channels.formattedsingle', [
        'driver' => 'single',
        'name' => 'fs',
        'path' => storage_path('logs/venusian.log'),
        'formatter' => HtmlFormatter::class,
        'formatter_with' => [
            'dateFormat' => 'Y/m/d--test',
        ],
        'replace_placeholders' => false,
    ]);

    $logger = $manager->channel('formattedsingle');
    $handler = $logger->getLogger()->getHandlers()[0];
    $formatter = $handler->getFormatter();

    expect($handler)->toBeInstanceOf(StreamHandler::class)
        ->and($formatter)->toBeInstanceOf(HtmlFormatter::class)
        ->and($logger->getLogger()->getProcessors())->toHaveCount(1)
        ->and(logManagerProperty($formatter, 'dateFormat'))->toBe('Y/m/d--test');
});

test('the daily driver takes the configured formatter', function () {
    $app = logApp();
    $config = $app['config'];
    $config->set('logging.channels.defaultdaily', [
        'driver' => 'daily',
        'name' => 'dd',
        'path' => storage_path('logs/venusian.log'),
        'replace_placeholders' => true,
    ]);

    $manager = new LogManager($app);

    // create logger with handler specified from configuration
    $logger = $manager->channel('defaultdaily');
    $handler = $logger->getLogger()->getHandlers()[0];
    $formatter = $handler->getFormatter();

    expect($handler)->toBeInstanceOf(StreamHandler::class)
        ->and($formatter)->toBeInstanceOf(LineFormatter::class)
        ->and($logger->getLogger()->getProcessors()[1])->toBeInstanceOf(PsrLogMessageProcessor::class);

    $config->set('logging.channels.formatteddaily', [
        'driver' => 'daily',
        'name' => 'fd',
        'path' => storage_path('logs/venusian.log'),
        'formatter' => HtmlFormatter::class,
        'formatter_with' => [
            'dateFormat' => 'Y/m/d--test',
        ],
        'replace_placeholders' => false,
    ]);

    $logger = $manager->channel('formatteddaily');
    $handler = $logger->getLogger()->getHandlers()[0];
    $formatter = $handler->getFormatter();

    expect($handler)->toBeInstanceOf(StreamHandler::class)
        ->and($formatter)->toBeInstanceOf(HtmlFormatter::class)
        ->and($logger->getLogger()->getProcessors())->toHaveCount(1)
        ->and(logManagerProperty($formatter, 'dateFormat'))->toBe('Y/m/d--test');
});

test('the syslog driver takes the configured formatter', function () {
    $app = logApp();
    $app['config']->set('app.name', 'Venusian');
    $config = $app['config'];
    $config->set('logging.channels.defaultsyslog', [
        'driver' => 'syslog',
        'name' => 'ds',
        'replace_placeholders' => true,
    ]);

    $manager = new LogManager($app);

    // create logger with handler specified from configuration
    $logger = $manager->channel('defaultsyslog');
    $handler = $logger->getLogger()->getHandlers()[0];
    $formatter = $handler->getFormatter();

    expect($handler)->toBeInstanceOf(SyslogHandler::class)
        ->and($formatter)->toBeInstanceOf(LineFormatter::class)
        ->and($logger->getLogger()->getProcessors()[1])->toBeInstanceOf(PsrLogMessageProcessor::class);

    $config->set('logging.channels.formattedsyslog', [
        'driver' => 'syslog',
        'name' => 'fs',
        'formatter' => HtmlFormatter::class,
        'formatter_with' => [
            'dateFormat' => 'Y/m/d--test',
        ],
        'replace_placeholders' => false,
    ]);

    $logger = $manager->channel('formattedsyslog');
    $handler = $logger->getLogger()->getHandlers()[0];
    $formatter = $handler->getFormatter();

    expect($handler)->toBeInstanceOf(SyslogHandler::class)
        ->and($formatter)->toBeInstanceOf(HtmlFormatter::class)
        ->and($logger->getLogger()->getProcessors())->toHaveCount(1)
        ->and(logManagerProperty($formatter, 'dateFormat'))->toBe('Y/m/d--test');
});

test('a resolved channel can be purged', function () {
    $app = logApp();
    $manager = new LogManager($app);

    expect($manager->getChannels())->toBeEmpty();

    $manager->channel('single')->getLogger();

    expect($manager->getChannels())->toHaveCount(1);

    $manager->forgetChannel('single');

    expect($manager->getChannels())->toBeEmpty();
});

test('an on demand channel can be built from a config array', function () {
    $app = logApp();
    $manager = new LogManager($app);

    $logger = $manager->build([
        'driver' => 'single',
        'path' => storage_path('logs/on-demand.log'),
    ]);
    $handler = $logger->getLogger()->getHandlers()[0];

    expect($handler)->toBeInstanceOf(StreamHandler::class)
        ->and(logManagerProperty($handler, 'url'))->toBe(storage_path('logs/on-demand.log'));
});

test('an on demand channel can join an on demand stack', function () {
    $app = logApp();
    $manager = new LogManager($app);
    $app['config']->set('logging.channels.test', [
        'driver' => 'single',
        'path' => storage_path('logs/venusian.log'),
    ]);

    $channel = $manager->build([
        'driver' => 'custom',
        'via' => CustomChannelFactory::class,
    ]);
    $logger = $manager->stack(['test', $channel]);

    $handler = $logger->getLogger()->getHandlers()[1];

    expect($handler)->toBeInstanceOf(StreamHandler::class)
        ->and($logger->getLogger()->getProcessors()[2])->toBeInstanceOf(UidProcessor::class)
        ->and(logManagerProperty($handler, 'url'))->toBe(storage_path('logs/custom.log'));
});

describe('fingers crossed', function () {
    test('an action level wraps the handler', function () {
        $app = logApp();
        $app['config']->set('logging.channels.fingerscrossed', [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'level' => 'debug',
            'action_level' => 'critical',
            'with' => [
                'stream' => 'php://stderr',
                'bubble' => false,
            ],
        ]);

        $manager = new LogManager($app);

        // create logger with handler specified from configuration
        $logger = $manager->channel('fingerscrossed');
        $handlers = $logger->getLogger()->getHandlers();

        expect($logger)->toBeInstanceOf(Logger::class)
            ->and($handlers)->toHaveCount(1);

        $expectedFingersCrossedHandler = $handlers[0];
        expect($expectedFingersCrossedHandler)->toBeInstanceOf(FingersCrossedHandler::class);

        $activationStrategyValue = logManagerProperty($expectedFingersCrossedHandler, 'activationStrategy');
        $actionLevelValue = logManagerProperty($activationStrategyValue, 'actionLevel');

        expect($actionLevelValue)->toEqual(Level::Critical);

        if (method_exists($expectedFingersCrossedHandler, 'getHandler')) {
            $expectedStreamHandler = $expectedFingersCrossedHandler->getHandler();
        } else {
            $expectedStreamHandler = logManagerProperty($expectedFingersCrossedHandler, 'handler');
        }

        expect($expectedStreamHandler)->toBeInstanceOf(StreamHandler::class)
            ->and($expectedStreamHandler->getLevel())->toEqual(Level::Debug);
    });

    test('buffering stops after the first flush by default', function () {
        $app = logApp();
        $app['config']->set('logging.channels.fingerscrossed', [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'level' => 'debug',
            'action_level' => 'critical',
            'with' => [
                'stream' => 'php://stderr',
                'bubble' => false,
            ],
        ]);

        $manager = new LogManager($app);

        // create logger with handler specified from configuration
        $logger = $manager->channel('fingerscrossed');
        $handlers = $logger->getLogger()->getHandlers();

        expect(logManagerProperty($handlers[0], 'stopBuffering'))->toBeTrue();
    });

    test('buffering can be configured to resume after flushing', function () {
        $app = logApp();
        $app['config']->set('logging.channels.fingerscrossed', [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'level' => 'debug',
            'action_level' => 'critical',
            'stop_buffering' => false,
            'with' => [
                'stream' => 'php://stderr',
                'bubble' => false,
            ],
        ]);

        $manager = new LogManager($app);

        // create logger with handler specified from configuration
        $logger = $manager->channel('fingerscrossed');
        $handlers = $logger->getLogger()->getHandlers();

        expect(logManagerProperty($handlers[0], 'stopBuffering'))->toBeFalse();
    });
});

describe('shared context', function () {
    test('is shared with already resolved channels', function () {
        $app = logApp();
        $manager = new LogManager($app);
        $channel = $manager->channel('single');
        $context = null;

        $channel->listen(function ($message) use (&$context) {
            $context = $message->context;
        });
        $manager->shareContext([
            'invocation-id' => 'expected-id',
        ]);
        $channel->info('xxxx');

        expect($context)->toBe(['invocation-id' => 'expected-id']);
    });

    test('is shared with freshly resolved channels', function () {
        $app = logApp();
        $manager = new LogManager($app);
        $context = null;

        $manager->shareContext([
            'invocation-id' => 'expected-id',
        ]);
        $manager->channel('single')->listen(function ($message) use (&$context) {
            $context = $message->context;
        });
        $manager->channel('single')->info('xxxx');

        expect($context)->toBe(['invocation-id' => 'expected-id']);
    });

    test('can be read publicly by other logging systems', function () {
        $app = logApp();
        $manager = new LogManager($app);

        $manager->shareContext([
            'invocation-id' => 'expected-id',
        ]);

        expect($manager->sharedContext())->toBe(['invocation-id' => 'expected-id']);
    });

    test('is shared with stacks when they are resolved', function () {
        $app = logApp();
        $manager = new LogManager($app);
        $context = null;

        $manager->shareContext([
            'invocation-id' => 'expected-id',
        ]);
        $stack = $manager->stack(['single']);
        $stack->listen(function ($message) use (&$context) {
            $context = $message->context;
        });
        $stack->info('xxxx');

        expect($context)->toBe(['invocation-id' => 'expected-id']);
    });

    test('merges rather than replaces', function () {
        $app = logApp();
        $manager = new LogManager($app);
        $context = null;

        $manager->shareContext([
            'invocation-id' => 'expected-id',
        ]);
        $manager->shareContext([
            'invocation-start' => 1651800456,
        ]);
        $manager->channel('single')->listen(function ($message) use (&$context) {
            $context = $message->context;
        });
        $manager->channel('single')->info('xxxx', [
            'logged' => 'context',
        ]);

        expect($context)->toBe([
            'invocation-id' => 'expected-id',
            'invocation-start' => 1651800456,
            'logged' => 'context',
        ])->and($manager->sharedContext())->toBe([
            'invocation-id' => 'expected-id',
            'invocation-start' => 1651800456,
        ]);
    });

    test('can be flushed', function () {
        $app = logApp();
        $manager = new LogManager($app);

        $manager->shareContext($context = ['foo' => 'bar']);

        expect($manager->sharedContext())->toBe($context);

        $manager->flushSharedContext();

        expect($manager->sharedContext())->toBeEmpty();
    });
});

test('a tap can customize the formatter', function () {
    $app = logApp();
    $app['config']->set('logging.channels.custom', [
        'driver' => 'single',
        'path' => storage_path('logs/venusian.log'),
        'tap' => [CustomizeFormatter::class],
    ]);

    $manager = new LogManager($app);

    $logger = $manager->channel('custom');
    $handler = $logger->getLogger()->getHandlers()[0];
    $formatter = $handler->getFormatter();

    expect($formatter)->toBeInstanceOf(LineFormatter::class)
        ->and(rtrim(logManagerProperty($formatter, 'format')))
        ->toEqual('[%datetime%] %channel%.%level_name%: %message% %context% %extra%');
});

test('a driver returning a PSR logger is used as the channel', function () {
    $app = logApp();
    // Given
    $app['config']->set('logging.channels.spy', [
        'driver' => 'spy',
    ]);

    $manager = new LogManager($app);

    $loggerSpy = new LoggerSpy;
    $manager->extend('spy', fn () => $loggerSpy);

    // When
    $logger = $manager->channel('spy');
    $logger->alert('some alert');

    // Then
    expect($loggerSpy->logs)->toHaveCount(1)
        ->and($loggerSpy->logs[0]['message'])->toEqual('some alert');
});
