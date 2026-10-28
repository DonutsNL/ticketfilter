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
 *  @version    2.0.2
 *  @author     Chris Gralike
 *  @copyright  Copyright (c) 2023-2026 by Chris Gralike
 *  @license    GPLv3+
 *  @link       https://github.com/DonutsNL/ticketfilter
 *  ------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\Ticketfilter\Compat;

use CommonDropdown;

if (!function_exists('GlpiPlugin\Ticketfilter\Compat\ticketfilter_glpi12')) {
    /**
     * Tells whether the running GLPI core is version 12 or above.
     *
     * @return bool True on GLPI >= 12.0.0.
     */
    function ticketfilter_glpi12(): bool
    {
        return defined('GLPI_VERSION') && ((int) explode('.', (string) GLPI_VERSION)[0] >= 12);
    }
}

if (ticketfilter_glpi12()) {
    /**
     * FilterPattern intermediate: 'config' rights, GLPI 12 typing.
     */
    abstract class FilterPatternCompat extends CommonDropdown
    {
        public static string $rightname = 'config';
    }
} else {
    /**
     * FilterPattern intermediate: 'config' rights, GLPI 11 untyped.
     */
    abstract class FilterPatternCompat extends CommonDropdown
    {
        public static $rightname = 'config';
    }
}
