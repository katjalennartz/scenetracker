<?php
define("IN_MYBB", 1);
require("global.php");
// error_reporting(-1);
// ini_set('display_errors', 1);

global $db, $mybb, $lang;

echo (
  '<style type="text/css">
body {
  background-color: #efefef;
  text-align: center;
  margin: 40px 100px;
  font-family: Verdana;
}
fieldset {
  width: 50%;
  margin: auto;
  margin-bottom: 20px;
}
legend {
  font-weight: bold;
}
</style>'
);
if ($mybb->usergroup['canmodcp'] == 1) {

  echo "<h1>Import IPT3 von jule zu Szenentracker </h1>";

  echo '<form action="" method="post">';
  echo '<input type="submit" name="import" value="Szenen importiere">';
  echo '</form>';
  //Szenen bekommen
  if (isset($_POST['import'])) {
    $get_sceneinfos = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "ipt_scenes");
    while ($scene = $db->fetch_array($get_sceneinfos)) {

      $get_user = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "ipt_scenes_partners WHERE tid= '{$scene['tid']}'");
      $names = "";
      while ($user = $db->fetch_array($get_user)) {
        $userinfo = get_user($user['uid']);
        $names = $userinfo['username'] . ",";
        $scenesave = array(
          "uid"  => $userinfo['uid'],
          "tid"  => $scene['tid']
        );
        $db->insert_query("scenetracker", $scenesave);
      }
      //letzteskomma entfernen
      $names = substr_replace($names, "", -1);

      $import = array(
        "scenetracker_date" => date("Y-m-d", $scene['date']),
        "scenetracker_place" => $db->escape_string($scene['iport']),
        "scenetracker_user" => $db->escape_string($names),
      );
      $db->update_query("threads", $import, "tid = '{$scene['tid']}'");
    }
  }
}
