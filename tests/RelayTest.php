<?php
namespace Relay;

use Zend\Diactoros\ServerRequestFactory;
use Zend\Diactoros\Response;

class RelayTest extends \PHPUnit_Framework_TestCase
{
    public function test()
    {
        FakeMiddleware::$count = 0;

        $queue[] = new FakeMiddleware();
        $queue[] = new FakeMiddleware();
        $queue[] = new FakeMiddleware();

        $relayBuilder = new RelayBuilder();
        $relay = $relayBuilder->newInstance($queue);

        // run the first time
        $response = $relay(
            ServerRequestFactory::fromGlobals(),
            new Response()
        );
        $actual = (string) $response->getBody();
        $this->assertSame('123456', $actual);

        // run again
        $response = $relay(
            ServerRequestFactory::fromGlobals(),
            new Response()
        );
        $actual = (string) $response->getBody();
        $this->assertSame('789101112', $actual);
    }
}
