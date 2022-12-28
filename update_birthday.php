<?php
define("IN_MYBB", 1);
require("global.php");
// error_reporting(E_ERROR | E_PARSE);
// ini_set('display_errors', true);

global $db, $mybb, $lang;

$gid = $db->fetch_field($db->simple_select("settinggroups", "gid", "name = 'scenetracker'"), "gid");
$setting_array = array(
  'scenetracker_birhday' => array(
    'title' => 'Geburtstagsfeld für Kalendar',
    'description' => 'Wird ein Profilfeld (Format dd.mm.YYYY) verwendet, das Standardgeburtstagsfeld oder benutzt ihr das Steckbrief(Format YYYY-mm-dd wie datumsfeld) im UCP Plugin?',
    'optionscode' => "select\n0=fid\n1=standard\n2=ausschalten\n3=Steckbrief im Profil",
    'value' => '1', // Default
    'disporder' => 6
  ),
  'scenetracker_birhdayfid' => array(
    'title' => 'Geburtstagsfeld ID?',
    'description' => 'Wenn der Geburtstags über ein Profilfeld angegeben wird, bitte hier die ID(ohne fid) eingeben, oder den namen des Steckbrieffelds.',
    'optionscode' => 'text',
    'value' => '0', // Default
    'disporder' => 7
  ),
);

foreach ($setting_array as $name => $setting) {
  $db->update_query('settings', $setting, "name = '{$name}'");
}

rebuild_settings();

echo "Done. Datei jetzt löschen!";
