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

echo "SecurityIsolationTest completed successfully!\n\n";
