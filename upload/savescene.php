<?php
define("IN_MYBB", 1);
define('NO_ONLINE', 1);
define('THIS_SCRIPT', 'savescene.php');
require_once "./global.php";
// error_reporting ( -1 );
// ini_set ( 'display_errors', true );

$thisuser = $mybb->user['user'];
$datetime = $mybb->input['datetime'];
$date = date('Y-m-d H:i:s', strtotime($datetime));
$scenetracker_place = $db->escape_string($mybb->input['place']);
$trigger = $db->escape_string($mybb->input['trigger']);
$tid = intval($mybb->input['id']);
$teilnehmer = $db->escape_string($mybb->input['user']);
$teilnehmer = str_replace(", ", "", $teilnehmer);
$einzeln = explode(",", $teilnehmer);
$user = $db->fetch_field($db->simple_select("threads", "scenetracker_user", "tid={$tid}"), "scenetracker_user");

$einzeln = array_filter($einzeln);

foreach ($einzeln as $username) {
  if (stripos($user, $username) !== false) {
    $teilnehmer = str_replace($username, "", $teilnehmer);
  }
}
$teilnehmer = str_replace(",,", ",", $teilnehmer);
$array_users = scenetracker_getUids($teilnehmer);
$array_users = array_filter($array_users);
$chrstr = ",";
foreach ($array_users as $uid => $username) {
  if ($uid != $username) {
    $alert_array = array(
      "uid" => $uid,
      "tid" => $tid,
      "type" => "always"
    );
  $db->insert_query("scenetracker", $alert_array);

    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
      $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('scenetracker_newScene');
      //Not null, the user wants an alert and the user is not on his own page.
      if ($alertType != NULL && $alertType->getEnabled() && $thisuser != $uid) {
        //constructor for MyAlert gets first argument, $user (not sure), second: type  and third the objectId 
        $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$uid, $alertType);
        //some extra details
        $alert->setExtraDetails([
          'tid' => $tid,
          'fromuser' => $uid
        ]);
        //add the alert
        MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
      }
    }
  
  }
  $chrstr .= $username . ",";
}

$user .= $chrstr;
if (substr($user, -1) == ",") {
  $user = substr($user, 0, -1);
}

$update = array(
  "scenetracker_date" => $date,
  "scenetracker_place" => $scenetracker_place,
  "scenetracker_trigger" => $trigger,
  "scenetracker_user" => $user
);

$db->update_query("threads", $update, "tid='{$tid}'");
