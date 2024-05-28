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
 *  - bei Antwort von bestimmten User x
 *  - keine Benachrichtigung
 * Postingerinnerung (kann vom Admin aktiviert werden)
 *  - wenn man Postingpartner länger als x Tage warten gelassen hat
 * 
 */
// error_reporting(-1);
// ini_set('display_errors', true);

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
  die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}


function scenetracker_info()
{
  return array(
    "name" => "Szenentracker von Risuena",
    "description" => "Automatischer Tracker, mit Benachrichtigungseinstellung, (Mini-)Kalender, Indexanzeige und Reminder.<br/>
      <b style=\"color: red;\">Achtung</b> Bitte die Infos in der <b>readme</b> beachten, um Szenen im Kalender angezeigt zu bekommen.",
    "website" => "https://github.com/katjalennartz",
    "author" => "risuena",
    "authorsite" => "https://github.com/katjalennartz",
    "version" => "1.0.3",
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
  //
  scenetracker_uninstall();

  // Einfügen der Trackerfelder in die Threadtabelle
  $db->add_column("threads", "scenetracker_date", "DATETIME NULL DEFAULT NULL");
  $db->add_column("threads", "scenetracker_time_text", "varchar(200) NOT NULL DEFAULT ''");
  $db->add_column("threads", "scenetracker_place", "varchar(200) NOT NULL DEFAULT ''");
  $db->add_column("threads", "scenetracker_user", "varchar(1500) NOT NULL DEFAULT ''");
  $db->add_column("threads", "scenetracker_trigger", "varchar(200) NOT NULL DEFAULT ''");

  //einfügen der Kalender einstellungen
  $db->add_column("users", "scenetracker_calendar_settings", "INT(1) NOT NULL DEFAULT '0'");
  //für großen Kalender: 0 = nur szenen von diesem Charakter, 1 = Szenen aller eigenen Charas, 2 = Szenen aller Charas
  $db->add_column("users", "scenetracker_calendarsettings_big", "INT(1) NOT NULL DEFAULT '0'");
  //für mini Kalender: 0 = nur szenen von diesem Charakter, 1 = Szenen aller eigenen Charas, 2 = Szenen aller Charas
  $db->add_column("users", "scenetracker_calendarsettings_mini", "INT(1) NOT NULL DEFAULT '0'");

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
    'description' => 'Einstellungen für Risuenas Szenentracker.<br/> <b>Achtung</b> Bitte die Infos in der Readme beachten um Szenen im Kalender angezeigt zu bekommen.',
    'disporder' => 7, // The order your setting group will display
    'isdefault' => 0
  );
  $gid = $db->insert_query("settinggroups", $setting_group);

  scenetracker_add_settings();

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
  if ($db->field_exists("scenetracker_time_text", "threads")) {
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "threads DROP scenetracker_time_text");
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

  if ($db->field_exists("scenetracker_calendar_settings", "users")) {
    $db->drop_column("users", "scenetracker_calendar_settings");
  }
  if ($db->field_exists("scenetracker_calendarsettings_big", "users")) {
    $db->drop_column("users", "scenetracker_calendarsettings_big");
  }
  if ($db->field_exists("scenetracker_calendarsettings_mini", "users")) {
    $db->drop_column("users", "scenetracker_calendarsettings_mini");
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
  find_replace_templatesets("showthread", "#" . preg_quote('{$thread[\'displayprefix\']}{$thread[\'subject\']}') . "#i", '{$thread[\'displayprefix\']}{$thread[\'subject\']}{$scenetracker_showthread}');
  find_replace_templatesets("forumdisplay_thread", "#" . preg_quote('{$thread[\'multipage\']}</span>') . "#i", '{$thread[\'multipage\']}</span>{$scenetrackerforumdisplay}');
  find_replace_templatesets("index", "#" . preg_quote('{$header}') . "#i", '{$header}{$scenetracker_index_reminder}');
  find_replace_templatesets("index", "#" . preg_quote('{$footer}') . "#i", '{$scenetracker_index_main}{$footer}');
  find_replace_templatesets("member_profile", "#" . preg_quote('{$avatar}</td>') . "#i", '{$avatar}</td></tr><tr><td colspan="2">{$scenetracker_profil}</td>');
  find_replace_templatesets("usercp_nav_misc", "#" . preg_quote('<tbody style="{$collapsed[\'usercpmisc_e\']}" id="usercpmisc_e">') . "#i", '
  <tbody style="{$collapsed[\'usercpmisc_e\']}" id="usercpmisc_e"><tr><td class="trow1 smalltext"><a href="usercp.php?action=scenetracker">Szenentracker</a></td></tr>
  ');
  find_replace_templatesets("calendar_weekrow_thismonth", "#" . preg_quote('{$day_events}') . "#i", '{$day_events}{$scene_ouput}{$birthday_ouput}');

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
  find_replace_templatesets("calendar_weekrow_thismonth", "#" . preg_quote('{$scene_ouput}{$birthday_ouput}') . "#i", '');

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

/***
 * Adding The settings
 */
function scenetracker_add_settings($type = 'install')
{

  global $db;
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
      'disporder' => 2
    ),
    'scenetracker_alert_alerts' => array(
      'title' => 'My Alerts',
      'description' => 'Sollen Charaktere per MyAlerts (Plugin muss installiert sein) informiert werden?',
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 3
    ),
    'scenetracker_alert_pm' => array(
      'title' => 'Private Nachricht',
      'description' => 'Sollen Charaktere per privater Nachricht informiert werden?',
      'optionscode' => 'yesno',
      'value' => '0', // Default
      'disporder' => 3
    ),
    'scenetracker_as' => array(
      'title' => 'Accountswitcher?',
      'description' => 'Ist der Accountswitcher installiert',
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 4
    ),
    'scenetracker_ingame' => array(
      'title' => 'Ingame',
      'description' => 'ID des Ingames',
      'optionscode' => 'forumselect',
      'value' => '7', // Default
      'disporder' => 5
    ),
    'scenetracker_archiv' => array(
      'title' => 'Archiv',
      'description' => 'ID des Archivs',
      'optionscode' => 'forumselect',
      'value' => '0', // Default
      'disporder' => 6
    ),
    'scenetracker_birhday' => array(
      'title' => 'Geburtstagsfeld für Kalender',
      'description' => 'Wird ein Profilfeld (Format dd.mm.YYYY) verwendet, das Standardgeburtstagsfeld oder benutzt ihr das Steckbrief(Format YYYY-mm-dd wie datumsfeld) im UCP Plugin?',
      'optionscode' => "select\n0=fid\n1=standard\n2=ausschalten\n3=Steckbrief im Profil",
      'value' => '1', // Default
      'disporder' => 7
    ),
    'scenetracker_birhdayfid' => array(
      'title' => 'Geburtstagsfeld ID?',
      'description' => 'Wenn der Geburtstags über ein Profilfeld angegeben wird, bitte hier die ID eingeben, wenn das Steckbrieffeld benutzt wird, die Kennung von diesem.',
      'optionscode' => 'text',
      'value' => '0', // Default
      'disporder' => 8
    ),
    'scenetracker_reminder' => array(
      'title' => 'Erinnerung',
      'description' => 'Sollen Charaktere auf dem Index darauf aufmerksam gemacht werden, wenn sie jemanden in einer Szene länger als X Tage warten lassen? 0 wenn nicht',
      'optionscode' => 'text',
      'value' => '0', // Default
      'disporder' => 9
    ),
    'scenetracker_ingametime' => array(
      'title' => 'Ingame Zeitraum',
      'description' => 'Bitte Ingamezeitraum eingeben - Monat und Jahr. Bei mehreren mit , getrennt. Z.b für April, Juni und Juli "1997-04, 1997-06, 1997-07. <b>Achtung genauso wie im Beispiel!</b> (Wichtig für Minicalender)."',
      'optionscode' => 'text',
      'value' => '2024-04, 2024-06, 2024-07', // Default
      'disporder' => 10
    ),
    'scenetracker_ingametime_tagstart' => array(
      'title' => 'Ingame Zeitraum 1. Tag',
      'description' => 'Gib hier den ersten Tag eures Ingamezeitraums an. z.B. 1 oder 15.',
      'optionscode' => 'text',
      'value' => '1', // Default
      'disporder' => 11
    ),
    'scenetracker_ingametime_tagend' => array(
      'title' => 'Ingame Zeitraum letzter Tag',
      'description' => 'Gib hier den letzte  Tag eures Ingamezeitraums an. z.B. 15 oder 30.<br><i>Tage im Zeitraum, bekommen die Klasse "activeingame" und können gesondert gestylt werden.</i>',
      'optionscode' => 'text',
      'value' => '30', // Default
      'disporder' => 12
    ),
    'scenetracker_exludedfids' => array(
      'title' => 'ausgeschlossene Foren',
      'description' => 'Gibt es Foren, die im Ingame liegen aber nicht zum Tracker gezählt werden sollen (Keine Verfolgung, keine Anzeige im Profil, z.B. Communication).',
      'optionscode' => 'forumselect',
      'value' => '', // Default
      'disporder' => 13
    ),
    //Kalender einstellungen
    'scenetracker_calendarview_all' => array(
      'title' => 'Kalender Szenen Ansicht - Alle Szenen',
      'description' => 'Dürfen Mitglieder auswählen das die Szenen von allen Charakteren angezeigt werden?',
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 14
    ),
    'scenetracker_calendarview_ownall' => array(
      'title' => 'Kalender Szenen Ansicht - Alle eigenen Szenen',
      'description' => 'Dürfen Mitglieder auswählen das die Szenen von allen eigenen (verbundenen) Charakteren angezeigt werden?',
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 15
    ),
    'scenetracker_calendarview_own' => array(
      'title' => 'Kalender Szenen Ansicht - Szenen des Charaktes',
      'description' => 'Dürfen Mitglieder auswählen das die Szenen nur von dem Charakter angezeigt werden, mit dem man online ist?',
      'optionscode' => 'yesno',
      'value' => '1', // Default
      'disporder' => 16
    ),
    'scenetracker_time_text' => array(
      'title' => 'Angabe Tageszeit',
      'description' => 'Soll das Datum für eine Szene mit fester Zeit (Datum + Zeit z.B. 24.02.01 - 11:00) oder mit offener Zeit, als Textfenster (z.B. Mittags) angegeben werden?',
      'optionscode' => "select\n0=feste Zeit\n1=offene Texteingabe",
      'value' => '0', // Default
      'disporder' => 17
    ),
  );
  $gid = $db->fetch_field($db->write_query("SELECT gid FROM `" . TABLE_PREFIX . "settinggroups` WHERE name like 'scenetracker%' LIMIT 1;"), "gid");

  if ($type == 'install') {
    foreach ($setting_array as $name => $setting) {
      $setting['name'] = $name;
      $setting['gid'] = $gid;
      $db->insert_query('settings', $setting);
    }
  }
  if ($type == 'update') {
    foreach ($setting_array as $name => $setting) {
      $setting['name'] = $name;
      $check = $db->write_query("SELECT name FROM `" . TABLE_PREFIX . "settings` WHERE name = '{$name}'");
      $check = $db->num_rows($check);
      $setting['gid'] = $gid;
      if ($check == 0) {
        $db->insert_query('settings', $setting);
        echo "Setting: {$name} wurde hinzugefügt.";
      }

      if ($name == 'scenetracker_ingame' || $name == 'scenetracker_archiv' || $name == 'scenetracker_exludedfids') {
        $change = array(
          'optionscode' => 'forumselect'
        );
        $db->update_query("settings", $change, "name='{$name}'");
        echo "Setting: {$name} zu forumselect auswahl geändert.";
      }
    }
    echo "<p>Einstellungen wurden aktualisiert</p>";
  }

  rebuild_settings();
}


/**
 * Adds the templates and stylesheet
 */
function scenetracker_add_templates($type = 'install')
{
  global $db;

  //add templates and stylesheets
  // Add templategroup
  //templategruppe nur beim installieren hinzufügen
  if ($type == 'install') {
    $templategrouparray = array(
      'prefix' => 'scenetracker',
      'title'  => $db->escape_string('Szenentracker'),
      'isdefault' => 1
    );
    $db->insert_query("templategroups", $templategrouparray);
  }
  $template[0] = array(
    "title" => 'scenetracker_forumdisplay_infos',
    "template" => '<div class="author smalltext">
    <div class="scenetracker_forumdisplay scene_infos">
    <div class="scenetracker_forumdisplay scene_date icon"><i class="fas fa-calendar"></i> Szenendatum: {$scene_date}</div>
    <div class="scenetracker_forumdisplay scene_place icon"><i class="fas fa-map-marker-alt"></i> Szenenort: {$scene_place}</div>
    {$scenetrigger}
    <div class="scenetracker_forumdisplay scene_users icon"><i class="fas fa-users"></i> Szenenteilnehmer: {$scenetracker_forumdisplay_user}</div>	
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
    "template" => '<div class="scenetracker_index wrapper_container"><strong>Szenenverwaltung {$counter}</strong>
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
    <input type="date" value="{$scenetracker_date}" name="scenetracker_date" /> 
    <input type="{$time_input_type}" value="{$scenetracker_time}" name="{$time_input_name}" {$input_time_placeholder}/>
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
    "template" => '<a onclick="$(\\\'#certain{$id}\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;"><i class="fas fa-cogs"></i></a>
    <div class="modal addrela" id="certain{$id}" style="display: none; padding: 10px; margin: auto; text-align: center;">
            <form method="post" action="usercp.php?action=scenetracker">
                {$hidden}
                <input type="hidden" value="{$data[\\\'id\\\']}" name="getid">
                 <select name="charakter">
                  {$users_options_bit}
                </select><br />
                <input type="submit" name="certainuser" />
              </form>
                </div>',
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
    <div class="scenetracker__sceneitem scene_status icon"><i class="fas fa-play"></i> {$scenestatus}</div>
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
    <div class="sceneucp__sceneitem scene_title icon"><i class="fas fa-folder-open"></i> {$scene} {$statusclass}</div>
    <div class="sceneucp__sceneitem scene_status icon"><i class="fas fa-play"></i> scene {$close}
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
    <title>{$mybb->settings[\\\'bbname\\\']} - Szenentracker</title>
    {$headerinclude}
    </head>
    <body>
    {$header}
   
    <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
    <table width="100%" border="0" align="center">
    <tr>
      {$usercpnav}
      <td valign="top">
        
         <div class="scene_ucp container">
        <div class="scene_ucp manage alert_item">
          <h1><i class="fas fa-book-open" aria-hidden="true"></i> Szenentracker</h1>
          <p>Hier kannst du alles rund um den Szenentracker anschauen und verwalten. Die Einstellungen für die Alerts
        kannst du <a href="alerts.php?action=settings">hier</a> vornehmen. Stelle hier erst einmal allgemein ein,
        ob du die Szenen auf dem Index angezeigt werden möchtest und ob du eine Meldung haben möchtest, wenn du in
        einer Szene länger als {$days_reminder} Tage(n) dran bist.
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
          {$ucp_main_reminderopt}
          {$calendar_setting_form}
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
    
    <div class="calendar-container">
    {$scenetracker_calendar_bit}
    </div>
   ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );
  $template[21] = array(
    "title" => 'scenetracker_calendar_bit',
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
  $template[22] = array(
    "title" => 'scenetracker_misc_allscenes',
    "template" => '
    <html>
    <head>
      <title>Szenenliste</title>
      {$headerinclude}
    </head>
    <body>
      {$header}
      <table width="100%" border="0" align="center">
        <tr>
          <td valign="top">
            <table border="0" cellspacing="{$theme[\\\'borderwidth\\\']}" cellpadding="{$theme[\\\'tablespace\\\']}" class="tborder">
              <tr>
                <td class="trow2">
                  <div class="ucp_charstat bl-globalcard">
                    <div class="bl-tabcon__title">
                      <div class="forum_line forum_line--profile"></div>
                      <span class="bl-boldtitle bl-boldtitle--profile">Szenenliste</span>
                    </div>
  
                    {$scenes}
                  </div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
      {$footer}
    </body>
  </html>
   ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  //TODO <input type="date" value="{$scenetracker_date}" name="scenetracker_date" /> <input type="time" name="scenetracker_time" value="{$scenetracker_time}" /><br />


  foreach ($template as $row) {
    //prüfen ob das template schon existiert
    $check = $db->num_rows($db->simple_select("templates", "title", "title LIKE '{$row['title']}'"));
    if ($check == 0) {
      $db->insert_query("templates", $row);
      if ($type == 'update') {
        echo "{$row['title']} wurde hinzugefügt.<br>";
      }
    }
  }

  if ($type == 'install') {
    $css = array(
      'name' => 'scenetracker.css',
      'tid' => 1,
      'attachedto' => '',
      "stylesheet" =>    '
    :root {
      --background-light: #bcbcbc;
      --background-dark: #898989;
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
      background-color: var(--background-dark);
    }
    
    .scene_ucp.chara_item__scene:nth-child(odd) {
      background-color: var(--background-light);
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
    
    .sceneucp__sceneitem.scene_status{
      grid-column-start: 1 ;
    }
    
    .sceneucp__sceneitem.scene_profil {
      grid-column-start: span 2;
    }
    
    .scenetracker.scenebit.scenetracker_profil {
      padding: 5px 10px;
      display: flex;
      flex-wrap: wrap;
    }
    
    .scenetracker_profil .scenetracker__sceneitem.scene_title {
      width: 100%;
    }
    
    .scenetracker_profil .scenetracker__sceneitem {
      padding: 0px 5px;
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
    .scenetracker.container {
      width: 90%;
      height: 400px;
      overflow: auto;
      margin: auto auto;
      background: var(--background-light);
      padding: 10px;
    }
    
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
      background-color: var(--background-dark);
      padding: 10px
    }
    
    .scenetracker_index.chara_item__scene:nth-child(even) {
      background-color: var(--background-dark);
    }

    .closepop { 
      position: absolute; 
     right: -5px; 
     top:-5px; 
     width: 100%; 
     height: 100%; 
     z-index:0; 
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
      background-color: var(--background-light);
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
      overflow: auto
      padding-left: 30px;
    }
    
    .scenetracker_reminder.item:before {
      content: "» ";
    }
    
    span.senetracker_reminder.text {
      text-align: center;
      display: block;
    }
    
    .scenetracker_index.character_box {
      background-color: var(--background-dark);
    }    

/*calendar*/ 

.calendar-container {
    display: flex;
    justify-content: center;
	gap: 20px;
}
 
.calendar {
  background-color: var(--background-light);
  width: 205px;
  padding-left: 5px;
  padding: 5px;
  border: 1px solid var(--background-dark);
}

.calendar:first-child {
	padding: 0px;
}

/* For the month*/
.month-indicator {
  text-transform: uppercase;
  font-weight: 700;
  text-align: center;
}

/* CSS grid used for the dates */
.day-of-week,
.date-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
}

/* Styles for the weekday/weekend header*/
.day-of-week > * {
  font-size: 12px;
  font-weight: 700;
  text-align: center;
  margin-top: 5px;
}

/* Dates */
.date-grid {
  margin-top: 0;
  text-align: center;
}
    
.calendar .day.old {
  opacity: 0.3;
}

.st_mini_scene {
cursor: pointer;
position: relative;
display: inline-block;
font-weight:bold;
}


.day.st_mini_scene.fullmoon {
text-decoration: underline;
}

.st_mini_scene_show {
opacity: 0;
z-index: 300;
width: 200px;
display: block;
font-size: 11px;
padding: 5px 10px;
text-align: center;
background: var(--background-dark);
border: 5px solid var(--background-light);
-webkit-transition: all .2s ease-in-out;
-moz-transition: all .2s ease-in-out;
-o-transition: all .2s ease-in-out;
-ms-transition: all .2s ease-in-out;
transition: all .2s ease-in-out;
-webkit-transform: scale(0);
-moz-transform: scale(0);
-o-transform: scale(0);
-ms-transform: scale(0);
transform: scale(0);
position: absolute;
left: -65px;
bottom: 20px;
}

.st_mini_scene_show:before,.st_mini_scene_show:after {
content: "";
border-left: 10px solid transparent;
border-right: 10px solid transparent;
border-top: 10px solid var(--background-light);
position: absolute;
bottom: -13px;
left: 59px;
}

.st_mini_scene:hover .st_mini_scene_show,a:hover .st_mini_scene_show {
opacity: 1;
-webkit-transform: scale(1);
-moz-transform: scale(1);
-o-transform: scale(1);
-ms-transform: scale(1);
transform: scale(1);
background-color:var(--background-dark);
}

.st_mini_scene_title {
  text-decoration: underline;
}

.st_mini_scenelink {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  padding-bottom: 4px;
}
#calsettings_button {
  grid-column: 1 / -1;
  justify-self: center;
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
}

/**
 * Neuen Thread erstellen - Felder einfügen
 */

$plugins->add_hook("newthread_start", "scenetracker_newthread", 20);
function scenetracker_newthread()
{
  global $db, $mybb, $templates, $fid, $scenetracker_newthread, $thread, $scenetrackeredit, $post_errors, $scenetracker_date, $scenetracker_time, $scenetracker_time_input, $scenetracker_user;
  $scenetrackeredit = $scenetracker_place = $scenetracker_time_input = $scenetracker_trigger = "";
  if (scenetracker_testParentFid($fid)) {

    if ($mybb->get_input('previewpost') || $post_errors) {
      $scenetracker_date = $mybb->get_input('scenetracker_date');
      $scenetracker_time = $mybb->get_input('scenetracker_time');
      $scenetracker_time_str = $mybb->get_input('scenetracker_time_str');
      $scenetracker_user = $mybb->get_input('teilnehmer');
      $scenetracker_trigger = $mybb->get_input('scenetracker_trigger');
      $scenetracker_place = $mybb->get_input('place');
    } else {
      $ingame =  explode(",", str_replace(" ", "", $mybb->settings['scenetracker_ingametime']));

      $scenetracker_date = $ingame[0] . "-01";
      if ($mybb->settings['scenetracker_time_text'] == 0) {
        $scenetracker_time = "12:00";
      } else {
        $scenetracker_time = "";
      }
      $scenetracker_user = $mybb->user['username'] . ",";
    }
    if ($mybb->settings['scenetracker_time_text'] == 0) {
      $time_input_type = "time";
      $input_time_placeholder = "";
      $time_input_name = "scenetracker_time";
    } else {
      $time_input_type = "text";
      $input_time_placeholder = "placeholder=\"z.B. mittags\"";
      $time_input_name = "scenetracker_time_str";
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
  global $db, $mybb, $tid, $fid, $visible, $lang;
  $lang->load("scenetracker");
  $scenetrackeredit = "";
  $time_text = "";
  //Ist das Forum im Ingame bereich? Dann wollen wir den Tracker. 
  if (scenetracker_testParentFid($fid)) {
    $thisuser = intval($mybb->user['uid']);
    $alertsetting_alert = $mybb->settings['scenetracker_alert_alerts'];
    $usersettingIndex = intval($mybb->user['tracker_index']);
    $array_users = array();
    //einstellungen datefeld 
    if ($mybb->settings['scenetracker_time_text'] == 0) {
      //einstellungen Zeit als feste Uhrzeit
      $date = $db->escape_string($mybb->get_input('scenetracker_date')) . " " . $db->escape_string($mybb->get_input('scenetracker_time'));
    } else if ($mybb->settings['scenetracker_time_text'] == 1) {
      //einstellunge Zeit als offenes textfeld
      $date = $db->escape_string($mybb->get_input('scenetracker_date'));
      $time_text = $db->escape_string($mybb->get_input('scenetracker_time_str'));
    }
    $scenetracker_place = $db->escape_string($mybb->get_input('place'));
    $teilnehmer = $mybb->get_input('teilnehmer');
    $trigger = $db->escape_string($mybb->get_input('scenetracker_trigger'));

    //wir wollen nicht, dass das letzte zeichen in Komma ist, also löschen wir es
    if (substr($teilnehmer, -1, 1) == ",") {
      $teilnehmer = substr($teilnehmer, 0, -1);
    }
    //die uids der Teilnehmer bekommen
    $array_users = scenetracker_getUids($teilnehmer);
    if ($visible == 1) {
      $save = array(
        "scenetracker_date" => $date,
        "scenetracker_user" => $db->escape_string($teilnehmer),
        "scenetracker_place" => $scenetracker_place,
        "scenetracker_trigger" => $trigger,
        "scenetracker_time_text" => $time_text
      );
      $db->update_query("threads", $save, "tid='{$tid}'");
    }
    if ($visible == 1) {
      //alle teilnehmer durchgehen
      foreach ($array_users as $uid => $username) {
        if ($uid != $username) {
          $alert_array = array(
            "uid" => $uid,
            "tid" => $tid,
            "type" => "always"
          );
          $db->insert_query("scenetracker", $alert_array);

          //Alerts eingestellungen
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

          //Private Nachricht ist eingestellt
          if ($mybb->settings['scenetracker_alert_pm'] == 1) {
            require_once MYBB_ROOT . "inc/datahandlers/pm.php";
            $pmhandler = new PMDataHandler();
            $profile = get_profile_link($uid);
            $link = get_thread_link($tid);
            $message = $lang->sprintf($lang->scenetracker_newScene_pm, $profile, $link);
            $pm = array(
              "subject" => $lang->scenetracker_newscene_subject,
              "message" => $message,
              "toid" => $uid,
              "fromid" => 1,
              "icon" => "",
              "do" => "",
              "pmid" => "",

            );
            $pm['options'] = array(
              'signature' => '0',
              'savecopy' => '0',
              'disablesmilies' => '0',
              'readreceipt' => '0',
            );

            $pmhandler->set_data($pm);

            if (!$pmhandler->validate_pm()) {
              $pm_errors = $pmhandler->get_friendly_errors();
              return $pm_errors;
            } else {
              $pmhandler->insert_pm();
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
  $scenetrackeredit = "";
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
  global $db, $mybb, $lang, $tid, $thread, $templates, $fid, $pid, $visible;
  $scenetrackeredit = "";
  $lang->load("scenetracker");
  $thisuser = intval($mybb->user['uid']);
  $teilnehmer = $thread['scenetracker_user'];
  $array_users = scenetracker_getUids($teilnehmer);
  $username = $db->escape_string($mybb->user['username']);
  $alertsetting_alert = $mybb->settings['scenetracker_alert_alerts'];
  if (scenetracker_testParentFid($fid)) {
    // füge den charakter, der gerade antwortet hinzu wenn gewollt und noch nicht in der Szene eingetragen
    if ($mybb->get_input('scenetracker_add') == "add") {
      $isthere = $db->simple_select("scenetracker", "*", "uid = {$thisuser} AND tid = {$tid}");
      if (!$db->num_rows($isthere)) {
        $db->write_query("UPDATE " . TABLE_PREFIX . "threads SET scenetracker_user = CONCAT(scenetracker_user, '," . $username . "') WHERE tid = {$tid}");
        if ($visible == 1) {
          $to_add = array(
            "uid" => $thisuser,
            "tid" => $tid,
            "type" => "always"
          );
          $db->insert_query("scenetracker", $to_add);
        }
      }
    }

    if ($visible == 1) {
      foreach ($array_users as $uid => $username) {
        // Alle teilnehmer bekommen
        if ($uid != $username) {
          $type = $db->fetch_array($db->write_query("SELECT type, inform_by FROM " . TABLE_PREFIX . "scenetracker WHERE tid = $tid AND uid = $uid"));
          // Je nach Benachrichtigungswunsch alerts losschicken
          if ($type['type'] == "always") {
            if ($alertsetting_alert == 1) {
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
            }
          } elseif ($type['type'] == "certain" && $type['inform_by'] == $thisuser) {
            $update = array(
              "alert" => 1,
            );
            $db->update_query("scenetracker", $update, "tid = {$tid} AND uid = {$uid}");
            if ($alertsetting_alert == 1) {
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
            }

            //Private Nachricht ist eingestellt
            if ($mybb->settings['scenetracker_alert_pm'] == 1) {
              require_once MYBB_ROOT . "inc/datahandlers/pm.php";
              $pmhandler = new PMDataHandler();
              $profile = get_profile_link($uid);
              $link = get_post_link($pid, $tid);
              $message = $lang->sprintf($lang->scenetracker_newPost_pm, $profile, $link);
              $pm = array(
                "subject" => $lang->scenetracker_newpost_subject,
                "message" => $message,
                "toid" => $uid,
                "fromid" => 1,
                "icon" => "",
                "do" => "",
                "pmid" => "",

              );
              $pm['options'] = array(
                'signature' => '0',
                'savecopy' => '0',
                'disablesmilies' => '0',
                'readreceipt' => '0',
              );

              $pmhandler->set_data($pm);

              if (!$pmhandler->validate_pm()) {
                $pm_errors = $pmhandler->get_friendly_errors();
                return $pm_errors;
              } else {
                $pmhandler->insert_pm();
              }
            }
          } elseif ($uid == $thisuser) {
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
}

/*********************************
 * Thread editieren 
 * Datum oder/und Teilnehmer bearbeiten - Anzeige
 *********************************/
$plugins->add_hook("editpost_end", "scenetracker_editpost");
function scenetracker_editpost()
{
  global $thread, $templates, $db, $lang, $mybb, $templates, $fid, $post_errors, $post, $scenetrackeredit, $postinfo;
  $scenetrackeredit = "";
  if (scenetracker_testParentFid($fid)) {
    if ($mybb->settings['scenetracker_time_text'] == 0) {
      $time_input_type = "time";
      $input_time_placeholder = "";
      $time_input_name = "scenetracker_time";
    } else {
      $time_input_type = "text";
      $input_time_placeholder = "placeholder=\"z.B. mittags\"";
      $time_input_name = "scenetracker_time_str";
    }

    if ($thread['firstpost'] == $mybb->get_input('pid')) {
      $scenetrackeredit = "";
      $date = explode(" ", $thread['scenetracker_date']);
      if ($mybb->get_input('previewpost') || $post_errors) {
        $scenetracker_date = $mybb->get_input('scenetracker_date');

        if ($mybb->settings['scenetracker_time_text'] != 0) {
          $scenetracker_time = $mybb->get_input('scenetracker_time');
        } else {
          $scenetracker_time = $mybb->get_input('scenetracker_time_str');
        }

        $scenetracker_user = $mybb->get_input('teilnehmer');
        $scenetracker_place = $mybb->get_input('place');
        $scenetracker_trigger = $mybb->get_input('scenetracker_trigger');
      } else {
        $scenetracker_date = $date[0];

        if ($mybb->settings['scenetracker_time_text'] == 0) {

          $scenetracker_time = $date[1];
        } else {
          $scenetracker_time = $thread['scenetracker_time_text'];
        }
        if ($thread['scenetracker_user'] == "") {
          $scenetracker_user = "";
        } else {
          $scenetracker_user = $thread['scenetracker_user'] . ",";
        }
        $scenetracker_place = $thread['scenetracker_place'];
        $scenetracker_trigger = $thread['scenetracker_trigger'];
      }
      $teilnehmer_alt =  array_map('trim', explode(",", $thread['scenetracker_user']));
      eval("\$scenetrackeredit = \"" . $templates->get("scenetracker_newthread") . "\";");
    } else { //we're answering to a post.
      $scenetrackeredit = "";

      $teilnehmer = $thread['scenetracker_user'];

      if ($mybb->get_input('previewpost')) {
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
  $scenetrackeredit = "";
  global $db, $mybb, $tid, $pid, $thread, $fid, $post;
  if (scenetracker_testParentFid($fid)) {
    $alertsetting_alert = $mybb->settings['scenetracker_alert_alerts'];
    if ($pid != $thread['firstpost']) {
      if ($mybb->get_input('scenetracker_add')) {
        $insert_array = array(
          "uid" => $post['uid'],
          "tid" => $tid
        );
        $db->insert_query("scenetracker", $insert_array);
        $db->write_query("UPDATE " . TABLE_PREFIX . "threads SET scenetracker_user = CONCAT(scenetracker_user, ', " . $db->escape_string($post['username']) . "') WHERE tid = $tid");
      }
    } else {

      if ($mybb->settings['scenetracker_time_text'] == 0) {
        $time_text = "";
        $date = $db->escape_string($mybb->get_input('scenetracker_date')) . " " . $db->escape_string($mybb->get_input('scenetracker_time'));
      } else if ($mybb->settings['scenetracker_time_text'] == 1) {
        //einstellunge Zeit als offenes textfeld
        $date = $db->escape_string($mybb->get_input('scenetracker_date'));
        $time_text = $db->escape_string($mybb->get_input('scenetracker_time_str'));
      }

      $place = $db->escape_string($mybb->get_input('place'));
      $trigger = $db->escape_string($mybb->get_input('scenetracker_trigger'));
      $teilnehmer_alt = array_map('trim', explode(",",  $thread['scenetracker_user']));
      $teilnehmer_neu = array_filter(array_map('trim', explode(",", $mybb->get_input('teilnehmer'))));

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
        if ($alertsetting_alert == 1) {
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

        //Private Nachricht ist eingestellt
        if ($mybb->settings['scenetracker_alert_pm'] == 1) {
          require_once MYBB_ROOT . "inc/datahandlers/pm.php";
          $pmhandler = new PMDataHandler();
          $profile = get_profile_link($uid);
          $link = get_thread_link($tid);
          $message = $lang->sprintf($lang->scenetracker_newScene_pm, $profile, $link);

          $pm = array(
            "subject" => $lang->scenetracker_newscene_subject,
            "message" => $profile . " " . $message,
            "toid" => $uid,
            "fromid" => 1,
            "icon" => "",
            "do" => "",
            "pmid" => "",

          );
          $pm['options'] = array(
            'signature' => '0',
            'savecopy' => '0',
            'disablesmilies' => '0',
            'readreceipt' => '0',
          );

          $pmhandler->set_data($pm);

          if (!$pmhandler->validate_pm()) {
            $pm_errors = $pmhandler->get_friendly_errors();
            return $pm_errors;
          } else {
            $pmhandler->insert_pm();
          }
        }
      }
      //Build the new String for users and save it
      $to_save_str = implode(",", $new_userfield);
      //wir wollen nicht, dass das letzte zeichen ein Komma ist, also löschen wir es
      if (substr($to_save_str, -1, 1) == ",") {
        $to_save_str = substr($to_save_str, 0, -1);
      }
      $save = array(
        "scenetracker_date" => $date,
        "scenetracker_time_text" => $time_text,
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
  global $thread, $templates, $db, $fid, $scenetrackerforumdisplay, $mybb;

  if (scenetracker_testParentFid($fid)) {

    if ($mybb->settings['scenetracker_time_text'] == 0) {
      $scene_date = date('d.m.Y - H:i', strtotime($thread['scenetracker_date']));
    } else if ($mybb->settings['scenetracker_time_text'] == 1) {
      //einstellunge Zeit als offenes textfeld
      $scene_date = date('d.m.Y ', strtotime($thread['scenetracker_date'])) . " " . $thread['scenetracker_time_text'];
    }

    $userArray = scenetracker_getUids($thread['scenetracker_user']);
    $scene_place = $thread['scenetracker_place'];

    $author = build_profile_link($thread['username'], $thread['uid']);
    $scenetracker_forumdisplay_user = "";
    if ($thread['scenetracker_trigger'] != "") {
      $scenetrigger = "<div class=\"scenetracker_forumdisplay scene_trigger icon  bl-btn bl-btn--info\"> Triggerwarnung: {$thread['scenetracker_trigger']}</div>";
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

/**
 * Anzeige in suchergebnissen 
 * */
$plugins->add_hook("search_results_thread", "scenetracker_search_showtrackerstuff");
function scenetracker_search_showtrackerstuff()
{
  global $thread, $templates, $db, $fid, $sceneinfos, $mybb;
  if (scenetracker_testParentFid($thread['fid'])) {
    // $scene_date = date('d.m.Y - H:i', strtotime($thread['scenetracker_date']));

    if ($mybb->settings['scenetracker_time_text'] == 0) {
      $scene_date = date('d.m.Y - H:i', strtotime($thread['scenetracker_date']));
    } else if ($mybb->settings['scenetracker_time_text'] == 1) {
      //einstellunge Zeit als offenes textfeld
      $scene_date = date('d.m.Y ', strtotime($thread['scenetracker_date'])) . " " . $thread['scenetracker_time_text'];
    }

    $scene_place = $thread['scenetracker_place'];
    $userArray = scenetracker_getUids($thread['scenetracker_user']);

    $author = build_profile_link($thread['username'], $thread['uid']);
    $scenetracker_forumdisplay_user = "";
    if ($thread['scenetracker_trigger'] != "") {
      $scenetrigger = "<div class=\"scenetracker_trigger\"><span style=\"color: var(--alert-color);\"><i class=\"fas fa-circle-exclamation\"></i>Triggerwarnung: {$thread['scenetracker_trigger']}</span></div>";
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
    $sceneinfos =  "<div class=\"sceneinfo-container\">
    <div class=\"scenetracker_date\"><i class=\"fas fa-calendar\"></i>{$scene_date}</div>
    <div class=\"scenetracker_place\"><i class=\"fas fa-map-marker-alt\"></i>{$thread['scenetracker_place']}</div>
    {$scenetrigger}
    <div class=\"scenetracker_user\"><i class=\"fas fa-users\"></i>{$user}</div>
    </div>";
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
  global $thread, $templates, $db, $fid, $tid, $mybb, $lang, $scenetracker_showthread, $scenetracker_showthread_user, $scene_newshowtread, $statusscene_new, $scenetrigger;

  $lang->load("scenetracker");

  if (scenetracker_testParentFid($fid)) {
    $allowclosing = false;
    $thisuser = intval($mybb->user['uid']);
    // $scene_date = date('d.m.Y - H:i', strtotime($thread['scenetracker_date']));

    if ($mybb->settings['scenetracker_time_text'] == 0) {
      $scene_date = date('d.m.Y - H:i', strtotime($thread['scenetracker_date']));
    } else if ($mybb->settings['scenetracker_time_text'] == 1) {
      //einstellunge Zeit als offenes textfeld
      $scene_date = date('d.m.Y ', strtotime($thread['scenetracker_date'])) . " " . $thread['scenetracker_time_text'];
    }

    $scenetracker_date = date('Y-m-d', strtotime($thread['scenetracker_date']));
    $scenetracker_date_thread = date('d.m.Y', strtotime($thread['scenetracker_date']));

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
          $delete = "<a href=\"showthread.php?tid=" . $tid . "&delete=" . $uid . "\">{$lang->scenetracker_delete}</a>";
        }
      } else {
        $user = $username;
        $delete = "";
      }
      eval("\$scenetracker_showthread_user.= \"" . $templates->get("scenetracker_showthread_user") . "\";");
    }
    if ($thread['scenetracker_trigger'] != "") {
      $scenetrigger = "<div class=\"scenetracker__sceneitem scenethread scene_trigger\"><span class=\"scene_trigger__title\">{$lang->scenetracker_triggeredit}</span> {$thread['scenetracker_trigger']}</div>";
    } else {
      $scenetrigger = "";
    }
    if ($allowclosing || $mybb->usergroup['canmodcp'] == 1) {
      if ($thread['closed'] == 1) {
        $mark = "<a href=\"showthread.php?tid=" . $tid . "&scenestate=open\">{$lang->scenetracker_openscene}</a></span>";
        $scenestatus = "<span class=\"scenestate bl-btn bl-btn--scenetracker\">{$lang->scenetracker_closescenestatus} " . $mark;
      } else {
        $mark = "<a href=\"showthread.php?tid=" . $tid . "&scenestate=close\">{$lang->scenetracker_closescene}</a></span>";
        $scenestatus = "<span class=\"scenestate bl-btn bl-btn--scenetracker\">{$lang->scenetracker_openscenestatus} " . $mark;
      }

      if ($mybb->settings['scenetracker_time_text'] == 0) {
        $scenetracker_time = date('H:i', strtotime($thread['scenetracker_date']));
        $time_input_type = "time";
        $input_time_placeholder = "";
        $time_input_name = "scenetracker_time";
      } else {
        $scenetracker_time = $thread['scenetracker_time_text'];

        $time_input_type = "text";
        $input_time_placeholder = "placeholder=\"z.B. mittags\"";
        $time_input_name = "scenetracker_time_str";
      }

      $edit = "
      <div class=\"scenetracker__sceneitem scene_edit icon bl-btn bl-btn--scenetracker\">
      <a onclick=\"$('#sceneinfos{$tid}').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== 'undefined' ? modal_zindex : 9999) }); return false;\" style=\"cursor: pointer;\">{$lang->scenetracker_editinfos}</a>
          <div class=\"modal editscname\" id=\"sceneinfos{$tid}\" style=\"display: none; padding: 10px; margin: auto; text-align: center;\">
             <form action=\"\" id=\"formeditscene\" method=\"post\" >
              <input type=\"hidden\" value=\"{$tid}\" name=\"id\" id=\"id\"/>
                <center><input id=\"teilnehmer\" placeholder=\"{$lang->scenetracker_teilnehmer}\" type=\"text\" value=\"\" size=\"40\"  name=\"teilnehmer\" autocomplete=\"off\" style=\"display: block;\" /></center>
                <div id=\"suggest\" style=\"display:none; z-index:10;\"></div>
                <input type=\"date\" id=\"scenetracker_date\" name=\"scenetracker_date\" value=\"{$scenetracker_date}\" /> 
                <input type=\"{$time_input_type}\" id=\"scenetracker_time\" name=\"{$time_input_name}\" value=\"{$scenetracker_time}\" />
                  <input type=\"text\" name=\"scenetrigger\" id=\"scenetrigger\" placeholder=\"{$lang->scenetracker_trigger}\" value=\"{$scenetriggerinput}\" />
                  <input type=\"text\" name=\"sceneplace\" id=\"sceneplace\" placeholder=\"{$lang->scenetracker_place}\" value=\"{$sceneplace}\" />
                  
            </form><button name=\"edit_sceneinfos\" id=\"edit_sceneinfos\">{$lang->scenetracker_btnsubmit}</button>
            <script src=\"./jscripts/scenetracker.js\"></script>
            <script type=\"text/javascript\" src=\"./jscripts/suggest.js\"></script>

        </div>

      </div>
 ";
    }
    $statusscene_new = "<div class=\"bl-sceneinfos__item bl-sceneinfos__item--status bl-smallfont \">" . $scenestatus . $edit . "</div>";

    eval("\$scenetracker_showthread = \"" . $templates->get("scenetracker_showthread") . "\";");
  }

  //delete a participant
  if ($mybb->get_input('delete')) {
    $uiddelete = intval($mybb->get_input('delete'));
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

  if ($mybb->get_input('scenestate') == "open") {
    scenetracker_scene_change_status(0,  $tid,  $thisuser);
    redirect("showthread.php?tid=" . $tid);
  }
  if ($mybb->get_input('scenestate') == "close") {
    scenetracker_scene_change_status(1,  $tid,  $thisuser);
    redirect("showthread.php?tid=" . $tid);
  }
}

/************
 * Verwaltung der Einstellungen und Szenen im UCP 
 *********** */
$plugins->add_hook("usercp_start", "scenetracker_usercp");
function scenetracker_usercp()
{
  global $mybb, $db, $templates, $lang, $cache, $templates, $themes, $headerinclude, $header, $footer, $usercpnav, $ucp_main_calendarsettings, $scenetracker_ucp_main, $scenetracker_ucp_bit_char, $scenetracker_ucp_bit_chara_new, $scenetracker_ucp_bit_chara_old, $scenetracker_ucp_bit_chara_closed;
  if ($mybb->get_input('action') != "scenetracker") {
    return false;
  }
  $lang->load('scenetracker');

  $hidden = $yes_ind = $no_ind = $yes_rem = $no_rem = $yes_indall =  $no_indall = $move = $status = "";
  $always  = $scenetracker_ucp_bit_chara  = "";

  $sel_s["both"] = $sel_s["closed"] = $sel_m["beides"] = $sel_m["ja"] = $sel_m["nein"] = "";

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
  $days_reminder =  $mybb->settings['scenetracker_reminder'];
  $lang->scenetracker_reminderopt = $lang->sprintf($lang->scenetracker_reminderopt, $days_reminder);

  if ($days_reminder != 0) {
    $ucp_main_reminderopt = "<div class=\"scenefilteroptions__items scenefilteroptions__items--alerts\">
  <form action=\"usercp.php?action=scenetracker\" method=\"post\">
  <fieldset><label for=\"reminder\">{$lang->scenetracker_reminderopt}</label><br/>
  <input type=\"radio\" name=\"reminder\" id=\"reminder_yes\" value=\"1\" {$yes_rem}> 
  <label for=\"index_rem\">Ja</label>
  <input type=\"radio\" name=\"reminder\" id=\"reminder_no\" value=\"0\" {$no_rem}> 
  <label for=\"index_rem\">Nein</label><br />
  <input type=\"submit\" name=\"opt_reminder\" value=\"{$lang->scenetracker_btnsubmit}\" id=\"reminder_button\" />
  </fieldset>
</form>
</div>";
  } else {
    $ucp_main_reminderopt = "";
  }
  if (
    $mybb->settings['scenetracker_calendarview_all'] ||
    $mybb->settings['scenetracker_calendarview_ownall'] ||
    $mybb->settings['scenetracker_calendarview_own']
  ) {
    $setting_calendar = 1;
  }
  $get_calsettings = "";

  // 'scenetracker_calendarview_all' => array(
  if ($setting_calendar != 0) {
    $get_calsettings = $db->fetch_array($db->simple_select("users", "scenetracker_calendar_settings,scenetracker_calendarsettings_big,scenetracker_calendarsettings_mini", "uid = '{$thisuser}'"));
    $calendar_setting_form = "<form action=\"usercp.php?action=scenetracker\" method=\"post\" class=\"scenetracker_cal_setting\">";
    if ($mybb->settings['scenetracker_calendarview_all'] == 1) {
      $setforalls_all = "";
      $setforalls_this = "";
      if ($get_calsettings['scenetracker_calendar_settings'] == 0) {
        $setforalls_all = "";
        $setforalls_this = " CHECKED";
      } else {
        $setforalls_all = " CHECKED";
        $setforalls_this = "";
      }
      $scenetracker_calendarview_all =
        "<fieldset class='scenefilteroptions__items scenefilteroptions__items--alerts'>
			      <label>Settings für alle verbundene Charaktere oder nur diesen?</label><br/>
            <input type=\"radio\" name=\"calendar_setforalls\" id=\"calendar_setforalls_all\" value=\"1\" {$setforalls_all}> <label for=\"calendar_setforalls_all\">alle</label><br />
            <input type=\"radio\" name=\"calendar_setforalls\" id=\"calendar_setforalls_this\" value=\"0\" {$setforalls_this}> <label for=\"calendar_setforalls_this\">diesen</label><br />
            </fieldset>";
      $calendar_setting_form .= $scenetracker_calendarview_all;
    } else {
      $scenetracker_calendarview_all = "";
    }
    if ($mybb->settings['scenetracker_calendarview_ownall'] == 1) {
      $mini_view_all = "";
      $mini_view_all_own = "";
      $mini_view_all_this = "";

      if ($get_calsettings['scenetracker_calendarsettings_mini'] == 0) {
        $mini_view_all = "";
        $mini_view_all_own = "";
        $mini_view_all_this = " CHECKED";
      }

      if ($get_calsettings['scenetracker_calendarsettings_mini'] == 1) {
        $mini_view_all = "";
        $mini_view_all_own = " CHECKED";
        $mini_view_all_this = "";
      }
      if ($get_calsettings['scenetracker_calendarsettings_mini'] == 2) {
        $mini_view_all = " CHECKED";
        $mini_view_all_own = "";
        $mini_view_all_this = "";
      }
      //einstellungen kleiner Kalender
      $scenetracker_calendarview_ownall =  "<fieldset class='scenefilteroptions__items scenefilteroptions__items--alerts'>
			    <label>Mini Kalender: Welche Szenen sollen angezeigt werden? </label><br/>
            <input type=\"radio\" name=\"mini_view\" id=\"mini_view_all\" value=\"2\" {$mini_view_all}> 
            <label for=\"mini_view_all\">Von allen Charakteren des Forums.</label><br />
            <input type=\"radio\" name=\"mini_view\" id=\"mini_view_all_own\" value=\"1\" {$mini_view_all_own}> 
            <label for=\"mini_view_all_own\">Von deinen Charakteren.</label><br />
			      <input type=\"radio\" name=\"mini_view\" id=\"mini_view_all_this\" value=\"0\" {$mini_view_all_this}>
            <label for=\"mini_view_all_this\">Nur von diesem Charakter</label><br />
            </fieldset>";
      $calendar_setting_form .= $scenetracker_calendarview_ownall;
    } else {
      $scenetracker_calendarview_ownall = "";
    }
    //Einstellungen großer Kalender
    if ($mybb->settings['scenetracker_calendarview_own'] == 1) {
      $big_view_all = "";
      $big_view_all_own = "";
      $big_view_all_this = "";

      if ($get_calsettings['scenetracker_calendarsettings_big'] == 0) {
        $big_view_all = "";
        $big_view_all_own = "";
        $big_view_all_this = " CHECKED";
      }
      if ($get_calsettings['scenetracker_calendarsettings_big'] == 1) {
        $big_view_all = "";
        $big_view_all_own = " CHECKED";
        $big_view_all_this = "";
      }
      if ($get_calsettings['scenetracker_calendarsettings_big'] == 2) {
        $big_view_all = " CHECKED";
        $big_view_all_own = "";
        $big_view_all_this = "";
      }

      $scenetracker_calendarview_own = "<fieldset class='scenefilteroptions__items scenefilteroptions__items--alerts'>
			  <label>Großer Kalender: Welche Szenen sollen angezeigt werden? </label><br/>
        <input type=\"radio\" name=\"big_view\" id=\"big_view_all\" value=\"2\" {$big_view_all}> 
        <label for=\"big_view_all\">Von allen Charakteren des Forums.</label><br />
        <input type=\"radio\" name=\"big_view\" id=\"big_view_all_own\" value=\"1\" {$big_view_all_own}> 
        <label for=\"big_view_all_own\">Von deinen Charakteren.</label><br />
			  <input type=\"radio\" name=\"big_view\" id=\"big_view_all_this\" value=\"0\" {$big_view_all_this}>
        <label for=\"big_view_all_this\">Nur von diesem Charakter</label><br />
        </fieldset>";
      $calendar_setting_form .= $scenetracker_calendarview_own;
      $calendar_setting_form .= " <input type=\"submit\" name=\"calendar_settings\" value=\"{$lang->scenetracker_btnsubmit}\" id=\"calsettings_button\" />
        </form>";
    } else {
      $scenetracker_calendarview_own = "";
      $calendar_setting_form = "";
    }

    //Speichern der Kalender Settings
    if ($mybb->get_input('calendar_settings')) {
      $thisuseras_id = $mybb->user['as_uid'];
      //angehangene bekommen
      $chararray = array_keys(scenetracker_get_accounts($thisuser, $thisuseras_id));
      //string zusammensetzen
      $charstring = implode(",", $chararray);

      //inputs ins array speichern
      $save = array(
        "scenetracker_calendar_settings" => $mybb->get_input('calendar_setforalls', MYBB::INPUT_INT),
        "scenetracker_calendarsettings_big" => $mybb->get_input('big_view', MYBB::INPUT_INT),
        "scenetracker_calendarsettings_mini" =>  $mybb->get_input('mini_view', MYBB::INPUT_INT),
      );

      //Nur für diesen Charakter
      if ($mybb->get_input('calendar_setforalls', MYBB::INPUT_INT) == 0) {
        //checken wie die einstellung vorher war, wenn sie für alle war, müssen wir sie, wenn sie nun nur noch für diesen charakter gelten soll auf den neuen input setzen
        $get_calsettings_check = $db->fetch_field($db->simple_select("users", "scenetracker_calendar_settings", "uid = '{$thisuser}'"), "scenetracker_calendar_settings");
        if ($get_calsettings_check == 1) {
          $save2 = array(
            "scenetracker_calendar_settings" => $mybb->get_input('calendar_setforalls', MYBB::INPUT_INT),
          );
          $db->update_query("users", $save2, "uid in ($charstring)");
        }

        $db->update_query("users", $save, "uid='{$thisuser}'");
        redirect("usercp.php?action=scenetracker");
      } else {

        $db->update_query("users", $save, "uid in ($charstring)");
        redirect("usercp.php?action=scenetracker");
      }
    }
  } else {
    $ucp_main_calendarsettings = "";
  }
  //welcher user ist online
  //set as uid
  if (isset($mybb->user['as_uid'])) {
    $asuid = $mybb->user['as_uid'];
  } else {
    $asuid = 0;
  }
  //get all charas of this user
  $charas = scenetracker_get_accounts($mybb->user['uid'], $asuid);
  $charakter = "0";
  if (isset($mybb->input['scenefilter'])) {
    $charakter = intval($mybb->get_input('charakter'));
    $status = $db->escape_string($mybb->get_input('status'));
    $move = $db->escape_string($mybb->get_input('move'));
  }

  if ($charakter == 0) {
    $charasquery = scenetracker_get_accounts($thisuser, $asuid);
    $charastr = "";
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


  $query = "";
  //Status der Szene
  $status_str = $status;

  $solvplugin = $mybb->settings['scenetracker_solved'];

  if ($solvplugin == 1) {
    $solvefield = " threadsolved,";
    $solved_toone = " OR threadsolved=1 ";
    $solved_tozero = " OR threadsolved=0 ";
  }

  // catch error if settings for threadsolved are wrong
  if (!$db->field_exists("threadsolved", "threads")) {
    $solvefield = "";
    $solved_toone = "";
    $solved_tozero = "";
  }

  if ($status == "open") {
    $query .=  " AND (closed = 0 {$solved_tozero} ) ";
  } else if ($status == "closed") {
    $query .=  " AND (closed = 1 {$solved_toone} ) ";
  } else if ($status == "both") {
    $status_str = "open & closed";
    $query .= "";
  } else {
    $status_str = "open";
    $status = "open";
    $query = " AND (closed = 0 {$solved_tozero} ) ";
  }
  $sel_s[$status] = "SELECTED";

  //Dran oder nicht? 
  $move_str = $move;

  if ($move != "ja" || $move != "nein") {
    $move_str = "beides";
    $query .= "";
  }

  $selectchara = "<select name=\"charakter\" id=\"charakter\">
    <option value=\"0\">{$lang->scenetracker_select_allChars}</option>";
  foreach ($charas as $uid_sel => $username) {
    if ($uid_sel == $charakter) {
      $charsel_[$charakter] = "SELECTED";
    } else {
      $charsel_[$charakter] = "";
    }
    $selectchara .=  "<option value=\"{$uid_sel}\" {$charsel_[$charakter]} >{$username}</option>";
  }
  $selectchara .= "</select>";

  $users_options_bit = "<option value=\"0\">{$lang->scenetracker_select_another}</option>
  <option value=\"-1\">{$lang->scenetracker_select_never}</option>";
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
      $charname_str = $lang->scenetracker_showstring_all;
    } else {
      $charname_str = $charname;
    }

    $writequery = "
    SELECT s.*,
      fid, subject, dateline, lastpost, lastposter, 
      lastposteruid, closed, {$solvefield} 
      scenetracker_date, scenetracker_user, scenetracker_place, scenetracker_trigger
      FROM " . TABLE_PREFIX . "scenetracker  s LEFT JOIN 
      " . TABLE_PREFIX . "threads t on s.tid = t.tid WHERE 
      s.uid = {$uid}
      " . $query . $querymove . "  
      ORDER by uid ASC, lastpost DESC";

    $scenes = $db->write_query($writequery);
    $cnt += $db->num_rows($scenes);
    $chara_cnt = $db->num_rows($scenes);

    $scenes_title = $lang->sprintf($lang->scenetracker_showstring, $charname_str, $status_str, $move_str, $cnt);

    // $scenes_title = "Deine Szenen ({$charname_str} - {$status_str} - dran? {$move_str} - #{$cnt})";

    if ($db->num_rows($scenes) == 0) {
      $tplcount = 0;
    } else {
      $tplcount = 1;

      $charaname = build_profile_link($charname, $uid);
      if ($charakter == 0) {
        $charaname .= $lang->sprintf($lang->scenetracker_showstring_characount, $chara_cnt);
      }
      $scenetracker_ucp_bit_scene = "";
      while ($data = $db->fetch_array($scenes)) {
        $statusofscene = $db->fetch_array($db->write_query("SELECT s.*, t.lastposteruid FROM " . TABLE_PREFIX . "scenetracker s INNER JOIN " . TABLE_PREFIX . "threads t ON s.tid = t.tid WHERE s.tid = {$data['tid']} AND s.uid = {$uid}"));

        if ($statusofscene['type'] == "always" && $statusofscene['lastposteruid'] != $uid) {
          $statusclass = "<span class=\"yourturn\">{$lang->scenetracker_yourturn}</span>";
        } else if ($statusofscene['type'] == "certain" && $statusofscene['lastposteruid'] == $statusofscene['inform_by']) {
          $statusclass = "<span class=\"yourturn\">{$lang->scenetracker_yourturn}</span>";
        } else {
          $statusclass = "";
        }
        $edit = "";
        $alert = $lang->scenetracker_alert;

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
        if ($mybb->settings['scenetracker_time_text'] == 0) {
          $scenedate = date('d.m.Y - H:i', strtotime($data['scenetracker_date']));
        } else if ($mybb->settings['scenetracker_time_text'] == 1) {
          //einstellunge Zeit als offenes textfeld
          $scenedate = date('d.m.Y ', strtotime($data['scenetracker_date'])) . " " . $data['scenetracker_time_text'];
        }

        // $scenedate = date('d.m.Y H:i', strtotime($data['scenetracker_date']));
        $lastposterlink = '<a href="member.php?action=profile&uid=' . $lastposter['uid'] . '">' .  $lastposter['username'] . '</a>';
        $users = $sceneusers = str_replace(",", ", ", $data['scenetracker_user']);
        $sceneplace = $data['scenetracker_place'];
        if ($alerttype == 'certain') {
          $info = get_user($data['inform_by']);
          $alertclass = "certain";
          $username = build_profile_link($info['username'], $data['inform_by']);
          $alerttype =  $username;
        } else if ($alerttype == 'always') {
          $alerttype = $lang->scenetracker_alerttypealways;
          $alertclass = "always";
        } else if ($alerttype == 'never') {
          $alerttype = $lang->scenetracker_alerttypenever;
          $alertclass = "never";
        }
        $scene = '<a href="showthread.php?tid=' . $data['tid'] . '&action=lastpost" class="scenelink">' . $data['subject'] . '</a>';
        if ($data['profil_view'] == 1) {
          $hide = "{$lang->scenetracker_displaystatus_shown} <a href=\"usercp.php?action=scenetracker&showsceneprofil=0&getsid=" . $id . "\"><i class=\"fas fa-toggle-on\"></i></a>";
        } else {
          $hide = "{$lang->scenetracker_displaystatus_hidden} <a href=\"usercp.php?action=scenetracker&showsceneprofil=1&getsid=" . $id . "\"><i class=\"fas fa-toggle-off\"></i></a></a>";
        }
        if ($data['closed'] == 1) {
          $close = "{$lang->scenetracker_sceneisclosed} <a href=\"usercp.php?action=scenetracker&closed=0&getsid=" . $id . "&gettid=" . $tid . "&getuid=" . $uid . "\"><i class=\"fas fa-unlock\"></i></a>";
        } else {
          $close = "{$lang->scenetracker_sceneisopen} <a href=\"usercp.php?action=scenetracker&closed=1&getsid=" . $id . "&gettid=" . $tid . "&getuid=" . $uid . "\"><i class=\"fas fa-lock\"></i></a>";
        }

        if ($data['type'] == 'certain' && $info_by != 0) {
          $users_options_bit = "<option value=\"0\">{$lang->scenetracker_select_another}</option>
                                <option value=\"-1\">{$lang->scenetracker_select_never}</option>";
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

        eval("\$scenetracker_ucp_bit_scene .= \"" . $templates->get('scenetracker_ucp_bit_scene') . "\";");
      }
    }
    if ($tplcount == 1) {
      eval("\$scenetracker_ucp_bit_chara .=\"" . $templates->get("scenetracker_ucp_bit_chara") . "\";");
    }
  }

  //Save Settings of user
  if ($mybb->get_input('opt_index')) {
    $index = $mybb->get_input('index');
    foreach ($charas as $uid => $chara) {
      $db->query("UPDATE " . TABLE_PREFIX . "users SET tracker_index = " . $index . " WHERE uid = " . $uid . " ");
    }
    redirect('usercp.php?action=scenetracker');
  }

  //Save Settings of user
  if ($mybb->get_input('opt_indexall')) {
    $index = $mybb->get_input('indexall');
    $db->query("UPDATE " . TABLE_PREFIX . "users SET tracker_indexall = " . $index . " WHERE uid = " . $mybb->user['uid'] . " ");
    redirect('usercp.php?action=scenetracker');
  }

  //Save Settings of user
  if ($mybb->get_input('opt_reminder')) {
    $reminder = $mybb->get_input('reminder');
    foreach ($charas as $uid => $chara) {
      $db->query("UPDATE " . TABLE_PREFIX . "users SET tracker_reminder = " . $reminder . " WHERE uid = " . $uid . " ");
    }
    redirect('usercp.php?action=scenetracker');
  }

  if ($mybb->get_input('certainuser')) {
    //info by
    $certained = intval($mybb->get_input('charakter'));
    //for which scene
    $id = intval($mybb->get_input('getid'));
    scenetracker_scene_inform_status($id, $certained);
    redirect('usercp.php?action=scenetracker');
  }

  if ($mybb->get_input('showsceneprofil') == "0") {
    $id = intval($mybb->get_input('getsid'));
    $uid = $db->fetch_field($db->simple_select("scenetracker", "uid", "id = $id"), "uid");
    scenetracker_scene_change_view(0, $id, $uid);
    redirect('usercp.php?action=scenetracker');
  } elseif ($mybb->get_input('showsceneprofil') == "1") {
    $id = intval($mybb->get_input('getsid'));
    $uid = $db->fetch_field($db->simple_select("scenetracker", "uid", "id = $id"), "uid");
    scenetracker_scene_change_view(1, $id, $uid);
    $uid = $db->fetch_field($db->simple_select("scenetracker", "uid", "id = $id"), "uid");
    redirect('usercp.php?action=scenetracker');
  }

  if ($mybb->get_input('closed') == "1") {
    $tid = intval($mybb->get_input('gettid'));
    $uid = intval($mybb->get_input('getuid'));
    scenetracker_scene_change_status(1,  $tid,  $uid);
    redirect('usercp.php?action=scenetracker');
  } elseif ($mybb->get_input('closed') == "0") {
    $tid = intval($mybb->get_input('gettid'));
    $uid = intval($mybb->get_input('getuid'));
    scenetracker_scene_change_status(0,  $tid,  $uid);
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
  date_default_timezone_set('Europe/Berlin');
  setlocale(LC_ALL, 'de_DE.utf8', 'de_DE@euro', 'de_DE', 'de', 'ge');
  $ingame =  $mybb->settings['scenetracker_ingame'];
  $archiv = $mybb->settings['scenetracker_archiv'];
  if ($ingame == '') $ingame = "0";
  if ($archiv == '') $archiv = "0";

  $allowmanage = scenetracker_check_switcher($userprofil);
  $show_monthYear = array();
  // $sort = $mybb->settings['scenetracker_profil_sort'];

  if ($mybb->settings['scenetracker_solved'] == 1) {
    $solved = ", threadsolved";
  }

  // catch error if settings for threadsolved are wrong
  if (!$db->field_exists("threadsolved", "threads")) {
    $solved = "";
  }

  //hide scene in profile
  if ($mybb->get_input('show') == "0") {
    $id = intval($mybb->get_input('getsid'));
    scenetracker_scene_change_view(0, $id, $userprofil);
    redirect('member.php?action=profile&uid=' . $userprofil);
  }
  //close scene from profile
  if ($mybb->get_input('closed') == "1") {
    $tid = intval($mybb->get_input('gettid'));
    scenetracker_scene_change_status(1,  $tid,  $userprofil);
    redirect('member.php?action=profile&uid=' . $userprofil);
  } elseif ($mybb->get_input('closed') == "0") {
    $tid = intval($mybb->get_input('gettid'));
    scenetracker_scene_change_status(0,  $tid,  $userprofil);
    redirect('member.php?action=profile&uid=' . $userprofil);
  }

  //wenn alle foren bei ingame ausgewählt sind oder keins (weil keins macht keinen sinn), alle foren zeigen. 
  //archiv-> auch immer anzeigen weil inkludiert in 'alle foren' 
  //Wir brauchen keine Einschränkung
  if (($ingame == "") || ($ingame == "-1") || ($archiv == "-1")) {

    $forenquerie = "";
  } else {

    //ingame -> foren ausgewählt & archiv foren ausgewählt
    $ingamestr = "";
    if ($ingame != "") {
      //ein array mit den fids machen

      $ingameexplode = explode(",", $ingame);
      foreach ($ingameexplode as $ingamefid) {
        //wir basteln unseren string fürs querie um zu schauen ob das forum in der parentlist (also im ingame ist)
        $ingamestr .= " concat(',',parentlist,',') LIKE '%," . $ingamefid . ",%' OR ";
        // $ingamestr .= "$ingamefid in (parentlist) OR ";
      }
    }

    //wenn kein archiv mehr folgt, das letzte OR rauswerfen
    if ($archiv == "" || $archiv == "-1") {
      $ingamestr = substr($ingamestr, 0, -3);
    }

    $archivstr = "";
    if ($archiv != "") {

      $archivexplode = explode(",", $archiv);
      foreach ($archivexplode as $archivfid) {
        $archivstr .= " concat(',',parentlist,',') LIKE '%," . $archivfid . ",%' OR ";
      }
      // das letzte OR rauswerfen
      $archivstr = substr($archivstr, 0, -3);
    }
    $forenquerie = " AND ($ingamestr $archivstr) ";
  }

  $scene_query = $db->write_query("
          SELECT s.*,t.fid, parentlist, subject, dateline, t.closed as threadclosed, 
          scenetracker_date, scenetracker_user, scenetracker_place, scenetracker_trigger" . $solved . " FROM " . TABLE_PREFIX . "scenetracker s, 
          " . TABLE_PREFIX . "threads t LEFT JOIN " . TABLE_PREFIX . "forums fo ON t.fid = fo.fid 
          WHERE t.tid = s.tid AND s.uid = " . $userprofil . "  
          $forenquerie AND s.profil_view = 1 ORDER by scenetracker_date DESC;
  ");

  $date_flag = "1";
  while ($scenes = $db->fetch_array($scene_query)) {
    $scenes['threadsolved'] = "";
    if ($solved == "") {
      $scenes['threadsolved'] = $scenes['threadclosed'];
    }

    $tid = $scenes['tid'];
    $sid = $scenes['id'];
    $subject = $scenes['subject'];
    $sceneusers = str_replace(",", ", ", $scenes['scenetracker_user']);
    $sceneplace = $scenes['scenetracker_place'];
    if ($scenes['scenetracker_trigger'] != "") {
      $scenetrigger = "<div class=\"scenetracker__sceneitem scene_trigger icon bl-btn bl-btn--info \">Triggerwarnung: {$scenes['scenetracker_trigger']}</div>";
    } else {
      $scenetrigger = "";
    }
    if ($scenes['threadclosed'] == 1 or $scenes['threadsolved'] == 1) {
      if ($allowmanage || $mybb->usergroup['canmodcp'] == 1) {
        $scenestatus = "<a href=\"member.php?action=profile&uid=" . $userprofil . "&closed=0&gettid=" . $tid . "\" data-id=\"#trackeropen\" data-tooltip=\"Szene öffnen\" data-position=\"top\" \"><i class=\"fas fa-check-circle\"></i></a>";
      } else {
        $scenestatus = "<i class=\"fas fa-check-circle\"></i>";
      }
    } else {
      if ($allowmanage || $mybb->usergroup['canmodcp'] == 1) {
        $scenestatus = "<a href=\"member.php?action=profile&uid=" . $userprofil . "&closed=1&gettid=" . $tid . "\" data-id=\"#trackerclose\" data-tooltip=\"Szene schließen\" data-position=\"top\" \"><i class=\"fas fa-times-circle\"></i></a>";
      } else {
        $scenestatus = "<i class=\"fas fa-times-circle\"></i>";
      }
    }

    if ($allowmanage || $mybb->usergroup['canmodcp'] == 1) {
      $scenehide = "<a href=\"member.php?action=profile&uid=" . $userprofil . "&show=0&getsid=" . $sid . "\" data-id=\"#trackerhide\" data-tooltip=\"Szene ausblenden\" data-position=\"top\" \"><i class=\"fas fa-eye-slash\"></i></a>";
    } else {
      $scenehide = "";
    }

    $scenedate = date('d.m.Y - H:i', strtotime($scenes['scenetracker_date']));
    if ($dateYear != date('m.Y', strtotime($scenes['scenetracker_date']))) {
      $scenedatetitle = date('F Y', strtotime($scenes['scenetracker_date']));
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
  global $templates, $db, $mybb, $scenetracker_index_main, $scenetracker_index_bit_chara, $expthead, $expcolimage, $expaltext, $expaltext, $expdisplay, $theme;
  //set as uid
  if (isset($mybb->user['as_uid'])) {
    $asuid = $mybb->user['as_uid'];
  } else {
    $asuid = 0;
  }
  $uid = $mybb->user['uid'];
  $index = $db->fetch_field($db->simple_select("users", "tracker_index", "uid = $uid"), "tracker_index");
  $index_all = $db->fetch_field($db->simple_select("users", "tracker_indexall", "uid = $uid"), "tracker_indexall");

  if ($uid != 0 && $index == 1) {
    if ($index_all == 1) {
      $charas = scenetracker_get_accounts($mybb->user['uid'], $asuid);
    } else {
      $charas[$uid] = $mybb->user['username'];
      // $charas[$uid] = $users['username'];
    }
    $solvplugin = $mybb->settings['scenetracker_solved'];
    //show new scenes
    scenetracker_get_scenes($charas, "index");

    //change inform status always/never/certain user
    if ($mybb->get_input('certainuser')) {
      //info by
      $certained = intval($mybb->get_input('charakter'));
      //for which scene
      $id = intval($mybb->get_input('getid'));
      scenetracker_scene_inform_status($id, $certained);
      redirect('index.php#closepop');
    }

    //change status of scenes
    if ($mybb->get_input('closed') == "1") {
      $tid = intval($mybb->get_input('gettid'));
      $uid = intval($mybb->get_input('getuid'));
      scenetracker_scene_change_status(1,  $tid,  $uid);
      redirect('index.php');
    } elseif ($mybb->get_input('closed') == "0") {
      $tid = intval($mybb->get_input('gettid'));
      $uid = intval($mybb->get_input('getuid'));
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
  $scenetracker_index_reminder_bit = "";
  $uid = $mybb->user['uid'];

  //set as uid
  if (isset($mybb->user['as_uid'])) {
    $asuid = $mybb->user['as_uid'];
  } else {
    $asuid = 0;
  }
  $reminder = $db->fetch_field($db->simple_select("users", "tracker_reminder", "uid = $uid"), "tracker_reminder");

  $solvefield = $mybb->settings['scenetracker_solved'];


  if ($solvefield == 1 && $db->field_exists("threadsolved", "threads")) {
    $solvefield = " threadsolved,";
    $solved_toone = " AND threadsolved = 1 ";
    $solved_tozero = " AND threadsolved = 0 ";
  }

  // catch error if settings for threadsolved are wrong
  if (!$db->field_exists("threadsolved", "threads")) {
    $solvefield = "";
    $solved_toone = "";
    $solved_tozero = "";
  }

  if ($uid != 0 && $reminder == 1) {

    // Alle Charaktere des Users holen
    $charas = scenetracker_get_accounts($mybb->user['uid'], $asuid);
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
           {$solved_tozero}
            AND closed= 0 ORDER by st.uid"
      );
      while ($scenes = $db->fetch_array($scenetracker_get_scenes)) {

        $today = new DateTime();

        $postdate = new DateTime();
        $postdate->setTimestamp($scenes['lastpost']);

        $interval = $postdate->diff($today);


        $lastpostdays = $interval->days;
        if ($lastpostdays >= $days) {

          $userarr = get_user($uid);
          if (($scenes['type'] == 'always') || ($scenes['type'] == 'never')) {
            $cnt = 1;
            if ($scenes['index_view'] == 1) {
              eval("\$scenetracker_index_reminder_bit .=\"" . $templates->get("scenetracker_index_reminder_bit") . "\";");
            }
          }
          if ($scenes['type'] == 'certain' &&  ($scenes['lastposteruid'] == $scenes['inform_by'])) {
            if ($scenes['index_view'] == 1) {
              $cnt = 1;
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

  if ($mybb->get_input('action') == 'reminder') {
    // echo "bla";
    foreach ($charas as $uid => $chara) {
      $db->query("UPDATE " . TABLE_PREFIX . "users SET tracker_reminder = 0 WHERE uid = " . $uid . " ");
    }
    echo "<script>alert('Die Anzeige kannst du in deinem UCP wieder anstellen.')
    window.location = './index.php';</script>";
  }
}


/***
 * shows Scenes and events and birthdays in calendar
 * /***
 * Darstellung der Szenen im mybb Kalender 
 * ACHTUNG! diese Funktion geht nur, wenn die Anleitung zum Hinzufügen des Hakens für die Funktion
 * in der Readme befolgt wird!
 * Am Besten über Patches lösen -> calendar.php
 * suchen nach eval("\$day_bits .= \"".$templates->get("calendar_weekrow_thismonth")."\";");
 * darüber einfügen $plugins->run_hooks("calendar_weekview_day");
 */

$plugins->add_hook("calendar_weekview_day", "scenetracker_calendar");
function scenetracker_calendar()
{
  global $db, $mybb, $day, $month, $year, $scene_ouput, $birthday_ouput, $teilnehmer_scene, $plotoutput;
  $thisuser = $mybb->user['uid'];

  if (isset($mybb->user['as_uid'])) {
    $thisuseras_id = $mybb->user['as_uid'];
  } else {
    $thisuseras_id = 0;
  }
  $username = $mybb->user['username'];

  $showownscenes = 1;

  //Nur eigenen Szenen anzeigen
  //alle szenen anzeigen
  // 0 Szenen des Charas der online ist
  // 1 Szenen aller eignen Charas
  // 2 Szenen aller Charas des Boars


  //get day and month mit null bitte
  $daynew = sprintf("%02d", $day);
  $monthzero  = sprintf("%02d", $month);

  //wir müssen das Datum in das gleiche format wie den geburtstag bekommen
  $datetoconvert = "{$daynew}.{$monthzero}.{$year}";
  $timestamp = strtotime($datetoconvert);
  // echo $converteddate;
  $setting_birhtday = $mybb->settings['scenetracker_birhday'];
  if ($setting_birhtday == "0") {
    $converteddate = date("d.m", $timestamp);
    $setting_fid = $mybb->settings['scenetracker_birhdayfid'];
    $get_birthdays = $db->write_query("
      SELECT username, uid FROM " . TABLE_PREFIX . "userfields LEFT JOIN " . TABLE_PREFIX . "users ON ufid = uid WHERE fid" . $setting_fid . " LIKE '{$converteddate}%'");
    $birth_num = $db->num_rows($get_birthdays);
  } elseif ($setting_birhtday == "1") {
    // 9-4-1987
    $converteddate = date("j-n", $timestamp);
    //convert date setting_fid 
    $get_birthdays = $db->write_query("
      SELECT username, uid FROM " . TABLE_PREFIX . "users WHERE birthday LIKE '{$converteddate}%'");
    // $scenedatetitle = date('%d.%m.%Y', strtotime($scenes['scenetracker_date']));
    $birth_num = $db->num_rows($get_birthdays);
  } elseif ($setting_birhtday == "3") {
    //application ucp
    $converteddate = date("m-d", $timestamp);
    $identifier = $mybb->settings['scenetracker_birhdayfid'];
    $feldid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '{$identifier}'"), "id");
    $get_birthdays = $db->write_query("SELECT uf.uid, username FROM " . TABLE_PREFIX . "application_ucp_userfields uf 
      LEFT JOIN " . TABLE_PREFIX . "users u ON uf.uid = u.uid 
      WHERE fieldid = '{$feldid}' and value LIKE '%{$converteddate}'");

    $birth_num = $db->num_rows($get_birthdays);
  } else {
    $birth_num = 0;
  }

  //Jules Plottracker ist installiert
  if ($db->table_exists("plots")) {
    $plottracker = 1;
  } else {
    $plottracker = 0;
  }
  $plotoutput = "";
  if ($plottracker == 1) {
    $plotquery =  $db->simple_select("plots", "*", "{$timestamp} BETWEEN startdate AND enddate;");
    while ($plot = $db->fetch_array($plotquery)) {
      $plotoutput = "<a href=\"plottracker.php?action=view&plid={$plot['plid']}\">" . $plot['name'] . "</a>";
    }
  }

  //Einstellungen des Users für Kalender bekommen
  $viewsetting = $db->fetch_field($db->simple_select("users", "scenetracker_calendarsettings_big", "uid='$thisuser'"), "scenetracker_calendarsettings_big");

  if ($viewsetting == 1) {
    // 1 Szenen aller Charas des Users
    $chararray = array_keys(scenetracker_get_accounts($thisuser, $thisuseras_id));
    $charstring = implode(",", $chararray);
    $scene_querie = " AND s.uid in ($charstring) GROUP BY tid";
  } else if ($viewsetting == 2) {
    // alle Szenen aller Charaktere des Forums
    $scene_querie = " GROUP BY tid";
  } else { // viewsetting == 0 -> default nur vom chara von dem man online ist
    // 0 Szenen des Charas der online ist
    $scene_querie = " AND s.uid = '{$thisuser} GROUP BY tid'";
  }

  $scenes = $db->write_query("
        SELECT subject, scenetracker_date, TIME_FORMAT(scenetracker_date, '%H:%i') scenetime, 
        scenetracker_place, scenetracker_user, scenetracker_trigger, s.* 
        FROM " . TABLE_PREFIX . "threads t 
        left join " . TABLE_PREFIX . "scenetracker s ON s.tid = t.tid 
        WHERE scenetracker_date LIKE '{$year}-{$monthzero}-{$daynew}%' 
        {$scene_querie} 
        ");

  $szene = "";
  $scene_in = "";
  $scene_ouput = "";
  $birthday_show = "";
  $birthday_ouput = "";
  $birthday_in = "";
  $poster_array = array();
  $teilnehmer_scene = "";
  if ($db->num_rows($scenes) > 0 || $birth_num > 0) {
    if ($db->num_rows($scenes) > 0) {
      $szene = "<a onclick=\"$('#scene{$day}').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== 'undefined' ? modal_zindex : 9999) }); return false;\" style=\"cursor: pointer;\">[Szenen]</a>";

      $scene_ouput = "{$szene}
      <div class=\"modal\" id=\"scene{$day}\" style=\"display: none; padding: 10px; margin: auto; text-align: center;\">
      
      ";
      $scene_in = "";
      while ($scene = $db->fetch_array($scenes)) {
        $scene_in .= "
        <div class=\"st_calendar\">
          <div class=\"st_calendar__sceneitem scene_date icon\">{$scene['scenetime']}</div>
          <div class=\"st_calendar__sceneitem scene_title icon\"><a href=\"showthread.php?tid={$scene['tid']}\">{$scene['subject']}</a> </div>
          <div class=\"st_calendar__sceneitem scene_place icon\">{$scene['scenetracker_place']}</div>
          <div class=\"st_calendar__sceneitem scene_users icon \">{$scene['scenetracker_user']}</div>
         </div> ";
        $scenearray = explode(",", $scene['scenetracker_user']);
        $poster_array = array_unique(array_merge($scenearray, $poster_array));
      }
      $charlist = implode(", ", $poster_array);
      $teilnehmer_scene = "<details style=\"font-size: 0.8em;\"><summary>von...</summary> 
            <span>{$charlist}</span></details>";
      $scene_ouput .= "{$scene_in}</div>";
    }
    if ($birth_num > 0) {
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

/***
 * shows minicalender 
 * global functions, use {$scenetracker_calendar} for showing calender in Header or Footer
 *  * credit to:
 * https://zellwk.com/blog/calendar-with-css-grid/
 * https://www.schattenbaum.net/php/kalender.php
 * 
 */
$plugins->add_hook('global_intermediate', 'scenetracker_minicalendar');
function scenetracker_minicalendar()
{
  global $db, $mybb, $templates, $scenetracker_calendar;
  $scenetracker_calendar = $scenetracker_calendar_bit = $fullmoon = $ownscene = $birthdaycss = $eventcss = "";
  $startdate_ingame = $mybb->settings['scenetracker_ingametime_tagstart'];
  $enddate_ingame = $mybb->settings['scenetracker_ingametime_tagend'];

  $thisuser = $mybb->user['uid'];
  if (isset($mybb->user['as_uid'])) {
    $thisuseras_id = $mybb->user['as_uid'];
  } else {
    $thisuseras_id = 0;
  }
  $username = $db->escape_string($mybb->user['username']);
  $ingame =  explode(",", str_replace(" ", "", $mybb->settings['scenetracker_ingametime']));
  foreach ($ingame as $monthyear) {
    $ingamelastday = $monthyear . "-" .  sprintf("%02d", $enddate_ingame);
  }
  $ingamefirstday = $ingame[0] . "-" . sprintf("%02d", $startdate_ingame);
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
    // $scenedatetitle = date('%F %Y', strtotime($scenes['scenetracker_date']));
    $kal_title = date('F Y', $kal_datum);
    // $kal_title = strftime("%B %Y", $kal_datum);
    // echo "tes titelt: $kal_title";
    $kal_day = "";
    $kal_day .= "<div class=\"date-grid\"  style=\"grid-template-columns: repeat(7, 1fr);\">";
    //Tage bis zum wochenstart, also wieviele tage der woche sind noch im vormonat
    //(7 - $kal_ende_tag) wieviele tage am ende des monats in der woche 
    for ($i = 1; $i <= $kal_tage_gesamt + ($kal_start_tag - 1) + (7 - $kal_ende_tag); $i++) {
      $kal_anzeige_akt_tag = $i - $kal_start_tag;
      $kal_anzeige_heute_timestamp = strtotime($kal_anzeige_akt_tag . " day", $kal_start_timestamp);
      $kal_anzeige_heute_tag = date("j", $kal_anzeige_heute_timestamp);
      $daynew = sprintf("%02d", $kal_anzeige_heute_tag);

      //Einstellungen des Users für Kalender bekommen
      if ($db->field_exists("scenetracker_calendarsettings_mini", "users")) {
        $viewsetting = $db->fetch_field($db->simple_select("users", "scenetracker_calendarsettings_mini", "uid='$thisuser'"), "scenetracker_calendarsettings_mini");
      }
      if ($viewsetting == 1) {
        // 1 Szenen aller Charas des Users
        $chararray = array_keys(scenetracker_get_accounts($thisuser, $thisuseras_id));
        $charstring = implode(",", $chararray);
        $scene_querie = " AND s.uid in ($charstring) GROUP BY tid";
      } else if ($viewsetting == 2) {
        // alle Szenen des Forums
        $scene_querie = " GROUP BY tid";
      } else { // viewsetting == 0 -> default nur vom chara von dem man online ist
        // 0 Szenen des Charas der online ist
        $scene_querie = " AND s.uid = '{$thisuser} GROUP BY tid'";
      }
      // $scenes = $db->write_query("
      // SELECT *, TIME_FORMAT(scenetracker_date, '%H:%i') scenetime FROM " . TABLE_PREFIX . "threads 
      // WHERE scenetracker_date LIKE '{$monthyear}-{$daynew}%' 
      // {$scene_querie} 
      // ");

      $scenes = $db->write_query("
        SELECT subject, scenetracker_date, TIME_FORMAT(scenetracker_date, '%H:%i') scenetime, 
        scenetracker_place, scenetracker_user, scenetracker_trigger, s.* 
        FROM " . TABLE_PREFIX . "threads t 
        left join " . TABLE_PREFIX . "scenetracker s ON s.tid = t.tid 
        WHERE scenetracker_date LIKE '{$monthyear}-{$daynew}%' 
        {$scene_querie} 
        ");

      // ist der tag im ingamezeitraum
      if ($monthyear . "-" . $daynew >= $ingamefirstday && $monthyear . "-" . $daynew <= $ingamelastday) {
        $ingame = "activeingame";
      } else {
        $ingame = "";
      }
      $datetoconvert = "{$monthyear}-{$daynew}";
      //original date is in format YYYY-mm-dd
      $timestamp = strtotime($datetoconvert);
      $converteddate = date("d.m", $timestamp);

      $setting_birhtday = $mybb->settings['scenetracker_birhday'];

      //Geburtstage anzeige - welches Feld? 
      if ($setting_birhtday == "0") {
        //MYBB Profileld
        $converteddate = date("d.m", $timestamp);
        $setting_fid = $mybb->settings['scenetracker_birhdayfid'];
        $get_birthdays = $db->write_query("
        SELECT username, uid FROM " . TABLE_PREFIX . "userfields LEFT JOIN " . TABLE_PREFIX . "users ON ufid = uid WHERE fid" . $setting_fid . " LIKE '{$converteddate}%'");
        $birth_num = $db->num_rows($get_birthdays);
      } elseif ($setting_birhtday == "1") {
        // MyBB Geburtstagsfeld
        $converteddate = date("j-n", $timestamp);
        $get_birthdays = $db->write_query("
        SELECT username, uid FROM " . TABLE_PREFIX . "users WHERE birthday LIKE '{$converteddate}%'");
        $birth_num = $db->num_rows($get_birthdays);
      } elseif ($setting_birhtday == "3") {
        //application ucp
        $converteddate = date("m-d", $timestamp);
        $identifier = $mybb->settings['scenetracker_birhdayfid'];
        $feldid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '{$identifier}'"), "id");
        $get_birthdays = $db->write_query("SELECT uf.uid, username FROM " . TABLE_PREFIX . "application_ucp_userfields uf 
        LEFT JOIN " . TABLE_PREFIX . "users u ON uf.uid = u.uid 
        WHERE fieldid = {$feldid} and value LIKE '%{$converteddate}'");

        $birth_num = $db->num_rows($get_birthdays);
      } else {
        $birth_num = 0;
      }
      $get_events = $db->write_query(
        "
            SELECT * FROM " . TABLE_PREFIX . "events WHERE '{$timestamp}' BETWEEN starttime and endtime"
      );

      //Jules Plottracker ist installiert
      if ($db->table_exists("plots")) {
        $plottracker = 1;
      } else {
        $plottracker = 0;
      }

      $plotoutput = "";
      if ($plottracker == 1) {
        $plotquery =  $db->simple_select("plots", "*", "{$timestamp} BETWEEN startdate AND enddate;");
        $plotquery_num = $db->num_rows($plotquery);
      } else {
        $plotquery_num = 0;
      }


      if ($kal_anzeige_akt_tag >= 0 and $kal_anzeige_akt_tag < $kal_tage_gesamt) {
        $sceneshow = "";
        $birthdayshow = "";
        $eventshow = "";
        $plotshow = "";

        if ($db->num_rows($scenes) > 0 || $birth_num > 0 || $db->num_rows($get_events) > 0 || $plotquery_num > 0) {
          if ($db->num_rows($scenes) > 0) {
            $sceneshow = "<span class=\"st_mini_scene_title\">Szenen</span>";
            while ($scene = $db->fetch_array($scenes)) {
              $teilnehmer = str_replace(",", ", ", $scene['scenetracker_user']);
              $sceneshow .= "<div class=\"st_mini_scenelink\"><span><span class=\"raquo\">&raquo;</span> <a href=\"showthread.php?tid={$scene['tid']}\">{$scene['subject']}</a></span><span>({$scene['scenetime']} - {$teilnehmer})</span></div>";
              $ownscene = "ownscene";
            }
          }
          if ($birth_num > 0) {
            $birthdayshow = "<span class=\"st_mini_scene_title\">Geburtstage</span>";
            while ($birthday = $db->fetch_array($get_birthdays)) {
              $birthdayshow .= "<div class=\"st_mini_scenelink\">" . build_profile_link($birthday['username'], $birthday['uid']) . "</div>";
              $birthdaycss = "birthdaycal";
            }
          }

          if ($db->num_rows($get_events) > 0) {
            $eventcss = "event";
            $eventshow = "<span class=\"st_mini_scene_title\">Events</span>";

            while ($event = $db->fetch_array($get_events)) {
              if ($event['name'] == "Fullmoon") {
                $fullmoon = "fullmoon";
              } else {
                $fullmoon = "";
              }
              $eventshow .= "<div class=\"st_mini_scenelink \"><a href=\"calendar.php?action=event&eid={$event['eid']}\">{$event['name']}</a></div>";
            }
          }
          if ($plotquery_num > 0) {
            $plotshow = "<span class=\"st_mini_scene_title\">Plots</span>";
            while ($plot = $db->fetch_array($plotquery)) {
              $plotshow .= "<div class=\"st_mini_scenelink plot\"><a href=\"plottracker.php?action=view&plid={$plot['plid']}\">" . $plot['name'] . "</a></div>";
            }
          }
          if ($mybb->user['uid'] != 0) {
            $showpop = "<div class=\"st_mini_scene_show\">
                       {$sceneshow}
                        {$birthdayshow}
                        {$eventshow}
                        {$plotshow}
                      </div>";
          } else {
            $showpop = "";
          }
          $kal_day .= "
            <div class=\"day st_mini_scene {$fullmoon} {$birthdaycss} {$ownscene} {$eventcss} {$ingame}\">
              {$kal_anzeige_heute_tag}
              {$showpop}
          </div>";
          $fullmoon = "";
          $eventcss = "";
          $birthdaycss = "";
          $ownscene = "";
        } else {
          $kal_day .= "<div class=\"day {$ingame}\">" . $kal_anzeige_heute_tag . "</div>";
        }
      } else {
        $kal_day .= "<div class=\"day old\"></div>";
      }
    }
    $kal_day .= "</div>";
    eval("\$scenetracker_calendar_bit .= \"" . $templates->get("scenetracker_calendar_bit") . "\";");
  }
  eval("\$scenetracker_calendar .= \"" . $templates->get("scenetracker_calendar") . "\";");
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
 * Auflistung von allen Szenen 
 */
$plugins->add_hook("misc_start", "scenetracker_misc_list");
function scenetracker_misc_list()
{
  // scenetracker_profil
  // scenetracker_profil_bit	
  // scenetracker_profil_bit_mY
  global $mybb, $db, $templates, $header, $footer, $theme, $headerinclude, $scenes;
  if ($mybb->get_input('action') == "scenelist") {
    $page = "";
    $thisuser = intval($mybb->user['uid']);
    // $userprofil = $memprofile['uid'];
    $scenetracker_profil_bit = "";
    $sort = "0";
    $dateYear = "";
    date_default_timezone_set('Europe/Berlin');
    setlocale(LC_ALL, 'de_DE.utf8', 'de_DE@euro', 'de_DE', 'de', 'ge');
    $ingame =  $mybb->settings['scenetracker_ingame'];
    $archiv = $mybb->settings['scenetracker_archiv'];
    if ($ingame == '') $ingame = "0";
    if ($archiv == '') $archiv = "0";

    // $allowmanage = scenetracker_check_switcher($userprofil);
    $show_monthYear = array();

    if ($mybb->settings['scenetracker_solved'] == 1) {
      $solved = ", threadsolved";
    }

    // catch error if settings for threadsolved are wrong
    if (!$db->field_exists("threadsolved", "threads")) {
      $solved = "";
    }

    //wenn alle foren bei ingame ausgewählt sind oder keins (weil keins macht keinen sinn), alle foren zeigen. 
    //archiv-> auch immer anzeigen weil inkludiert in 'alle foren' 
    //Wir brauchen keine Einschränkung
    if (($ingame == "") || ($ingame == "-1") || ($archiv == "-1")) {

      $forenquerie = "";
    } else {

      //ingame -> foren ausgewählt & archiv foren ausgewählt
      $ingamestr = "";
      if ($ingame != "") {
        //ein array mit den fids machen

        $ingameexplode = explode(",", $ingame);
        foreach ($ingameexplode as $ingamefid) {
          //wir basteln unseren string fürs querie um zu schauen ob das forum in der parentlist (also im ingame ist)
          $ingamestr .= " concat(',',parentlist,',') LIKE '%," . $ingamefid . ",%' OR ";
          // $ingamestr .= "$ingamefid in (parentlist) OR ";
        }
      }

      //wenn kein archiv mehr folgt, das letzte OR rauswerfen
      if ($archiv == "" || $archiv == "-1") {
        $ingamestr = substr($ingamestr, 0, -3);
      }

      $archivstr = "";
      if ($archiv != "") {

        $archivexplode = explode(",", $archiv);
        foreach ($archivexplode as $archivfid) {
          $archivstr .= " concat(',',parentlist,',') LIKE '%," . $archivfid . ",%' OR ";
        }
        // das letzte OR rauswerfen
        $archivstr = substr($archivstr, 0, -3);
      }
      $forenquerie = " AND ($ingamestr $archivstr) ";
    }

    $scene_query = $db->write_query("
          SELECT s.*,t.fid, parentlist, subject, dateline, t.closed as threadclosed, 
          scenetracker_date, scenetracker_user, scenetracker_place, scenetracker_trigger" . $solved . " FROM " . TABLE_PREFIX . "scenetracker s, 
          " . TABLE_PREFIX . "threads t LEFT JOIN " . TABLE_PREFIX . "forums fo ON t.fid = fo.fid 
          WHERE t.tid = s.tid   
          $forenquerie AND s.profil_view = 1 
          GROUP by t.tid
          ORDER by scenetracker_date DESC;
  ");

    $date_flag = "1";
    while ($scenes = $db->fetch_array($scene_query)) {
      $scenes['threadsolved'] = "";
      if ($solved == "") {
        $scenes['threadsolved'] = $scenes['threadclosed'];
      }

      $tid = $scenes['tid'];
      $sid = $scenes['id'];
      $subject = $scenes['subject'];
      $sceneusers = str_replace(",", ", ", $scenes['scenetracker_user']);
      $sceneplace = $scenes['scenetracker_place'];
      if ($scenes['scenetracker_trigger'] != "") {
        $scenetrigger = "<div class=\"scenetracker__sceneitem scene_trigger icon bl-btn bl-btn--info \">Triggerwarnung: {$scenes['scenetracker_trigger']}</div>";
      } else {
        $scenetrigger = "";
      }
      if ($scenes['threadclosed'] == 1 or $scenes['threadsolved'] == 1) {
        $scenestatus = "<i class=\"fas fa-check-circle\"></i> (Szene geschlossen)";
      } else {
        $scenestatus = "";
      }

      $scenedate = date('d.m.Y - H:i', strtotime($scenes['scenetracker_date']));
      if ($dateYear != date('m.Y', strtotime($scenes['scenetracker_date']))) {
        $scenedatetitle = date('F Y', strtotime($scenes['scenetracker_date']));
        eval("\$scenetracker_profil_bit_mY = \"" . $templates->get("scenetracker_profil_bit_mY") . "\";");
        $dateYear = date('m.Y', strtotime($scenes['scenetracker_date']));
      } else {
        $scenetracker_profil_bit_mY = "";
      }
      eval("\$scenetracker_profil_bit .= \"" . $templates->get("scenetracker_profil_bit") . "\";");
    }
    eval("\$scenes= \"" . $templates->get("scenetracker_profil") . "\";");

    eval("\$page = \"" . $templates->get("scenetracker_misc_allscenes") . "\";");
    output_page($page);
  }
}

/**
 *Function for ajax requests (jscripts/scenetracker.js)
 */
$plugins->add_hook('xmlhttp', 'scenetracker_savescene');
function scenetracker_savescene()
{
  global $db, $mybb;
  if ($mybb->get_input('action') == 'xml_st_savescenes') {
    $thisuser = $mybb->user['uid'];
    $date = $db->escape_string($mybb->input['date']);
    $time_text = "";

    if ($mybb->settings['scenetracker_time_text'] == 0) {
      //einstellungen Zeit als feste Uhrzeit
      $date = $db->escape_string($date) . " " . $db->escape_string($mybb->get_input('time'));
    } else if ($mybb->settings['scenetracker_time_text'] == 1) {
      //einstellunge Zeit als offenes textfeld
      $date = $db->escape_string($mybb->get_input('date'));
      $time_text = $db->escape_string($mybb->get_input('time'));
    }

    $scenetracker_place = $db->escape_string($mybb->input['place']);
    $trigger = $db->escape_string($mybb->input['trigger']);
    $tid = intval($mybb->input['id']);
    $teilnehmer = $mybb->input['user'];
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
      "scenetracker_user" => $db->escape_string($user),
      "scenetracker_time_text" => $time_text
    );

    $db->update_query("threads", $update, "tid='{$tid}'");
  }

  if ($mybb->get_input('action') == 'xml_st_getusers') {
    $get_users = $db->query("SELECT username From mybb_users ORDER by username");
    $user = array();
    while ($row = $db->fetch_array($get_users)) {
      $user[] = $row;
    }
    echo json_encode($user, JSON_UNESCAPED_UNICODE);
  }
}



/**************************** */
/*** HELPER FUNCTIONS***/

/**************************** */

/**
 * get all attached account to a given user
 * @param this_user the user whose attached account we want  
 * @param as_uid the uid of mainaaccount of this user or 0 if uid=mainaccount 
 * @return array with uids and names
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

  $solved = $query_open = $query_all = $solvefield = "";
  $solvplugin = $mybb->settings['scenetracker_solved'];
  $cnt_array = array(
    "all" => 0,
    "open" => 0
  );

  if ($solvplugin == 1 && $db->field_exists("threadsolved", "threads")) {
    $solvefield = " threadsolved, ";
    $solved = " OR threadsolved = 0 ";
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
    if (isset($mybb->user['as_uid'])) {
      $uid_as = $mybb->user['as_uid'];
    } else {
      $uid_as = 0;
    }
    $chars = scenetracker_get_accounts($mybb->user['uid'], $uid_as);
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

  // scenetracker_exludedfids
  // ausgeschlossene fids - leertasten rausschmeißen
  $excludedfids = explode(",", str_replace(" ", "", $mybb->settings['scenetracker_exludedfids']));

  //die parents des forums holen in dem wir sind.
  $parents = $db->fetch_field($db->write_query("SELECT CONCAT(',',parentlist,',') as parents FROM " . TABLE_PREFIX . "forums WHERE fid = $fid"), "parents");

  //gewollte foren aus den settings holen
  $ingame = $mybb->settings['scenetracker_ingame'];
  $archiv = $mybb->settings['scenetracker_archiv'];
  //Archiv und ingame zusammenkleben.
  if ($archiv != "" || $archiv == "-1") {
    $ingame .= "," . $archiv;
  }

  //sicher gehen, dass wir nicht ausversehen die falschen foren holen, weil Zahl enthalten ist.
  $parents = "," . $parents . ",";
  // erst mal testen ob ausgeschlossen 
  foreach ($excludedfids as $fid) {
    if (strpos($parents, "," . $fid . ",")) {
      return false;
    }
  }
  //es sollen eh alle foren angezeigt werden
  if (($ingame == "") || ($ingame == "-1") || ($archiv == "-1")) {
    //alle foren, also immer wahr
    return true;
  }
  //array basteln aus parentids für ingame und evt. archiv
  $ingameexplode = explode(",", $ingame);
  //array durchgehen und testen ob gewolltes forum in der parentlist ist.
  foreach ($ingameexplode as $ingamefid) {
    //jetzt holen wir uns die parentliste des aktuellen forums und testen, ob die parentid enthalten ist. wenn ja, dann sind wir richtig
    if (strpos($parents, "," . $ingamefid . ",")) {
      return true;
    }
  }
  //wenn das alles nicht zutrifft, dann nicht in der parentlist
  return false;
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
    $username_query = $db->escape_string($username);
    $uid = $db->fetch_field($db->simple_select("users", "uid", "username='$username_query'"), "uid");
    // deleted user or an other string;
    //we need an unique key in case of there is more than one deleted user -> we use the username
    if ($uid == "") $uid = $username;
    //else key is uid
    $array_user[$uid] = trim($username);
  }
  return $array_user;
}


/**
 * Helper to get Scenes
 * @param arrray of uids
 * @param string template/place to return
 **/
function scenetracker_get_scenes($charas, $tplstring)
{
  global $db, $mybb, $templates, $users_options_bit, $scenetracker_ucp_bit_chara_new, $scenetracker_ucp_bit_chara_old, $scenetracker_ucp_bit_chara_closed, $scenetracker_ucp_bit_scene, $scenetracker_index_bit_chara, $scenetracker_index_bit_scene;
  //fürs select feld, alle usernamen suchen 
  $solvplugin = $mybb->settings['scenetracker_solved'];

  $hidden = $solved = $solvefield = $query = "";

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
    $solvefield = " threadsolved, ";
    if ($tplstring == "closed") {
      $solved = " OR threadsolved = 1 ";
    } else {
      $solved = " OR threadsolved = 0 ";
    }
  }

  //Catch error if settings for threadsolved in acp are wrong
  if (!$db->field_exists("threadsolved", "threads")) {
    $solved = "";
    $solvefield = "";
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
              fid, subject,dateline, lastpost,lastposter, lastposteruid, closed, " . $solvefield . " 
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
  //set as uid
  if (isset($mybb->user['as_uid'])) {
    $asuid = $mybb->user['as_uid'];
  } else {
    $asuid = 0;
  }
  $chars = scenetracker_get_accounts($mybb->user['uid'], $asuid);
  if ($mybb->user['uid'] == 0) return false;

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

  //ist das erledigt/unerledigt programmiert?
  $solvplugin = $mybb->settings['scenetracker_solved'];
  if ($db->field_exists("threadsolved", "threads")) {
    $solvplugin = 1;
  } else {
    $solvplugin = 0;
  }
  //Teilnehmer holen
  $teilnehmer = $db->fetch_field($db->simple_select("threads", "scenetracker_user", "tid={$tid}"), "scenetracker_user");

  //soll geschlossen werden?
  if ($close == 1) {
    //thread schließen
    //prüft ob übergebene id zu dem chara gehört der online ist 
    //-> gesamte teilnehmerliste müsste durchgegangen werden
    if (scenetracker_change_allowed($teilnehmer)) {
      $db->query("UPDATE " . TABLE_PREFIX . "threads SET closed = '1' WHERE tid = " . $tid . " ");
      if ($solvplugin == "1") {
        $db->query("UPDATE " . TABLE_PREFIX . "threads SET threadsolved = '1' WHERE tid = " . $tid . " ");
      }
    }
    $fid = $db->fetch_field($db->simple_select("threads", "fid", "tid = {$tid}"), "fid");
    if ($db->field_exists('archiving_inplay', 'forums')) {
      redirect("misc.php?action=archiving&fid={$fid}&tid={$tid}");
    } else {
      redirect("showthread.php?tid={$tid}");
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
function scenetracker_scene_change_view($hidescene, $id, $uid)
{
  global $db, $mybb;
  //security check, is this user allowes to change entry?
  if (scenetracker_check_switcher($uid)) {
    $db->query("UPDATE " . TABLE_PREFIX . "scenetracker SET profil_view = '" . $hidescene . "' WHERE id = " . $id . " ");
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
        $alertContent['tid']
      );
    }
    /**
     * Initialize the language, we need the variables $l['myalerts_setting_alertname'] for user cp! 
     * and if need initialize other stuff
     * @return void
     */
    public function init()
    {
      $this->lang->load('scenetracker');
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
        $alertContent['pid']
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

###########################
##### ONLINE LOCATION #####
###########################
$plugins->add_hook("fetch_wol_activity_end", "scenetracker_online_activity");

function scenetracker_online_activity($user_activity)
{
  global $parameters, $user;

  $split_loc = explode(".php", $user_activity['location']);
  if (isset($user['location']) && $split_loc[0] == $user['location']) {
    $filename = '';
  } else {
    $filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
  }
  if ($filename == "getusernames") {
    $user_activity['activity'] = "getusernames";
  }
  return $user_activity;
}

$plugins->add_hook("build_friendly_wol_location_end", "scenetracker_online_location");
function scenetracker_online_location($plugin_array)
{
  global $mybb, $theme, $lang;

  if ($plugin_array['user_activity']['activity'] == "getusernames") {
    $plugin_array['location_name'] = "Fügt in einer Szene Teilnehmer hinzu.";
  }

  return $plugin_array;
}
