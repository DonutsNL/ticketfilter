<?php
namespace tests;
require('./tests/bootstrap.php');

use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

use GlpiPlugin\Ticketfilter\TicketHandler;

class ticketFilterTest extends Testcase
{
    public function testTicketHandler()
    {
        $tf = new TicketHandler();

        
        print("\nEvaluate initHandler exists in TicketHandler::class : ");
        if(!$this->assertTrue(
            method_exists(TicketHandler::class, 'initHandler'), 
            'Class TicketHandler::class does not have method initHandler'
        )){ print("Ok!\n"); }

        print("Evaluate getId exists in TicketHandler::class : ");
        if(!$this->assertTrue(
            method_exists(TicketHandler::class, 'getId'), 
            'Class TicketHandler::class does not have method getId'
        )){ print("Ok!\n"); }

        print("Evaluate TicketHandler->getId returns interger : ");
        if(!$this->assertTrue(is_int($tf->getId(1)), 
            'Class TicketHandler::getId(1) did not return integer')){
            print("Ok!\n");
        };
        
        print("Evaluate addSolvedMessage exists in TicketHandler::class : ");
        if(!$this->assertTrue(
            method_exists(TicketHandler::class, 'addSolvedMessage'), 
            'Class TicketHandler::class does not have method addSolvedMessage'
        )){ print("Ok!\n"); }
        
        $this->assertTrue(
            method_exists(TicketHandler::class, 'addSolvedMessage'), 
            'Class TicketHandler::class does not have method addSolvedMessage'
        );
    }

    public function testWithoutDelay()
    {
        $this->assertNull(null);
    }
}
// This plugin uses extensive database functions
// therefor we need to modify the classes to allow for more
// testing. This function is just preparations to allow 
// phpunit testing. Simply run ./vendor/bin/phpunit ./tests