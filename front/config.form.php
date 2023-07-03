<?php

use GlpiPlugin\Ticketfilter\Config;
use Plugin;

include ("../../../inc/includes.php");

Plugin::load('ticketfilter', true);

$dropdown = new Config();
include (GLPI_ROOT . "/front/dropdown.common.form.php");