<?php

namespace Relay;

use Closure;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7Server\ServerRequestCreator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use TypeError;

class RelayTest extends TestCase
{
    /** @var Closure */
    protected $responder;

    protected function setUp(): void
    {
        $this->responder = function ($request, $next) {
            return new Response();
        };
    }

    protected function assertRelay(Relay $relay): void
    {
        FakeMiddleware::$count = 0;

        // relay once
        $response = $relay->handle($this->createRequestFromGlobals());
        $actual   = (string) $response->getBody();
        $this->assertSame('<3<2<1', $actual);

        // relay again
        $response = $relay->handle($this->createRequestFromGlobals());
        $actual   = (string) $response->getBody();
        $this->assertSame('<6<5<4', $actual);
    }

    public function testArrayQueue(): void
    {
        $queue = [
            new FakeMiddleware(),
            new FakeMiddleware(),
            new FakeMiddleware(),
            $this->responder,
        ];

        $this->assertRelay(new Relay($queue));
    }

    public function testTraversableQueue(): void
    {
        $queue = new class implements IteratorAggregate {
            public function getIterator(): Generator
            {
                yield new FakeMiddleware();
                yield new FakeMiddleware();
                yield new FakeMiddleware();
                yield function ($request, $next) {
                    return new Response();
                };
            }
        };

        $this->assertRelay(new Relay($queue));
    }

    /**
     * @psalm-suppress InvalidArgument
     */
    public function testBadQueue(): void
    {
        $this->expectException(TypeError::class);
        new Relay('bad');
    }

    public function testEmptyQueue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$queue cannot be empty');

        new Relay([]);
    }

    public function testQueueWithInvalidEntry(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Invalid middleware queue entry: bad. Middleware must either be callable or implement Psr\Http\Server\MiddlewareInterface.'
        );

        $relay = new Relay(['bad']);
        $relay->handle($this->createRequestFromGlobals());
    }

    public function testResolverEntries(): void
    {
        $queue = [
            FakeMiddleware::class,
            FakeMiddleware::class,
            FakeMiddleware::class,
            $this->responder,
        ];

        $resolver = new FakeResolver();

        $this->assertRelay(new Relay($queue, $resolver));
    }

    public function testRequestHandlerInQueue(): void
    {
        $queue          = [
            new FakeMiddleware(),
            new FakeMiddleware(),
            new FakeMiddleware(),
            $this->responder,
        ];
        $requestHandler = new Relay($queue);
        $this->assertRelay(new Relay([$requestHandler]));
    }

    public function testCallableMiddleware(): void
    {
        $queue = [
            function (
                ServerRequestInterface $request,
                callable $next
            ): ResponseInterface {
                $response = $next($request);

                $response->getBody()->write('Hello, callable world!');

                return $response;
            },
            $this->responder,
        ];

        $relay    = new Relay($queue);
        $response = $relay->handle($this->createRequestFromGlobals());

        $this->assertEquals('Hello, callable world!', (string) $response->getBody());
    }

    private function createRequestFromGlobals(): ServerRequestInterface
    {
        $psr17Factory = new Psr17Factory();

        $creator = new ServerRequestCreator(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        return $creator->fromGlobals();
    }
}
