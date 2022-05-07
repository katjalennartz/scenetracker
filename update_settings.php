<?php
define("IN_MYBB", 1);
require("global.php");
// error_reporting(E_ERROR | E_PARSE);
// ini_set('display_errors', true);

global $db, $mybb, $lang;

$gid = $db->fetch_field($db->simple_select("settinggroups", "gid", "name = 'scenetracker'"), "gid");
$setting_array = array(
  'scenetracker_ingametime_tagstart' => array(
    'title' => 'Ingame Zeitraum 1. Tag',
    'description' => 'Gib hier den ersten Tag eures Ingamezeitraums an. z.B. 1 oder 15.',
    'optionscode' => 'text',
    'value' => '1', // Default
    'disporder' => 10
  ),
  'scenetracker_ingametime_tagend' => array(
    'title' => 'Ingame Zeitraum letzter Tag',
    'description' => 'Gib hier den letzte  Tag eures Ingamezeitraums an. z.B. 15 oder 30.<br><i>Tage im Zeitraum, bekommen die Klasse "activeingame" und können gesondert gestylt werden.</i>',
    'optionscode' => 'text',
    'value' => '30', // Default
    'disporder' => 11
  ),
);

foreach ($setting_array as $name => $setting) {
  $setting['name'] = $name;
  $setting['gid'] = $gid;
  $db->insert_query('settings', $setting);
}

rebuild_settings();

echo "Done. Datei jetzt löschen!";