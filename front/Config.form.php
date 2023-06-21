<?php

include ("../../../inc/includes.php");
use GlpiPlugin\Ticketfilter\Config;

// Check if plugin is activated...
$plugin = new Plugin();
if (!$plugin->isInstalled('ticketfilter') || !$plugin->isActivated('ticketfilter')) {
   Html::displayNotFoundError();
}

$Config = new Config();

if (isset($_POST['add'])) {
   //Check CREATE ACL
   $Config->check(-1, CREATE, $_POST);
   //Do object creation
   $newid = $Config->add($_POST);
   //Redirect to newly created object form
   Html::redirect("{$CFG_GLPI['root_doc']}/plugins/front/myobject.form.php?id=$newid");
} else if (isset($_POST['update'])) {
   //Check UPDATE ACL
   $Config->check($_POST['id'], UPDATE);
   //Do object update
   $Config->update($_POST);
   //Redirect to object form
   Html::back();
} else if (isset($_POST['delete'])) {
   //Check DELETE ACL
   $Config->check($_POST['id'], DELETE);
   //Put object in dustbin
   $Config->delete($_POST);
   //Redirect to objects list
   $Config->redirectToList();
} else if (isset($_POST['purge'])) {
   //Check PURGE ACL
   $Config->check($_POST['id'], PURGE);
   //Do object purge
   $Config->delete($_POST, 1);
   //Redirect to objects list
   Html::redirect("{$CFG_GLPI['root_doc']}/plugins/front/myobject.php");
} else {
   //per default, display object
   $withtemplate = (isset($_GET['withtemplate']) ? $_GET['withtemplate'] : 0);
   $Config->display(
      [
         'id'           => $_GET['id'],
         'withtemplate' => $withtemplate
      ]
   );
}