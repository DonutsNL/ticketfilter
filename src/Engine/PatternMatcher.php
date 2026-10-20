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

namespace GlpiPlugin\Ticketfilter\Engine;

/**
 * Safe regular expression execution engine and syntax validator.
 */
class PatternMatcher
{
    /**
     * Named capture group for ticket match identifier.
     */
    public const GROUP_MATCH = 'match';

    /**
     * Named capture group for ticket solved keyword.
     */
    public const GROUP_SOLVED = 'solved';

    /**
     * Normalizes pattern delimiters and ensures valid wrapping.
     *
     * @param string $pattern Raw regex string.
     * @return string Normalized pattern with delimiters.
     */
    public static function normalizePattern(string $pattern): string
    {
        $trimmed = trim(html_entity_decode($pattern, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($trimmed === '') {
            return '';
        }

        $validDelimiters = ['/', '#', '~', '@', '!'];
        $firstChar = $trimmed[0];

        if (in_array($firstChar, $validDelimiters, true)) {
            $lastDelimPos = strrpos($trimmed, $firstChar);
            if ($lastDelimPos !== false && $lastDelimPos > 0) {
                return $trimmed;
            }
        }

        $escaped = str_replace('/', '\/', $trimmed);
        return '/' . $escaped . '/';
    }

    /**
     * Checks if a pattern contains a specific named capture group.
     *
     * @param string $pattern Regex pattern.
     * @param string $groupName Name of the group to check.
     * @return bool
     */
    public static function hasNamedGroup(string $pattern, string $groupName): bool
    {
        $escaped = preg_quote($groupName, '/');
        $check = '/\(\?(?:P<|(?:\<|\'))' . $escaped . '(?:\>|\')/u';
        return (@preg_match($check, $pattern) === 1);
    }

    /**
     * Validates a regex pattern for syntax correctness and required named groups.
     *
     * @param string $rawPattern Candidate regex string.
     * @param string $requiredGroup Optional required named group name.
     * @return array [bool 'valid', string 'normalized', string|null 'error']
     */
    public static function validate(string $rawPattern, string $requiredGroup = ''): array
    {
        if (trim($rawPattern) === '') {
            return [
                'valid'      => false,
                'normalized' => '',
                'error'      => __('Pattern cannot be empty.', 'ticketfilter')
            ];
        }

        $normalized = self::normalizePattern($rawPattern);

        $testMatch = @preg_match($normalized, '');
        if ($testMatch === false) {
            $lastError = preg_last_error_msg();
            return [
                'valid'      => false,
                'normalized' => $normalized,
                'error'      => sprintf(__('Regex compilation failed: %s', 'ticketfilter'), $lastError)
            ];
        }

        if ($requiredGroup !== '' && !self::hasNamedGroup($normalized, $requiredGroup)) {
            return [
                'valid'      => false,
                'normalized' => $normalized,
                'error'      => sprintf(
                    __('Pattern must contain the named capture group (?<%s>...)', 'ticketfilter'),
                    $requiredGroup
                )
            ];
        }

        return [
            'valid'      => true,
            'normalized' => $normalized,
            'error'      => null
        ];
    }

    /**
     * Safely executes a regular expression against a target string.
     *
     * @param string $rawPattern Regex pattern from configuration.
     * @param string $subject Text string to evaluate (e.g. ticket title or body).
     * @param string $captureGroup Named group to extract ('match' or 'solved').
     * @return array [bool 'matched', string|null 'token', array 'matches', string|null 'error']
     */
    public static function match(string $rawPattern, string $subject, string $captureGroup = self::GROUP_MATCH): array
    {
        $validation = self::validate($rawPattern);
        if (!$validation['valid']) {
            return [
                'matched' => false,
                'token'   => null,
                'matches' => [],
                'error'   => $validation['error']
            ];
        }

        $normalized = $validation['normalized'];
        $matches = [];
        $result = @preg_match($normalized, $subject, $matches);

        if ($result === false) {
            return [
                'matched' => false,
                'token'   => null,
                'matches' => [],
                'error'   => preg_last_error_msg()
            ];
        }

        if ($result === 1) {
            $token = null;
            if (isset($matches[$captureGroup]) && is_string($matches[$captureGroup])) {
                $token = trim($matches[$captureGroup]);
            } elseif (isset($matches[1]) && is_string($matches[1])) {
                $token = trim($matches[1]);
            }

            return [
                'matched' => true,
                'token'   => $token,
                'matches' => $matches,
                'error'   => null
            ];
        }

        return [
            'matched' => false,
            'token'   => null,
            'matches' => [],
            'error'   => null
        ];
    }
}
