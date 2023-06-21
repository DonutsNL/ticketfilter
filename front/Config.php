<?php
include ("../../../inc/includes.php");
use GlpiPlugin\Ticketfilter\Config;
use Plugin;

// Check if plugin is activated...
$plugin = new Plugin();
if (!$plugin->isInstalled('ticketfilter') || !$plugin->isActivated('ticketfilter')) {
   Html::displayNotFoundError();
}

//check for ACLs
/*if (Config::canView()) { */
   //View is granted: display the list.

   //Add page header
   Html::header(
      __('Ticket Filter', 'ticketfilter'),
      $_SERVER['PHP_SELF'],
      'assets',
      Config::class,
      'ticketfilter'
   );

   Search::show(Config::class);

   Html::footer();

/*
} else {
   //View is not granted.
   Html::displayRightError();
}
*/