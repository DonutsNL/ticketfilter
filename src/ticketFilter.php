<?php

namespace GlpiPlugin\ticketFilter;
use MailCollector;

/**
 * Summary of PluginMailAnalyzer
 */
class ticketFilter
{

   public static function preItemAdd($param)
   {
      echo "Hook called!<br><pre>";
      var_dump($param);
      die();
      exit(0); 
   }
}
