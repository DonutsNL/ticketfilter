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

namespace GlpiPlugin\Ticketfilter\Controller;

use Glpi\Application\View\TemplateRenderer;
use Glpi\Controller\AbstractController;
use GlpiPlugin\Ticketfilter\Audit\AuditLog;
use GlpiPlugin\Ticketfilter\Engine\Simulator;
use GlpiPlugin\Ticketfilter\Model\FilterPattern;
use GlpiPlugin\Ticketfilter\Utility\SanityChecker;
use Html;
use Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Central controller managing TicketFilter configuration tabs, audit logs, and simulator.
 */
final class TicketFilterController extends AbstractController
{
    public const CONFIG_ROUTE     = 'front/config';
    public const CONFIG_FORM_FILE = 'front/config.form.php';
    public const CONFIG_NAME      = 'ticketfilter_config';

    public const SIMULATOR_ROUTE  = 'front/simulator';
    public const SIMULATOR_FILE   = 'front/simulator.php';
    public const SIMULATOR_NAME   = 'ticketfilter_simulator';

    /**
     * Renders the main tabbed configuration interface or handles AJAX simulator requests.
     *
     * @param Request $request HTTP request.
     * @return Response
     */
    #[Route(self::CONFIG_ROUTE, name: self::CONFIG_NAME)]
    #[Route(self::CONFIG_FORM_FILE, name: self::CONFIG_NAME . '_form_file')]
    public function config(Request $request): Response
    {
        Session::checkRight('config', UPDATE);

        $action = $request->query->get('action') ?? $request->request->get('action');

        if ($action === 'simulate_sample') {
            return $this->handleSampleSimulation($request);
        }

        if ($action === 'simulate_historical') {
            return $this->handleHistoricalSimulation($request);
        }

        if ($action === 'get_pattern') {
            return $this->handleGetPattern($request);
        }

        if ($action === 'save_pattern') {
            return $this->handleSavePattern($request);
        }

        if ($action === 'delete_pattern') {
            return $this->handleDeletePattern($request);
        }

        return $this->renderView();
    }

    /**
     * Dedicated endpoint for simulator operations.
     *
     * @param Request $request HTTP request.
     * @return Response
     */
    #[Route(self::SIMULATOR_ROUTE, name: self::SIMULATOR_NAME)]
    #[Route(self::SIMULATOR_FILE, name: self::SIMULATOR_NAME . '_file')]
    public function simulator(Request $request): Response
    {
        Session::checkRight('config', UPDATE);

        $action = $request->query->get('action') ?? $request->request->get('action');

        if ($action === 'simulate_sample') {
            return $this->handleSampleSimulation($request);
        }

        if ($action === 'simulate_historical') {
            return $this->handleHistoricalSimulation($request);
        }

        return new JsonResponse(['success' => false, 'error' => 'Invalid action']);
    }

    /**
     * Handles ad-hoc sample regex simulation requests.
     *
     * @param Request $request
     * @return JsonResponse
     */
    private function handleSampleSimulation(Request $request): JsonResponse
    {
        $pattern       = (string)$request->request->get('pattern', '');
        $subject       = (string)$request->request->get('subject', '');
        $body          = (string)$request->request->get('body', '');
        $sender        = (string)$request->request->get('sender', '');
        $solvedPattern = (string)$request->request->get('solved_pattern', '');
        $policy        = (string)$request->request->get('policy', FilterPattern::POLICY_STRICT_REQUESTER);

        $result = Simulator::simulateSample($pattern, $subject, $body, $sender, $solvedPattern, $policy);
        return new JsonResponse($result);
    }

    /**
     * Handles batch historical ticket simulation requests.
     *
     * @param Request $request
     * @return JsonResponse
     */
    private function handleHistoricalSimulation(Request $request): JsonResponse
    {
        $pattern = (string)$request->request->get('pattern', '');
        $limit   = (int)$request->request->get('limit', 50);

        $result = Simulator::simulateHistorical($pattern, $limit);
        return new JsonResponse($result);
    }

    /**
     * Handles fetching a pattern's data for the in-page editor modal.
     *
     * @param Request $request
     * @return JsonResponse
     */
    private function handleGetPattern(Request $request): JsonResponse
    {
        $id = (int)($request->query->get('id') ?? $request->request->get('id') ?? 0);
        if ($id <= 0) {
            return new JsonResponse(['success' => false, 'error' => __('Invalid pattern ID', 'ticketfilter')]);
        }

        $pattern = new FilterPattern();
        if (!$pattern->getFromDB($id)) {
            return new JsonResponse(['success' => false, 'error' => __('Pattern not found', 'ticketfilter')]);
        }

        return new JsonResponse([
            'success' => true,
            'pattern' => $pattern->fields,
        ]);
    }

    /**
     * Handles saving (creating or updating) a pattern from the in-page modal.
     *
     * @param Request $request
     * @return JsonResponse
     */
    private function handleSavePattern(Request $request): JsonResponse
    {
        $id       = (int)$request->request->get('id', 0);
        $name     = trim((string)$request->request->get('name', ''));
        $matchStr = trim((string)$request->request->get('TicketMatchString', ''));

        if ($name === '') {
            return new JsonResponse(['success' => false, 'error' => __('Pattern name is required', 'ticketfilter')]);
        }
        if ($matchStr === '') {
            return new JsonResponse(['success' => false, 'error' => __('Ticket match regex is required', 'ticketfilter')]);
        }

        // Validate match regex syntax
        $testPattern = html_entity_decode($matchStr, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (@preg_match($testPattern, '') === false && !str_starts_with($testPattern, '/')) {
            $testPattern = '/' . $testPattern . '/';
        }
        if (@preg_match($testPattern, '') === false) {
            return new JsonResponse(['success' => false, 'error' => __('Invalid regex syntax in match pattern', 'ticketfilter')]);
        }
        if (!str_contains($testPattern, '(?<match>')) {
            return new JsonResponse(['success' => false, 'error' => __('Match pattern must include named group (?<match>...)', 'ticketfilter')]);
        }

        // Validate solved regex syntax if provided
        $solvedStr = trim((string)$request->request->get('SolvedMatchString', ''));
        if ($solvedStr !== '') {
            $testSolved = html_entity_decode($solvedStr, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (@preg_match($testSolved, '') === false && !str_starts_with($testSolved, '/')) {
                $testSolved = '/' . $testSolved . '/';
            }
            if (@preg_match($testSolved, '') === false) {
                return new JsonResponse(['success' => false, 'error' => __('Invalid regex syntax in solved pattern', 'ticketfilter')]);
            }
            if (!str_contains($testSolved, '(?<solved>')) {
                return new JsonResponse(['success' => false, 'error' => __('Solved pattern must include named group (?<solved>...)', 'ticketfilter')]);
            }
        }

        FilterPattern::ensureSchema();

        $fields = [
            'name'                    => $name,
            'comment'                 => (string)$request->request->get('comment', ''),
            'TicketMatchString'       => $matchStr,
            'TicketMatchStringLength' => max(1, (int)$request->request->get('TicketMatchStringLength', 255)),
            'SolvedMatchString'       => $solvedStr,
            'SolvedMatchStringLength' => max(1, (int)$request->request->get('SolvedMatchStringLength', 255)),
            'sender_policy'           => (string)$request->request->get('sender_policy', FilterPattern::POLICY_STRICT_REQUESTER),
            'unmatched_sender_action' => (string)$request->request->get('unmatched_sender_action', FilterPattern::ACTION_NEW_TICKET),
            'is_active'               => (int)(bool)$request->request->get('is_active', 0),
            'ReopenClosedTickets'     => (int)(bool)$request->request->get('ReopenClosedTickets', 0),
            'max_reopen_age_days'     => max(1, (int)$request->request->get('max_reopen_age_days', 30)),
            'SuppressNotification'    => (int)(bool)$request->request->get('SuppressNotification', 0),
            'SearchTicketBody'        => (int)(bool)$request->request->get('SearchTicketBody', 0),
            'reopen_message_template' => trim((string)$request->request->get('reopen_message_template', '')),
            'solved_message_template' => trim((string)$request->request->get('solved_message_template', '')),
        ];

        $pattern = new FilterPattern();
        if ($id > 0) {
            $fields['id'] = $id;
            $success = $pattern->update($fields);
            if (!$success) {
                return new JsonResponse(['success' => false, 'error' => __('Failed to update pattern in database', 'ticketfilter')]);
            }
            return new JsonResponse(['success' => true, 'id' => $id, 'message' => __('Pattern updated successfully', 'ticketfilter')]);
        }

        $newId = $pattern->add($fields);
        if (!$newId) {
            return new JsonResponse(['success' => false, 'error' => __('Failed to create pattern in database', 'ticketfilter')]);
        }
        return new JsonResponse(['success' => true, 'id' => $newId, 'message' => __('Pattern created successfully', 'ticketfilter')]);
    }

    /**
     * Handles deleting a pattern from the in-page modal.
     *
     * @param Request $request
     * @return JsonResponse
     */
    private function handleDeletePattern(Request $request): JsonResponse
    {
        $id = (int)$request->request->get('id', 0);
        if ($id <= 0) {
            return new JsonResponse(['success' => false, 'error' => __('Invalid pattern ID', 'ticketfilter')]);
        }

        $pattern = new FilterPattern();
        $success = $pattern->delete(['id' => $id]);
        if (!$success) {
            return new JsonResponse(['success' => false, 'error' => __('Failed to delete pattern', 'ticketfilter')]);
        }
        return new JsonResponse(['success' => true, 'message' => __('Pattern deleted successfully', 'ticketfilter')]);
    }

    /**
     * Renders the tabbed administration form.
     *
     * @return Response
     */
    public function renderView(): Response
    {
        global $DB, $CFG_GLPI;

        $patterns = FilterPattern::getFilterPatterns();

        $logs = [];
        if ($DB && $DB->tableExists(AuditLog::TABLE)) {
            $iterator = $DB->request([
                'FROM'  => AuditLog::TABLE,
                'ORDER' => [AuditLog::FIELD_ID . ' DESC'],
                'LIMIT' => 100,
            ]);
            foreach ($iterator as $row) {
                $logs[] = $row;
            }
        }

        $sanity = SanityChecker::check();

        $templateVars = [
            'patterns'    => $patterns,
            'logs'        => $logs,
            'sanity'      => $sanity,
            'plugin_url'  => ticketfilter_webdir(),
            'glpi_url'    => $CFG_GLPI['url_base'] ?? '',
        ];

        // Invalidate cached session menu so newly updated menu titles/links appear immediately
        if (isset($_SESSION['glpimenu'])) {
            unset($_SESSION['glpimenu']);
        }

        ob_start();
        Html::header(__('TicketFilter Configuration', 'ticketfilter'), $_SERVER['PHP_SELF'], 'config', 'plugins');

        if (class_exists(TemplateRenderer::class)) {
            echo TemplateRenderer::getInstance()->render('@ticketfilter/configForm.html.twig', $templateVars);
        } else {
            $this->renderFallbackHtml($templateVars);
        }

        Html::footer();
        $content = (string)ob_get_clean();

        return new Response($content);
    }

    /**
     * Fallback standard HTML rendering if Twig renderer is unavailable.
     *
     * @param array $vars Template variables.
     * @return void
     */
    private function renderFallbackHtml(array $vars): void
    {
        echo '<div class="card p-3 m-3">';
        echo '<h2>' . __('TicketFilter Administration', 'ticketfilter') . '</h2>';
        echo '<p>' . __('Please browse to Setup > Ticket filter to configure matching rules.', 'ticketfilter') . '</p>';
        echo '</div>';
    }
}
