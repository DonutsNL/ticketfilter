<?php
namespace tests;

use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ticketFilterTest extends Testcase
{
    public function testWithoutDelay()
    {
        $this->assertNull(null);
    }
}
// This plugin uses extensive database functions
// therefor we need to modify the classes to allow for more
// testing. This function is just preparations to allow 
// phpunit testing. Simply run ./vendor/bin/phpunit ./tests