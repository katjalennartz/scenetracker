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
    "description" => "Automatischer Tracker, mit Benachrichtigungseinstellung, Indexanzeige und Reminder",
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


  /*** DATABASE QUERIES ************************************/

  //TODO: Update Scenetracker table

  // //   // UPDATE mybb_threads SET description = REPLACE(description, "&&", ",") WHERE description LIKE "%&&% WHERE `trackerdate` != "0000-00-00 00:00:00"";
  //   UPDATE mybb_threads SET description = REPLACE(description, "&", ",") WHERE description LIKE "%&% WHERE `trackerdate` != "0000-00-00 00:00:00"";
  // //   // UPDATE mybb_threads SET description = REPLACE(description, "|", ",") WHERE description LIKE "%|% WHERE `trackerdate` != "0000-00-00 00:00:00""
  //   UPDATE mybb_threads SET description = REPLACE(description, "-", ",") WHERE description LIKE "%-%" WHERE `trackerdate` != "0000-00-00 00:00:00";
  //   UPDATE mybb_threads SET description = REPLACE(description, "+", ",") WHERE description LIKE "%+% WHERE `trackerdate` != "0000-00-00 00:00:00""
  //   UPDATE mybb_threads SET description = REPLACE(description, "und", ",") WHERE description LIKE "%und% WHERE `trackerdate` != "0000-00-00 00:00:00""

  //   UPDATE mybb_threads SET description = REPLACE(description, " , ", ",") WHERE description LIKE "% , % WHERE `trackerdate` != "0000-00-00 00:00:00""
  //   UPDATE mybb_threads SET description = REPLACE(description, ", ", ",") WHERE description LIKE "%, % WHERE `trackerdate` != "0000-00-00 00:00:00""
  //   UPDATE mybb_threads SET description = REPLACE(description, " ,", ",") WHERE description LIKE "% ,% WHERE `trackerdate` != "0000-00-00 00:00:00""
  //   UPDATE mybb_threads SET `scenetracker_date` = `trackerdate` WHERE `trackerdate` != "0000-00-00 00:00:00"
  //   UPDATE mybb_threads SET `scenetracker_user` = `description` WHERE `trackerdate` != "0000-00-00 00:00:00"

  //   UPDATE mybb_threads SET `newdate` = CONCAT(`feldjahr`,"-",`feldmonat`,"-",`feldtag`," 00:00:00");
  /******************************************************** */

  //Threadtabelle braucht, Feld für Datum, Feld für Teilnehmer
  $db->add_column("threads", "scenetracker_date", "datetime NOT NULL");
  $db->add_column("threads", "scenetracker_place", "varchar(200) NOT NULL");
  $db->add_column("threads", "scenetracker_user", "varchar(200) NOT NULL");
  $db->query("ALTER TABLE `mybb_users` ADD `tracker_index` INT(1) NOT NULL DEFAULT '1', ADD `tracker_reminder` INT(1) NOT NULL DEFAULT '1';");
  //new table for saving scenes and notifivation status
  $db->write_query("CREATE TABLE `" . TABLE_PREFIX . "scenetracker` (
        `id` int(10) NOT NULL AUTO_INCREMENT,
        `uid` int(10) NOT NULL,
        `tid` int(10) NOT NULL,
        `alert` int(1) NOT NULL DEFAULT 1,
        `type` varchar(50) NOT NULL DEFAULT 'always',
        `inform_by` int(10) NOT NULL DEFAULT 1,
        `index_view` int(1) NOT NULL DEFAULT 1,
        `profil_view` int(1) NOT NULL DEFAULT 1,
        `index_reminder` int(1) NOT NULL DEFAULT 1,
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
      'description' => 'Ist das Thema erledigt/unerledigt Plugin installiert und wird zum kennzeichnen von erledigten Szenen genutzt?',
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
    'scenetracker_as' => array(
      'title' => 'Accountswitcher?',
      'description' => 'Ist der Accountswitcher installiert',
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 1
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
  if ($db->field_exists("scenetracker_index", "users")) {
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "users DROP scenetracker_index");
  }
  if ($db->field_exists("tracker_reminder", "users")) {
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "users DROP tracker_reminder");
  }
  if ($db->field_exists("scenetracker_date", "threads")) {
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "threads DROP scenetracker_date");
  }
  if ($db->field_exists("scenetracker_place", "threads")) {
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "threads DROP scenetracker_place");
  }
  if ($db->field_exists("scenetracker_user", "threads")) {
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "threads DROP scenetracker_user");
  }
  if ($db->field_exists("tracker_index", "users")) {
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "users DROP tracker_index");
  }
  // if ($db->field_exists("threadsolved", "threads")) {
  //   $db->write_query("ALTER TABLE " . TABLE_PREFIX . "threads DROP threadsolved");
  // }

  $db->delete_query("templates", "title LIKE 'scenetracker%'");
  $db->delete_query("templategroups", "prefix = 'scenetracker'");

  require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";
  $db->delete_query("themestylesheets", "name = 'scenetracker.css'");
  $query = $db->simple_select("themes", "tid");
  while ($theme = $db->fetch_array($query)) {
    update_theme_stylesheet_list($theme['tid']);
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

  find_replace_templatesets("newreply", "#" . preg_quote('{$posticons}') . "#i", '{$scenetrackerreply}{$scenetrackeredit}{$posticons}');
  find_replace_templatesets("editpost", "#" . preg_quote('{$posticons}') . "#i", '{$scenetrackeredit}{$posticons}');
  find_replace_templatesets("newthread", "#" . preg_quote('{$posticons}') . "#i", '{$scenetrackeredit}{$posticons}
	{$scenetracker_newthread}');
  find_replace_templatesets("showthread", "#" . preg_quote('{$thread[\'subject\']}') . "#i", '{$thread[\'subject\']}{$scenetracker_showthread}');
  find_replace_templatesets("forumdisplay_thread", "#" . preg_quote('{$thread[\'multipage\']}</span>') . "#i", '{$thread[\'multipage\']}</span>{$scenetrackerforumdisplay}');
  find_replace_templatesets("index", "#" . preg_quote('{$header}</span>') . "#i", '{$header}{$scenetracker_index_reminder}');
  find_replace_templatesets("index", "#" . preg_quote('{$footer}') . "#i", '{$scenetracker_index_main}{$footer}');
  find_replace_templatesets("member_profile", "#" . preg_quote('{$avatar}</td>') . "#i", '{$avatar}</td></tr><tr><td colspan="2">{$scenetracker_profil}</td>');
  find_replace_templatesets("usercp_nav_misc", "#" . preg_quote('<tbody style="{$collapsed[\'usercpmisc_e\']}" id="usercpmisc_e">') . "#i", '
  <tbody style="{$collapsed[\'usercpmisc_e\']}" id="usercpmisc_e"><tr><td class="trow1 smalltext"><a href="usercp.php?action=scenetracker">Szenentracker</a></td></tr>
  ');


  //  find_replace_templatesets("newthread", "#" . preg_quote('{$thread[\'profilelink\']}') . "#i", '{$scenetrackerforumdisplay}{$thread[\'profilelink\']}');

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

  find_replace_templatesets("newreply", "#" . preg_quote('{$scenetrackerreply}{$scenetrackeredit}') . "#i", '');
  find_replace_templatesets("editpost", "#" . preg_quote('{$scenetrackeredit}') . "#i", '');
  find_replace_templatesets("newthread", "#" . preg_quote('{$scenetrackeredit}{$posticons}{$scenetracker_newthread}') . "#i", '{$posticons}');
  find_replace_templatesets("showthread", "#" . preg_quote('{$scenetracker_showthread}') . "#i", '');
  find_replace_templatesets("forumdisplay_thread", "#" . preg_quote('{$scenetrackerforumdisplay}') . "#i", '');
  find_replace_templatesets("index", "#" . preg_quote('{$scenetracker_index_reminder}') . "#i", '');
  find_replace_templatesets("index", "#" . preg_quote('{$scenetracker_index_main}') . "#i", '');
  find_replace_templatesets("member_profile", "#" . preg_quote('</tr><tr><td colspan="2">{$scenetracker_profil}</td>') . "#i", '');
  find_replace_templatesets("usercp_nav_misc", "#" . preg_quote('<tr><td class="trow1 smalltext"><a href="usercp.php?action=scenetracker">Szenentracker</a></td></tr>') . "#i", '');

  if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
    $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

    if (!$alertTypeManager) {
      $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
    }
    $alertTypeManager->deleteByCode('scenetracker_newScene');
    $alertTypeManager->deleteByCode('scenetracker_newAnswer');
  }
}
/**
 * Adds the templates and variables
 */
function scenetracker_add_templates()
{
  global $db;


  //add templates and stylesheets
  // Add templategroup
  $templategrouparray = array(
    'prefix' => 'scenetracker',
    'title'  => $db->escape_string('Szenentracker'),
    'isdefault' => 1
  );
  $db->insert_query("templategroups", $templategrouparray);

  // überprüfe ob templates schon vorhanden, wenn ja tue nichts
  // else füge sie neu ein
  $template[0] = array(
    "title" => 'scenetracker_forumdisplay_infos',
    "template" => '<div class="author smalltext">
    <div class="scenetracker_forumdisplay scene_infos">
    <div class="scenetracker_forumdisplay scene_date icon">Szenendatum: {$scene_date}</div>
    <div class="scenetracker_forumdisplay scene_place icon">Szenenort: {$scene_place}</div>
    <div class="scenetracker_forumdisplay scene_users icon">Szenenteilnehmer: 		{$scenetracker_forumdisplay_user}</div>	
    <div class="scenetracker_forumdisplay scene_autor">Autor: {$author}</div>
  </div>
  </div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );
  $template[1] = array(
    "title" => 'scenetracker_forumdisplay_user',
    "template" => '<span class="scenetracker_forumdisplay scenetracker_user">{$user} {$delete}</span>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );
  $template[2] = array(
    "title" => 'scenetracker_index_bit_chara',
    "template" => '<div class="scenetracker_index character_box">
    <div class="scenetracker_index character_item name "><h1>{$charaname}</h1></div>
  <div class="scenetracker_index character container item">
    {$scenetracker_index_bit_scene}
  </div>
  </div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[3] = array(
    "title" => 'scenetracker_index_bit_scene',
    "template" => '<div class ="scenetracker_index container sceneindex__scenebox scene_index chara_item__scene">
    <div class="sceneindex__sceneitem scene_title icon">{$scene} 
        <span class="scene_status"> - {$close} {$certain} </span> 
    </div>
      <div class="sceneindex__sceneitem scene_last ">
        <span class="scene_last icon">Letzter Post: {$lastposterlink} am {$lastpostdate}</span>
      </div>
  
    <div class="sceneindex__sceneitem scene_date scene_place">
      <span class="scene_date icon">{$scenedate} </span>
      <span class="scene_place icon">{$sceneplace} </span>
      <span class="scene_users icon ">{$users}</span>
    </div>
    
  </div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );
  $template[4] = array(
    "title" => 'scenetracker_index_main',
    "template" => '<div class="scenetracker_index wrapper_container">Szenenverwaltung
    {$scenetracker_index_bit_chara}
  </div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[5] = array(
    "title" => 'scenetracker_index_reminder',
    "template" => '<div class="scenetracker_reminder box"><span class="senetracker_reminder text">Du lässt deinen Postpartner in folgenden Szenen warten:</span>
    <div class="scenetracker_reminder container">
  {$scenetracker_index_reminder_bit}
    </div>
  </div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[6] = array(
    "title" => 'scenetracker_index_reminder_bit',
    "template" => '<div class="scenetracker_reminder item">
    <a href="showthread.php?tid={$scenes[\\\'tid\\\']}&action=lastpost">
    {$scenes[\\\'subject\\\']}</a> ({$lastpostdays} Tage) 
    <a href="index.php?action=reminder&sid={$scenes[\\\'id\\\']}&uid={$uid}">[x]</a></div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );
  $template[7] = array(
    "title" => 'scenetracker_newreply',
    "template" => '<tr>
    <td class="trow2" width="20%" colspan="2" align="center">
      <input type="checkbox" name="scenetracker_add" id="scenetracker_add" value="add" checked /> <label for="scenetracker_add">Charakter zu Teilnehmern hinzufügen</label>
    </td>
  </tr>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[8] = array(
    "title" => 'scenetracker_newthread',
    "template" => '<tr>
    <td class="trow2" width="20%"><strong>Szenendatum:</strong></td>
    <td class="trow2">
    <input type="date" value="{$scenetracker_date}" name="scenetracker_date" /> <input type="time" name="scenetracker_time" value="{$scenetracker_time}" /><br />
    </td>
    </tr>
  <tr>
    <td class="trow2" width="20%"><strong>Ort:</strong></td>
    <td class="trow2">
      <div class="con">
        <div class="con-item">
          <input type="text" id="place" name="place" size="40" value="{$scenetracker_place}" />
        </div>
        <div class="con-item">
          Hier den Ort eintragen. Wo findet die Szene statt? 
        </div>
      </div>
    </td>
    </tr><tr>
    <td class="trow2" width="20%"><strong>Teilnehmer:</strong></td>
    <td class="trow2">
      
      <div class="con">
        <div class="con-item">
          <input id="teilnehmer" type="text" value="{$scenetracker_user}" size="40"  name="teilnehmer" autocomplete="off" style="display: block;" />
          <div id="suggest" style="display:none; z-index:10;"></div>
        </div>
        <div class="con-item">
          Mit , getrennt lassen sich mehrere Teilnehmer eintragen, bitte ohne Leertaste nach dem Komma.
        </div>
      </div>
  
    </td>
    </tr>
  <script type="text/javascript" src="./jscripts/suggest.js"></script>
  <script type="text/javascript" src="./jscripts/scenetracker.js"></script>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[9] = array(
    "title" => 'scenetracker_popup',
    "template" => '<div id="trackerpop{$id}" class="trackerpop">
    <div class="pop">
      <form method="post" action="">
        {$hidden}
        <input type="hidden" value="{$data[\\\'id\\\']}" name="getid">
       <select name="charakter">
          {$users_options_bit}
        </select><br />
        <input type="submit" name="certainuser" />
      </form>
      </div><a href="#closepop" class="closepop"></a>
  </div>
  
  <a href="#trackerpop{$id}"><i class="fas fa-cogs"></i></a>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );
  $template[10] = array(
    "title" => 'scenetracker_profil',
    "template" => '<div class="scenetracker container scenetracker_profil">
    {$scenetracker_profil_bit}
    </div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );


  $template[11] = array(
    "title" => 'scenetracker_profil_active',
    "template" => '	<div class="scenetracker container active">
    {$scenetracker_profil_bit_active}
    </div>	',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[12] = array(
    "title" => 'scenetracker_profil_bit',
    "template" => '{$scenetracker_profil_bit_mY}
    <div class="scenetracker scenebit scenetracker_profil">
      <div class="scenetracker__sceneitem scene_date icon ">{$scenedate}</div>
      <div class="scenetracker__sceneitem scene_place icon ">{$sceneplace}</div>
      <div class="scenetracker__sceneitem scene_title icon"><a href="showthread.php?tid={$tid}">{$subject}</a> </div>
      <div class="scenetracker__sceneitem scene_users icon ">{$sceneusers}</div>
      <div class="scenetracker__sceneitem scene_status">{$scenestatus}</div>
      <div class="scenetracker__sceneitem scene_hide">{$scenehide}</div>
    </div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[13] = array(
    "title" => 'scenetracker_profil_bit_mY',
    "template" => '<span class="scentracker month">{$scenedatetitle}</span>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[14] = array(
    "title" => 'scenetracker_profil_closed',
    "template" => '<div class="scenetracker container closed">
    {$scenetracker_profil_bit_closed}
    </div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[15] = array(
    "title" => 'scenetracker_showthread',
    "template" => '<div class="scenetracker scenebit scenetracker_showthread">
    <div class="scenetracker__sceneitem scene_date icon">{$scene_date}</div>
    <div class="scenetracker__sceneitem scene_place icon ">{$sceneplace}</div>
    <div class="sceneucp__sceneitem scene_status icon">{$scenestatus}</div>
    <div class="scenetracker__sceneitem scene_users icon">{$scenetracker_showthread_user}</div> 
  
  </div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[16] = array(
    "title" => 'scenetracker_showthread_user',
    "template" => '<span class="scenetracker_user">{$user} {$delete}</span>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[17] = array(
    "title" => 'scenetracker_ucp_bit_chara',
    "template" => '<div class="scene_ucp chara_item">
    <h3>{$charaname}</h3>
    <div class="scene_ucp chara_item__scenes-con">
      {$scenetracker_ucp_bit_scene}
    </div>
  </div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[18] = array(
    "title" => 'scenetracker_ucp_bit_scene',
    "template" => '<div class ="sceneucp__scenebox scene_ucp chara_item__scene">
    <div class="sceneucp__sceneitem scene_title icon">{$scene}</div>
    <div class="sceneucp__sceneitem scene_status icon">Szene {$close}
    </div>
    <div class="sceneucp__sceneitem scene_profil icon">Szene {$hide}</div>
    <div class="sceneucp__sceneitem scene_alert icon {$alertclass}">
      <span class="sceneucp__scenealerts">{$alerttype} {$certain}  {$always}</span>
    </div>
  
    <div class="sceneucp__sceneitem scene_date icon">{$scenedate}</div>
    <div class="sceneucp__sceneitem scene_place icon">{$sceneplace}</div>
    <div class="sceneucp__sceneitem scene_users icon ">{$users}</div>
    <div class="sceneucp__sceneitem scene_last icon ">Letzter Post von {$lastposterlink} am {$lastpostdate}</div>
  </div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[19] = array(
    "title" => 'scenetracker_ucp_main',
    "template" => '<html>
    <head>
    <title>Szenenverwaltung</title>
    {$headerinclude}
      <script type="text/javascript" src="./jscripts/scenetracker.js"></script>
    </head>
    <body>
    {$header}
    <table width="100%" border="0" align="center">
    <tr>
    {$usercpnav}
    <td valign="top">
    <div class="scene_ucp container">
    <div class="scene_ucp manage alert_item">
      <h1>Szenentracker</h1>
      <p>Hier kannst du alles rund um den Szenentracker anschauen und verwalten. Die Einstellungen für die Alerts
    kannst du <a href="alerts.php?action=settings">hier</a> vornehmen. Stelle hier erst einmal allgemein ein,
    ob du die Szenen auf dem Index angezeigt werden möchtest und ob du eine Meldung haben möchtest, wenn du in
    einer Szene länger als 6 Wochen dran bist.
      </p>
      <h2>Benachrichtigungseinstellungen</h2>
    <div class="scene_ucp container alerts">
      <div class="scene_ucp alerts_item">
        <form action="usercp.php?action=scenetracker" method="post">
        <fieldset><legend>Szenenübersicht auf der Indexseite</legend>
        <input type="radio" name="index" id="index_yes" value="1" {$yes_ind}> <label for="index_yes">Ja</label>
        <input type="radio" name="index" id="index_no" value="1" {$no_ind}> <label for="index_no">Nein</label><br />
        <input type="submit" name="opt_index" value="speichern" id="index_button" />
        </fieldset>
        </form>
      </div>
      <div class="scene_ucp alerts_item">
        <form action="usercp.php?action=scenetracker" method="post">
        <fieldset><legend>Szenenerinnerung nach 6 Wochen</legend>
        <input type="radio" name="index" id="reminder_yes" value="1" {$yes_ind}> <label for="index_yes">Ja</label>
        <input type="radio" name="index" id="reminder_no" value="1" {$no_ind}> <label for="index_no">Nein</label><br />
        <input type="submit" name="opt_reminder" value="speichern" id="reminder_button" />
        </fieldset>
      </form>
      </div>
    </div>
    </div><!--scene_ucp manage alert_item-->
    <div class="scene_ucp manage overview_item overview_con">
      <div class="scene_ucp overview_item">
      <h2>aktuelle Szenen - hier bist du dran</h2>
        <div class="scene_ucp overview_chara_con">
        {$scenetracker_ucp_bit_chara_new} 
        </div>
      </div>
      <div class="scene_ucp overview_item">
      <h2>aktuelle Szenen - aber du bist nicht dran</h2>
        <div class="scene_ucp overview_chara_con">
        {$scenetracker_ucp_bit_chara_old} 
        </div>
      </div>
      <div class="scene_ucp overview_item">
      <h2>geschlossene Szenen </h2>
        <div class="scene_ucp overview_chara_con">
        {$scenetracker_ucp_bit_chara_closed} 
        </div>
      </div>
      </div>
    </div><!--scene_ucp container-->
    
    <script type="text/javascript" src="./jscripts/suggest.js"></script>
    </td>
    </tr>
    </table>
    {$footer}
    </body>
    
    </html>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  foreach ($template as $row) {
    $db->insert_query("templates", $row);
  }

  $css = array(
    'name' => 'socialnetwork.css',
    'tid' => 1,
    'attachedto' => '',
    "stylesheet" =>    '
    :root {
      --main-red: #a02323;
      --dark-grey-back: #b1b1b1;
      --lighter-grey: #c5c5c5;
      --light-grey-back: #bcbcbc;
      --darkest-grey: #898989;
  }
  
  /* **********
  * Showthread
  ******** */
  .scenetracker_user {
    display:inline-block;
  }
  .scenetracker_user:after {
    content: ", ";
  }
  .scenetracker_user:last-child:after {
    content: none;
  }
  
  .breadcrumbs li {
    display: inline-block;
  }
  .breadcrumbs li:after {
    content: ">";
    margin-left: 10px;
  }
  .breadcrumbs li:last-child:after {
    content: none;
  }
  
  /* **********
  *POP UP
  ******** */
  .trackerpop { 
    position: fixed; 
    top: 0; 
    right: 0; 
    bottom: 0; 
    left: 0; 
    background: hsla(0, 0%, 0%, 0.5); 
    z-index: 9; 
    opacity:0; 
    -webkit-transition: .5s ease-in-out; 
    -moz-transition: .5s ease-in-out; 
    transition: .5s ease-in-out; 
    pointer-events: none; 
  } 
  
  .trackerpop:target {
    opacity:1;
    pointer-events: auto;
    z-index: 20;
  } 
  
  .trackerpop > .pop {
    background: #aaaaaa;
    width: 200px;
    position: relative;
    margin: 10% auto;
    padding: 15px;
    z-index: 50;
    text-align: center;
  } 
  
  .trackerclosepop { 
    position: absolute; 
    right: -5px; 
    top:-5px; 
    width: 100%; 
    height: 100%; 
    z-index: 10; 
  }
  
  .trackerpop input[type="submit"] {
    background-color: var(--darkest-grey);
    border: none;
    color: white;
    padding: 8px 20px;
    text-decoration: none;
    margin-top: 10px;
    cursor: pointer;
  }
  
  /* **********
  * UCP
  ******** */
  .scene_ucp.container.alerts {
      display: flex;
      justify-content: space-around;
  }
  .scene_ucp.alerts_item {
      display: block;
      width: 48%;
  }
  
  .scene_ucp.overview_chara_con {
      display: grid;
      grid-template-columns: 49% 49%;
  }
  
  .scene_ucp.chara_item__scenes-con {
      max-height: 120px;
      overflow: auto;
      margin: 5px;
      margin-top:0px;
  }
  
  .scene_ucp.chara_item__scene {
     padding: 8px;
  }
  
  .scene_ucp.chara_item__scene:nth-child(even) {
      background-color: var(--dark-grey-back);
  }
  
  .scene_ucp.chara_item__scene:nth-child(odd) {
    background-color: var(--lighter-grey);
  }
  
  .scene_ucp > h2 {
    position: relative;
  }
  
  .scene_ucp > h2::after {
    content: " ";
    display: block;
    position: relative;
    height: 1px;
    background: black;
    top: 0px;
  }
  
  .sceneucp__scenebox {
      display: grid;
      grid-template-columns: 1fr 1fr;
  }
  
  .sceneucp__sceneitem.scene_status,{
      grid-column-start: 1 ;
  }
  .sceneucp__sceneitem.scene_profil {
    grid-column-start: span 2;
  }
  .sceneucp__sceneitem.scene_alert.certain,
  .sceneucp__sceneitem.sceneinfos,
  .sceneucp__sceneitem.scene_alert.always,
  .sceneucp__sceneitem.scene_title,
  .sceneucp__sceneitem.scene_last,
  .sceneucp__sceneitem.scene_users,
  .sceneucp__sceneitem.scene_infos {
      grid-column-start: span 3;
  }
  
  .sceneucp__sceneitem.scene_infos {
      display: flex;
  }
  
  .sceneucp__sceneitem > .flexitem {
      padding: 3px;
  }
  .sceneucp__sceneitem > .flexitem.left {
      width: 40%;
  }
  .sceneucp__sceneitem.scene_title a:after { 
      content: "";
      display: block;
      margin-top: -5px;
      height: 1px;
      background: black;
  }
  
  /********
  *ICONS **
  *********/
  .icon::before{
    display: inline-block;
    font-style: normal;
    font-variant: normal;
    text-rendering: auto;
    -webkit-font-smoothing: antialiased;
  padding: 3px;
      font-family: "Font Awesome 5 Free";
    font-weight: 900;
  }
  .scene_title.icon::before {
    content: "&#92;f07c";
  }
  
  .scene_status.icon::before {
    content: "&#92;f144";
  }
  
  .scene_profil.icon::before {
    content: "&#92;f2bd";
  }
  .scene_alert.icon::before {
    content: "&#92;f0a1";
  }
  .scene_date.icon::before{
      content: "&#92;f133";
  }
  .scene_users.icon::before{
      content: "&#92;f0c0";
  }
  .scene_place.icon::before{
      content: "&#92;f3c5";
  }
  .scene_last.icon::before{
      content: "&#92;f06a";
  }
  
  /*****************
  **PROFIL
  *****************/ 
  .scenetracker.container {width: 90%;height: 400px;overflow: auto;margin: auto auto;background: #c7c7c7;padding: 10px;}
  
  span.scentracker.month {
    margin-top:10px;
      width: 90%;
      font-weight: 600;
      font-size: 1.3em;
      border-bottom: 1px solid black;
      display: block;
  }
  
  .scenetracker.scenebit {
      padding-left: 10px;
      padding-right:20px;
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 20px;
  }
  
  .scenetracker__sceneitem.scene_users {
      grid-column: 2 / -1;
      grid-row: 2;
  }
  
  .scenetracker__sceneitem.scene_title {
      grid-column: 1 / 2;
      grid-row: 2;
  }
  
  .scenetracker__sceneitem.scene_status {
    grid-column: -1;
    grid-row:1;
      text-align: center;
      place-self: center;
  }
  
  .scenetracker__sceneitem.scene_date {
      grid-column: 1;
      grid-row: 1;
  }
  .scenetracker__sceneitem.scene_hide {
      grid-row: 2;
      grid-column: -1;
  }
  
  .scenetracker.scenebit:nth-child(even) {background: var(--dark-grey-back);}
  .scenetracker.scenebit:nth-child(odd) {background: var(--light-grey-back);}
  
  
  /*****************
  *Forumdisplay
  *****************/ 
  
  .scenetracker_forumdisplay.scene_infos {
      display: grid;
      grid-template-columns: 1fr 2fr;
  
  }
  
  .scenetracker_forumdisplay.scene_users.icon {
      grid-column: span 2;
  }
  
  /*********************
  *INDEX
  *********************/
  
  .scenetracker_index.character.container {
      /* display: grid; */
      width: 100%;
      max-height: 150px;
      overflow: auto;
  }
  
  .scenetracker_index.wrapper_container{
    
  }
  .scenetracker_index.chara_item__scene:nth-child(even) {
      background-color: var(--dark-grey-back);
  }
  
  .scenetracker_index h1 {
    position:relative;
    font-size: 1.5em;
    z-index: 20;
    margin-bottom: 5px;
    padding-left:15px;
  }
  
  .scenetracker_index h1:after {
    content: " ";
    display: block;
    height: 1px;
    background: black;
    margin-top:-10px;
    margin-bottom:-5px;
  }
  
  .scenetracker_index.chara_item__scene:nth-child(odd) {
    background-color: var(--lighter-grey);
    width: 100%;
  }
  
  .sceneindex__scenebox.container {
    /* width:100%; */
    display: grid;
    grid-template-columns: 1fr 1fr;
  }
  
  .sceneindex__sceneitem.scene_users {
      grid-column: 1 / -1;
  }
  .sceneindex__sceneitem.scene_title {
      padding-top: 5px;
      font-weight: 600;
      grid-row: 1;
      grid-column: 1;
  }
  .sceneindex__sceneitem.scene_status.scene_place {
      grid-column: 3;
      grid-row: 1;
  }
  .sceneindex__sceneitem.scene_place.scene_date {
      grid-column: 1 / -1;
      grid-row: 2;
  }
  
  .sceneindex__sceneitem.scene_last {
      grid-row: 1;
      grid-column: 2;
  }
  
  .sceneindex__sceneitem.scene_alert {
      grid-column: 4;
      grid-row: span 2;
      margin-right: 10px;
  }
  
  .sceneindex__sceneitem.scene_last {
      padding-top: 5px;
  }
  
  /*INDEX REMINDER */ 
  .scenetracker_reminder.container {
      max-height: 100px;
      overflow: auto;
      padding-left: 30px;
    margin-bottom: 20px;
  }
  
  .scenetracker_reminder.item:before {
    content: "» ";
  }
  
  span.senetracker_reminder.text {
      text-align: center;
      display: block;
  }
    ',
    'cachefile' => $db->escape_string(str_replace('/', '', 'socialnetwork.css')),
    'lastmodified' => time()
  );

  require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

  $sid = $db->insert_query("themestylesheets", $css);
  $db->update_query("themestylesheets", array("cachefile" => "css.php?stylesheet=" . $sid), "sid = '" . $sid . "'", 1);

  $tids = $db->simple_select("themes", "tid");
  while ($theme = $db->fetch_array($tids)) {
    update_theme_stylesheet_list($theme['tid']);
  }
}

/**
 * Neuen Thread erstellen - Felder einfügen
 */

$plugins->add_hook("newthread_start", "scenetracker_newthread");
function scenetracker_newthread()
{
  global $db, $mybb, $templates, $fid, $scenetracker_newthread, $thread,  $post_errors, $scenetracker_date, $scenetracker_time, $scenetracker_user;

  if (testParentFid($fid)) {
    if ($mybb->input['previewpost'] || $post_errors) {
      $scenetracker_date = $db->escape_string($mybb->input['scenetracker_date']);
      $scenetracker_time = $db->escape_string($mybb->input['scenetracker_time']);
      $scenetracker_user = $db->escape_string($mybb->input['teilnehmer']);
      $scenetracker_place = $db->escape_string($mybb->input['place']);
    } else {
      $scenetracker_date = "1997-04-01";
      $scenetracker_time = "12:00";
      $scenetracker_user = $db->escape_string($mybb->user['username']) . ",";
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
    $alertsetting_alert = $mybb->settings['scenetracker_alert_alerts'];
    $usersettingIndex = intval($mybb->user['tracker_index']);
    $array_users = array();
    $date = $db->escape_string($mybb->input['scenetracker_date']) . " " . $db->escape_string($mybb->input['scenetracker_time']);
    $scenetracker_place = $db->escape_string($mybb->input['place']);
    $teilnehmer = $db->escape_string($mybb->input['teilnehmer']);

    $array_users = getUids($teilnehmer);

    $save = array(
      "scenetracker_date" => $date,
      "scenetracker_user" => $teilnehmer,
      "scenetracker_place" => $scenetracker_place
    );
    $db->update_query("threads", $save, "tid='{$tid}'");

    foreach ($array_users as $uid => $username) {
      if ($uid != $username) {
        $alert_array = array(
          "uid" => $uid,
          "tid" => $tid,
          "type" => "always"
        );
        $db->insert_query("scenetracker", $alert_array);

        //add alert for new scene
        $usersettingAlert = $db->simple_select("users", "tracker_alert", "uid={$uid}");

        if ($alertsetting_alert == 1 && $usersettingAlert == 1) {
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
}

/**********************************
 * new reply View 
 * shows the possibility to add your character to the list of users
 *********************************/
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

/********************************
 * send new reply 
 * send and save data
 *******************************/
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
      $db->write_query("UPDATE " . TABLE_PREFIX . "threads SET scenetracker_user = CONCAT(scenetracker_user, '," . $username . "') WHERE tid = {$tid}");

      $to_add = array(
        "uid" => $thisuser,
        "tid" => $tid,
        "type" => "always"
      );
      $db->insert_query("scenetracker", $to_add);
    }

    foreach ($array_users as $uid => $username) {
      $usersettingAlert = $db->simple_select("users", "tracker_alert", "uid={$uid}");
      if ($uid != $username && $usersettingAlert == 1) {
        $type = $db->fetch_array($db->write_query("SELECT type, inform_by FROM " . TABLE_PREFIX . "scenetracker WHERE tid = $tid AND uid = $uid"));
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

/*********************************
 * Thread editieren 
 * Datum oder/und Teilnehmer bearbeiten - Anzeige
 *********************************/
$plugins->add_hook("editpost_end", "scenetracker_editpost");
function scenetracker_editpost()
{
  global $thread, $templates, $db, $lang, $mybb, $templates, $fid, $post_errors, $post, $scenetrackeredit;
  if (testParentFid($fid)) {
    if ($thread['firstpost'] == $mybb->input['pid']) {

      $date = explode(" ", $thread['scenetracker_date']);
      if ($mybb->input['previewpost'] || $post_errors) {
        $scenetracker_date = $db->escape_string($mybb->input['scenetracker_date']);
        $scenetracker_time = $db->escape_string($mybb->input['scenetracker_time']);
        $scenetracker_user = $db->escape_string($mybb->input['teilnehmer']);
        $scenetracker_place = $db->escape_string($mybb->input['place']);
      } else {
        $scenetracker_date = $date[0];
        $scenetracker_time = $date[1];
        $scenetracker_user = $db->escape_string($thread['scenetracker_user']) . " , ";
        $scenetracker_place = $db->escape_string($thread['scenetracker_place']);
      }
      $teilnehmer_alt =  array_map('trim', explode(",", $thread['scenetracker_user']));
      eval("\$scenetrackeredit = \"" . $templates->get("scenetracker_newthread") . "\";");
    } else { //we're answering to a post.

      $teilnehmer = $thread['scenetracker_user'];
      $thisuser = $post['username'];
      $contains = strpos($teilnehmer, $thisuser);
      //show add to teilnehmer, if not already in
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

    if ($pid != $thread['firstpost']) {
      if ($mybb->input['scenetracker_add']) {
        $insert_array = array(
          "uid" => $post['uid'],
          "tid" => $tid
        );
        $db->insert_query("scenetracker", $insert_array);
        $db->write_query("UPDATE " . TABLE_PREFIX . "threads SET scenetracker_user = CONCAT(scenetracker_user, ', " . $db->escape_string($post['username']) . "') WHERE tid = $tid");
      }
    } else {
      $date = $db->escape_string($mybb->input['scenetracker_date']) . " " . $db->escape_string($mybb->input['scenetracker_time']);
      $place = $db->escape_string($mybb->input['place']);

      $teilnehmer_alt = array_map('trim', explode(",",  $thread['scenetracker_user']));
      $teilnehmer_neu = array_filter(array_map('trim', explode(",", $mybb->input['teilnehmer'])));


      //to build the new input for scenetracker user 
      $new_userfield = array();
      //array_diff-> got the users we add new, array intersect the old ones, without the ones we want to delete. and then merge both of them! 
      $workarray = array_merge(array_intersect($teilnehmer_alt, $teilnehmer_neu), array_diff($teilnehmer_neu, $teilnehmer_alt));
      //no whitespaces at the beginn and the end to be sure
      $workarray = array_map('trim', $workarray);
      foreach ($workarray as $name) {
        if ($name != "") {
          $user = get_user_by_username($name);
          if ($user == "") {
            $uid = $db->escape_string($name);
            $new_userfield[$uid] = $db->escape_string($name);
          } else {
            $uid = $user['uid'];
            $new_userfield[$uid] = $db->escape_string($name);
            var_dump($new_userfield);
            if (($db->num_rows($db->simple_select("scenetracker", "*", "tid = $tid AND uid = $uid")) == 0)) {
              $insert_array = array(
                "uid" => $uid,
                "tid" => $tid,
              );
              $db->insert_query("scenetracker", $insert_array);
            }
          }
        }
      }
      //we want to inform a user if he is added to a scene: 
      foreach (array_diff($teilnehmer_neu, $teilnehmer_alt) as $name) {
        $user = get_user_by_username($name);
        if ($user != "") {
          $uid = $user['uid'];
        }
        //TODO alert acp aktiviert?
        if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
          $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('scenetracker_newScene');
          if ($alertType != NULL && $alertType->getEnabled() && $uid != $mybb->user['uid']) {
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
      //Build the new String for users and save it
      $to_save_str = implode(",", $new_userfield);
      $save = array(
        "scenetracker_date" => $date,
        "scenetracker_place" => $place,
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
            $db->delete_query("scenetracker", "uid={$uid} AND tid  = {$tid}");
          }
        }
      }
    }
  }
}

/*********************
 * Anzeige von Datum, Ort und Teilnehmer im Forumdisplay
 *********************/
$plugins->add_hook("forumdisplay_thread", "scenetracker_forumdisplay_showtrackerstuff");
function scenetracker_forumdisplay_showtrackerstuff()
{
  global $thread, $templates, $db, $fid, $scenetrackerforumdisplay;

  if (testParentFid($fid)) {
    $scene_date = date('d.m.Y - H:i', strtotime($thread['scenetracker_date']));

    $userArray = getUids($thread['scenetracker_user']);
    $scene_place = $db->escape_string($thread['scenetracker_place']);
    $author = build_profile_link($thread['username'], $thread['uid']);
    $scenetracker_forumdisplay_user = "";
    foreach ($userArray as $uid => $username) {
      if ($uid != $username) {
        $user = build_profile_link($username, $uid);
      } else {
        $user = $db->escape_string($username);
      }

      eval("\$scenetracker_forumdisplay_user.= \"" . $templates->get("scenetracker_forumdisplay_user") . "\";");
    }
    eval("\$scenetrackerforumdisplay = \"" . $templates->get("scenetracker_forumdisplay_infos") . "\";");
  }
}


/*********************
 * Anzeige von Datum, Ort und Teilnehmer im showthread
 *********************/
$plugins->add_hook("showthread_start", "scenetracker_showthread_showtrackerstuff");
function scenetracker_showthread_showtrackerstuff()
{
  global $thread, $templates, $db, $fid, $tid, $mybb, $scenetracker_showthread;
  if (testParentFid($fid)) {
    $allowclosing = false;
    $thisuser = intval($mybb->user['uid']);
    $scene_date = date('d.m.Y - H:i', strtotime($thread['scenetracker_date']));
    $sceneplace = $db->escape_string($thread['scenetracker_place']);
    //all users of scene
    //TODO show place
    $userArray = getUids($thread['scenetracker_user']);
    $finish = "<button >close scene</button>";
    foreach ($userArray as $uid => $username) {
      if ($uid != $username) {
        $user = build_profile_link($username, $uid);
        if ($mybb->usergroup['canmodcp'] == 1 || change_allowed($thread['scenetracker_user'])) {
          $allowclosing = true; //if he's a participant he is also allowed to close/open scene
          $delete = "<a href=\"showthread.php?tid=" . $tid . "&delete=" . $uid . "\">[x]</a>";
        }
      } else {
        $user = $username;
        $delete = "";
      }
      eval("\$scenetracker_showthread_user.= \"" . $templates->get("scenetracker_showthread_user") . "\";");
    }

    if ($allowclosing || $mybb->usergroup['canmodcp'] == 1) {
      if ($thread['threadsolved'] == 1) {
        $mark = "<a href=\"showthread.php?tid=" . $tid . "&scenestate=open\">[öffnen]</a></span>";
        $scenestatus = "<span class=\"scenestate\">Szene ist beendet. " . $mark;
      } else {
        $mark = "<a href=\"showthread.php?tid=" . $tid . "&scenestate=close\">[schließen]</a></span>";
        $scenestatus = "<span class=\"scenestate\">Szene ist offen. " . $mark;
      }
    }
    eval("\$scenetracker_showthread = \"" . $templates->get("scenetracker_showthread") . "\";");
  }

  //delete a participant
  if ($mybb->input['delete']) {
    $uiddelete = intval($mybb->input['delete']);
    $userdelete = $db->fetch_field($db->simple_select("users", "username", "uid = $uiddelete"), "username");
    if ($mybb->usergroup['canmodcp'] == 1 || check_switcher($uid)) {
      $teilnehmer = str_replace($userdelete . ",", "", $thread['scenetracker_user']);
      $teilnehmer = str_replace("," . $userdelete, "", $teilnehmer);
      $teilnehmer = $db->escape_string($teilnehmer);
      $db->query("UPDATE " . TABLE_PREFIX . "threads SET scenetracker_user = '" . $teilnehmer . "' WHERE tid = " . $tid . " ");
      $db->delete_query("scenetracker", "tid = " . $tid . " AND uid = " . $uiddelete . "");

      redirect("showthread.php?tid=" . $tid);
    }
  }

  //TODO is user allowed to close/open scene?
  if ($mybb->input['scenestate'] == "open") {

    // $db->query("UPDATE " . TABLE_PREFIX . "threads SET threadsolved = 1, closed = 0 WHERE tid= " . $tid);
    $db->query("UPDATE " . TABLE_PREFIX . "scenetracker SET closed = 1 WHERE tid = " . $tid);

    redirect("showthread.php?tid=" . $tid);
  }
  if ($mybb->input['scenestate'] == "close") {
    scene_change_status(1,  $tid,  $thisuser);
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

/************
 * Verwaltung der szenen im UCP 
 *********** */
$plugins->add_hook("usercp_start", "scenetracker_usercp");
function scenetracker_usercp()
{
  global $mybb, $db, $templates, $cache, $templates, $themes, $headerinclude, $header, $footer, $usercpnav, $scenetracker_ucp_main, $scenetracker_ucp_bit_char, $scenetracker_ucp_bit_chara_new, $scenetracker_ucp_bit_chara_old, $scenetracker_ucp_bit_chara_closed;
  if ($mybb->input['action'] != "scenetracker") {
    return false;
  }

  if ($mybb->user['uid'] == 0) {
    error_no_permission();
  }
  $index_settinguser = $db->fetch_field($db->simple_select("users", "tracker_index", "uid = " . $mybb->user['uid']), "tracker_index");
  if ($index_settinguser == 1) {
    $yes_ind = "checked";
    $no_ind = "";
  } else if ($index_settinguser == 0) {
    $yes_ind = "";
    $no_ind = "checked";
  }
  $index_reminder = $db->fetch_field($db->simple_select("users", "tracker_reminder", "uid= " . $mybb->user['uid']), "tracker_reminder");
  if ($index_reminder == 1) {
    $yes_rem = "checked";
    $no_rem = "";
  } else if ($index_reminder == 0) {
    $yes_rem = "";
    $no_rem = "checked";
  }
  //welcher user ist online
  //gett all charas of this user
  $charas = get_accounts($mybb->user['uid'], $mybb->user['as_uid']);
  $solvplugin = $mybb->settings['scenetracker_solved'];
  // AND (closed = 0 OR threadsolved=0)

  //new scenes
  get_scenes($charas, "new");

  get_scenes($charas, "old");

  get_scenes($charas, "closed");

  if ($mybb->input['opt_index']) {
    $index = $mybb->input['index'];
    foreach ($charas as $uid => $chara) {
      $db->query("UPDATE " . TABLE_PREFIX . "users SET tracker_index = " . $index . " WHERE uid = " . $uid . " ");
      redirect('usercp.php?action=scenetracker');
    }
  }

  if ($mybb->input['opt_reminder']) {
    $reminder = $mybb->input['reminder'];
    foreach ($charas as $uid => $chara) {
      $db->query("UPDATE " . TABLE_PREFIX . "users SET tracker_index = " . $reminder . " WHERE uid = " . $uid . " ");
      redirect('usercp.php?action=scenetracker');
    }
  }

  if ($mybb->input['certainuser']) {
    //info by
    $certained = intval($mybb->input['charakter']);
    //for which scene
    $id = intval($mybb->input['getid']);
    scene_inform_status($id, $certained);
    redirect('usercp.php?action=scenetracker');
  }

  if ($mybb->input['showsceneprofil'] == "0") {
    $id = intval($mybb->input['getsid']);
    $uid = $db->fetch_field($db->simple_select("scenetracker", "uid", "id = $id"), "uid");
    scene_change_view(0, $id, $uid);
    redirect('usercp.php?action=scenetracker');
  } elseif ($mybb->input['showsceneprofil'] == "1") {
    $id = intval($mybb->input['getsid']);
    $uid = $db->fetch_field($db->simple_select("scenetracker", "uid", "id = $id"), "uid");
    redirect('usercp.php?action=scenetracker');
  }

  if ($mybb->input['closed'] == "1") {
    $tid = intval($mybb->input['gettid']);
    $uid = intval($mybb->input['getuid']);
    scene_change_status(1,  $tid,  $uid);
    redirect('usercp.php?action=scenetracker');
  } elseif ($mybb->input['closed'] == "0") {
    $tid = intval($mybb->input['gettid']);
    $uid = intval($mybb->input['getuid']);
    scene_change_status(0,  $tid,  $uid);
    redirect('usercp.php?action=scenetracker');
  }

  eval("\$scenetracker_ucp_main =\"" . $templates->get("scenetracker_ucp_main") . "\";");
  output_page($scenetracker_ucp_main);
}

/**
 * automatische Anzeige von Tracker im Profil
 */
$plugins->add_hook("member_profile_end", "scenetracker_showinprofile");
function scenetracker_showinprofile()
{
  global $db, $mybb, $memprofile, $templates, $scenetracker_profil;
  $thisuser = intval($mybb->user['uid']);
  $userprofil = $memprofile['uid'];
  $scenetracker_profil_bit = "";
  $sort = "0";
  $dateYear = "";
  setlocale(LC_ALL, 'de_DE@euro', 'de_DE', 'de', 'ge');
  date_default_timezone_set('Europe/Berlin');
  setlocale(LC_ALL, 'de_DE.utf8', 'de_DE@euro', 'de_DE', 'de', 'ge');
  $ingame =  $mybb->settings['scenetracker_ingame'];
  $archiv = $mybb->settings['scenetracker_archiv'];

  $allowmanage = check_switcher($userprofil);
  $show_monthYear = array();
  $sort = $mybb->settings['scenetracker_profil_sort'];

  if ($mybb->settings['scenetracker_solved'] == 1)   $solved = ", threadsolved";
  // $scene_query = $db->write_query("SELECT s.*,fid, subject, dateline, t.closed as threadclosed, scenetracker_date, scenetracker_user" . $solved . " 
  // FROM " . TABLE_PREFIX . "scenetracker s, " . TABLE_PREFIX . "threads t WHERE t.tid = s.tid AND s.uid = " . $userprofil . " ORDER by scenetracker_date DESC");

  //hide scene in profile
  if ($mybb->input['show'] == "0") {
    $id = intval($mybb->input['getsid']);
    scene_change_view(0, $id, $userprofil);
    redirect('member.php?action=profile&uid=' . $userprofil);
  }
  //close scene from profile
  if ($mybb->input['closed'] == "1") {
    $tid = intval($mybb->input['gettid']);
    scene_change_status(1,  $tid,  $userprofil);
    redirect('member.php?action=profile&uid=' . $userprofil);
  } elseif ($mybb->input['closed'] == "0") {
    $tid = intval($mybb->input['gettid']);
    scene_change_status(0,  $tid,  $userprofil);
    redirect('member.php?action=profile&uid=' . $userprofil);
  }
  $scene_query = $db->write_query("
      SELECT s.*,t.fid, parentlist, subject, dateline, t.closed as threadclosed, scenetracker_date, scenetracker_user, scenetracker_place FROM " . TABLE_PREFIX . "scenetracker s, 
      " . TABLE_PREFIX . "threads t LEFT JOIN " . TABLE_PREFIX . "forums fo ON t.fid = fo.fid WHERE t.tid = s.tid AND s.uid = " . $userprofil . " 
      AND (concat(',',parentlist,',') LIKE '%," . $ingame . ",%' OR concat(',',parentlist,',') LIKE '%," . $archiv . ",%' ) AND s.profil_view = 1 ORDER by scenetracker_date DESC");

  $sort = 0;
  $date_flag = "1";
  while ($scenes = $db->fetch_array($scene_query)) {
    $tid = $scenes['tid'];
    $sid = $scenes['id'];
    $subject = $scenes['subject'];
    $sceneusers = str_replace(",", ", ", $scenes['scenetracker_user']);
    $sceneplace = $scenes['scenetracker_place'];
    if ($scenes['threadclosed'] == 1 or $scenes['threadsolved'] == 1) {
      if ($allowmanage || $mybb->usergroup['canmodcp'] == 1) {
        $scenestatus = "<a href=\"member.php?action=profile&uid=" . $userprofil . "&closed=0&gettid=" . $tid . "\"><i class=\"fas fa-check-circle\"></i></a>";
      } else {
        $scenestatus = "<i class=\"fas fa-check-circle\"></i>";
      }
    } else {
      if ($allowmanage || $mybb->usergroup['canmodcp'] == 1) {
        $scenestatus = "<a href=\"member.php?action=profile&uid=" . $userprofil . "&closed=1&gettid=" . $tid . "\"><i class=\"fas fa-times-circle\"></i></a>";
      } else {
        $scenestatus = "<i class=\"fas fa-times-circle\"></i>";
      }
    }

    if ($allowmanage || $mybb->usergroup['canmodcp'] == 1) {
      $scenehide = "<a href=\"member.php?action=profile&uid=" . $userprofil . "&show=0&getsid=" . $sid . "\"><i class=\"fas fa-eye-slash\"></i></a>";
    } else {
      $scenehide = "";
    }

    $scenedate = date('d.m.Y - H:i', strtotime($scenes['scenetracker_date']));
    if ($sort == 1) {
      //TODO make scenes sortable
      if ($scenes['threadclosed'] == 1 or $scenes['threadsolved'] == 1) {
        //show opened scenes
        //Threads aus der Datenbank holen
        //aufteilen in archiv / ingame -> auswählbar machen wonach sortieren? 
        //markieren ob erledigt oder nicht 
        //dort als erledigt markierbar machen
      } else {
        //show closed scened
      }
    } else {
      if ($dateYear != date('m.Y', strtotime($scenes['scenetracker_date']))) {
        $scenedatetitle = strftime('%B %Y', strtotime($scenes['scenetracker_date']));
        eval("\$scenetracker_profil_bit_mY = \"" . $templates->get("scenetracker_profil_bit_mY") . "\";");
        $dateYear = date('m.Y', strtotime($scenes['scenetracker_date']));
      } else {
        $scenetracker_profil_bit_mY = "";
      }
      eval("\$scenetracker_profil_bit .= \"" . $templates->get("scenetracker_profil_bit") . "\";");
    }
  }
  eval("\$scenetracker_profil= \"" . $templates->get("scenetracker_profil") . "\";");
}

/**
 *  Anzeige auf Indexseite
 */
$plugins->add_hook('index_start', 'scenetracker_list');
function scenetracker_list()
{
  global $templates, $db, $mybb, $scenetracker_index_main, $scenetracker_index_bit_chara;
  //TODO does the user want to see the scenes on index? 
  //gett all charas of this user
  $uid = $mybb->user['uid'];
  $index = $db->fetch_field($db->simple_select("users", "tracker_index", "uid = $uid"), "uid");
  if ($uid != 0 && $index == 1) {
    $charas = get_accounts($mybb->user['uid'], $mybb->user['as_uid']);
    $solvplugin = $mybb->settings['scenetracker_solved'];
    // AND (closed = 0 OR threadsolved=0)

    //show new scenes
    get_scenes($charas, "index");

    //change inform status always/never/certain user
    if ($mybb->input['certainuser']) {
      //info by
      $certained = intval($mybb->input['charakter']);
      //for which scene
      $id = intval($mybb->input['getid']);
      scene_inform_status($id, $certained);
      redirect('index.php#closepop');
    }

    //change status of scenes
    if ($mybb->input['closed'] == "1") {
      $tid = intval($mybb->input['gettid']);
      $uid = intval($mybb->input['getuid']);
      scene_change_status(1,  $tid,  $uid);
      redirect('index.php');
    } elseif ($mybb->input['closed'] == "0") {
      $tid = intval($mybb->input['gettid']);
      $uid = intval($mybb->input['getuid']);
      scene_change_status(0,  $tid,  $uid);
      redirect('index.php');
    }

    eval("\$scenetracker_index_main =\"" . $templates->get("scenetracker_index_main") . "\";");
  }
}

/******************************
 * Reminder
 * Erinnerung wenn man den Postpartner X Tage warten lässt
 * //WIP!!! 
 ******************************/

$plugins->add_hook('index_start', 'scenetracker_reminder');
function scenetracker_reminder()
{
  global $mybb, $db, $templates, $scenetracker_index_reminder;

  // Alle Charaktere des Users holen
  $charas = get_accounts($mybb->user['uid'], $mybb->user['as_uid']);
  $days = intval($mybb->settings['scenetracker_reminder']);
  // $days = 200;

  foreach ($charas as $uid => $username) {
    //get all scenes 
    //fürs board -> charstatus
    // $get_input = $db->fetch_array($db->simple_select("users", "charstatus, charstatus_date", "uid=$uid"));
    // $chara_status = (intval($mybb->user['char_status']));
    // $get_input = $db->fetch_array($db->simple_select("users", "charstatus, charstatus_date", "uid=$uid"));

    // if ((intval($mybb->user['charstatus'])) == 1) {
    $get_scenes = $db->write_query(
      "SELECT * FROM mybb_scenetracker st, mybb_threads t WHERE st.tid = t.tid 
            AND st.uid = " . $uid . "
            AND lastposteruid != 0
            AND lastposteruid != 1
            AND threadsolved = 0
            AND closed= 0"
    );
    while ($scenes = $db->fetch_array($get_scenes)) {
      $cnt = 1;
      $today = new DateTime();

      $postdate = new DateTime();
      $postdate->setTimestamp($scenes['lastpost']);

      $interval = $postdate->diff($today);
      $lastpostdays = $interval->days;
      if ($interval->days > $days) {

        if (($scenes['type'] == 'always') || ($scenes['type'] == 'never')) {
          if ($scenes['index_reminder'] == 1) {
            eval("\$scenetracker_index_reminder_bit .=\"" . $templates->get("scenetracker_index_reminder_bit") . "\";");
          }
        }
        if ($scenes['type'] == 'certain' &&  ($scenes['lastposteruid'] == $scenes['inform_by'])) {
          if ($scenes['index_reminder'] == 1) {
            eval("\$scenetracker_index_reminder_bit .=\"" . $templates->get("scenetracker_index_reminder_bit") . "\";");
          }
        }
      }
    }
    if ($cnt == 1) {
      eval("\$scenetracker_index_reminder =\"" . $templates->get("scenetracker_index_reminder") . "\";");
    }
  }
}
if ($mybb->input['action'] == 'reminder') {
  $sid = intval($mybb->input['sid']);
  $uid = intval($mybb->input['uid']);
  if (check_switcher($uid)) {
    $db->query("UPDATE " . TABLE_PREFIX . "scenetracker SET index_reminder = 0 WHERE id = " . $sid . " ");
  }
  redirect('index.php');
  // }
}

/***
 * //TODO Ingamcalendar
 * shows minicalender on index
 */
function scenetracker_calendar()
{
}
/***
 * //TODO
 * shows a list of user on blacklist and generates bbcode
 */
function scenetracker_blacklist()
{
}

/**
 * //TODO what happens when we delete a user
 */

/**
 * //TODO what happens when we delete a post
 */


/**************************** */
/*** HELPERS ***/

/**************************** */



/**
 * get all attached account to a given user
 * @param $user which is online, flag if main charakter or not
 * @return array true/false
 * */
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


/**
 * Check if an uid belongs to the user which is online (switcher)
 * @param $uid uid to check
 * @return boolean true/false
 * */
function check_switcher($uid)
{
  global $mybb;

  if ($mybb->user['uid'] != 0) {
    $chars = get_accounts($mybb->user['uid'], $mybb->user['as_uid']);
    return array_key_exists($uid, $chars);
  } else return false;
}

/**
 * Check if an fid belong to ingame/archiv
 * @param $fid to check
 * @return boolean true/false
 * */
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


/*************
 * Helper to get Scenes
 **************/
function get_scenes($charas, $tplstring)
{
  global $db, $mybb, $templates, $users_options_bit, $scenetracker_ucp_bit_chara_new, $scenetracker_ucp_bit_chara_old, $scenetracker_ucp_bit_chara_closed, $scenetracker_ucp_bit_scene, $scenetracker_index_bit_chara, $scenetracker_index_bit_scene;
  //fürs select feld, alle usernamen suchen 
  $solvplugin = $mybb->settings['scenetracker_solved'];

  $users_options_bit = "<option value=\"0\">immer benachrichtigen</option>
  <option value=\"-1\">nie benachrichtigen</option>";
  $all_users = array();
  $get_users = $db->query("SELECT username, uid FROM " . TABLE_PREFIX . "users ORDER by username");
  while ($users_select = $db->fetch_array($get_users)) {
    $getuid =  $users_select['uid'];
    $all_users[$getuid] = $users_select['username'];
    $users_options_bit .= "<option value=\"{$users_select['uid']}\">{$users_select['username']}</option>";
  }
  $all = $users_options_bit;
  if ($solvplugin == 1) {
    $solvefield = " threadsolved,";
    if ($tplstring == "closed") {
      $solved = " OR threadsolved=1";
    } else {
      $solved = " OR threadsolved=0";
    }
  }
  foreach ($charas as $uid => $charname) {
    if ($tplstring == "new" or $tplstring == "index") {

      $query =  " AND (closed = 0 " . $solved . ") AND ((lastposteruid != $uid and type ='always') OR (type = 'certain'))";
    } elseif ($tplstring == "old") {
      $query =  " AND (closed = 0 " . $solved . ") AND ((lastposteruid = $uid and type = 'always') OR (lastposteruid != inform_by and type = 'certain'))";
      // $query =  " AND (closed = 0 OR threadsolved=0) AND lastposteruid != $uid";
    } elseif ($tplstring == "closed") {

      $query =  " AND (closed = 1 " . $solved . ") ";
      // $query =  " AND (closed = 0 OR threadsolved=0) AND lastposteruid != $uid";
    }
    $charaname = build_profile_link($charname, $uid);

    $scenes = $db->write_query("
              SELECT s.*,
              fid,subject,dateline,lastpost,lastposter,lastposteruid, closed, " . $solvefield . " 
              scenetracker_date, scenetracker_user,scenetracker_place 
              FROM " . TABLE_PREFIX . "scenetracker  s LEFT JOIN mybb_threads t on s.tid = t.tid WHERE s.uid = {$uid} " . $query);
    $scenetracker_ucp_bit_scene = "";
    $scenetracker_index_bit_scene = "";
    $tplcount = 1;

    if ($db->num_rows($scenes) == 0) {

      $tplcount = 0;
    } else {
      $tplcount = 1;
      $info_by = "";
      $selected = "";
      $users_options_bit = "";
      while ($data = $db->fetch_array($scenes)) {
        $edit = "";
        $alert = "[alert]";

        $user = get_user($uid);
        $tid = $data['tid'];
        $info_by = $data['inform_by'];

        $id = $data['id'];
        $username = build_profile_link($user['username'], $uid);

        $lastpostdate = date('d.m.Y', $data['lastpost']);
        $lastposter = get_user($data['lastposteruid']);
        $alerttype = $data['type'];
        $scenedate = date('d.m.Y H:i', strtotime($data['scenetracker_date']));
        $lastposterlink = '<a href="member.php?action=profile&uid=' . $lastposter['uid'] . '">' .  $lastposter['username'] . '</a>';
        $users = $sceneusers = str_replace(",", ", ", $data['scenetracker_user']);
        $sceneplace = $data['scenetracker_place'];
        if ($alerttype == 'certain') {
          $info = get_user($data['inform_by']);
          $alertclass = "certain";
          $username = build_profile_link($info['username'], $data['inform_by']);
          $alerttype =  $username;
        } else if ($alerttype == 'always') {
          $alerttype = "immer";
          $alertclass = "always";
        } else if ($alerttype == 'never') {
          $alerttype = "nie";
          $alertclass = "never";
        }
        $scene = '<a href="showthread.php?tid=' . $data['tid'] . '&action=lastpost">' . $data['subject'] . '</a>';
        if ($data['profil_view'] == 1) {
          $hide = "wird angezeigt (Profil) <a href=\"usercp.php?action=scenetracker&showsceneprofil=0&getsid=" . $id . "\"><i class=\"fas fa-toggle-on\"></i></a>";
        } else {
          $hide = "wird versteckt <a href=\"usercp.php?action=scenetracker&showsceneprofil=1&getsid=" . $id . "\"><i class=\"fas fa-toggle-off\"></i></a></a>";
        }
        if ($data['closed'] == 1) {
          if ($tplstring != "index") {
            $close = "ist geschlossen <a href=\"usercp.php?action=scenetracker&closed=0&getsid=" . $id . "&gettid=" . $tid . "&getuid=" . $uid . "\"><i class=\"fas fa-unlock\"></i></a>";
          } else {
            $close = "<a href=\"usercp.php?action=scenetracker&closed=0&getsid=" . $id . "&gettid=" . $tid . "&getuid=" . $uid . "\"><i class=\"fas fa-unlock\"></i></a>";
          }
        } else {
          if ($tplstring != "index") {
            $close = "ist offen <a href=\"usercp.php?action=scenetracker&closed=1&getsid=" . $id . "&gettid=" . $tid . "&getuid=" . $uid . "\"><i class=\"fas fa-lock\"></i></a>";
          } else {
            $close = "<a href=\"usercp.php?action=scenetracker&closed=1&getsid=" . $id . "&gettid=" . $tid . "&getuid=" . $uid . "\"><i class=\"fas fa-lock\"></i></a>";
          }
        }

        if ($data['type'] == 'certain' && $info_by != 0) {
          $users_options_bit = "<option value=\"0\">immer benachrichtigen</option>
                                <option value=\"-1\">nie benachrichtigen</option>";
          foreach ($all_users as $uid => $username) {
            if ($info_by == $uid) {
              $selected = "selected";
            } else {
              $selected = "";
            }
            $users_options_bit .= "<option value=\"{$uid}\" $selected>{$username}</option>";
          }
        }
        if ($data['type'] == 'always' || $data['type'] == 'never') {
          $users_options_bit = $all;
        }

        eval("\$certain =\"" . $templates->get("scenetracker_popup") . "\";");
        if ($tplstring == "index") {
          eval("\$scenetracker_index_bit_scene .= \"" . $templates->get('scenetracker_index_bit_scene') . "\";");
        } else {
          eval("\$scenetracker_ucp_bit_scene .= \"" . $templates->get('scenetracker_ucp_bit_scene') . "\";");
        }
      }
    }

    if ($tplcount == 1) {
      if ($tplstring == "index") {
        eval("\$scenetracker_index_bit_chara .=\"" . $templates->get("scenetracker_index_bit_chara") . "\";");
      } else {
        eval("\$scenetracker_ucp_bit_chara_{$tplstring} .=\"" . $templates->get("scenetracker_ucp_bit_chara") . "\";");
      }
    }
  }
}
/*********
 * Change alert type for scenes/posts (always/never/certain user)
 ****** */
function scene_inform_status($id, $certained)
{
  global $db;

  if ($certained == 0) {
    //always
    $db->query("UPDATE " . TABLE_PREFIX . "scenetracker SET inform_by = '0', type='always' WHERE id = " . $id . " ");
  } else if ($certained == -1) {
    //never
    $db->query("UPDATE " . TABLE_PREFIX . "scenetracker SET inform_by = '0', type='never' WHERE id = " . $id . " ");
  } else {
    $db->query("UPDATE " . TABLE_PREFIX . "scenetracker SET inform_by = " . $certained . ", type='certain' WHERE  id = " . $id . " ");
  }
}

/***
 * allow to change status
 * -> gets list of usernames
 * returns true if allowed, falls if not
 */
function change_allowed($str_teilnehmer)
{
  global $mybb, $db;
  $chars = get_accounts($mybb->user['uid'], $mybb->user['as_uid']);
  foreach ($chars as $uid => $username) {
    $pos = stripos($str_teilnehmer, $username);
    if ($pos !== false) {
      return true;
    }
  }

  return false;
}

/**********
 * Change status from scene (open/close)
 ************/
function scene_change_status($close, $tid, $uid)
{
  global $db, $mybb;
  $solvplugin = $mybb->settings['scenetracker_solved'];
  // change_allowed($thread['scenetracker_user'])
  $teilnehmer = $db->simple_select("threads", "scenetracker_user", "tid={$tid}");

  if ($close == 1) {
    $db->query("UPDATE " . TABLE_PREFIX . "threads SET closed = '1' WHERE tid = " . $tid . " ");
    //TODO check switcher not working 
    //prüft ob übergebene id zu dem chara gehört der online ist 
    //-> gesamte teilnehmerliste müsste durchgegangen werden
    if (change_allowed($teilnehmer)) {
      if ($solvplugin == "1") {
        $db->query("UPDATE " . TABLE_PREFIX . "threads SET threadsolved = '1' WHERE tid = " . $tid . " ");
      }
    }
  } elseif ($close == 0) {
    if (change_allowed($teilnehmer)) {
      $db->query("UPDATE " . TABLE_PREFIX . "threads SET closed = '0' WHERE tid = " . $tid . " ");
      if ($solvplugin == "1") {
        $db->query("UPDATE " . TABLE_PREFIX . "threads SET threadsolved = '0' WHERE tid = " . $tid . " ");
      }
    }
  }
}

/**********
 * Change View of Scene (show in profil or not)
 ************/
function scene_change_view($hide, $id, $uid)
{
  global $db, $mybb;
  //security check, is this user allowes to change entry?
  if (check_switcher($uid)) {
    $db->query("UPDATE " . TABLE_PREFIX . "scenetracker SET profil_view = '" . $hide . "' WHERE id = " . $id . " ");
  }
}

/**************************** 
 * 
 *  My Alert Integration
 * 
 * *************************** */
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
     * Build the output string for listing page and the popup.
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
