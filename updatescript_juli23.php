<?php
define("IN_MYBB", 1);
require("global.php");
// error_reporting(E_ERROR | E_PARSE);
// ini_set('display_errors', true);

global $db, $mybb, $lang;
echo "Dieses Script fügt eine neue Einstellung und ändert die Foren auswahl des Ingames und Archiv.";

$gid = $db->fetch_field($db->write_query("SELECT gid FROM `" . TABLE_PREFIX . "settings` WHERE name like 'scenetracker%' LIMIT 1;"), "gid");

if ($mybb->settings['scenetracker_ingame']) {
  $setting_ingame = array(
    'optionscode' => 'forumselect'
  );
  $db->update_query('settings', $setting_ingame, "name LIKE 'scenetracker_ingame'");
  
  rebuild_settings();
  echo "settings für ingame  zu Forenauswahl geändert, mehrfach auswahl möglich.<br>";
} else {
  $setting_ingame = array(
      'title' => 'Ingame',
      'description' => 'Ingame',
      'optionscode' => 'forumselect',
      'value' => '1', // Default
      'gid' => $gid,
      'disporder' => 4
  );
  $db->insert_query('settings', $setting_ingame);

  rebuild_settings();
  echo "settings für ingame Forenauswahl hinzugefügt.<br>";
}

if ($mybb->settings['scenetracker_archiv']) {
  $settin_archiv = array(
      'optionscode' => 'forumselect'
  );
  $db->update_query('settings', $settin_archiv, "name LIKE 'scenetracker_archiv'");
  rebuild_settings();
  echo "settings für Archiv  zu Forenauswahl geändert, mehrfach auswahl möglich.<br>";
} else {
  $setting_archiv = array(
      'title' => 'Archiv',
      'description' => 'ID des Archivs',
      'optionscode' => 'forumselect',
      'value' => '1', // Default
      'gid' => $gid,
      'disporder' => 4
  );

  $db->insert_query('settings', $setting_archiv);

  rebuild_settings();
  echo "settings für archiv Forenauswahl hinzugefügt.<br>";
}

if (!$mybb->settings['scenetracker_exludedfids']) {
  $setting_exl = array(
      'title' => 'ausgeschlossene Foren',
      'description' => 'Gibt es Foren, die im Ingame liegen aber nicht zum Tracker gezählt werden sollen (Keine Verfolgung, keine Anzeige im Profil, z.B. Communication).',
      'optionscode' => 'forumselect',
      'value' => '1', // Default
      'gid' => $gid,
      'disporder' => 4
  );
  $db->insert_query('settings', $setting_exl);

  rebuild_settings();
  echo "settings für ausgeschlossene Foren als  Forenauswahl hinzugefügt.<br>";
}
