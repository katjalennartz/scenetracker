<?php
define("IN_MYBB", 1);
require("global.php");
// error_reporting(E_ERROR | E_PARSE);
// ini_set('display_errors', true);

global $db, $mybb, $lang;
echo "Dieses Script fügt eine neue Einstellung und ändert die Foren auswahl des Ingames und Archiv.";

$gid = $db->fetch_field($db->write_query("SELECT gid FROM `" . TABLE_PREFIX . "settings` WHERE name like 'scenetracker%' LIMIT 1;"), "gid");

if ($mybb->settings['scenetracker_calendarview_all']) {

  echo "Kalender Felder schon installiert<br>";
} else {

  $setting_array = array(
    //Kalendar einstellungen
    'scenetracker_calendarview_all' => array(
      'title' => 'Kalendar Szenen Ansicht - Alle Szenen',
      'description' => 'Dürfen Mitglieder auswählen das die Szenen von allen Charakteren angezeigt werden?',
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 13
    ),
    'scenetracker_calendarview_ownall' => array(
      'title' => 'Kalendar Szenen Ansicht - Alle eigenen Szenen',
      'description' => 'Dürfen Mitglieder auswählen das die Szenen von allen eigenen (verbundenen) Charakteren angezeigt werden?',
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 14
    ),
    'scenetracker_calendarview_own' => array(
      'title' => 'Kalendar Szenen Ansicht - Szenen des Charaktes',
      'description' => 'Dürfen Mitglieder auswählen das die Szenen nur von dem Charakter angezeigt werden, mit dem man online ist?',
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 14
    ),
  );

  foreach ($setting_array as $name => $setting) {
    $setting['name'] = $name;
    $setting['gid'] = $gid;
    $db->insert_query('settings', $setting);
  }
  rebuild_settings();
  echo "settings für ingame kalendar hinzugefügt.<br>";
}

if (!$db->field_exists("scenetracker_calendar_settings", "users")) {
  $db->add_column("users", "scenetracker_calendar_settings", "INT(1) NOT NULL DEFAULT '0'");
  echo "db spalte scenetracker_calendar_settings zu users hinzugefügt.<br>";
}
if (!$db->field_exists("scenetracker_calendarsettings_big", "users")) {
  $db->add_column("users", "scenetracker_calendarsettings_big", "INT(1) NOT NULL DEFAULT '0'");
  echo "db spalte scenetracker_calendarsettings_big zu users hinzugefügt.<br>";
}
if (!$db->field_exists("scenetracker_calendarsettings_mini", "users")) {
  $db->add_column("users", "scenetracker_calendarsettings_mini", "INT(1) NOT NULL DEFAULT '0'");
  echo "db spalte scenetracker_calendarsettings_mini zu users hinzugefügt.<br>";
}

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
  echo "settings für ingame  zu Forenauswahl geändert, mehrfach auswahl möglich. <b> Überprüfen ob Einstellungen noch passen</b><br><br>";
} else {
  $setting_array = array(
    'scenetracker_ingame' => array(
      'title' => 'Ingame',
      'description' => 'Ingame',
      'optionscode' => 'forumselect',
      'value' => '1', // Default
      'gid' => $gid,
      'disporder' => 4
    )
  );
  foreach ($setting_array as $name => $setting) {
    $setting['name'] = $name;
    $setting['gid'] = $gid;
    $db->insert_query('settings', $setting);
  }
  rebuild_settings();
  echo "settings für ingame Forenauswahl hinzugefügt.<b> Überprüfen ob Einstellungen noch passen</b><br><br>";
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
  echo "settings für Archiv  zu Forenauswahl geändert, mehrfach auswahl möglich.<b> Überprüfen ob Einstellungen noch passen</b><br><br>";
} else {
  $setting_array = array(
    'scenetracker_archiv' => array(
      'title' => 'Archiv',
      'description' => 'ID des Archivs',
      'optionscode' => 'forumselect',
      'value' => '1', // Default
      'gid' => $gid,
      'disporder' => 4
    )
  );
  foreach ($setting_array as $name => $setting) {
    $setting['name'] = $name;
    $setting['gid'] = $gid;
    $db->insert_query('settings', $setting);
  }
  rebuild_settings();
  echo "settings für archiv Forenauswahl hinzugefügt.<br> <b> Überprüfen ob Einstellungen noch passen</b><br>";
}
if (!$mybb->settings['scenetracker_exludedfids']) {
  $setting_array = array(
    'scenetracker_exludedfids' => array(
      'title' => 'ausgeschlossene Foren',
      'description' => 'Gibt es Foren, die im Ingame liegen aber nicht zum Tracker gezählt werden sollen (Keine Verfolgung, keine Anzeige im Profil, z.B. Communication).',
      'optionscode' => 'forumselect',
      'value' => '1', // Default
      'gid' => $gid,
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

if (!$mybb->settings['scenetracker_ingametime_tagend']) {
  $setting_array = array(
    'scenetracker_ingametime_tagend' => array(
      'title' => 'Ingame Zeitraum letzter Tag',
      'description' => 'Gib hier den letzte  Tag eures Ingamezeitraums an. z.B. 15 oder 30.<br><i>Tage im Zeitraum, bekommen die Klasse "activeingame" und können gesondert gestylt werden.</i>',
      'optionscode' => 'text',
      'value' => '15', // Default
      'disporder' => 11
    )
  );
  foreach ($setting_array as $name => $setting) {
    $setting['name'] = $name;
    $setting['gid'] = $gid;
    $db->insert_query('settings', $setting);
  }
  rebuild_settings();
  echo "settings für 'scenetracker_ingametime_tag ende hinzugefügt<br><br>";
}

if (!$mybb->settings['scenetracker_ingametime_tagstart']) {
  $setting_array = array(
    'scenetracker_ingametime_tagstart' => array(
      'title' => 'Ingame Zeitraum 1. Tag',
      'description' => 'Gib hier den ersten Tag eures Ingamezeitraums an. z.B. 1 oder 15.',
      'optionscode' => 'text',
      'value' => '1', // Default
      'disporder' => 10
    )
  );
  foreach ($setting_array as $name => $setting) {
    $setting['name'] = $name;
    $setting['gid'] = $gid;
    $db->insert_query('settings', $setting);
  }
  rebuild_settings();
  echo "settings für scenetracker_ingametime_tag start hinzugefügt.<br><br>";
}
