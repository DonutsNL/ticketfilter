<?php

use GlpiPlugin\Ticketfilter\Filter;
use Plugin;

include ("../../../inc/includes.php");

Plugin::load('ticketfilter', true);

Filter::getFilterPatterns();