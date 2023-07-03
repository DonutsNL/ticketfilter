<?php
use GlpiPlugin\Ticketfilter\Configs;
use Plugin;

include ("../../../inc/includes.php");

Plugin::load('ticketfilter', true);

$dropdown = new Configs();
include(GLPI_ROOT . '/front/dropdown.common.php');