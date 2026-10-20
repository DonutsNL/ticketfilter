<?php

/**
 *  ------------------------------------------------------------------------
 *  TicketFilter Plugin for GLPI
 *  Copyright (C) 2023-2026 by Chris Gralike
 *  ------------------------------------------------------------------------
 *
 *  LICENSE
 *
 *  This file is part of TicketFilter plugin for GLPI.
 *
 *  TicketFilter is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  TicketFilter is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with TicketFilter. If not, see <http://www.gnu.org/licenses/>.
 *
 *  ------------------------------------------------------------------------
 *
 *  @package    TicketFilter
 *  @version    2.0.0
 *  @author     Chris Gralike
 *  @copyright  Copyright (c) 2023-2026 by Chris Gralike
 *  @license    GPLv3+
 *  @link       https://github.com/DonutsNL/ticketfilter
 *  ------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/Shims.php';
require_once __DIR__ . '/../src/Audit/AuditLog.php';
require_once __DIR__ . '/../src/Audit/AuditLogger.php';
require_once __DIR__ . '/../src/Model/FilterPattern.php';
require_once __DIR__ . '/../src/Engine/PatternMatcher.php';
require_once __DIR__ . '/../src/Engine/TicketProcessor.php';
require_once __DIR__ . '/../src/Engine/FilterEngine.php';

use GlpiPlugin\Ticketfilter\Engine\FilterEngine;
use GlpiPlugin\Ticketfilter\Engine\TicketProcessor;
use GlpiPlugin\Ticketfilter\Model\FilterPattern;

if (!function_exists('assertCondition')) {
    function assertCondition(bool $condition, string $description): void
    {
        if (!$condition) {
            throw new \Exception("Assertion failed: $description");
        }
        echo "  [PASS] $description\n";
    }
}

echo "Running SecurityIsolationTest...\n";

// 1. Mock Ticket_User actor resolver
class MockTicketUser
{
    public function getActors(int $ticketId): array
    {
        if ($ticketId === 101) {
            return [
                'requesters' => [
                    [
                        'type'              => CommonITILActor::REQUESTER,
                        'users_id'          => 999,
                        'alternative_email' => 'attacker@evil.com',
                    ]
                ]
            ];
        }

        return [
            'requesters' => [
                [
                    'type'              => CommonITILActor::REQUESTER,
                    'users_id'          => 10,
                    'alternative_email' => 'client@customer.com',
                ]
            ],
            'assignees' => [
                [
                    'type'              => CommonITILActor::ASSIGN,
                    'users_id'          => 20,
                    'alternative_email' => 'technician@company.com',
                ]
            ]
        ];
    }
}

$targetTicket = new Ticket();
$targetTicket->fields['id'] = 100;
$targetTicket->fields['status'] = CommonITILObject::ASSIGNED;
$targetTicket->userlinkclass = MockTicketUser::class;

// 2. Test strict requester policy: matching requester passes
$itemRequester = new Ticket();
$itemRequester->input = [
    'name'                  => 'Re: Update',
    '_users_id_requester'   => 10,
    '_actors'               => [
        'requester' => [
            ['items_id' => 10, 'default_email' => 'client@customer.com']
        ]
    ]
];
$resStrictPass = TicketProcessor::verifySender($itemRequester, $targetTicket, FilterPattern::POLICY_STRICT_REQUESTER);
assertCondition($resStrictPass['authorized'] === true, 'Strict requester policy allows matching requester user ID/email');

// 2b. Test mailgate anonymous requester (no _actors, email in _users_id_requester_notif and _head)
$itemMailgateAnonymous = new Ticket();
$itemMailgateAnonymous->input = [
    'name'                      => 'Re: Update from mailgate',
    '_users_id_requester'       => 0,
    '_users_id_requester_notif' => [
        'alternative_email' => ['client@customer.com'],
    ],
    '_head' => [
        'from' => 'client@customer.com',
    ],
];
$resMailgatePass = TicketProcessor::verifySender($itemMailgateAnonymous, $targetTicket, FilterPattern::POLICY_STRICT_REQUESTER);
assertCondition($resMailgatePass['authorized'] === true, 'Mailgate anonymous requester without _actors is authorized via alternative_email');

// 3. Test strict requester policy: unauthorized attacker is BLOCKED
$itemAttacker = new Ticket();
$itemAttacker->input = [
    'name'                  => 'Re: Infiltration attempt',
    '_users_id_requester'   => 999,
    '_actors'               => [
        'requester' => [
            ['items_id' => 999, 'default_email' => 'attacker@evil.com']
        ]
    ]
];
$resStrictFail = TicketProcessor::verifySender($itemAttacker, $targetTicket, FilterPattern::POLICY_STRICT_REQUESTER);
assertCondition($resStrictFail['authorized'] === false, 'Strict requester policy BLOCKS unknown third-party sender');

// 4. Test same domain policy: matching domain passes, foreign domain blocked
$itemSameDomain = new Ticket();
$itemSameDomain->input = [
    'name'                  => 'Colleague update',
    '_users_id_requester'   => 55,
    '_actors'               => [
        'requester' => [
            ['items_id' => 55, 'default_email' => 'colleague@customer.com']
        ]
    ]
];
$resDomainPass = TicketProcessor::verifySender($itemSameDomain, $targetTicket, FilterPattern::POLICY_SAME_DOMAIN);
assertCondition($resDomainPass['authorized'] === true, 'Same domain policy allows colleague with matching @customer.com domain');

$resDomainFail = TicketProcessor::verifySender($itemAttacker, $targetTicket, FilterPattern::POLICY_SAME_DOMAIN);
assertCondition($resDomainFail['authorized'] === false, 'Same domain policy BLOCKS non-matching domain');

// 5. Test Mailgate Fault Isolation: calling FilterEngine with corrupted/malformed input NEVER throws uncaught exception
$corruptedTicket = new Ticket();
$corruptedTicket->input = ['name' => null]; // null name
FilterEngine::preItemAdd($corruptedTicket);
assertCondition(true, 'FilterEngine gracefully handles corrupted input without fatal error');

// 6. Test Multi-Candidate Resolution (DoS Mitigation / Option 1)
// Target ticket 100 (original) and Target ticket 101 (split copy)
$targetTicketOriginal = new Ticket();
$targetTicketOriginal->fields['id'] = 100;
$targetTicketOriginal->userlinkclass = MockTicketUser::class;

$targetTicketCopy = new Ticket();
$targetTicketCopy->fields['id'] = 101;
$targetTicketCopy->userlinkclass = MockTicketUser::class;

// When original requester sends: candidate #100 authorizes them
$candOriginal = TicketProcessor::verifySender($itemRequester, $targetTicketOriginal, FilterPattern::POLICY_STRICT_REQUESTER);
assertCondition($candOriginal['authorized'] === true, 'Original candidate #100 authorizes original requester');

// When copy creator sends: candidate #100 rejects them, but candidate #101 authorizes them
$candCopyOnOriginal = TicketProcessor::verifySender($itemAttacker, $targetTicketOriginal, FilterPattern::POLICY_STRICT_REQUESTER);
assertCondition($candCopyOnOriginal['authorized'] === false, 'Original candidate #100 rejects third-party/copy requester');

$candCopyOnCopy = TicketProcessor::verifySender($itemAttacker, $targetTicketCopy, FilterPattern::POLICY_STRICT_REQUESTER);
assertCondition($candCopyOnCopy['authorized'] === true, 'Candidate #101 (copy) authorizes its own creator, preventing collision DoS');

// 7. Test Followup Attribution on Merged Tickets
$_SESSION['glpilanguage'] = 'nl_NL';
$patternMock = [
    FilterPattern::FIELD_ID   => 1,
    FilterPattern::FIELD_NAME => 'Nieuwe aanvraag werkplek (CRU-5001)',
];
$incomingItemToMerge = new Ticket();
$incomingItemToMerge->input = [
    'name'                => 'Re: Nieuwe aanvraag werkplek (CRU-5001)',
    'content'             => 'Dit is een testreactie vanaf een niet-geregistreerd e-mailadres.',
    '_users_id_requester' => 10,
];
$initialFollowupCount = count(ITILFollowup::$createdFollowups);
$processRes = TicketProcessor::processTicket($incomingItemToMerge, $targetTicketOriginal, $patternMock, 'CRU-5001', false);
assertCondition($processRes['success'] === true, 'TicketProcessor::processTicket executes successfully');
assertCondition(count(ITILFollowup::$createdFollowups) === $initialFollowupCount + 2, 'Two followups added: email content and private audit followup');

$contentFollowup = ITILFollowup::$createdFollowups[$initialFollowupCount];
assertCondition($contentFollowup['content'] === 'Dit is een testreactie vanaf een niet-geregistreerd e-mailadres.', 'Content followup contains pure email content without technical metadata');
assertCondition(!str_contains($contentFollowup['content'], 'ticketfilter-attribution'), 'Content followup does NOT contain ticketfilter-attribution CSS block');
assertCondition(!str_contains($contentFollowup['content'], 'Nieuwe aanvraag werkplek'), 'Content followup does NOT leak internal filter rule name into customer notifications');

$auditFollowup = ITILFollowup::$createdFollowups[$initialFollowupCount + 1];
assertCondition($auditFollowup['items_id'] === 100, 'Audit followup targets the correct ticket ID');
assertCondition($auditFollowup['is_private'] === 1, 'Audit followup is marked as private');
assertCondition($auditFollowup['_disablenotif'] === true, 'Audit followup disables notifications to prevent alerting requesters');
assertCondition(str_contains($auditFollowup['content'], 'Nieuwe aanvraag werkplek (CRU-5001)'), 'Audit followup content includes pattern name');
assertCondition(str_contains($auditFollowup['content'], 'CRU-5001'), 'Audit followup content includes matched token');

// 8. Test Mailgate Fault Isolation: preNotImportedEmailAdd prevents bogus FAILED_OPERATION records
$mailgateTicket = new Ticket();
$mailgateTicket->input = [
    'name'      => 'Test Mailgate Ticket (CRU-5001)',
    'content'   => 'Mailgate body',
    '_mailgate' => 42,
    '_uid'      => '12345',
    '_head'     => ['message_id' => '<test-msg-001@example.com>'],
];

// Trigger evaluation so FilterEngine records the mailgate message
$patternRecord = [
    FilterPattern::FIELD_ID                      => 5,
    FilterPattern::FIELD_NAME                    => 'Mailgate Test Pattern',
    FilterPattern::FIELD_TICKETMATCHSTR          => '(?<match>CRU-[0-9]+)',
    FilterPattern::FIELD_SENDER_POLICY           => FilterPattern::POLICY_OPEN,
    FilterPattern::FIELD_UNMATCHED_SENDER_ACTION => FilterPattern::ACTION_NEW_TICKET,
];

FilterEngine::recordMailgateInterception($mailgateTicket);

// Verify deleteMailgateEmail moves email to ACCEPTED_FOLDER
MailCollector::$deletedMails = [];
$deleteRes = TicketProcessor::deleteMailgateEmail($mailgateTicket);
assertCondition($deleteRes === true, 'deleteMailgateEmail executes successfully');
assertCondition(count(MailCollector::$deletedMails) > 0, 'deleteMailgateEmail invoked MailCollector::deleteMails');
$lastDeleted = end(MailCollector::$deletedMails);
assertCondition($lastDeleted['uid'] === '12345', 'deleteMailgateEmail passed matching message UID');
assertCondition($lastDeleted['folder'] === MailCollector::ACCEPTED_FOLDER, 'deleteMailgateEmail directs message to ACCEPTED_FOLDER');

// Now simulate GLPI MailCollector attempting to log FAILED_OPERATION to NotImportedEmail
$notImported = new NotImportedEmail();
$notImported->input = [
    'mailcollectors_id' => 42,
    'messageid'         => '<test-msg-001@example.com>',
    'subject'           => 'Test Mailgate Ticket (CRU-5001)',
    'reason'            => NotImportedEmail::FAILED_OPERATION,
];
FilterEngine::preNotImportedEmailAdd($notImported);
assertCondition($notImported->input === false, 'preNotImportedEmailAdd intercepts and cancels NotImportedEmail creation for merged message');

// 9. Test Mailgate Error Count & Notification Cleanup
$errorKey = defined('ERROR') ? ERROR : 3;
$infoKey  = defined('INFO')  ? INFO  : 0;
$_SESSION['MESSAGE_AFTER_REDIRECT'] = [
    $errorKey => [
        'Number of messages: available=1, already imported=0, retrieved=1, refused=0, errors=1, blacklisted=0'
    ]
];
FilterEngine::handleMailgateShutdown();

assertCondition(empty($_SESSION['MESSAGE_AFTER_REDIRECT'][$errorKey]), 'handleMailgateShutdown clears the ERROR status banner');
assertCondition(!empty($_SESSION['MESSAGE_AFTER_REDIRECT'][$infoKey]), 'handleMailgateShutdown populates INFO status banner');
$infoMessages = implode(' ', $_SESSION['MESSAGE_AFTER_REDIRECT'][$infoKey]);
assertCondition(str_contains($infoMessages, 'errors=0'), 'handleMailgateShutdown corrects errors=1 to errors=0');
assertCondition(str_contains($infoMessages, 'TicketFilter:'), 'handleMailgateShutdown appends positive TicketFilter merge confirmation');

// 10. Test Multi-Email Batch Mailgate Processing
FilterEngine::resetMailgateState();
$batchTicket1 = new Ticket();
$batchTicket1->input = [
    'name'      => 'First Batch Email (CRU-5001)',
    'content'   => 'Batch mail 1',
    '_mailgate' => 42,
    '_uid'      => '1001',
    '_head'     => ['message_id' => '<batch-001@example.com>'],
];
FilterEngine::recordMailgateInterception($batchTicket1);

$batchTicket2 = new Ticket();
$batchTicket2->input = [
    'name'      => 'Second Batch Email (CRU-5001)',
    'content'   => 'Batch mail 2',
    '_mailgate' => 42,
    '_uid'      => '1002',
    '_head'     => ['message_id' => '<batch-002@example.com>'],
];
FilterEngine::recordMailgateInterception($batchTicket2);

assertCondition(count(FilterEngine::getMergedMailgateMessages()) === 2, 'Batch mailgate captures both merged emails in single batch');

$_SESSION['MESSAGE_AFTER_REDIRECT'] = [
    $errorKey => [
        'Number of messages: available=2, already imported=0, retrieved=2, refused=0, errors=2, blacklisted=0'
    ]
];
MailCollector::$deletedMails = [];
FilterEngine::handleMailgateShutdown();

assertCondition(count(MailCollector::$deletedMails) === 2, 'handleMailgateShutdown moved both batch emails to accepted folder');
assertCondition(MailCollector::$deletedMails[0]['uid'] === '1002', 'First moved message has UID 1002 (descending order)');
assertCondition(MailCollector::$deletedMails[1]['uid'] === '1001', 'Second moved message has UID 1001 (descending order)');
assertCondition(MailCollector::$deletedMails[0]['folder'] === MailCollector::ACCEPTED_FOLDER, 'Message moved to ACCEPTED_FOLDER');

assertCondition(empty($_SESSION['MESSAGE_AFTER_REDIRECT'][$errorKey]), 'handleMailgateShutdown clears ERROR banner for multi-mail batch');
assertCondition(!empty($_SESSION['MESSAGE_AFTER_REDIRECT'][$infoKey]), 'handleMailgateShutdown populates INFO banner for multi-mail batch');
$batchInfo = implode(' ', $_SESSION['MESSAGE_AFTER_REDIRECT'][$infoKey]);
assertCondition(str_contains($batchInfo, 'errors=0'), 'handleMailgateShutdown reduces errors=2 to errors=0 in batch');
assertCondition(str_contains($batchInfo, '2 e-mail(s) succesvol samengevoegd'), 'handleMailgateShutdown confirms 2 emails merged in Dutch message');

echo "SecurityIsolationTest completed successfully!\n\n";

