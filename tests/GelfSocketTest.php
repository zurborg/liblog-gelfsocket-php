<?php

use \Log\GelfSocket;
use \Pirate\Hooray\Arr;
use \Wrap\JSON;

class GelfSocketTest extends PHPUnit_Framework_TestCase
{
    public $sockfile = 'test.sock';
    public $listener;
    public function setUp()
    {
        $this->listener = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        socket_bind($this->listener, $this->sockfile);
    }

    private function readMsg()
    {
        $msg = "";
        socket_recv($this->listener, $msg, 65536, MSG_DONTWAIT);
        if (is_null($msg)) return null;
        return JSON::decodeArray($msg);
    }

    private function getLogger()
    {
        $this->assertSame(null, $this->readMsg());
        $logger = new \Log\GelfSocket($this->sockfile);
        $this->assertSame(null, $this->readMsg());
        return $logger;
    }

    public function test001()
    {
        $logger = $this->getLogger();
        $logger->info('Hej!');
        $gelf = $this->readMsg();
        $this->assertSame('Hej!', Arr::get($gelf, 'message'));
        $this->assertSame(7, Arr::get($gelf, 'level'));
    }

    public function test002()
    {
        $logger = $this->getLogger();
        $before = microtime(true);
        $logger->info('Hej!');
        $after = microtime(true);
        $gelf = $this->readMsg();
        $this->assertGreaterThan($before, Arr::get($gelf, 'timestamp'));
        $this->assertLessThan($after, Arr::get($gelf, 'timestamp'));
    }

    public function test003()
    {
        $logger = $this->getLogger();
        $logger->info('info');
        $logger->debug('debug');
        $logger->warning('warning');
        $info = $this->readMsg();
        $debug = $this->readMsg();
        $warning = $this->readMsg();
        $this->assertSame('info', Arr::get($info, 'message'));
        $this->assertSame('debug', Arr::get($debug, 'message'));
        $this->assertSame('warning', Arr::get($warning, 'message'));
    }

    public function test004()
    {
        $logger = $this->getLogger();
        $e = new \Exception('test');
        $logger->error('exception', ['exception' => $e]);
        $error = $this->readMsg();
        $this->assertRegExp('/Exception: test in/', Arr::get($error, '_exception'));
    }

    public function test005()
    {
        $logger = $this->getLogger();
        $counter = 0;
        $f = function () use (&$counter) { $counter++; return 'test'; };
        $this->assertSame(0, $counter);
        $logger->info('function', ['function' => $f]);
        $this->assertSame(1, $counter);
        $logger->info('function', ['function' => $f]);
        $this->assertSame(2, $counter);
        $error = $this->readMsg();
        $this->assertSame(2, $counter);
        $this->assertSame('test', Arr::get($error, '_function'));
        $this->assertSame(2, $counter);
        $this->assertSame('test', Arr::get($error, '_function'));
        $this->assertSame(2, $counter);
    }

    public function test006()
    {
        $logger = $this->getLogger();
        $logger->flush();
        $this->assertSame(null, $this->readMsg());
        $logger->disableAutoflush();
        $this->assertSame(null, $this->readMsg());
        $logger->info('info');
        $this->assertSame(null, $this->readMsg());
        $logger->flush();
        $this->assertSame('info', Arr::get($this->readMsg(), 'message'));
        $this->assertSame(null, $this->readMsg());
    }

    public function test007()
    {
        $logger = $this->getLogger();

        $logger->flush();
        $this->assertSame(null, $this->readMsg());

        $logger->disableAutoflush();
        $this->assertSame(null, $this->readMsg());

        $logger->info('info');
        $this->assertSame(null, $this->readMsg());

        $logger->debug('debug');
        $this->assertSame(null, $this->readMsg());

        $logger->warning('warning');
        $this->assertSame(null, $this->readMsg());

        $logger->flush();
        $info = $this->readMsg();
        $debug = $this->readMsg();
        $warning = $this->readMsg();
        $this->assertSame(null, $this->readMsg());

        $this->assertSame('info', Arr::get($info, 'message'));
        $this->assertSame('debug', Arr::get($debug, 'message'));
        $this->assertSame('warning', Arr::get($warning, 'message'));

        $logger->flush();
        $this->assertSame(null, $this->readMsg());
    }

    public function test008()
    {
        $logger = $this->getLogger();
        $logger->log('info', "1\n2\n3\n4");
        $gelf = $this->readMsg();
        $this->assertSame('1', Arr::get($gelf, 'message'));
        $this->assertSame("2\n3\n4", Arr::get($gelf, 'full_message'));
    }

    public function test009a()
    {
        $logger = $this->getLogger();
        $exception = new \DomainException('meh');
        $logger->logException('alert', $exception);
        $gelf = $this->readMsg();
        $this->assertSame('meh', Arr::get($gelf, 'message'));
        $this->assertSame('Domain~', Arr::get($gelf, '_class'));
    }

    public function test009b()
    {
        $logger = $this->getLogger();
        $exception = new \DomainException('meh');
        $logger->logThrowable('alert', $exception);
        $gelf = $this->readMsg();
        $this->assertSame('meh', Arr::get($gelf, 'message'));
        $this->assertSame('Domain~', Arr::get($gelf, '_class'));
    }

    public function test010()
    {
        $logger = $this->getLogger();
        $logger->installErrorHandler();
        \trigger_error('bla');
        $gelf = $this->readMsg();
        $this->assertSame('bla', Arr::get($gelf, 'message'));
    }

    public function test999()
    {
        $this->assertSame(null, $this->readMsg());
    }

    public function tearDown()
    {
        socket_close($this->listener);
        unlink($this->sockfile);
    }
}
