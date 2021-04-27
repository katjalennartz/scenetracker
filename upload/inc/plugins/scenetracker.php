<?php

/**
 * Szenentracker - by risuena
 * https://lslv.de/risu
 * Datum einfügen für Szenen 
 * Teilnehmer einfügen für Szenen
 * automatische Anzeige im Profil der Szenen
 *  - auf Wunsch getrennt nach Archiv/Ingame
 *  - auf Wunsch mit anzeige beendet oder nicht
 * Anzeige auf der Startseite, auf Wunsch von User
 *  - immer
 *  - wenn dran
 *  - wenn bestimmte User gepostet hat
 * Benachrichtung (Admin wählt, alert oder pn)
 *  - immer bei Antwort
 *  - bei Antwort von bestimmten User
 *  - keine Benachrichtigung
 * Postingerinnerung (kann vom Admin aktiviert werden)
 *  - wenn man Postingpartner länger als x Tage warten gelassen hat
 * 
 * DB scenetracker
 * id - uid - username -  tid - PN? - Index
 */

// error_reporting ( -1 );
// ini_set ( 'display_errors', true );

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
  die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}


function scenetracker_info()
{
  return array(
    "name" => "Szenentracker von Risuena",
    "description" => "Setzt Datum f&uuml;r Szenentracker",
    "website" => "https://github.com/katjalennartz",
    "author" => "risuena",
    "authorsite" => "https://github.com/katjalennartz",
    "version" => "1.0",
    "compatibility" => "18*"
  );
}

function scenetracker_is_installed()
{
  global $db;
  if ($db->table_exists("scenetracker")) {
    return true;
  }
  return false;
}

function scenetracker_install()
{
  global $db;
  scenetracker_uninstall();

  if ($db->field_exists("threadsolved", "threads")) {
  } else {
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "threads ADD `threadsolved` INT(1) NOT NULL DEFAULT '0'");
  }

  //Threadtabelle braucht, Feld für Datum, Feld für Teilnehmer
  $db->add_column("threads", "scenetracker_date", "varchar(200) NOT NULL");
  $db->add_column("threads", "scenetracker_date", "varchar(200) NOT NULL");
  $db->query("ALTER TABLE `mybb_users` ADD `tracker_index` INT(1) NOT NULL DEFAULT '1', ADD `tracker_alert` INT(1) NOT NULL DEFAULT '1';");
  //new table for saving scenes and notifivation status
  $db->write_query("CREATE TABLE `" . TABLE_PREFIX . "scenetracker` (
        `id` int(10) NOT NULL AUTO_INCREMENT,
        `uid` int(10) NOT NULL,
        `tid` int(10) NOT NULL,
        `alert` int(1) NOT NULL DEFAULT 1,
        `type` varchar(50) NOT NULL DEFAULT 'always',
        `index_view` int(1) NOT NULL DEFAULT 1,
        `profil_view` int(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`)
    ) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;");

  // Einstellungen
  $setting_group = array(
    'name' => 'scenetracker',
    'title' => 'Szenentracker',
    'description' => 'Einstellungen für Risuenas Szenentracker',
    'disporder' => 7, // The order your setting group will display
    'isdefault' => 0
  );
  $gid = $db->insert_query("settinggroups", $setting_group);

  $setting_array = array(
    'scenetracker_index' => array(
      'title' => 'Indexanzeige',
      'description' => 'Sollen die Szene auf Wunsch des Users auf dem Index angezeigt werden können?',
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 1
    ),
    'scenetracker_solved' => array(
      'title' => 'Thema erledigt/unerledigt',
      'description' => 'Ist das Thema erledigt/unerledigt Plugin installiert?',
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 1
    ),
    'scenetracker_alert_pn' => array(
      'title' => 'Private Nachricht',
      'description' => 'Sollen Charaktere bei neuer Szene / neuem Post per PN benachrichtigt werden?',
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 2
    ),
    'scenetracker_alert_alerts' => array(
      'title' => 'My Alerts',
      'description' => 'Sollen Charaktere per MyAlerts (Plugin muss installiert sein) informiert werden?',
      'optionscode' => 'yesno',
      'value' => '0', // Default
      'disporder' => 3
    ),
    'scenetracker_ingame' => array(
      'title' => 'Ingame',
      'description' => 'ID des Ingames',
      'optionscode' => 'text',
      'value' => '0', // Default
      'disporder' => 4
    ),
    'scenetracker_archiv' => array(
      'title' => 'Archiv',
      'description' => 'ID des Archivs',
      'optionscode' => 'text',
      'value' => '0', // Default
      'disporder' => 5
    ),
    'scenetracker_profil_sort' => array(
      'title' => 'Profilanzeige',
      'description' => 'Sollen Szenen im Profil des Charakters nach Ingame und Archiv sortiert werden?',
      'optionscode' => 'yesno',
      'value' => '0', // Default
      'disporder' => 6
    ),
    'scenetracker_reminder' => array(
      'title' => 'Erinnerung',
      'description' => 'Sollen Charaktere auf dem Index darauf aufmerksam gemacht werden, wenn sie jemanden in einer Szene länger als X Tage warten lassen? 0 wenn nicht',
      'optionscode' => 'text',
      'value' => '0', // Default
      'disporder' => 7
    ),
  );
  foreach ($setting_array as $name => $setting) {
    $setting['name'] = $name;
    $setting['gid'] = $gid;
    $db->insert_query('settings', $setting);
  }
  rebuild_settings();
  scenetracker_add_templates();
}

function scenetracker_uninstall()
{
  //DB Einträge löschen
  global $db, $mybb;
  if ($db->table_exists("scenetracker")) {
    $db->drop_table("scenetracker");
  }
  if ($db->field_exists("scenetracker_date", "threads")) {
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "threads DROP scenetracker_date");
  }
  if ($db->field_exists("scenetracker_members", "threads")) {
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "threads DROP scenetracker_user");
  }
  if ($mybb->settings['scenetracker_ingame'] == 0) {
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "threads DROP threadsolved");
  }
  //Einstellungen löschen
  $db->delete_query('settings', "name LIKE 'scenetracker_%'");
  $db->delete_query('settinggroups', "name = 'scenetracker'");
  rebuild_settings();
}

function scenetracker_activate()
{
  global $db, $mybb, $cache;


  //Variablen einfügen
  //Variable im Profil
  //Variable auf IndexSeite


  // add Alerts
  include MYBB_ROOT . "/inc/adminfunctions_templates.php";
  //Variable edit
  find_replace_templatesets("editpost", "#" . preg_quote('{$posticons}') . "#i", '{$scenetrackeredit}{$posticons}');
  //Variable new reply (add your character if not already in)
  find_replace_templatesets("newreply", "#" . preg_quote('{$posticons}') . "#i", '{$scenetrackerreply}{$posticons}');
  //variable new thread
  find_replace_templatesets("newthread", "#" . preg_quote('{$posticons}') . "#i", '{$scenetrackeredit}{$posticons}');
  //variable forumdisplay
  find_replace_templatesets("newthread", "#" . preg_quote('{$thread[\'profilelink\']}') . "#i", '{$scenetrackerforumdisplay}{$thread[\'profilelink\']}');

  if (function_exists('myalerts_is_activated') && myalerts_is_activated()) {

    $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

    if (!$alertTypeManager) {
      $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
    }

    $alertTypeSceneNew = new MybbStuff_MyAlerts_Entity_AlertType();
    $alertTypeSceneNew->setCanBeUserDisabled(true);
    $alertTypeSceneNew->setCode("scenetracker_newScene");
    $alertTypeSceneNew->setEnabled(true);
    $alertTypeManager->add($alertTypeSceneNew);

    $alertTypeSceneAnswer = new MybbStuff_MyAlerts_Entity_AlertType();
    $alertTypeSceneAnswer->setCanBeUserDisabled(true);
    $alertTypeSceneAnswer->setCode("scenetracker_newAnswer");
    $alertTypeSceneAnswer->setEnabled(true);
    $alertTypeManager->add($alertTypeSceneAnswer);
  }
  $cache->update_usergroups();
}

function scenetracker_deactivate()
{
  global $mybb;
  include MYBB_ROOT . "/inc/adminfunctions_templates.php";
  find_replace_templatesets("editpost", "#" . preg_quote('{$scenetrackeredit}{$posticons}') . "#i", '{$posticons}');
  find_replace_templatesets("newreply", "#" . preg_quote('{$scenetrackerreply}{$posticons}') . "#i", '{$posticons}');
  find_replace_templatesets("newthread", "#" . preg_quote('{$scenetrackeredit}{$posticons}') . "#i", '{$posticons}');
  find_replace_templatesets("newthread", "#" . preg_quote('{$scenetrackerforumdisplay}{$thread[\'profilelink\']}') . "#i", '{$thread[\'profilelink\']}');

  //{$posticons}{$scenetrackeredit} -> zu {$posticons} 
  //nur hauptvariablen löschen
  //Variable im Profil
  //Variable auf IndexSeite
  //My alerts raushauen
}
/**
 * Adds the templates and variables
 */
function scenetracker_add_templates()
{
  global $db;

  // überprüfe ob templates schon vorhanden, wenn ja tue nichts
  // else füge sie neu ein
  $template[0] = array(
    "title" => 'scenetracker_index',
    "template" => '		',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );
  $template[1] = array(
    "title" => 'scenetracker_indexbit',
    "template" => '		',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );
  $template[2] = array(
    "title" => 'scenetracker_newthread',
    "template" => '		',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[3] = array(
    "title" => 'scenetracker_profil',
    "template" => '		',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );
  $template[4] = array(
    "title" => 'scenetracker_ucp',
    "template" => '		',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  foreach ($template as $row) {
    $db->insert_query("templates", $row);
  }
  include MYBB_ROOT . "/inc/adminfunctions_templates.php";
  //TODO Variable Profilanzeige
  //find_replace_templatesets("member_profile", "#" . preg_quote('</fieldset>') . "#i", '</fieldset>{$relas_profil}');
  //TODO Variable UCP 
  //TODO templates einfügen
}

/**
 * Neuen Thread erstellen - Felder einfügen
 * 
 */
$plugins->add_hook("newthread_start", "scenetracker_newthread");
function scenetracker_newthread()
{
  global $db, $mybb, $templates, $fid, $scenetracker_newthread, $thread,  $post_errors, $scenetracker_date, $scenetracker_time, $scenetracker_user;

  if (testParentFid($fid)) {
    if ($mybb->input['previewpost'] || $post_errors) {
      $scenetracker_date = $db->escape_string($mybb->input['scenetracker_date']);
      $scenetracker_time = $db->escape_string($mybb->input['scenetracker_time']);
      $scenetracker_user = $db->escape_string($mybb->input['pattern']);
    } else {
      $scenetracker_date = "2017-08-01";
      $scenetracker_time = "12:00";
      $scenetracker_user = $db->escape_string($mybb->user['username']) . " , ";
    }
    eval("\$scenetracker_newthread = \"" . $templates->get("scenetracker_newthread") . "\";");
  }
}

/**
 * Neuen Thread erstellen (Neue Szene)
 */
$plugins->add_hook("newthread_do_newthread_end", "scenetracker_do_newthread");
function scenetracker_do_newthread()
{
  global $db, $mybb, $tid, $fid;
  if (testParentFid($fid)) {
    $thisuser = intval($mybb->user['uid']);
    $usersettingAlert = intval($mybb->user['tracker_alert']);
    $usersettingIndex = intval($mybb->user['tracker_index']);
    $array_users = array();
    $date = $db->escape_string($mybb->input['scenetracker_date']) . " " . $db->escape_string($mybb->input['scenetracker_time']);
    $teilnehmer = $db->escape_string($mybb->input['pattern']);

    $array_users = getUids($teilnehmer);

    $save = array(
      "scenetracker_date" => $date,
      "scenetracker_user" => $teilnehmer
    );
    $db->update_query("threads", $save, "tid='{$tid}'");

    foreach ($array_users as $uid => $username) {
      if ($uid != $username) {
        $alert_array = array(
          "uid" => $uid,
          "username" => $username,
          "tid" => $tid,
          "type" => "always"
        );
        $db->insert_query("scenetracker", $alert_array);

        //alert admin?
        if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
          $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('scenetracker_newScene');
          //Not null, the user wants an alert and the user is not on his own page.
          if ($alertType != NULL && $alertType->getEnabled() && $thisuser != $uid) {
            //constructor for MyAlert gets first argument, $user (not sure), second: type  and third the objectId 
            $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$uid, $alertType, (int)$id);
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
    }
  }
}

/**
 * new reply View 
 * shows the possibility to add your character to the list of users
 */
$plugins->add_hook("newreply_end", "scenetracker_newreply");
function scenetracker_newreply()
{
  global $db, $mybb, $tid, $thread, $templates, $fid, $scenetrackerreply;
  if (testParentFid($fid)) {
    //showing add possibility if not allready add to
    $teilnehmer = $thread['scenetracker_user'];
    $thisuser = $mybb->user['username'];

    $contains = strpos($teilnehmer, $thisuser);

    if ($contains === false) {

      eval("\$scenetrackerreply = \"" . $templates->get("scenetracker_newreply") . "\";");
    }
  }
}

/**
 * send new post - abschicken und daten speichern
 * 
 */
$plugins->add_hook("newreply_do_newreply_end", "scenetracker_do_newreply");
function scenetracker_do_newreply()
{
  global $db, $mybb, $tid, $thread, $templates, $fid, $pid;

  $thisuser = intval($mybb->user['uid']);
  $teilnehmer = $thread['scenetracker_user'];
  $array_users = getUids($teilnehmer);
  $username = $db->escape_string($mybb->user['username']);
  if (testParentFid($fid)) {

    //add the character if wanted and not already in 
    if ($mybb->input['scenetracker_add'] == "add") {
      $db->write_query("UPDATE " . TABLE_PREFIX . "threads SET scenetracker_user = CONCAT(scenetracker_user, ' , " . $username . "')");

      $to_add = array(
        "uid" => $thisuser,
        "username" => $username,
        "tid" => $tid,
        "type" => "always"
      );
      $db->insert_query("scenetracker", $to_add);
    }


    foreach ($array_users as $uid => $username) {
      if ($uid != $username) {
        $type = $db->fetch_array($db->write_query("SELECT type, inform_by FROM " . TABLE_PREFIX . "scenetracker WHERE tid = $tid AND uid = uid"));
        if ($type['type'] == "always") {

          if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('scenetracker_newAnswer');
            //Not null, the user wants an alert and the user is not on his own page.
            if ($alertType != NULL && $alertType->getEnabled() && $thisuser != $uid) {
              //constructor for MyAlert gets first argument, $user (not sure), second: type  and third the objectId 
              $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$uid, $alertType, (int)$id);
              //some extra details
              $alert->setExtraDetails([
                'tid' => $tid,
                'pid' => $pid,
                'fromuser' => $uid
              ]);
              //add the alert
              MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            }
          }
        } elseif ($type['type'] == "certain" && $type['inform_by'] == $thisuser) {
          if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('scenetracker_newAnswer');
            //Not null, the user wants an alert and the user is not on his own page.
            if ($alertType != NULL && $alertType->getEnabled() && $thisuser != $uid) {
              //constructor for MyAlert gets first argument, $user (not sure), second: type  and third the objectId 
              $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$uid, $alertType, (int)$id);
              //some extra details
              $alert->setExtraDetails([
                'tid' => $tid,
                'pid' => $pid,
                'fromuser' => $uid
              ]);
              //add the alert
              MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
            }
          }
        } elseif ($type['type'] == "never") {
          //do nothing
        }
      }
    }
  }
}

/**
 * 
 * Thread editieren - Datum oder/und Teilnehmer bearbeiten - Anzeige
 */
$plugins->add_hook("editpost_end", "scenetracker_editpost");
function scenetracker_editpost()
{
  global $thread, $templates, $db, $lang, $mybb, $templates, $fid, $post_errors, $thread, $post, $scenetrackeredit;
  if (testParentFid($fid)) {
    if ($thread['firstpost'] == $mybb->input['pid']) {

      $date = explode(" ", $thread['scenetracker_date']);
      if ($mybb->input['previewpost'] || $post_errors) {
        $scenetracker_date = $db->escape_string($mybb->input['scenetracker_date']);
        $scenetracker_time = $db->escape_string($mybb->input['scenetracker_time']);
        $scenetracker_user = $db->escape_string($mybb->input['pattern']);
      } else {
        $scenetracker_date = $date[0];
        $scenetracker_time = $date[1];
        $scenetracker_user = $db->escape_string($thread['scenetracker_user']) . " , ";
      }
      echo $thread['scenetracker_user'];
      //TODO find erorr
      $teilnehmer_alt =  explode(",", trim(str_replace(" , ", ",", trim($thread['scenetracker_user']))));

      eval("\$scenetrackeredit = \"" . $templates->get("scenetracker_newthread") . "\";");
    } else {

      $teilnehmer = $thread['scenetracker_user'];
      $thisuser = $post['username']; //whose post is it, which schould be edited
      $contains = strpos($teilnehmer, $thisuser);

      if ($contains === false) {
        eval("\$scenetrackeredit = \"" . $templates->get("scenetracker_newreply") . "\";");
      }
    }
  }
}

/**
 * Save the edit... manage of saving in database and send alerts
 * Used Tables: Threads & Scenetracker
 */
$plugins->add_hook("editpost_do_editpost_end", "scenetracker_do_editpost");
function scenetracker_do_editpost()
{
  global $db, $mybb, $tid, $pid, $thread, $fid, $post;
  if (testParentFid($fid)) {
    //just do edit if new thread else return and do nothing new
    if ($pid != $thread['firstpost']) {
      if ($mybb->input['scenetracker_add']) {
        $insert_array = array(
          "uid" => $post['uid'],
          "username" => $db->escape_string($post['username']),
          "tid" => $tid,
        );
        $db->insert_query("scenetracker", $insert_array);
        $db->write_query("UPDATE " . TABLE_PREFIX . "threads SET scenetracker_user = CONCAT(scenetracker_user, ', " . $db->escape_string($post['username']) . "') WHERE tid = $tid");
      }
    } else {
      $date = $db->escape_string($mybb->input['scenetracker_date']) . " " . $db->escape_string($mybb->input['scenetracker_time']);

      $teilnehmer_alt = explode(",", trim(str_replace(" , ", ",", $thread['scenetracker_user'])));
      $teilnehmer_neu = explode(",", trim(str_replace(" , ", ",", $mybb->input['pattern'])));
      //no whitespaces at begin and and
      $teilnehmer_alt = array_map('trim', $teilnehmer_alt);
      $teilnehmer_neu = array_map('trim', $teilnehmer_neu);
      //to build the new input for scenetracker user 
      $new_userfield = array();
      //here we first delete the users, that aren't any more in the scene, than we geht all users, who are added... and then merge them to one
      $workarray = array_merge(array_intersect($teilnehmer_alt, $teilnehmer_neu), array_diff($teilnehmer_neu, $teilnehmer_alt));
      //no whitespaces at the beginn and the end to be sure
      $workarray = array_map('trim', $workarray);
      foreach ($workarray as $name) {
        if ($name != "") {
          $user = get_user_by_username($name);
          if ($user == "") {
            $uid = $db->escape_string($name);
          } else {
            $uid = $user['uid'];
            $new_userfield[$uid] = $db->escape_string($name);
            if (($db->num_rows($db->simple_select("scenetracker", "*", "tid = $tid AND uid = $uid")) == 0)) {
              //TODO Test alert: you were added to a Scene
              $insert_array = array(
                "uid" => $uid,
                "username" => $db->escape_string($name),
                "tid" => $tid,
              );
              $db->insert_query("scenetracker", $insert_array);
            }
            //alert admin?
            if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
              $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('scenetracker_newScene');
              if ($alertType != NULL && $alertType->getEnabled() && $thread['uid'] != $mybb->user['uid']) {
                //constructor for MyAlert gets first argument, $user (not sure), second: type  and third the objectId 
                $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$uid, $alertType, (int)$id);
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
        }
      }
      //Build the new String for users and save it
      $to_save_str = implode(", ", $new_userfield);
      $save = array(
        "scenetracker_date" => $date,
        "scenetracker_user" =>  $to_save_str
      );
      $db->update_query("threads", $save, "tid='{$tid}'");
      //to delete in scenetracker table
      $to_delete = array_diff($teilnehmer_alt, $teilnehmer_neu);
      foreach ($to_delete as $name) {
        if ($name != "") {
          $user = get_user_by_username($name);
          if ($user == "") {
            $uid = $db->escape_string($name);
          } else {
            $uid = $user['uid'];
            $db->delete_query("scenetracker", "uid={$uid} AND tid 0 = {$tid}");
          }
        }
      }
    }
  }
}

/**
 * Anzeige von Datum und Teilnehmer im Forumdisplay
 * //TODO Anzeige Forumdisplay
 */
$plugins->add_hook("forumdisplay_thread", "scenetracker_forumdisplay_showtrackerstuff");
function scenetracker_forumdisplay_showtrackerstuff()
{
  global $thread, $templates, $db, $fid, $scenetrackerforumdisplay;

  if (testParentFid($fid)) {
    $scene_date = date('d.m.Y - H:i', strtotime($thread['scenetracker_date']));

    $userArray = getUids($thread['scenetracker_user']);
    foreach ($userArray as $uid => $username) {
      if ($uid != $username) {
        $user = build_profile_link($username, $uid);
      }
      eval("\$scenetracker_forumdisplay_user.= \"" . $templates->get("scenetracker_forumdisplay_user") . "\";");
    }
    eval("\$scenetrackerforumdisplay = \"" . $templates->get("scenetracker_forumdisplay_date") . "\";");
  }
}


/**
 * Anzeige von Datum und Teilnehmer im showthread
 */
$plugins->add_hook("showthread_start", "scenetracker_showthread_showtrackerstuff");
function scenetracker_showthread_showtrackerstuff()
{
  global $thread, $templates, $db, $fid, $tid, $mybb, $scenetracker_showthread;
  if (testParentFid($fid)) {
    $thisuser = $mybb->user['uid'];
    $scene_date = date('d.m.Y - H:i', strtotime($thread['scenetracker_date']));
    $userArray = getUids($thread['scenetracker_user']);
    if (array_key_exists($thisuser, $userArray) || $mybb->usergroup['canmodcp'] == 1) {
      if ($thread['threadsolved'] == 1) {
        $mark = "<a href=\"showthread.php?tid=" . $tid . "&scenestate=open\">[öffnen]</a></span>";
        $scenestatus = "<span class=\"scenestate\">Szene ist beendet. " . $mark;
      } else {
        $mark = "<a href=\"showthread.php?tid=" . $tid . "&scenestate=close\">[schließen]</a></span>";
        $scenestatus = "<span class=\"scenestate\">Szene ist offen. " . $mark;
      }
    }
    $userArray = getUids($thread['scenetracker_user']);
    //  var_dump($userArray);
    $finish = "<button >close scene</button>";
    foreach ($userArray as $uid => $username) {
      if ($uid != $username) {
        $user = build_profile_link($username, $uid);
        if ($mybb->usergroup['canmodcp'] == 1 || $thisuser == $uid) {
          $delete = "<a href=\"showthread.php?tid=" . $tid . "&delete=" . $uid . "\">[x]</a>";
        }
      } else {
        $user = $username;
        $delete = "";
      }
      eval("\$scenetracker_showthread_user.= \"" . $templates->get("scenetracker_showthread_user") . "\";");
    }

    eval("\$scenetracker_showthread = \"" . $templates->get("scenetracker_showthread") . "\";");
  }

  if ($mybb->input['delete']) {
    $uiddelete = intval($mybb->input['delete']);
    $userdelete = $db->fetch_field($db->simple_select("users", "username", "uid = $uiddelete"), "username");
    if ($mybb->usergroup['canmodcp'] == 1 || $thisuser == $uid) {
      $teilnehmer = str_replace($userdelete . " , ", "", $thread['scenetracker_user']);
      $teilnehmer = str_replace(" , " . $userdelete, "", $teilnehmer);
      $teilnehmer = $db->escape_string($teilnehmer);
      $db->query("UPDATE " . TABLE_PREFIX . "threads SET scenetracker_user = '" . $teilnehmer . "' WHERE tid = " . $tid . " ");
      $db->delete_query("scenetracker", "tid = " . $tid . " AND uid = " . $uiddelete . "");

      redirect("showthread.php?tid=" . $tid);
    }
  }
  if ($mybb->input['scenestate'] == "open") {

    $db->query("UPDATE " . TABLE_PREFIX . "threads SET threadsolved = 1, closed = 0 WHERE tid= " . $tid);
    // $db->query("UPDATE " . TABLE_PREFIX . "scenetracker SET closed = 1 WHERE tid = " . $tid);

    redirect("showthread.php?tid=" . $tid);
  }
  if ($mybb->input['scenestate'] == "close") {

    $db->query("UPDATE " . TABLE_PREFIX . "threads SET threadsolved = 0, closed = 1 WHERE tid= " . $tid);
    // $db->query("UPDATE " . TABLE_PREFIX . "scenetracker SET closed = 0 WHERE tid = " . $tid);

    redirect("showthread.php?tid=" . $tid);
  }
}

// /*
// *	UserCP Menu
// *	//TODO Link im UserCP Menü 
// */
// $plugins->add_hook("usercp_menu", "scenetracker_usercpmenu");
// function scenetracker_usercpmenu()
// {
//   global $lang, $templates;
//   $template = "\n\t<tr><td class=\"trow1 smalltext\"><i class=\"fas fa-book\" aria-hidden=\"true\"></i> <a href=\"usercp.php?action=tracker_usercp\">Szenenverwaltung Neu</a></td></tr>";

//   //<a href="https://lslv.de/usercp.php?action=scenetracker" class="usercp" id="tooltip"><i class="fas fa-book" aria-hidden="true"></i><span>Szenenverwaltung</span></a>
//   $templates->cache["usercp_nav_misc"] = str_replace("<tbody style=\"{\$collapsed['usercpmisc_e']}\" id=\"usercpmisc_e\">", "<tbody style=\"{\$collapsed['usercpmisc_e']}\" id=\"usercpmisc_e\">{$template}", $templates->cache["usercp_nav_misc"]);
// }

/**
 * Verwaltung der szenen im Profil
 * //WIP Szenenverwaltung
 */
$plugins->add_hook("usercp_start", "scenetracker_usercp");
function scenetracker_usercp()
{
  global $mybb, $db, $templates, $cache, $templates, $themes, $headerinclude, $header, $footer, $usercpnav, $scenetracker_ucp_main, $scenetracker_ucp_bit_char, $scenetracker_ucp_bit_chara_new, $scenetracker_ucp_bit_chara_old, $scenetracker_ucp_bit_chara_closed;
  if ($mybb->input['action'] != "scenetracker") {
    return false;
  }
  //usercp.php?action=scenetracker
  //welcher user ist online
  //gett all charas of this user
  $charas = get_accounts($mybb->user['uid'], $mybb->user['as_uid']);

  // AND (closed = 0 OR threadsolved=0)
  //TODO if abfrage - offen geschlossen etc
  //new scenes
  get_scenes($charas, "new");

  get_scenes($charas, "old");

  get_scenes($charas, "closed");
  //not your turn

  //old scenes
  //get_scenes($charas, "AND (closed = 1 OR threadsolved=1)", "_old");


  eval("\$scenetracker_ucp_main =\"" . $templates->get("scenetracker_ucp_main") . "\";");
  output_page($scenetracker_ucp_main);
}


function get_scenes($charas, $tplstring)
{
  global $db, $mybb, $templates, $scenetracker_ucp_bit_chara_new, $scenetracker_ucp_bit_chara_old, $scenetracker_ucp_bit_chara_closed, $scenetracker_ucp_bit_scene;
  foreach ($charas as $uid => $charname) {
    if ($tplstring == "new") {
      $query =  " AND (closed = 0 OR threadsolved=0) AND ((lastposteruid != $uid and type = 'always') OR (lastposteruid = inform_by and type = 'certain'))";
    } elseif ($tplstring == "old") {
      $query =  " AND (closed = 0 OR threadsolved=0) AND ((lastposteruid = $uid and type = 'always') OR (lastposteruid != inform_by and type = 'certain'))";
      // $query =  " AND (closed = 0 OR threadsolved=0) AND lastposteruid != $uid";
    } elseif ($tplstring == "closed") {
      $query =  " AND (closed = 1 OR threadsolved=1) ";
      // $query =  " AND (closed = 0 OR threadsolved=0) AND lastposteruid != $uid";
    }
    //SELECT s.*,fid,subject,dateline,lastpost,lastposter,lastposteruid, closed, threadsolved, scenetracker_date, scenetracker_user FROM mybb_scenetracker  s LEFT JOIN mybb_threads t on s.tid = t.tid WHERE s.uid = 583 AND (closed = 0 OR threadsolved=0) AND ((type = always and lastposteruid != 583) OR (type = "certain" and lastposteruid != inform_by))
    $charaname = $charname;
    $scenes = $db->write_query("SELECT s.*,fid,subject,dateline,lastpost,lastposter,lastposteruid, closed, threadsolved, scenetracker_date, scenetracker_user FROM " . TABLE_PREFIX . "scenetracker  s LEFT JOIN mybb_threads t on s.tid = t.tid WHERE s.uid = {$uid} " . $query);
    $scenetracker_ucp_bit_scene = "";
    $tplcount = 1;
    if ($db->num_rows($scenes) == 0) {
      // echo "----- no";
      $tplcount = 0;
    } else {
      // echo "---- jo";
      $tplcount = 1;

      while ($data = $db->fetch_array($scenes)) {
        // echo "ist - " . $uid. "- last". $data['lastposteruid']; 
        $edit = "";

        $close = "[close]";
        if ($tplstring == "closed") $close = "[open]";
        $delete = "[delete]";
        $alert = "[alert]";

        $user = get_user($uid);
        $tid = $data['tid'];
        $username = $user['username'];
        $lastpostdate = date('d.m.Y', $data['lastpost']);
        $lastposter = get_user($data['lastposteruid']);
        $alerttype = $data['type'];
        if ($alerttype == "always") {
          $alert = "<button class=\"certain\" name=\"certain\" onclick=\"certain({$tid})\">certain</button>
          ";
          ///https://www.feenders.de/ratgeber/experten/modalboxen-ohne-javascript.html#
        }
        $scenedate = date('d.m.Y - H:i', strtotime($data['scenetracker_date']));
        $lastposterlink = '<a href="member.php?action=profile&uid=' . $lastposter['uid'] . '">' .  $lastposter['username'] . '</a>';
        $scene = '<a href="showthread.php?tid=' . $data['tid'] . '">' . $data['subject'] . '</a>';
        $users = $data['scenetracker_user'];
        //if ($data['type'] == 'always' && $data['lasposter'] != $uid) {
        eval("\$scenetracker_ucp_bit_scene .= \"" . $templates->get('scenetracker_ucp_bit_scene') . "\";");
      }
    }
    // {'avas_female_'.$buchstabe}
    // $test = {'avas_female_'.$buchstabe}; 
    //eval("\$scenetracker_ucp_bit_chara.\$tplstring
    if ($tplcount == 1) {
      eval("\$scenetracker_ucp_bit_chara_{$tplstring} .=\"" . $templates->get("scenetracker_ucp_bit_chara") . "\";");
    }
  }
}

/**
 * automatische Anzeige von Tracker im Profil
 * //TODO make scene hidable
 */
$plugins->add_hook("member_profile_end", "scenetracker_showinprofile");
function scenetracker_showinprofile()
{
  global $db, $mybb, $memprofile, $templates, $scenetracker_profil;
  $userprofil = $memprofile['uid'];
  $scenetracker_profil_bit = "";
  $sort = "0";
  $dateYear = "";
  setlocale(LC_ALL, 'de_DE@euro', 'de_DE', 'de', 'ge');
  $show_monthYear = array();
  $sort = $mybb->settings['scenetracker_profil_sort'];
  $scene_query = $db->write_query("SELECT s.*,fid, subject, dateline, t.closed as threadclosed, scenetracker_date, scenetracker_user, threadsolved FROM " . TABLE_PREFIX . "scenetracker s, " . TABLE_PREFIX . "threads t WHERE t.tid = s.tid AND s.uid = " . $userprofil . " ORDER by scenetracker_date");

  // $scene_dates = $db->write_query("SELECT s.*,fid, subject, dateline, t.closed as threadclosed, scenetracker_date, scenetracker_user, threadsolved FROM " . TABLE_PREFIX . "scenetracker s, " . TABLE_PREFIX . "threads t WHERE t.tid = s.tid AND s.uid = " . $userprofil . " ORDER by scenetracker_date");
  //  while ($scenes_date = $db->fetch_array($scene_dates)) {
  //   $dateYear =  date('F Y', strtotime($scenes_date['scenetracker_date']));
  //   if (!in_array($dateYear , $show_monthYear, true)) {
  //     array_push($show_monthYear, $dateYear);
  //   }
  // }
  // var_dump($show_monthYear);
  while ($scenes = $db->fetch_array($scene_query)) {
    if ($sort == 1) {
    } else {
      $tid = $scenes['tid'];
      $subject = $scenes['subject'];
      $sceneusers = $scenes['scenetracker_user'];
      $scenedate = date('d.m.Y - H:i', strtotime($scenes['scenetracker_date']));

      // echo $test;
      if ($dateYear != date('m.Y', strtotime($scenes['scenetracker_date']))) {
        $scenetracker_profil_bit .= "<span class=\"scentracker month\">" . date('F Y', strtotime($scenes['scenetracker_date'])) . "</span>";
      }
      $dateYear = date('m.Y', strtotime($scenes['scenetracker_date']));
      eval("\$scenetracker_profil_bit .= \"" . $templates->get("scenetracker_profil_bit") . "\";");
    }
  }
  eval("\$scenetracker_profil = \"" . $templates->get("scenetracker_profil") . "\";");

  //Threads aus der Datenbank holen
  //aufteilen in archiv / ingame -> auswählbar machen wonach sortieren? 
  //markieren ob erledigt oder nicht 
  //dort als erledigt markierbar machen
}
/**
 * 
 * //TODO Anzeige auf Indexseite
 */
$plugins->add_hook('index_start', 'scenetracker_list');
function scenetracker_list()
{
}

/**
 * //TODO Reminder
 * //Erinnerung wenn man den Postpartner X Tage warten lässt
 */

function scenetracker_reminder()
{
}

/**************************** */
/*** HELPERS ***/
//TODO Accountswitcher
/**************************** */

function get_accounts($this_user, $as_uid)
{
  global $mybb, $db;
  // suche alle angehangenen accounts
  // as uid = 0 wenn hauptaccount oder keiner angehangen
  $charas = array();
  if ($as_uid == 0) {
    $get_all_users = $db->query("SELECT uid,username FROM " . TABLE_PREFIX . "users WHERE (as_uid = $this_user) OR (uid = $this_user) ORDER BY username");
  } else if ($as_uid != 0) {
    //id des users holen wo alle angehangen sind 
    $get_all_users = $db->query("SELECT uid,username FROM " . TABLE_PREFIX . "users WHERE (as_uid = $as_uid) OR (uid = $this_user) OR (uid = $as_uid) ORDER BY username");
  }
  while ($users = $db->fetch_array($get_all_users)) {
    $uid = $users['uid'];
    $charas[$uid] = $users['username'];
  }
  return $charas;
}

function testParentFid($fid)
{
  global $db, $mybb;
  $parents = $db->fetch_field($db->write_query("SELECT CONCAT(',',parentlist,',') as parents FROM " . TABLE_PREFIX . "forums WHERE fid = $fid"), "parents");
  rebuild_settings();
  $ingame =  "," . $mybb->settings['scenetracker_ingame'] . ",";
  $archiv = "," . $mybb->settings['scenetracker_archiv'] . ",";

  $containsIngame = strpos($parents, $ingame);
  $containsArchiv = strpos($parents, $archiv);

  if ($containsIngame !== false || $containsArchiv !== false) {
    return true;
  } else return false;
}

/**
 * get the user ids of each usernames in a string 
 * @param string string of usernames, seperated by , 
 * @return array key: uid value: username
 * */
function getUids($string_usernames)
{
  global $db;

  $array_user = array();
  //no whitespace at beginning and end of name
  $array_usernames = array_map('trim', explode(",", $string_usernames));
  foreach ($array_usernames as $username) {
    $username = $db->escape_string($username);
    $uid = $db->fetch_field($db->simple_select("users", "uid", "username='$username'"), "uid");
    // deleted user or an other string;
    //we need an unique key in case of there is more than one deleted user -> we use the username
    if ($uid == "") $uid = $username;
    //else key is uid
    $array_user[$uid] = trim($username);
  }
  return $array_user;
}


/**************************** */
/*
 *  My Alert Integration
 */
/**************************** */
if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
  $plugins->add_hook("global_start", "scenetracker_alert");
}

function scenetracker_alert()
{
  global $mybb, $lang;
  $lang->load('scenetracker');
  /**
   * We need our MyAlert Formatter
   * Alert Formater for NewScene
   */
  class MybbStuff_MyAlerts_Formatter_ScenetrackerNewSceneFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
  {
    /**
     * Build the output string tfor listing page and the popup.
     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
     * @return string The formatted alert string.
     */
    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
    {
      $alertContent = $alert->getExtraDetails();
      return $this->lang->sprintf(
        $this->lang->scenetracker_newScene,
        $outputAlert['from_user'],
        $alertContent['tid'],
        $outputAlert['dateline']
      );
    }
    /**
     * Initialize the language, we need the variables $l['myalerts_setting_alertname'] for user cp! 
     * and if need initialize other stuff
     * @return void
     */
    public function init()
    {
      if (!$this->lang->scenetracker) {
        $this->lang->load('scenetracker');
      }
    }
    /**
     * We want to define where we want to link to. 
     * @param MybbStuff_MyAlerts_Entity_Alert $alert for which alert.
     * @return string return the link.
     */
    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
    {
      $alertContent = $alert->getExtraDetails();
      return $this->mybb->settings['bburl'] . '/showthread.php?tid=' . $alertContent['tid'];
    }
  }
  if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
    $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
    if (!$formatterManager) {
      $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
    }
    $formatterManager->registerFormatter(
      new MybbStuff_MyAlerts_Formatter_ScenetrackerNewSceneFormatter($mybb, $lang, 'scenetracker_newScene')
    );
  }

  class MybbStuff_MyAlerts_Formatter_ScenetrackerNewAnswerFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
  {
    /**
     * Build the output string tfor listing page and the popup.
     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
     * @return string The formatted alert string.
     */
    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
    {
      $alertContent = $alert->getExtraDetails();
      return $this->lang->sprintf(
        $this->lang->scenetracker_newAnswer,
        $outputAlert['from_user'],
        $alertContent['tid'],
        $alertContent['pid'],
        $outputAlert['dateline']
      );
    }
    /**
     * Initialize the language, we need the variables $l['myalerts_setting_alertname'] for user cp! 
     * and if need initialize other stuff
     * @return void
     */
    public function init()
    {
      if (!$this->lang->scenetracker) {
        $this->lang->load('scenetracker');
      }
    }
    /**
     * We want to define where we want to link to. 
     * @param MybbStuff_MyAlerts_Entity_Alert $alert for which alert.
     * @return string return the link.
     */
    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
    {
      $alertContent = $alert->getExtraDetails();
      return $this->mybb->settings['bburl'] . '/showthread.php?tid=' . $alertContent['tid'] . '&pid=' . $alertContent['pid'] . '#pid' . $alertContent['pid'];
    }
  }
  if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
    $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
    if (!$formatterManager) {
      $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
    }
    $formatterManager->registerFormatter(
      new MybbStuff_MyAlerts_Formatter_ScenetrackerNewAnswerFormatter($mybb, $lang, 'scenetracker_newAnswer')
    );
  }
}
