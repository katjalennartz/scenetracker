<?php

/**
 * Szenentracker - by risuena
 * https://github.com/katjalennartz
 * Datum einfügen für Szenen 
 * Teilnehmer einfügen für Szenen
 * automatische Anzeige im Profil der Szenen
 * Anzeige auf der Startseite, auf Wunsch von User
 *  - immer
 *  - wenn dran
 *  - wenn bestimmte User gepostet hat
 * Benachrichtung ( alert )
 *  - immer bei Antwort
 *  - bei Antwort von bestimmten User
 *  - keine Benachrichtigung
 * Postingerinnerung (kann vom Admin aktiviert werden)
 *  - wenn man Postingpartner länger als x Tage warten gelassen hat
 * 
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


  // Einfügen der Trackerfelder in die Threadtabelle
  $db->add_column("threads", "scenetracker_date", "datetime NOT NULL");
  $db->add_column("threads", "scenetracker_place", "varchar(200) NOT NULL");
  $db->add_column("threads", "scenetracker_user", "varchar(1500) NOT NULL");
  $db->add_column("threads", "scenetracker_trigger", "varchar(200) NOT NULL");


  // Einfügen der Trackeroptionen in die user tabelle
  $db->query("ALTER TABLE `" . TABLE_PREFIX . "users` ADD `tracker_index` INT(1) NOT NULL DEFAULT '1', ADD `tracker_indexall` INT(1) NOT NULL DEFAULT '1', ADD `tracker_reminder` INT(1) NOT NULL DEFAULT '1';");
  // tracker_indexall
  //Neue Tabelle um Szenen zu speichern und informationen, wie die benachrichtigungen sein sollen.
  $db->write_query("CREATE TABLE `" . TABLE_PREFIX . "scenetracker` (
        `id` int(10) NOT NULL AUTO_INCREMENT,
        `uid` int(10) NOT NULL,
        `tid` int(10) NOT NULL,
        `alert` int(1) NOT NULL DEFAULT 0,
        `type` varchar(50) NOT NULL DEFAULT 'always',
        `inform_by` int(10) NOT NULL DEFAULT 1,
        `index_view` int(1) NOT NULL DEFAULT 1,
        `profil_view` int(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`)
    ) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;");

  // Admin Einstellungen
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
      'value' => '7', // Default
      'disporder' => 4
    ),
    'scenetracker_archiv' => array(
      'title' => 'Archiv',
      'description' => 'ID des Archivs',
      'optionscode' => 'text',
      'value' => '0', // Default
      'disporder' => 5
    ),
    // 'scenetracker_profil_sort' => array( //TODO
    //   'title' => 'Profilanzeige',
    //   'description' => 'Sollen Szenen im Profil des Charakters nach Ingame und Archiv sortiert werden?',
    //   'optionscode' => 'yesno',
    //   'value' => '0', // Default
    //   'disporder' => 6
    // ),
    'scenetracker_reminder' => array(
      'title' => 'Erinnerung',
      'description' => 'Sollen Charaktere auf dem Index darauf aufmerksam gemacht werden, wenn sie jemanden in einer Szene länger als X Tage warten lassen? 0 wenn nicht',
      'optionscode' => 'text',
      'value' => '0', // Default
      'disporder' => 7
    ),
    'scenetracker_ingametime' => array(
      'title' => 'Ingame Zeitraum',
      'description' => 'Bitte Ingamezeitraum eingeben - Monat und Jahr. Bei mehreren mit , getrennt. Z.b für April, Juni und Juli "1997-04, 1997-06, 1997-07. <b>Achtung genauso wie im Beispiel!</b> (Wichtig für Minicalender)."',
      'optionscode' => 'text',
      'value' => '2022-04, 2022-06, 2022-07', // Default
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

  global $db, $mybb;
  // Datenbankänderungen Einträge löschen
  if ($db->table_exists("scenetracker")) {
    $db->drop_table("scenetracker");
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
  if ($db->field_exists("scenetracker_trigger", "threads")) {
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "threads DROP scenetracker_trigger");
  }
  if ($db->field_exists("tracker_index", "users")) {
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "users DROP tracker_index");
  }
  if ($db->field_exists("tracker_indexall", "users")) {
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "users DROP tracker_indexall");
  }
  if ($db->field_exists("tracker_reminder", "users")) {
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "users DROP tracker_reminder");
  }

  // Templates löschen
  $db->delete_query("templates", "title LIKE 'scenetracker%'");
  $db->delete_query("templategroups", "prefix = 'scenetracker'");

  // CSS löschen
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
  // add Alerts
  include MYBB_ROOT . "/inc/adminfunctions_templates.php";

  // Variablen einfügen
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

  // Alerts hinzufügen
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
  // Variablen löschen
  find_replace_templatesets("newreply", "#" . preg_quote('{$scenetrackerreply}{$scenetrackeredit}') . "#i", '');
  find_replace_templatesets("editpost", "#" . preg_quote('{$scenetrackeredit}') . "#i", '');
  find_replace_templatesets("newthread", "#" . preg_quote('{$scenetrackeredit}') . "#i", '');
  find_replace_templatesets("newthread", "#" . preg_quote('{$scenetracker_newthread}') . "#i", '');
  find_replace_templatesets("showthread", "#" . preg_quote('{$scenetracker_showthread}') . "#i", '');
  find_replace_templatesets("forumdisplay_thread", "#" . preg_quote('{$scenetrackerforumdisplay}') . "#i", '');
  find_replace_templatesets("index", "#" . preg_quote('{$scenetracker_index_reminder}') . "#i", '');
  find_replace_templatesets("index", "#" . preg_quote('{$scenetracker_index_main}') . "#i", '');
  find_replace_templatesets("member_profile", "#" . preg_quote('</tr><tr><td colspan="2">{$scenetracker_profil}</td>') . "#i", '');
  find_replace_templatesets("usercp_nav_misc", "#" . preg_quote('<tr><td class="trow1 smalltext"><a href="usercp.php?action=scenetracker">Szenentracker</a></td></tr>') . "#i", '');

  // Alerts deaktivieren
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
    <div class="scenetracker_forumdisplay scene_date icon"><i class="fas fa-calendar"></i> Szenendatum: {$scene_date}</div>
    <div class="scenetracker_forumdisplay scene_place icon"><i class="fas fa-map-marker-alt"></i> Szenenort: {$scene_place}</div>
    {$scenetrigger}
    <div class="scenetracker_forumdisplay scene_users icon"><i class="fas fa-users"></i> Szenenteilnehmer: {$scenetracker_forumdisplay_user}</div>	
    <div class="scenetracker_forumdisplay scene_autor"><i class="fas fa-pen-circle"></i> Autor: {$author}</div>
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
    <div class="scenetracker_index character_item name "><h1>{$charaname} {$cnt_chara}</h1> </div>
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
    <div class="sceneindex__sceneitem scene_title icon"><i class="fas fa-folder-open"></i> {$scene} 
        <span class="scene_status"> - {$close} {$certain} </span> 
    </div>
      <div class="sceneindex__sceneitem scene_last ">
        <span class="scene_last icon"><i class="fas fa-arrow-right"></i> Letzter Post: {$lastposterlink} am {$lastpostdate}</span>
      </div>
  
    <div class="sceneindex__sceneitem scene_date scene_place">
      <span class="scene_date icon"><i class="fas fa-calendar"></i> {$scenedate} </span>
      <span class="scene_place icon"><i class="fas fa-map-marker-alt"></i> {$sceneplace} </span>
      <span class="scene_users icon "><i class="fas fa-users"></i> {$users}</span>
    </div>
    
  </div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );
  $template[4] = array(
    "title" => 'scenetracker_index_main',
    "template" => '<div class="scenetracker_index wrapper_container"><strong>SZENENVERWALTUNG {$counter}</strong>
    {$scenetracker_index_bit_chara}
  </div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[5] = array(
    "title" => 'scenetracker_index_reminder',
    "template" => '<div class="scenetracker_reminder box"><div class="scenetracker_reminder_wrapper"><span class="senetracker_reminder text">Du lässt deinen Postpartner in folgenden Szenen warten:</span>
    <div class="scenetracker_reminder container">
  {$scenetracker_index_reminder_bit}
    </div>
	<span class="senetracker_reminder text"><a href="index.php?action=reminder">[ignore all]</a></span>
	</div></div>',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[6] = array(
    "title" => 'scenetracker_index_reminder_bit',
    "template" => '<div class="scenetracker_reminder item">
    {$userarr[\\\'username\\\']} - <a href="showthread.php?tid={$scenes[\\\'tid\\\']}&action=lastpost">{$scenes[\\\'subject\\\']}</a> 
    ({$lastpostdays} Tage)
    </div>',
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
    </tr>
  <tr>
    <td class="trow2" width="20%"><strong>Triggerwarnung:</strong></td>
    <td class="trow2">
      <div class="con">
        <div class="con-item">
          <input type="text" id="scenetracker_trigger" name="scenetracker_trigger" size="40" value="{$scenetracker_trigger}" />
        </div>
        <div class="con-item">
          Gibt es eine Triggerwarnung für die Szene? Wenn ja dann mit aussagekräftigem Begriff(en) füllen.
        </div>
      </div>
    </td>
    </tr>
<tr>
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
      <div class="scenetracker__sceneitem scene_title icon"><i class="fas fa-folder-open"></i> <a href="showthread.php?tid={$tid}">{$subject}</a> {$scenestatus}{$scenehide}</div>
	  <div class="scenetracker__sceneitem scene_date icon "><i class="fas fa-calendar"></i> {$scenedate}</div>
      <div class="scenetracker__sceneitem scene_place icon "><i class="fas fa-map-marker-alt"></i> {$sceneplace}</div>
		{$scenetrigger}
	  <div class="scenetracker__sceneitem scene_break"></div>
      <div class="scenetracker__sceneitem scene_users icon "><i class="fas fa-users"></i> {$sceneusers}</div>

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
    <div class="scenetracker__sceneitem scene_date icon"><i class="fas fa-calendar"></i> {$scene_date}</div>
    <div class="scenetracker__sceneitem scene_place icon "><i class="fas fa-map-marker-alt"></i> {$sceneplace}</div>
    <div class="scenetracker__sceneitem scene_status icon"><i class="fas fa-circle-play"></i> {$scenestatus}</div>
	{$scenetrigger}
    <div class="scenetracker__sceneitem scene_users icon"><i class="fas fa-users"></i>{$scenetracker_showthread_user}</div> 
  	{$edit}
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
    <div class="sceneucp__sceneitem scene_title icon"><i class="fas fa-folder-open"></i> {$scene}</div>
    <div class="sceneucp__sceneitem scene_status icon"><i class="fas fa-circle-play"></i> scene {$close}
    </div>
    <div class="sceneucp__sceneitem scene_profil icon"><i class="fas fa-circle-user"></i> scene {$hide}</div>
    <div class="sceneucp__sceneitem scene_alert icon {$alertclass}"><i class="fas fa-bullhorn"></i>
      <span class="sceneucp__scenealerts">{$alerttype} {$certain}  {$always}</span>
    </div>
  
    <div class="sceneucp__sceneitem scene_date icon"><i class="fas fa-calendar"></i> {$scenedate}</div>
	 <div class="sceneucp__sceneitem scene_users icon "><i class="fas fa-users"></i>{$users}</div>
    <div class="sceneucp__sceneitem scene_place icon"><i class="fas fa-map-marker-alt"></i> {$sceneplace}</div>
    <div class="sceneucp__sceneitem scene_last icon ">last: {$lastposterlink} ({$lastpostdate})</div>
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
      
    </head>
    <body>
    {$header}
	<div class="ucp--overview nav-notucp">
	<div class="ucp-nav">
	{$usercpnav}
	</div>
	</div>
    <table width="100%" border="0" align="center"  class="tborder borderboxstyle">
    <tr>
    <td valign="top">
    <div class="scene_ucp container">
    <div class="scene_ucp manage alert_item">
      <h1><i class="fas fa-book-open" aria-hidden="true"></i> Szenentracker</h1>
      <p>Hier kannst du alles rund um den Szenentracker anschauen und verwalten. Die Einstellungen für die Alerts
    kannst du <a href="alerts.php?action=settings">hier</a> vornehmen. Stelle hier erst einmal allgemein ein,
    ob du die Szenen auf dem Index angezeigt werden möchtest und ob du eine Meldung haben möchtest, wenn du in
    einer Szene länger als 6 Wochen dran bist.
      </p>
      
    <div class="scene_ucp scenefilteroptions">
		<h2>Benachrichtigungseinstellungen</h2>
       <div class="scenefilteroptions__items">
        <form action="usercp.php?action=scenetracker" method="post">
        <fieldset><label for="index">Szenenübersicht auf der Indexseite?</label><br/>
        <input type="radio" name="index" id="index_yes" value="1" {$yes_ind}> <label for="index_yes">Ja</label>
        <input type="radio" name="index" id="index_no" value="0" {$no_ind}> <label for="index_no">Nein</label><br />
        <input type="submit" name="opt_index" value="speichern" id="index_button" />
        </fieldset>
        </form>
      </div>
		<div class="scenefilteroptions__items">
        <form action="usercp.php?action=scenetracker" method="post">
        <fieldset><label for="index_yesall">Szenen aller Charaktere auf dem Index anzeigen?</label><br/>
        <input type="radio" name="indexall" id="index_yesall" value="1" {$yes_indall}> <label for="index_yesall">Ja</label>
        <input type="radio" name="indexall" id="index_noall" value="0" {$no_indall}> <label for="index_noall">Nein</label><br />
			<span style="font-size: 0.8em">(Einstellung für den jeweils eingeloggten Charakter)</span><br />
        <input type="submit" name="opt_indexall" value="speichern" id="indexall_button" />
        </fieldset>
        </form>
      </div>
       <div class="scenefilteroptions__items">
        <form action="usercp.php?action=scenetracker" method="post">
        <fieldset><label for="reminder">Szenenerinnerung nach 6 Wochen?</label><br/>
        <input type="radio" name="reminder" id="reminder_yes" value="1" {$yes_rem}> <label for="index_rem">Ja</label>
        <input type="radio" name="reminder" id="reminder_no" value="0" {$no_rem}> <label for="index_rem">Nein</label><br />
        <input type="submit" name="opt_reminder" value="speichern" id="reminder_button" />
        </fieldset>
      </form>
      </div>

    </div>
    </div><!--scene_ucp manage alert_item-->
	<form action="usercp.php?action=scenetracker" method="post">
	<div class="scene_ucp scenefilteroptions">
		<h2>Filteroptions</h2>
		<div class="scenefilteroptions__items">
			<label for="charakter">Szenen anzeigen von: </label>{$selectchara}
			<input type="hidden" value="{$thisuser}" name="uid" id="uid"/>
		</div>	
		<div class="scenefilteroptions__items">
			<label for="status">Status der Szene:  </label>
			<select name="status" id="status">
				<option value="both" {$sel_s[\\\'both\\\']}>beides</option>
    			<option value="open" {$sel_s[\\\'open\\\']} >offen</option>
				<option value="closed" {$sel_s[\\\'closed\\\']}>geschlossen</option>
			</select>
		</div>
		<div class="scenefilteroptions__items">
				<label for="move">Du bist dran: </label>
				<select name="move" id="move">
				<option value="beides" {$sel_m[\\\'beides\\\']}>beides</option>
    			<option value="ja" {$sel_m[\\\'ja\\\']}>ja</option>
				<option value="nein" {$sel_m[\\\'nein\\\']}>nein</option>
				
			</select>
		</div>
		<div class="scenefilteroptions__items button">
			<input type="submit" name="scenefilter" value="Szenen filtern" id="scenefilter" />
		</div>
	</div>
		</form>
    <div class="scene_ucp manage overview_item overview_con">
      <div class="scene_ucp overview_item">
      <h2>{$scenes_title}</h2>
        <div class="scene_ucp overview_chara_con">
			
	
        {$scenetracker_ucp_bit_chara} 
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

  $template[20] = array(
    "title" => 'scenetracker_calendar',
    "template" => '
    <div class="calendar">
    <div class="month-indicator">
      <div> {$kal_title}</div>
    </div>
    <div class="day-of-week" style="grid-template-columns: repeat(7, 1fr);">
      <div>M</div>
      <div>T</div>
      <div>W</div>
      <div>T</div>
      <div>F</div>
      <div>S</div>
      <div>S</div>
    </div>
	 {$kal_day}
  </div>
   ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );



  foreach ($template as $row) {
    $db->insert_query("templates", $row);
  }

  $css = array(
    'name' => 'scenetracker.css',
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
      grid-template-columns: 1fr 1fr 1fr;
  }
  
  .scenetracker__sceneitem.scene_users {
      grid-column: 1 / -2;
      grid-row: 2;
  }
  
  .scenetracker__sceneitem.scene_title {
      grid-column: 1 / 2;
      grid-row: 2;
  }
  
  .scenetracker__sceneitem.scene_status {

  }
  
  .scenetracker__sceneitem.scene_date {

  }
  .scenetracker__sceneitem.scene_hide {
      grid-row: 2;
      grid-column: -1;
  }
  
  
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
  .scenetracker_reminder.box {
    margin-bottom: 20px;
  }
  
  .scenetracker_reminder.container {
      max-height: 100px;
      overflow: auto;
      padding-left: 30px;
  }
  
  .scenetracker_reminder.item:before {
    content: "» ";
  }
  
  span.senetracker_reminder.text {
      text-align: center;
      display: block;
  }
    ',
    'cachefile' => $db->escape_string(str_replace('/', '', 'scenetracker.css')),
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

  if (scenetracker_testParentFid($fid)) {
    if ($mybb->input['previewpost'] || $post_errors) {
      $scenetracker_date = $mybb->input['scenetracker_date'];
      $scenetracker_time = $mybb->input['scenetracker_time'];
      $scenetracker_user = $mybb->input['teilnehmer'];
      $scenetracker_trigger = $mybb->input['scenetracker_trigger'];
      $scenetracker_place = $mybb->input['place'];
    } else {
      $scenetracker_date = "2020-04-01";
      $scenetracker_time = "12:00";
      $scenetracker_user = $mybb->user['username'] . ",";
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
  if (scenetracker_testParentFid($fid)) {
    $thisuser = intval($mybb->user['uid']);
    $alertsetting_alert = $mybb->settings['scenetracker_alert_alerts'];


    $usersettingIndex = intval($mybb->user['tracker_index']);
    $array_users = array();
    $date = $db->escape_string($mybb->input['scenetracker_date']) . " " . $db->escape_string($mybb->input['scenetracker_time']);
    $scenetracker_place = $db->escape_string($mybb->input['place']);
    $teilnehmer = $db->escape_string($mybb->input['teilnehmer']);
    $trigger = $db->escape_string($mybb->input['scenetracker_trigger']);

    $array_users = scenetracker_getUids($teilnehmer);

    $save = array(
      "scenetracker_date" => $date,
      "scenetracker_user" => $teilnehmer,
      "scenetracker_place" => $scenetracker_place,
      "scenetracker_trigger" => $trigger
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
        if ($alertsetting_alert == 1) {

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
  if (scenetracker_testParentFid($fid)) {
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
  $array_users = scenetracker_getUids($teilnehmer);
  $username = $db->escape_string($mybb->user['username']);

  if (scenetracker_testParentFid($fid)) {
    // füge den charakter, der gerade antwortet hinzu wenn gewollt und noch nicht in der Szene eingetragen
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
      // Alle teilnehmer bekommen
      if ($uid != $username) {
        $type = $db->fetch_array($db->write_query("SELECT type, inform_by FROM " . TABLE_PREFIX . "scenetracker WHERE tid = $tid AND uid = $uid"));
        // Je nach Benachrichtigungswunsch alerts losschicken
        if ($type['type'] == "always") {
          if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('scenetracker_newAnswer');
            //Not null, the user wants an alert and the user is not on his own page.
            if ($alertType != NULL && $alertType->getEnabled() && $thisuser != $uid) {
              //constructor for MyAlert gets first argument, $user (not sure), second: type  and third the objectId 
              $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$uid, $alertType);
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
          $update = array(
            "alert" => 1,
          );
          $db->update_query("scenetracker", $update, "tid = {$tid} AND uid = {$uid}");

          if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('scenetracker_newAnswer');
            //Not null, the user wants an alert and the user is not on his own page.
            if ($alertType != NULL && $alertType->getEnabled() && $thisuser != $uid) {
              //constructor for MyAlert gets first argument, $user (not sure), second: type  and third the objectId 
              $alert = new MybbStuff_MyAlerts_Entity_Alert((int)$uid, $alertType);
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
        } elseif ($uid == $thisuser) {
          // echo " uid == this user {$type['inform_by']} == $thisuser <br><br>";

          $update = array(
            "alert" => 0,
          );
          $db->update_query("scenetracker", $update, "tid = {$tid} AND uid = {$uid}");
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
  global $thread, $templates, $db, $lang, $mybb, $templates, $fid, $post_errors, $post, $scenetrackeredit, $postinfo;
  if (scenetracker_testParentFid($fid)) {
    if ($thread['firstpost'] == $mybb->input['pid']) {

      $date = explode(" ", $thread['scenetracker_date']);
      if ($mybb->input['previewpost'] || $post_errors) {
        $scenetracker_date = $mybb->input['scenetracker_date'];
        $scenetracker_time = $mybb->input['scenetracker_time'];
        $scenetracker_user = $mybb->input['teilnehmer'];
        $scenetracker_place = $mybb->input['place'];
        $scenetracker_trigger = $mybb->input['scenetracker_trigger'];
      } else {
        $scenetracker_date = $date[0];
        $scenetracker_time = $date[1];
        if ($thread['scenetracker_user'] == "") {
          $scenetracker_user = "";
        } else {
          $scenetracker_user = $thread['scenetracker_user'] . " , ";
        }
        $scenetracker_place = $thread['scenetracker_place'];
        $scenetracker_trigger = $thread['scenetracker_trigger'];
      }
      $teilnehmer_alt =  array_map('trim', explode(",", $thread['scenetracker_user']));
      eval("\$scenetrackeredit = \"" . $templates->get("scenetracker_newthread") . "\";");
    } else { //we're answering to a post.

      $teilnehmer = $thread['scenetracker_user'];
      // var_dump($postinfo);

      if ($mybb->input['previewpost']) {
        $thisuser = $postinfo['username'];
      } else {
        $thisuser = $post['username'];
      }

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
  if (scenetracker_testParentFid($fid)) {

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
      $trigger = $db->escape_string($mybb->input['scenetracker_trigger']);
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
            //  var_dump($new_userfield);
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

        if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
          $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('scenetracker_newScene');
          if ($alertType != NULL && $alertType->getEnabled() && $uid != $mybb->user['uid']) {
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
      //Build the new String for users and save it
      $to_save_str = implode(",", $new_userfield);
      $save = array(
        "scenetracker_date" => $date,
        "scenetracker_place" => $place,
        "scenetracker_user" =>  $to_save_str,
        "scenetracker_trigger" =>  $trigger

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

  if (scenetracker_testParentFid($fid)) {
    $scene_date = date('d.m.Y - H:i', strtotime($thread['scenetracker_date']));

    $userArray = scenetracker_getUids($thread['scenetracker_user']);
    $scene_place = $thread['scenetracker_place'];

    $author = build_profile_link($thread['username'], $thread['uid']);
    $scenetracker_forumdisplay_user = "";
    if ($thread['scenetracker_trigger'] != "") {
      $scenetrigger = "<div class=\"scenetracker_forumdisplay scene_trigger icon\"><i class=\"fas fa-circle-exclamation\"></i> Triggerwarnung: {$thread['scenetracker_trigger']}</div>";
    } else {
      $scenetrigger = "";
    }
    foreach ($userArray as $uid => $username) {
      if ($uid != $username) {
        $user = build_profile_link($username, $uid);
      } else {
        $user = $username;
      }
      eval("\$scenetracker_forumdisplay_user.= \"" . $templates->get("scenetracker_forumdisplay_user") . "\";");
    }

    eval("\$scenetrackerforumdisplay = \"" . $templates->get("scenetracker_forumdisplay_infos") . "\";");
  }
}

/*Anzeige in suchergebnissen */
$plugins->add_hook("search_results_thread", "scenetracker_search_showtrackerstuff");
function scenetracker_search_showtrackerstuff()
{
  global $thread, $templates, $db, $fid, $sceneinfos;
  if (scenetracker_testParentFid($thread['fid'])) {
    $scene_date = date('d.m.Y - H:i', strtotime($thread['scenetracker_date']));
    $scene_place = $thread['scenetracker_place'];
    $userArray = scenetracker_getUids($thread['scenetracker_user']);

    $author = build_profile_link($thread['username'], $thread['uid']);
    $scenetracker_forumdisplay_user = "";
    if ($thread['scenetracker_trigger'] != "") {
      $scenetrigger = "<span style=\"color: var(--alert-color);\"><i class=\"fas fa-circle-exclamation\"></i>Triggerwarnung: {$thread['scenetracker_trigger']}</span><br/>";
    } else {
      $scenetrigger = "";
    }
    $user = "<div class=\"usernamesearch_con\">";
    foreach ($userArray as $uid => $username) {
      if ($uid != $username) {
        $user .= "<span class=\"usernamesearch scenetracker_user\">" . build_profile_link($username, $uid) . "</span>";
      } else {
        $user .= "<span class=\"usernamesearch scenetracker_user\">" . $username . "</span>";
      }
    }
    $user .= "</div>";
    $sceneinfos =  "<i class=\"fas fa-calendar\"></i>{$scene_date} 
    <i class=\"fas fa-map-marker-alt\"></i>{$thread['scenetracker_place']}<br/> 
    {$scenetrigger}
    <i class=\"fas fa-users\"></i>{$user} <br/> ";
  } else {
    $sceneinfos = "";
  }
}

/*********************
 * Anzeige von Datum, Ort und Teilnehmer im showthread
 *********************/
$plugins->add_hook("showthread_end", "scenetracker_showthread_showtrackerstuff");
function scenetracker_showthread_showtrackerstuff()
{
  global $thread, $templates, $db, $fid, $tid, $mybb, $scenetracker_showthread;


  if (scenetracker_testParentFid($fid)) {
    $allowclosing = false;
    $thisuser = intval($mybb->user['uid']);
    $scene_date = date('d.m.Y - H:i', strtotime($thread['scenetracker_date']));
    $scenetracker_date = date('Y-m-d', strtotime($thread['scenetracker_date']));
    $scenetracker_time = date('H:i', strtotime($thread['scenetracker_date']));
    $sceneplace = $thread['scenetracker_place'];
    $scenetriggerinput = $thread['scenetracker_trigger'];
    $scenetracker_user = $thread['scenetracker_user'];

    //all users of scene
    $userArray = scenetracker_getUids($thread['scenetracker_user']);
    $finish = "<button >close scene</button>";
    foreach ($userArray as $uid => $username) {
      if ($uid != $username) {
        $user = build_profile_link($username, $uid);
        if ($mybb->usergroup['canmodcp'] == 1 || scenetracker_change_allowed($thread['scenetracker_user'])) {
          $allowclosing = true; //if he's a participant he is also allowed to close/open scene
          $delete = "<a href=\"showthread.php?tid=" . $tid . "&delete=" . $uid . "\">[x]</a>";
        }
      } else {
        $user = $username;
        $delete = "";
      }
      eval("\$scenetracker_showthread_user.= \"" . $templates->get("scenetracker_showthread_user") . "\";");
    }
    if ($thread['scenetracker_trigger'] != "") {
      $scenetrigger = "<div class=\"scenetracker__sceneitem scene_trigger icon\">Triggerwarnung: {$thread['scenetracker_trigger']}</div>";
    } else {
      $scenetrigger = "";
    }
    if ($allowclosing || $mybb->usergroup['canmodcp'] == 1) {
      if ($thread['threadsolved'] == 1) {
        $mark = "<a href=\"showthread.php?tid=" . $tid . "&scenestate=open\">[öffnen]</a></span>";
        $scenestatus = "<span class=\"scenestate\">Szene ist beendet. " . $mark;
      } else {
        $mark = "<a href=\"showthread.php?tid=" . $tid . "&scenestate=close\">[schließen]</a></span>";
        $scenestatus = "<span class=\"scenestate\">Szene ist offen. " . $mark;
      }
      $edit = "
      <div class=\"scenetracker__sceneitem scene_edit icon\">
      <a onclick=\"$('#sceneinfos{$tid}').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== 'undefined' ? modal_zindex : 9999) }); return false;\" style=\"cursor: pointer;\">[edit infos]</a>
          <div class=\"modal editscname\" id=\"sceneinfos{$tid}\" style=\"display: none; padding: 10px; margin: auto; text-align: center;\">
             <form action=\"\" id=\"formeditscene\" method=\"post\" >
              <input type=\"hidden\" value=\"{$tid}\" name=\"id\" id=\"id\"/>
                <center><input id=\"teilnehmer\" placeholder=\"Teilnehmer hinzufügen\" type=\"text\" value=\"\" size=\"40\"  name=\"teilnehmer\" autocomplete=\"off\" style=\"display: block;\" /></center>
                <div id=\"suggest\" style=\"display:hidden; z-index:10;\"></div>
                <input type=\"date\" id=\"scenetracker_date\" name=\"scenetracker_date\" value=\"{$scenetracker_date}\" /> 
                  <input type=\"time\" id=\"scenetracker_time\" name=\"scenetracker_time\" value=\"{$scenetracker_time}\" />
                  <input type=\"text\" name=\"scenetrigger\" id=\"scenetrigger\" placeholder=\"Triggerwarnung\" value=\"{$scenetriggerinput}\" />
                  <input type=\"text\" name=\"sceneplace\" id=\"sceneplace\" placeholder=\"Ort\" value=\"{$sceneplace}\" />
                  <button name=\"edit_sceneinfos\" id=\"edit_sceneinfos\">Submit</button>
            </form>
            <script src=\"./jscripts/scenetracker.js\"></script>
            <script type=\"text/javascript\" src=\"./jscripts/suggest.js\"></script>
        </div>

      </div>
 ";
    }

    eval("\$scenetracker_showthread = \"" . $templates->get("scenetracker_showthread") . "\";");
  }

  //delete a participant
  if ($mybb->input['delete']) {
    $uiddelete = intval($mybb->input['delete']);
    $userdelete = $db->fetch_field($db->simple_select("users", "username", "uid = $uiddelete"), "username");
    if ($mybb->usergroup['canmodcp'] == 1 || scenetracker_check_switcher($uid)) {
      $teilnehmer = str_replace($userdelete . ",", "", $thread['scenetracker_user']);
      $teilnehmer = str_replace("," . $userdelete, "", $teilnehmer);
      $teilnehmer = $db->escape_string($teilnehmer);
      $db->query("UPDATE " . TABLE_PREFIX . "threads SET scenetracker_user = '" . $teilnehmer . "' WHERE tid = " . $tid . " ");
      $db->delete_query("scenetracker", "tid = " . $tid . " AND uid = " . $uiddelete . "");

      redirect("showthread.php?tid=" . $tid);
    }
  }

  if ($mybb->input['scenestate'] == "open") {
    scenetracker_scene_change_status(0,  $tid,  $thisuser);
    redirect("showthread.php?tid=" . $tid);
  }
  if ($mybb->input['scenestate'] == "close") {
    scenetracker_scene_change_status(1,  $tid,  $thisuser);
    redirect("showthread.php?tid=" . $tid);
  }
}

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

  $thisuser = $mybb->user['uid'];
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
  $index_settingall = $db->fetch_field($db->simple_select("users", "tracker_indexall", "uid = " . $mybb->user['uid']), "tracker_indexall");
  if ($index_settingall == 1) {
    $yes_indall = "checked";
    $no_indall = "";
  } else if ($index_settingall == 0) {
    $yes_indall = "";
    $no_indall = "checked";
  }

  // scenetracker_ucp_bit_scene

  //welcher user ist online
  //get all charas of this user
  $charas = scenetracker_get_accounts($mybb->user['uid'], $mybb->user['as_uid']);

  if (isset($mybb->input['scenefilter'])) {
    $charakter = intval($mybb->input['charakter']);
    $status = $db->escape_string($mybb->input['status']);
    $move = $db->escape_string($mybb->input['move']);
  }

  if ($charakter == 0) {
    $charasquery = scenetracker_get_accounts($thisuser, $mybb->user['as_uid']);
    // $charas = scenetracker_get_accounts($thisuser, $mybb->user['as_uid']); 
    $charastr = "";
    // var_dump($charasquery);
    foreach ($charasquery as $uid => $username) {
      $charastr .= $uid . ",";
    }
  } else {
    $username = get_user($charakter);
    $charasquery[$charakter] = $username['username'];
    $charastr = $charakter . ",";
  }
  $sel_m[$move] = "SELECTED";
  if (substr($charastr, -1) == ",") {
    $charastr = substr($charastr, 0, -1);
  }

  if ($status == "") {
  }

  $query = "";
  //Status der Szene
  $status_str = $status;
  if ($status == "open") {
    $query .=  " AND (closed = 0 OR threadsolved = 0 ) ";
  } else if ($status == "closed") {
    $query .=  " AND (closed = 1 OR threadsolved = 1 ) ";
  } else if ($status == "both") {
    $status_str = "open & closed";
    $query .= "";
  } else {
    $status_str = "open";
    $status = "open";
    $query = " AND (closed = 0 OR threadsolved = 0 ) ";
  }
  $sel_s[$status] = "SELECTED";

  //Dran oder nicht? 
  $move_str = $move;

  if ($move != "ja" || $move != "nein") {
    $move_str = "beides";
    $query .= "";
  }

  $selectchara = "<select name=\"charakter\" id=\"charakter\">
    <option value=\"0\">allen Charas</option>";
  foreach ($charas as $uid => $username) {
    if ($uid == $charakter) {
      $charsel_[$charakter] = "SELECTED";
    } else {
      $charsel_[$charakter] = "";
    }
    $selectchara .=  "<option value=\"{$uid}\" {$charsel_[$charakter]} >{$username}</option>";
  }
  $selectchara .= "</select>";

  $users_options_bit = "<option value=\"0\">another user posted</option>
  <option value=\"-1\">never</option>";
  $all_users = array();

  $get_users = $db->query("SELECT username, uid FROM " . TABLE_PREFIX . "users ORDER by username");
  while ($users_select = $db->fetch_array($get_users)) {
    $getuid =  $users_select['uid'];
    $all_users[$getuid] = $users_select['username'];
    $users_options_bit .= "<option value=\"{$users_select['uid']}\">{$users_select['username']}</option>";
  }
  $all = $users_options_bit;

  $cnt = 0;
  foreach ($charasquery as $uid => $charname) {
    $querymove = "";
    if ($move == "ja") {
      $querymove .=   " 
                      AND (
                        (lastposteruid != {$uid} and type ='always') 
                      OR (alert = 1 and type = 'certain')
                      )";
      $move_str = "Ja";
    }
    if ($move == "nein") {
      $querymove .=  "  AND (
                          (lastposteruid = {$uid} and type = 'always') 
                            OR 
                              (alert = 0 and type = 'certain')
                        ) ";
      $move_str = "Nein";
    }
    if ($charakter == 0) {
      $charname_str = "Alle Charaktere";
    } else {
      $charname_str = $charname;
    }

    $writequery = "
    SELECT s.*,
      fid, subject, dateline, lastpost, lastposter, 
      lastposteruid, closed, threadsolved, 
      scenetracker_date, scenetracker_user, scenetracker_place, scenetracker_trigger
      FROM " . TABLE_PREFIX . "scenetracker  s LEFT JOIN 
      " . TABLE_PREFIX . "threads t on s.tid = t.tid WHERE 
      s.uid = {$uid}
      " . $query . $querymove . "  
      ORDER by uid ASC, lastpost DESC";

    $scenes = $db->write_query($writequery);
    $cnt += $db->num_rows($scenes);
    $chara_cnt = $db->num_rows($scenes);
    $scenes_title = "Deine Szenen ({$charname_str} - {$status_str} - dran? {$move_str} - #{$cnt})";

    if ($db->num_rows($scenes) == 0) {
      $tplcount = 0;
    } else {
      $tplcount = 1;

      $charaname = build_profile_link($charname, $uid);
      if ($charakter == 0) {
        $charaname .= " (#{$chara_cnt})";
      }
      $scenetracker_ucp_bit_scene = "";
      while ($data = $db->fetch_array($scenes)) {

        $edit = "";
        $alert = "[alert]";

        $user = get_user($uid);
        $tid = $data['tid'];
        $info_by = $data['inform_by'];

        $threadread = $db->simple_select("threadsread", "*", "tid = {$tid} and uid = {$mybb->user['uid']}");
        $threadreadcnt = $db->num_rows($threadread);

        $isread = "newscene";

        while ($readthreaddata = $db->fetch_array($threadread)) {
          if ($readthreaddata['dateline'] >= $data['lastpost']) {
            $isread = "oldscene";
          } else if ($readthreaddata['dateline'] < $data['lastpost']) {
            $isread = "newscene";
          } else if ($threadreadcnt == 0) {
            $isread = "newscene";
          } else {
            $isread = "newscene";
          }
        }

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
        $scene = '<a href="showthread.php?tid=' . $data['tid'] . '&action=lastpost" class="scenelink">' . $data['subject'] . '</a>';
        if ($data['profil_view'] == 1) {
          $hide = "is displayed (profile) <a href=\"usercp.php?action=scenetracker&showsceneprofil=0&getsid=" . $id . "\"><i class=\"fas fa-toggle-on\"></i></a>";
        } else {
          $hide = "is hidden <a href=\"usercp.php?action=scenetracker&showsceneprofil=1&getsid=" . $id . "\"><i class=\"fas fa-toggle-off\"></i></a></a>";
        }
        if ($data['closed'] == 1) {
          $close = "is closed <a href=\"usercp.php?action=scenetracker&closed=0&getsid=" . $id . "&gettid=" . $tid . "&getuid=" . $uid . "\"><i class=\"fas fa-unlock\"></i></a>";
        } else {
          $close = "is open <a href=\"usercp.php?action=scenetracker&closed=1&getsid=" . $id . "&gettid=" . $tid . "&getuid=" . $uid . "\"><i class=\"fas fa-lock\"></i></a>";
        }

        if ($data['type'] == 'certain' && $info_by != 0) {
          $users_options_bit = "<option value=\"0\">another user posted</option>
                                <option value=\"-1\">never</option>";
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

        eval("\$scenetracker_ucp_bit_scene .= \"" . $templates->get('scenetracker_ucp_bit_scene') . "\";");
      }
    }
    if ($tplcount == 1) {
      eval("\$scenetracker_ucp_bit_chara .=\"" . $templates->get("scenetracker_ucp_bit_chara") . "\";");
    }
  }

  //Save Settings of user
  if ($mybb->input['opt_index']) {
    $index = $mybb->input['index'];
    foreach ($charas as $uid => $chara) {
      $db->query("UPDATE " . TABLE_PREFIX . "users SET tracker_index = " . $index . " WHERE uid = " . $uid . " ");
    }
    redirect('usercp.php?action=scenetracker');
  }

  //Save Settings of user
  if ($mybb->input['opt_indexall']) {
    $index = $mybb->input['indexall'];
    $db->query("UPDATE " . TABLE_PREFIX . "users SET tracker_indexall = " . $index . " WHERE uid = " . $mybb->user['uid'] . " ");
    redirect('usercp.php?action=scenetracker');
  }

  //Save Settings of user
  if ($mybb->input['opt_reminder']) {
    $reminder = $mybb->input['reminder'];
    foreach ($charas as $uid => $chara) {
      $db->query("UPDATE " . TABLE_PREFIX . "users SET tracker_reminder = " . $reminder . " WHERE uid = " . $uid . " ");
    }
    redirect('usercp.php?action=scenetracker');
  }

  if ($mybb->input['certainuser']) {
    //info by
    $certained = intval($mybb->input['charakter']);
    //for which scene
    $id = intval($mybb->input['getid']);
    scenetracker_scene_inform_status($id, $certained);
    redirect('usercp.php?action=scenetracker');
  }

  if ($mybb->input['showsceneprofil'] == "0") {
    $id = intval($mybb->input['getsid']);
    $uid = $db->fetch_field($db->simple_select("scenetracker", "uid", "id = $id"), "uid");
    scenetracker_scene_change_view(0, $id, $uid);
    redirect('usercp.php?action=scenetracker');
  } elseif ($mybb->input['showsceneprofil'] == "1") {
    $id = intval($mybb->input['getsid']);
    $uid = $db->fetch_field($db->simple_select("scenetracker", "uid", "id = $id"), "uid");
    scenetracker_scene_change_view(1, $id, $uid);
    $uid = $db->fetch_field($db->simple_select("scenetracker", "uid", "id = $id"), "uid");
    redirect('usercp.php?action=scenetracker');
  }

  if ($mybb->input['closed'] == "1") {
    $tid = intval($mybb->input['gettid']);
    $uid = intval($mybb->input['getuid']);
    scenetracker_scene_change_status(1,  $tid,  $uid);
    redirect('usercp.php?action=scenetracker');
  } elseif ($mybb->input['closed'] == "0") {
    $tid = intval($mybb->input['gettid']);
    $uid = intval($mybb->input['getuid']);
    scenetracker_scene_change_status(0,  $tid,  $uid);
    redirect('usercp.php?action=scenetracker');
  }

  eval("\$scenetracker_ucp_main =\"" . $templates->get("scenetracker_ucp_main") . "\";");
  output_page($scenetracker_ucp_main);
}

/**
 * automatische Anzeige von Tracker im Profil
 * //TODO make scenes sortable
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

  $allowmanage = scenetracker_check_switcher($userprofil);
  $show_monthYear = array();
  $sort = $mybb->settings['scenetracker_profil_sort'];

  if ($mybb->settings['scenetracker_solved'] == 1)   $solved = ", threadsolved";
  //hide scene in profile
  if ($mybb->input['show'] == "0") {
    $id = intval($mybb->input['getsid']);
    scenetracker_scene_change_view(0, $id, $userprofil);
    redirect('member.php?action=profile&uid=' . $userprofil);
  }
  //close scene from profile
  if ($mybb->input['closed'] == "1") {
    $tid = intval($mybb->input['gettid']);
    scenetracker_scene_change_status(1,  $tid,  $userprofil);
    redirect('member.php?action=profile&uid=' . $userprofil);
  } elseif ($mybb->input['closed'] == "0") {
    $tid = intval($mybb->input['gettid']);
    scenetracker_scene_change_status(0,  $tid,  $userprofil);
    redirect('member.php?action=profile&uid=' . $userprofil);
  }
  $scene_query = $db->write_query("
      SELECT s.*,t.fid, parentlist, subject, dateline, t.closed as threadclosed, scenetracker_date, scenetracker_user, scenetracker_place, scenetracker_trigger" . $solved . " FROM " . TABLE_PREFIX . "scenetracker s, 
      " . TABLE_PREFIX . "threads t LEFT JOIN " . TABLE_PREFIX . "forums fo ON t.fid = fo.fid WHERE t.tid = s.tid AND s.uid = " . $userprofil . " 
      AND (concat(',',parentlist,',') LIKE '%," . $ingame . ",%' OR concat(',',parentlist,',') LIKE '%," . $archiv . ",%' ) AND s.profil_view = 1 ORDER by scenetracker_date DESC");


  $date_flag = "1";
  while ($scenes = $db->fetch_array($scene_query)) {
    $tid = $scenes['tid'];
    $sid = $scenes['id'];
    $subject = $scenes['subject'];
    $sceneusers = str_replace(",", ", ", $scenes['scenetracker_user']);
    $sceneplace = $scenes['scenetracker_place'];
    if ($scenes['scenetracker_trigger'] != "") {
      $scenetrigger = "<div class=\"scenetracker__sceneitem scene_trigger icon\">Triggerwarnung: {$scenes['scenetracker_trigger']}</div>";
    } else {
      $scenetrigger = "";
    }
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
    if ($dateYear != date('m.Y', strtotime($scenes['scenetracker_date']))) {
      $scenedatetitle = strftime('%B %Y', strtotime($scenes['scenetracker_date']));
      eval("\$scenetracker_profil_bit_mY = \"" . $templates->get("scenetracker_profil_bit_mY") . "\";");
      $dateYear = date('m.Y', strtotime($scenes['scenetracker_date']));
    } else {
      $scenetracker_profil_bit_mY = "";
    }
    eval("\$scenetracker_profil_bit .= \"" . $templates->get("scenetracker_profil_bit") . "\";");
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

  //get all charas of this user
  $uid = $mybb->user['uid'];
  $index = $db->fetch_field($db->simple_select("users", "tracker_index", "uid = $uid"), "tracker_index");
  $index_all = $db->fetch_field($db->simple_select("users", "tracker_indexall", "uid = $uid"), "tracker_indexall");

  if ($uid != 0 && $index == 1) {
    if ($index_all == 1) {
      $charas = scenetracker_get_accounts($mybb->user['uid'], $mybb->user['as_uid']);
    } else {
      $charas[$uid] = $mybb->user['username'];
      // $charas[$uid] = $users['username'];
    }
    $solvplugin = $mybb->settings['scenetracker_solved'];
    //show new scenes
    scenetracker_get_scenes($charas, "index");

    //change inform status always/never/certain user
    if ($mybb->input['certainuser']) {
      //info by
      $certained = intval($mybb->input['charakter']);
      //for which scene
      $id = intval($mybb->input['getid']);
      scenetracker_scene_inform_status($id, $certained);
      redirect('index.php#closepop');
    }

    //change status of scenes
    if ($mybb->input['closed'] == "1") {
      $tid = intval($mybb->input['gettid']);
      $uid = intval($mybb->input['getuid']);
      scenetracker_scene_change_status(1,  $tid,  $uid);
      redirect('index.php');
    } elseif ($mybb->input['closed'] == "0") {
      $tid = intval($mybb->input['gettid']);
      $uid = intval($mybb->input['getuid']);
      scenetracker_scene_change_status(0,  $tid,  $uid);
      redirect('index.php');
    }
    $cnts = scenetracker_count_scenes($charas);

    $counter = "( {$cnts['open']} / {$cnts['all']} )";

    eval("\$scenetracker_index_main =\"" . $templates->get("scenetracker_index_main") . "\";");
  }
}

/******************************
 * Reminder
 * Erinnerung wenn man den Postpartner X Tage warten lässt
 ******************************/

$plugins->add_hook('index_start', 'scenetracker_reminder');
function scenetracker_reminder()
{
  global $mybb, $db, $templates, $scenetracker_index_reminder;

  $uid = $mybb->user['uid'];
  $reminder = $db->fetch_field($db->simple_select("users", "tracker_reminder", "uid = $uid"), "tracker_reminder");
  if ($uid != 0 && $reminder == 1) {
    // Alle Charaktere des Users holen
    $charas = scenetracker_get_accounts($mybb->user['uid'], $mybb->user['as_uid']);
    $days = intval($mybb->settings['scenetracker_reminder']);
    // $days = 200;
    $cnt = 0;
    foreach ($charas as $uid => $username) {

      $scenetracker_get_scenes = $db->write_query(
        "SELECT * FROM " . TABLE_PREFIX . "scenetracker st, " . TABLE_PREFIX . "threads t WHERE st.tid = t.tid 
            AND st.uid = " . $uid . "
            AND lastposteruid != 0
            AND lastposteruid != 1
            AND lastposteruid != {$uid}
            AND threadsolved = 0
            AND closed= 0 ORDER by st.uid"
      );
      while ($scenes = $db->fetch_array($scenetracker_get_scenes)) {

        $today = new DateTime();

        $postdate = new DateTime();
        $postdate->setTimestamp($scenes['lastpost']);

        $interval = $postdate->diff($today);
        $lastpostdays = $interval->days;
        if ($interval->days >= $days) {
          $cnt = 1;
          $userarr = get_user($uid);
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
    }
    if ($cnt == 1) {
      eval("\$scenetracker_index_reminder =\"" . $templates->get("scenetracker_index_reminder") . "\";");
    }
  }

  if ($mybb->input['action'] == 'reminder') {
    // echo "bla";
    foreach ($charas as $uid => $chara) {
      $db->query("UPDATE " . TABLE_PREFIX . "users SET tracker_reminder = 0 WHERE uid = " . $uid . " ");
    }
    echo "<script>alert('Die Anzeige kannst du in deinem UCP wieder anstellen.')
    window.location = './index.php';</script>";
  }
}


/***
 * shows Scenes in calendar
 * /***
 * Darstellung der Szenen im mybb Kalendar 
 * ACHTUNG! diese Funktion geht nur, wenn die Anleitung zum Hinzufügen des Hakens für die Funktion
 * in der Readme befolgt wird!
 * Am Besten über Patches lösen -> calendar.php
 * suchen nach eval("\$day_bits .= \"".$templates->get("calendar_weekrow_thismonth")."\";");
 * darüber einfügen $plugins->run_hooks("calendar_weekview_day");
 */

$plugins->add_hook("calendar_weekview_day", "scenetracker_calendar");
function scenetracker_calendar()
{
  global $db, $mybb, $day, $month, $year, $scene_ouput, $birthday_ouput;
  $thisuser = $mybb->user['uid'];
  $username = $mybb->user['username'];

  $showownscenes = 1;
  if ($showownscenes == 1) {

    $daynew = sprintf("%02d", $day);
    $monthzero  = sprintf("%02d", $month);

    //wir müssen das Datum in das gleiche format wie den geburtstag bekommen
    $datetoconvert = "{$daynew}.{$monthzero}.{$year}";
    $timestamp = strtotime($datetoconvert);
    $converteddate = date("d.m", $timestamp);
    // echo $converteddate;
    $get_birthdays = $db->write_query("
      SELECT username, uid FROM " . TABLE_PREFIX . "userfields LEFT JOIN " . TABLE_PREFIX . "users ON ufid = uid WHERE fid4 LIKE '{$converteddate}%'");


    $scenes = $db->write_query("
    SELECT *, TIME_FORMAT(scenetracker_date, '%H:%i') scenetime FROM " . TABLE_PREFIX . "threads WHERE scenetracker_date LIKE '{$year}-{$monthzero}-{$daynew}%' and scenetracker_user LIKE '%{$username}%'");
    $szene = "";
    $scene_in = "";
    $scene_ouput = "";
    $birthday_show = "";
    $birthday_ouput = "";
    $birthday_in = "";
    if ($db->num_rows($scenes) > 0 || $db->num_rows($get_birthdays) > 0) {
      if ($db->num_rows($scenes) > 0) {
        $szene = "<a onclick=\"$('#day{$day}').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== 'undefined' ? modal_zindex : 9999) }); return false;\" style=\"cursor: pointer;\">[Szenen]</a>";
        $scene_ouput = " {$szene}
      <div class=\"modal\" id=\"day{$day}\" style=\"display: none; padding: 10px; margin: auto; text-align: center;\">
      
      ";
        $scene_in = "";
        while ($scene = $db->fetch_array($scenes)) {
          $scene_in .= "
        <div class=\"st_calendar\">
          <div class=\"st_calendar__sceneitem scene_date icon\"><i class=\"fas fa-calendar\"></i> {$scene['scenetime']}</div>
          <div class=\"st_calendar__sceneitem scene_title icon\"><i class=\"fas fa-folder-open\"></i> <a href=\"showthread.php?tid={$scene['tid']}\">{$scene['subject']}</a> </div>
          <div class=\"st_calendar__sceneitem scene_place icon\"><i class=\"fas fa-map-marker-alt\"></i> {$scene['scenetracker_place']}</div>
          <div class=\"st_calendar__sceneitem scene_users icon \">><i class=\"fas fa-users\"></i> {$scene['scenetracker_user']}</div>
         </div> ";
        }
        $scene_ouput .= "{$scene_in}</div>";
      }

      if ($db->num_rows($get_birthdays) > 0) {
        $birthday_show = "<a onclick=\"$('#day{$day}').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== 'undefined' ? modal_zindex : 9999) }); return false;\" style=\"cursor: pointer;\">[Geburtstage]</a>";
        $birthday_ouput = " {$birthday_show}
      <div class=\"modal\" id=\"day{$day}\" style=\"display: none; padding: 10px; margin: auto; text-align: center;\">
      ";
        $birthday_in = "";
        while ($birthd = $db->fetch_array($get_birthdays)) {
          $birthday_in .= "
        <div class=\"st_calendar\">
          <div class=\"st_calendar__sceneitem birthday icon\">" . build_profile_link($birthd['username'], $birthd['uid']) . "</div>
         </div> ";
        }
        $birthday_ouput .= "{$birthday_in}</div>";
      }
    }
  }
}

/***
 * shows minicalender on index
 *  * credit to:
 * https://zellwk.com/blog/calendar-with-css-grid/
 * https://www.schattenbaum.net/php/kalender.php
 * 
 */
$plugins->add_hook('global_start', 'scenetracker_minicalendar');
function scenetracker_minicalendar()
{
  global $db, $mybb, $templates, $scenetracker_calendar;
  $startdate_ingame = $mybb->settings['memberstats_ingamemonat_tag_start'];
  $enddate_ingame = $mybb->settings['memberstats_ingamemonat_tag_end'];


  $username = $db->escape_string($mybb->user['username']);
  $ingame =  explode(",", str_replace(" ", "", $mybb->settings['scenetracker_ingametime']));
  foreach ($ingame as $monthyear) {
    // echo "Blubber";
    $ingamelastday = $monthyear . "-" .  sprintf("%02d", $enddate_ingame);
  }
  $ingamefirstday = $ingame[0] . "-" . sprintf("%02d", $startdate_ingame);
  // echo "first {$ingamefirstday} last {$ingamelastday}";
  foreach ($ingame as $monthyear) {
    $kal_datum = strtotime($monthyear . "-01");

    //wieviele tage hat der monat
    $kal_tage_gesamt = date("t", $kal_datum);
    //die nullen sind die zeit also 00:00:00, dann Monat ohne führende null, 1 für den 1. des Monats, letztes Jahr
    $kal_start_timestamp = mktime(0, 0, 0, date("n", $kal_datum), 1, date("Y", $kal_datum));
    //Welcher Wochentag?
    $kal_start_tag = date("N", $kal_start_timestamp);
    //hier der tag des letzten im Monat
    $kal_ende_tag = date("N", mktime(0, 0, 0, date("n", $kal_datum), $kal_tage_gesamt, date("Y", $kal_datum)));

    //Monat Jahr
    $kal_title = strftime("%B %Y", $kal_datum);
    $kal_day = "";
    $kal_day .= "<div class=\"date-grid\"  style=\"grid-template-columns: repeat(7, 1fr);\">";
    //Tage bis zum wochenstart, also wieviele tage der woche sind noch im vormonat
    //(7 - $kal_ende_tag) wieviele tage am ende des monats in der woche 
    for ($i = 1; $i <= $kal_tage_gesamt + ($kal_start_tag - 1) + (7 - $kal_ende_tag); $i++) {

      $kal_anzeige_akt_tag = $i - $kal_start_tag;
      $kal_anzeige_heute_timestamp = strtotime($kal_anzeige_akt_tag . " day", $kal_start_timestamp);
      $kal_anzeige_heute_tag = date("j", $kal_anzeige_heute_timestamp);
      $daynew = sprintf("%02d", $kal_anzeige_heute_tag);

      $scenes = $db->write_query("
      SELECT *, TIME_FORMAT(scenetracker_date, '%H:%i') scenetime FROM " . TABLE_PREFIX . "threads WHERE scenetracker_date LIKE '{$monthyear}-{$daynew}%' and scenetracker_user LIKE '%{$username}%'");

      //wir müssen das Datum in das gleiche format wie den geburtstag bekommen
      if ($monthyear . "-" . $daynew >= $ingamefirstday && $monthyear . "-" . $daynew <= $ingamelastday) {
        $ingame = "activeingame";
      } else {
        $ingame = "";
      }
      $datetoconvert = "{$monthyear}-{$daynew}";
      //original date is in format YYYY-mm-dd
      $timestamp = strtotime($datetoconvert);
      $converteddate = date("d.m", $timestamp);
      // echo $converteddate;
      $get_birthdays = $db->write_query("
      SELECT username, uid FROM " . TABLE_PREFIX . "userfields LEFT JOIN " . TABLE_PREFIX . "users ON ufid = uid WHERE fid4 LIKE '{$converteddate}%'");

      $get_events = $db->write_query("
      SELECT * FROM " . TABLE_PREFIX . "events WHERE DATE_FORMAT(FROM_UNIXTIME(starttime), '%Y-%m-%d') LIKE '{$datetoconvert}%'");
      //SELECT DATE_FORMAT(FROM_UNIXTIME(starttime), '%Y-%m-%d') FROM " . TABLE_PREFIX . "events

      // echo "{$monthyear}-{$daynew} <br>";
      // echo $monthyear . "-" . sprintf("%02d", $kal_anzeige_akt_tag) . "<br>";

      if ($kal_anzeige_akt_tag >= 0 and $kal_anzeige_akt_tag < $kal_tage_gesamt) {
        $sceneshow = "";
        $birthdayshow = "";
        $eventshow = "";
        if ($db->num_rows($scenes) > 0 || $db->num_rows($get_birthdays) > 0 || $db->num_rows($get_events) > 0) {
          if ($db->num_rows($scenes) > 0) {
            $sceneshow = "<span class=\"st_mini_scene_title\">Szenen</span>";
            while ($scene = $db->fetch_array($scenes)) {
              $sceneshow .= "<div class=\"st_mini_scenelink\"><span class=\"raquo\">&raquo;</span> <a href=\"showthread.php?tid={$scene['tid']}\">{$scene['subject']}</a> ({$scene['scenetime']})</div>";
            }
          }
          if ($db->num_rows($get_birthdays) > 0) {
            $birthdayshow = "<span class=\"st_mini_scene_title\">Geburtstage</span>";
            while ($birthday = $db->fetch_array($get_birthdays)) {
              $birthdayshow .= "<div class=\"st_mini_scenelink\">" . build_profile_link($birthday['username'], $birthday['uid']) . "</div>";
            }
          }

          if ($db->num_rows($get_events) > 0) {
            $eventcss = "event";
            $eventshow = "<span class=\"st_mini_scene_title\">Events</span>";

            while ($event = $db->fetch_array($get_events)) {
              if ($event['name'] == "Fullmoon") {
                $fullmoon = "fullmoon";
              }
              $eventshow .= "<div class=\"st_mini_scenelink \"><a href=\"calendar.php?action=event&eid={$event['eid']}\">{$event['name']}</a></div>";
            }
          }
          if ($mybb->user['uid'] != 0) {
            $showpop = "<div class=\"st_mini_scene_show\">
                       {$sceneshow}
                        {$birthdayshow}
                        {$eventshow}
                      </div>";
          } else {
            $showpop = "";
          }
          $kal_day .= "
            <div class=\"day st_mini_scene {$fullmoon} {$eventcss} {$ingame}\">
              {$kal_anzeige_heute_tag}

              {$showpop}

          </div>";
          $fullmoon = "";
          $eventcss = "";
        } else {
          $kal_day .= "<div class=\"day {$ingame}\">" . $kal_anzeige_heute_tag . "</div>";
        }
      } else {
        $kal_day .= "<div class=\"day old\"></div>";
      }
    }
    $kal_day .= "</div>";
    // echo $scenetracker_calendar;
    eval("\$scenetracker_calendar .= \"" . $templates->get("scenetracker_calendar") . "\";");
  }
}

/**
 * Was passiert wenn ein User gelöscht wird
 * Einträge aus scenetracker löschen
 */
$plugins->add_hook("admin_user_users_delete_commit_end", "scenetracker_userdelete");
function scenetracker_userdelete()
{
  global $db, $cache, $mybb, $user;
  $todelete = (int)$user['uid'];
  $db->delete_query('scenetracker', "uid = " . (int)$user['uid'] . "");
}

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
function scenetracker_get_accounts($this_user, $as_uid)
{
  global $mybb, $db;
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

function scenetracker_count_scenes($charas)
{
  global $db, $mybb;
  $solvplugin = $mybb->settings['scenetracker_solved'];
  $cnt_array = array(
    "all" => 0,
    "open" => 0
  );

  if ($solvplugin == 1) {
    $solvefield = " threadsolved,";
    $solved = " OR threadsolved=0";
  }
  $cnt_open = 0;
  $cnt_all = 0;

  foreach ($charas as $uid => $charname) {
    //open scenes
    $query_open =  " AND (closed = 0 " . $solved . ") AND ((lastposteruid != $uid and type ='always') OR (alert = 1 and type = 'certain'))";

    $query_all =  " AND (closed = 0 " . $solved . ") AND ((type ='always') OR (type = 'certain'))";

    $charaname = build_profile_link($charname, $uid);

    $scenes_open = $db->write_query("
              SELECT s.*,
              fid,subject,dateline,lastpost,lastposter,lastposteruid, closed, " . $solvefield . " 
              scenetracker_date, scenetracker_user,scenetracker_place 
              FROM " . TABLE_PREFIX . "scenetracker  s LEFT JOIN " . TABLE_PREFIX . "threads t on s.tid = t.tid WHERE s.uid = {$uid} " . $query_open . " ORDER by lastpost DESC");

    $scenes_all = $db->write_query("
              SELECT s.*,
              fid,subject,dateline,lastpost,lastposter,lastposteruid, closed, " . $solvefield . " 
              scenetracker_date, scenetracker_user,scenetracker_place 
              FROM " . TABLE_PREFIX . "scenetracker  s LEFT JOIN " . TABLE_PREFIX . "threads t on s.tid = t.tid WHERE s.uid = {$uid} " . $query_all . " ORDER by lastpost DESC");

    $cnt_open += $db->num_rows($scenes_open);
    $cnt_all += $db->num_rows($scenes_all);
    $cnt_array[$uid . '-open'] = $db->num_rows($scenes_open);
    $cnt_array[$uid . '-all'] = $db->num_rows($scenes_all);
  }

  $cnt_array['all'] = $cnt_all;
  $cnt_array['open'] = $cnt_open;

  return $cnt_array;
}


/**
 * Check if an uid belongs to the user which is online (switcher)
 * @param $uid uid to check
 * @return boolean true/false
 * */
function scenetracker_check_switcher($uid)
{
  global $mybb;

  if ($mybb->user['uid'] != 0) {
    $chars = scenetracker_get_accounts($mybb->user['uid'], $mybb->user['as_uid']);
    return array_key_exists($uid, $chars);
  } else return false;
}

/**
 * Check if an fid belong to ingame/archiv
 * @param $fid to check
 * @return boolean true/false
 * */
function scenetracker_testParentFid($fid)
{
  global $db, $mybb;
  $parents = $db->fetch_field($db->write_query("SELECT CONCAT(',',parentlist,',') as parents FROM " . TABLE_PREFIX . "forums WHERE fid = $fid"), "parents");
  // rebuild_settings();
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
function scenetracker_getUids($string_usernames)
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
function scenetracker_get_scenes($charas, $tplstring)
{
  global $db, $mybb, $templates, $users_options_bit, $scenetracker_ucp_bit_chara_new, $scenetracker_ucp_bit_chara_old, $scenetracker_ucp_bit_chara_closed, $scenetracker_ucp_bit_scene, $scenetracker_index_bit_chara, $scenetracker_index_bit_scene;
  //fürs select feld, alle usernamen suchen 
  $solvplugin = $mybb->settings['scenetracker_solved'];

  $users_options_bit = "<option value=\"0\">another user posted</option>
  <option value=\"-1\">never</option>";
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

  $cnt = scenetracker_count_scenes($charas);
  // var_dump($cnt);
  foreach ($charas as $uid => $charname) {
    if ($tplstring == "new" or $tplstring == "index") {
      $query =  " AND (closed = 0 " . $solved . ") AND ((lastposteruid != $uid and type ='always') OR (alert = 1 and type = 'certain'))";
    } elseif ($tplstring == "old") {
      $query =  " AND (closed = 0 " . $solved . ") AND ((lastposteruid = $uid and type = 'always') OR (alert = 0 and type = 'certain'))";
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
              FROM " . TABLE_PREFIX . "scenetracker  s LEFT JOIN " . TABLE_PREFIX . "threads t on s.tid = t.tid WHERE s.uid = {$uid} " . $query . " ORDER by lastpost DESC");
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

        $threadread = $db->simple_select("threadsread", "*", "tid = {$tid} and uid = {$mybb->user['uid']}");
        $threadreadcnt = $db->num_rows($threadread);

        // $readthreaddate = $db->fetch_field($db->simple_select("threadsread", "*", "tid = {$tid} and uid = {$uid}"), "dateline");
        $isread = "newscene";

        while ($readthreaddata = $db->fetch_array($threadread)) {
          if ($readthreaddata['dateline'] >= $data['lastpost']) {
            $isread = "oldscene";
          } else if ($readthreaddata['dateline'] < $data['lastpost']) {
            $isread = "newscene";
          } else if ($threadreadcnt == 0) {
            $isread = "newscene";
          } else {
            $isread = "newscene";
          }
        }

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
        $scene = '<a href="showthread.php?tid=' . $data['tid'] . '&action=lastpost" class="scenelink">' . $data['subject'] . '</a>';
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
          $users_options_bit = "<option value=\"0\">another user posted</option>
                                <option value=\"-1\">never</option>";
          foreach ($all_users as $uid_sel => $username) {
            if ($info_by == $uid_sel) {
              $selected = "selected";
            } else {
              $selected = "";
            }
            $users_options_bit .= "<option value=\"{$uid_sel}\" $selected>{$username}</option>";
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


        $string_open = "{$uid}-open";
        $string_all = "{$uid}-all";

        $cnt_chara = "(" . $cnt[$string_open] . "/" . $cnt[$string_all] . ")";

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
function scenetracker_scene_inform_status($id, $certained)
{
  global $db;

  if ($certained == 0) {
    //always
    $db->query("UPDATE " . TABLE_PREFIX . "scenetracker SET inform_by = '0', type='always' WHERE id = " . $id . " ");
  } else if ($certained == -1) {
    //never
    $db->query("UPDATE " . TABLE_PREFIX . "scenetracker SET inform_by = '0', type='never' WHERE id = " . $id . " ");
  } else {
    //certain user
    //wir gehen erst einmal davon aus, der user hat als letztes gepostet und will noch nicht informiert werden
    $alert = 0;
    //jetzt testen wir ob das wirklich so ist:
    //wir brauchen die treadid 
    $tid = $db->fetch_field($db->simple_select("scenetracker", "tid", "id = {$id}"), "tid");
    //und die uid
    $uid = $db->fetch_field($db->simple_select("scenetracker", "uid", "id = {$id}"), "uid");
    //das datum des  letzten posts im Thread vom chara der gerade den alert einstellt
    $getlastpostdate = $db->fetch_field($db->write_query("SELECT uid, username, dateline FROM  " . TABLE_PREFIX . "posts WHERE tid = {$tid} AND uid = {$uid} ORDER by dateline DESC LIMIT 1"), "dateline");

    //der user hat hier noch nie gepostet, er möchte also informiert werden, sobald der certainuser gepostet hat
    if ($getlastpostdate == "" || empty($getlastpostdate)) {
      //wir setzen das datum auf 0, weil dateline dann immer größer ist
      $getlastpostdate = 0;
    }

    //wir holen uns jetzt alle posts, wo das datum größer ist als der letzte post des users
    $alert_query = $db->write_query("SELECT uid, username, dateline FROM " . TABLE_PREFIX . "posts WHERE tid ={$tid} and dateline > {$getlastpostdate} ORDER by dateline");
    // Jetzt gehen wir durch ob der certain user schon gepostet hat
    while ($d = $db->fetch_array($alert_query)) {
      if ($d['uid'] == $certained) {
        $alert = 1;
      }
    }

    $db->query("UPDATE " . TABLE_PREFIX . "scenetracker SET alert= {$alert}, inform_by = " . $certained . ", type='certain' WHERE  id = " . $id . " ");
  }
}

/***
 * allow to change status
 * -> gets list of usernames
 * returns true if allowed, false if not
 */
function scenetracker_change_allowed($str_teilnehmer)
{
  global $mybb, $db;
  $chars = scenetracker_get_accounts($mybb->user['uid'], $mybb->user['as_uid']);
  foreach ($chars as $uid => $username) {
    $pos = stripos($str_teilnehmer, $username);
    if ($pos !== false) {
      return true;
    }
  }
  if ($mybb->usergroup['canmodcp'] == 1) {
    return true;
  }
  return false;
}

/**********
 * Change status from scene (open/close)
 ************/
function scenetracker_scene_change_status($close, $tid, $uid)
{
  global $db, $mybb;
  $solvplugin = $mybb->settings['scenetracker_solved'];
  // scenetracker_change_allowed($thread['scenetracker_user'])
  $teilnehmer = $db->fetch_field($db->simple_select("threads", "scenetracker_user", "tid={$tid}"), "scenetracker_user");

  if ($close == 1) {
    $db->query("UPDATE " . TABLE_PREFIX . "threads SET closed = '1' WHERE tid = " . $tid . " ");
    //TODO check switcher not working 
    //prüft ob übergebene id zu dem chara gehört der online ist 
    //-> gesamte teilnehmerliste müsste durchgegangen werden
    if (scenetracker_change_allowed($teilnehmer)) {
      if ($solvplugin == "1") {
        $db->query("UPDATE " . TABLE_PREFIX . "threads SET threadsolved = '1' WHERE tid = " . $tid . " ");
      }
    }
  } elseif ($close == 0) {
    if (scenetracker_change_allowed($teilnehmer)) {
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
function scenetracker_scene_change_view($hide, $id, $uid)
{
  global $db, $mybb;
  //security check, is this user allowes to change entry?
  if (scenetracker_check_switcher($uid)) {
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
