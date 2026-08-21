<?php

use Voyager\Contracts\Debug\ExceptionHandler;
use Voyager\Http\Client\ConnectionException;
use Voyager\Http\Client\Factory as HttpFactory;
use Voyager\Http\Client\Response;
use Voyager\Validation\NotPwnedVerifier;
use Voyager\Vessel\Vessel;

afterEach(function () {
    Vessel::setInstance(null);
});

test('empty values', function () {
        $httpFactory = Mockery::mock(HttpFactory::class);
        $verifier = new NotPwnedVerifier($httpFactory);

        foreach (['', false, 0] as $password) {
            expect($verifier->verify([
                'value' => $password,
                'threshold' => 0,
            ]))->toBeFalse();
        }
    });

test('api response goes wrong', function () {
        $httpFactory = Mockery::mock(HttpFactory::class);
        $response = Mockery::mock(Response::class);

        $httpFactory = Mockery::mock(HttpFactory::class);

        $httpFactory
            ->shouldReceive('withHeaders')
            ->once()
            ->with(['Add-Padding' => true])
            ->andReturn($httpFactory);

        $httpFactory
            ->shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturn($httpFactory);

        $httpFactory->shouldReceive('get')
            ->once()
            ->andReturn($response);

        $response->shouldReceive('successful')
            ->once()
            ->andReturn(true);

        $response->shouldReceive('body')
            ->once()
            ->andReturn('');

        $verifier = new NotPwnedVerifier($httpFactory);

        expect($verifier->verify([
            'value' => 123123123,
            'threshold' => 0,
        ]))->toBeTrue();
    });

test('api goes down', function () {
        $httpFactory = Mockery::mock(HttpFactory::class);
        $response = Mockery::mock(Response::class);

        $httpFactory
            ->shouldReceive('withHeaders')
            ->once()
            ->with(['Add-Padding' => true])
            ->andReturn($httpFactory);

        $httpFactory
            ->shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturn($httpFactory);

        $httpFactory->shouldReceive('get')
            ->once()
            ->andReturn($response);

        $response->shouldReceive('successful')
            ->once()
            ->andReturn(false);

        $verifier = new NotPwnedVerifier($httpFactory);

        expect($verifier->verify([
            'value' => 123123123,
            'threshold' => 0,
        ]))->toBeTrue();
    });

test('dns down', function () {
        $container = Vessel::getInstance();
        $exception = new ConnectionException();

        $exceptionHandler = Mockery::mock(ExceptionHandler::class);
        $exceptionHandler->shouldReceive('report')->once()->with($exception);
        $container->bind(ExceptionHandler::class, function () use ($exceptionHandler) {
            return $exceptionHandler;
        });

        $httpFactory = Mockery::mock(HttpFactory::class);

        $httpFactory
            ->shouldReceive('withHeaders')
            ->once()
            ->with(['Add-Padding' => true])
            ->andReturn($httpFactory);

        $httpFactory
            ->shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturn($httpFactory);

        $httpFactory
            ->shouldReceive('get')
            ->once()
            ->andThrow($exception);

        $verifier = new NotPwnedVerifier($httpFactory);
        expect($verifier->verify([
            'value' => 123123123,
            'threshold' => 0,
        ]))->toBeTrue();

        unset($container[ExceptionHandler::class]);
    });

