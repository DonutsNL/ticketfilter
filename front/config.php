<?php
<<<<<<< HEAD
use GlpiPlugin\Ticketfilter\Configs;
=======
use GlpiPlugin\Ticketfilter\Config;
>>>>>>> 85292ed (In development)
use Plugin;

include ("../../../inc/includes.php");

Plugin::load('ticketfilter', true);

<<<<<<< HEAD
$dropdown = new Configs();
=======
$dropdown = new Config();
>>>>>>> 85292ed (In development)
include(GLPI_ROOT . '/front/dropdown.common.php');