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

namespace GlpiPlugin\Ticketfilter\Cron;

use CommonDBTM;
use CronTask as glpiCronTask;
use Migration;
use GlpiPlugin\Ticketfilter\Audit\AuditLog;
use Throwable;

/**
 * Scheduled cron task to purge aged audit trail records.
 */
class CleanLogsTask extends CommonDBTM
{
    /**
     * Automatic task name.
     */
    public const TASK_NAME = 'cleanLogs';

    /**
     * Default retention period in days.
     */
    public const DEFAULT_RETENTION_DAYS = 60;

    /**
     * Provides metadata describing the cron task.
     *
     * @param string $name Task identifier.
     * @return array
     */
    public static function cronInfo(string $name): array
    {
        if ($name === self::TASK_NAME) {
            return [
                'description' => __('Purge old TicketFilter audit log records', 'ticketfilter'),
                'parameter'   => __('Log retention period (in days, 0 for infinite)', 'ticketfilter'),
            ];
        }
        return [];
    }

    /**
     * Executes the automatic cleanup task.
     *
     * @param glpiCronTask $task Task context passed by GLPI.
     * @return int 0 if nothing done, 1 if records cleaned.
     */
    public static function cronCleanLogs(glpiCronTask $task): int
    {
        global $DB;

        $days = (int)($task->fields['param'] ?? self::DEFAULT_RETENTION_DAYS);
        if ($days <= 0) {
            return 0;
        }

        try {
            $table = AuditLog::TABLE;
            if (!$DB->tableExists($table)) {
                return 0;
            }

            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            $result = $DB->delete(
                $table,
                [
                    AuditLog::FIELD_DATE_CREATION => ['<', $cutoffDate]
                ]
            );

            $volume = ($result) ? (int)$DB->affectedRows() : 0;
            $task->addVolume($volume);

            return ($volume > 0) ? 1 : 0;
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Registers the cron task during plugin installation.
     *
     * @param Migration $migration GLPI migration handler.
     * @return void
     */
    public static function install(Migration $migration): void
    {
        glpiCronTask::register(
            self::class,
            self::TASK_NAME,
            DAY_TIMESTAMP,
            [
                'param'     => self::DEFAULT_RETENTION_DAYS,
                'mode'      => glpiCronTask::MODE_INTERNAL,
                'state'     => glpiCronTask::STATE_WAITING,
                'allowmode' => glpiCronTask::MODE_EXTERNAL | glpiCronTask::MODE_INTERNAL,
            ]
        );
    }

    /**
     * Unregisters the cron task during plugin uninstallation.
     *
     * @param Migration $migration GLPI migration handler.
     * @return void
     */
    public static function uninstall(Migration $migration): void
    {
        glpiCronTask::unregister(self::class);
    }
}
