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

if (file_exists(dirname(__DIR__, 3) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
}

spl_autoload_register(function ($class) {
    $prefix = 'GlpiPlugin\\Ticketfilter\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', dirname(__DIR__, 3));
}

if (!defined('GLPI_PLUGINS_DIRECTORIES')) {
    define('GLPI_PLUGINS_DIRECTORIES', [GLPI_ROOT . '/plugins']);
}

if (!defined('PLUGIN_TICKETFILTER_NAME')) {
    define('PLUGIN_TICKETFILTER_NAME', 'ticketfilter');
}

if (!defined('PLUGIN_NAME')) {
    define('PLUGIN_NAME', 'ticketfilter');
}

if (!function_exists('__')) {
    function __(string $text, string $domain = 'glpi'): string
    {
        return $text;
    }
}

if (!isset($GLOBALS['DB'])) {
    class MockDB
    {
        public function request(array $query): array
        {
            return [];
        }
        public function tableExists(string $table): bool
        {
            return true;
        }
        public function insert(string $table, array $params): int
        {
            return 1;
        }
        public function update(string $table, array $params, array $where): bool
        {
            return true;
        }
        public function delete(string $table, array $where): bool
        {
            return true;
        }
        public function affectedRows(): int
        {
            return 0;
        }
    }
    $GLOBALS['DB'] = new MockDB();
}

if (!class_exists('CommonDBTM', false)) {
    class CommonDBTM
    {
        public array $fields = [];
        public array $input  = [];

        public static function getTable($classname = null): string
        {
            return 'glpi_mock';
        }

        public function getFromDB(int $id): bool
        {
            $this->fields['id'] = $id;
            return true;
        }
    }
}

if (!class_exists('CommonDropdown', false)) {
    class CommonDropdown extends CommonDBTM
    {
        public static function getMenuName(): string
        {
            return 'MockDropdown';
        }
        public static function getSearchURL($full = true): string
        {
            return '/mock';
        }
    }
}

if (!class_exists('CommonITILObject', false)) {
    class CommonITILObject extends CommonDBTM
    {
        public const INCOMING = 1;
        public const ASSIGNED = 2;
        public const PLANNED  = 3;
        public const WAITING  = 4;
        public const SOLVED   = 5;
        public const CLOSED   = 6;
    }
}

if (!class_exists('CommonITILActor', false)) {
    class CommonITILActor
    {
        public const REQUESTER = 1;
        public const ASSIGN    = 2;
        public const OBSERVER  = 3;
    }
}

if (!class_exists('Ticket', false)) {
    class Ticket extends CommonITILObject
    {
        public string $userlinkclass = 'MockTicketUser';

        public static function getTable($classname = null): string
        {
            return 'glpi_tickets';
        }
    }
}

if (!class_exists('ITILFollowup', false)) {
    class ITILFollowup extends CommonDBTM
    {
        public static array $createdFollowups = [];

        public function add(array $input): int|bool
        {
            self::$createdFollowups[] = $input;
            return count(self::$createdFollowups);
        }
    }
}

if (!class_exists('Toolbox', false)) {
    class Toolbox
    {
        public static array $logs = [];

        public static function logInFile(string $name, string $text): void
        {
            self::$logs[] = [$name, $text];
        }
    }
}

if (!class_exists('Session', false)) {
    class Session
    {
        public static array $messages = [];

        public static function addMessageAfterRedirect(string $msg, bool $check_once = false, int $severity = 0): void
        {
            self::$messages[] = $msg;
        }

        public static function checkRight(string $module, int $right): bool
        {
            return true;
        }
    }
}

if (!class_exists('Plugin', false)) {
    class Plugin
    {
        public static array $registeredClasses = [];

        public function isInstalled(string $name): bool
        {
            return true;
        }

        public function isActivated(string $name): bool
        {
            return true;
        }

        public static function isPluginLoaded(string $name): bool
        {
            return true;
        }

        public static function registerClass(string $class, array $options = []): bool
        {
            self::$registeredClasses[] = $class;
            return true;
        }
    }
}

if (!class_exists('Config', false)) {
    class Config extends CommonDBTM
    {
        public static function canUpdate(): bool
        {
            return true;
        }

        public static function canView(): bool
        {
            return true;
        }
    }
}

