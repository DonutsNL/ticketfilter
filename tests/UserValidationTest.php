<?php
namespace tests;

use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

use GlpiPlugin\Ticketfilter\TicketHandler;
use GlpiPlugin\Ticketfilter\FilterPattern;

// Needs more work.
// We need to work around the fact that the GLPI objects
// are not available in the plugin repository.
// Maybe create MOC objects instead.
/*
class UserValidationTest extends Testcase {

    public function testUserValidation() 
    {
        $ticket = new TestTicket();
        $ticket->addToFields('status', 1);

        $ticketHandler = new TicketHandler($ticket, FilterPattern::getDummyPattern());
        //if(!$this->assertTrue() 
    }
}

class Ticket {
    // Do stuff to represent a real ticket class.
}
*/