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

namespace GlpiPlugin\Ticketfilter;

use GlpiPlugin\Ticketfilter\Model\FilterPattern as ModelFilterPattern;

/**
 * Backward compatibility facade for FilterPattern model.
 */
class FilterPattern extends ModelFilterPattern
{
    /**
     * Backward-compatible constant aliases.
     */
    public const NAME              = self::FIELD_NAME;
    public const ACTIVE            = self::FIELD_ACTIVE;
    public const DATE_CREATION     = self::FIELD_DATE_CREATION;
    public const DATE_MOD          = self::FIELD_DATE_MOD;
    public const TICKETMATCHSTR    = self::FIELD_TICKETMATCHSTR;
    public const TICKETMATCHSTRLEN = self::FIELD_TICKETMATCHSTRLEN;
    public const ASSETMATCHSTR     = self::FIELD_ASSETMATCHSTR;
    public const ASSETMATCHSTRLEN  = self::FIELD_ASSETMATCHSTRLEN;
    public const SOLVEDMATCHSTR    = self::FIELD_SOLVEDMATCHSTR;
    public const SOLVEDMATCHSTRLEN = self::FIELD_SOLVEDMATCHSTRLEN;
    public const AUTOMERGE         = self::FIELD_AUTOMERGE;
    public const REOPENCLOSED      = self::FIELD_REOPENCLOSED;
    public const SEARCHBODY        = self::FIELD_SEARCHBODY;
    public const MATCHSOURCE       = self::FIELD_MATCHSOURCE;
    public const SUPPRESNOTIF      = self::FIELD_SUPPRESNOTIF;
}
