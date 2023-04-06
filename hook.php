<?php
use Symfony\Component\Console\Event\ConsoleCommandEvent;

/**
 * Summary of plugin_ticketFilter install
 * @return boolean
 * test
 */
function plugin_ticketfilter_install() : bool 
{
   global $DB;
   return true;
}


/**
 * Summary of plugin_ticketFilter uninstall
 * @return boolean
 */
function plugin_ticketfilter_uninstall()
{

   // nothing to uninstall
   // do not delete table

   return true;
}