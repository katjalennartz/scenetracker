<?php
define("IN_MYBB", 1);
require("global.php");
// error_reporting(E_ERROR | E_PARSE);
// ini_set('display_errors', true);

global $db, $mybb, $lang;
echo "Dieses Script fügt eine neue Einstellung und ändert die Foren auswahl des Ingames und Archiv.";

$gid = $db->fetch_field($db->write_query("SELECT gid FROM `" . TABLE_PREFIX . "_settings` WHERE name like 'scenetracker%' LIMIT 1;"), "gid");

if ($mybb->settings['scenetracker_ingame']) {
  $setting_array = array(
    'scenetracker_ingame' => array(
      'optionscode' => 'forumselect',
    )
  );
  foreach ($setting_array as $name => $setting) {
    $setting['name'] = $name;
    $setting['gid'] = $gid;
    $db->update_query('settings', $setting, "name LIKE 'scenetracker_ingame'");
  }
  rebuild_settings();
  echo "settings für ingame  zu Forenauswahl geändert, mehrfach auswahl möglich.<br>";
} else {
  $setting_array = array(
    'scenetracker_ingame' => array(
      'title' => 'Ingame',
      'description' => 'Ingame',
      'optionscode' => 'forumselect',
      'value' => '1', // Default
      'disporder' => 4
    )
  );
  foreach ($setting_array as $name => $setting) {
    $setting['name'] = $name;
    $setting['gid'] = $gid;
    $db->insert_query('settings', $setting);
  }
  rebuild_settings();
  echo "settings für ingame Forenauswahl hinzugefügt.<br>";
}

if ($mybb->settings['scenetracker_archiv']) {
  $setting_array = array(
    'scenetracker_archiv' => array(
      'optionscode' => 'forumselect',
    )
  );
  foreach ($setting_array as $name => $setting) {
    $setting['name'] = $name;
    $setting['gid'] = $gid;
    $db->update_query('settings', $setting, "name LIKE 'scenetracker_archiv'");
  }
  rebuild_settings();
  echo "settings für Archiv  zu Forenauswahl geändert, mehrfach auswahl möglich.<br>";
} else {
  $setting_array = array(
    'scenetracker_archiv' => array(
      'title' => 'Archiv',
      'description' => 'ID des Archivs',
      'optionscode' => 'forumselect',
      'value' => '1', // Default
      'disporder' => 4
    )
  );
  foreach ($setting_array as $name => $setting) {
    $setting['name'] = $name;
    $setting['gid'] = $gid;
    $db->insert_query('settings', $setting);
  }
  rebuild_settings();
  echo "settings für archiv Forenauswahl hinzugefügt.<br>";
}
if (!$mybb->settings['scenetracker_exludedfids']) {
  $setting_array = array(
    'scenetracker_exludedfids' => array(
      'title' => 'ausgeschlossene Foren',
      'description' => 'Gibt es Foren, die im Ingame liegen aber nicht zum Tracker gezählt werden sollen (Keine Verfolgung, keine Anzeige im Profil, z.B. Communication).',
      'optionscode' => 'forumselect',
      'value' => '1', // Default
      'disporder' => 4
    )
  );
  foreach ($setting_array as $name => $setting) {
    $setting['name'] = $name;
    $setting['gid'] = $gid;
    $db->insert_query('settings', $setting);
  }
  rebuild_settings();
  echo "settings für ausgeschlossene Foren als  Forenauswahl hinzugefügt.<br>";
}
