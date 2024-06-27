<?php
define("IN_MYBB", 1);
require("global.php");
// error_reporting(-1);
// ini_set('display_errors', 1);

global $db, $mybb, $lang;
echo '<html lang="de">
<head>
<meta charset="utf-8"></head><body>';
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
$gid = $db->fetch_field($db->write_query("SELECT gid FROM `" . TABLE_PREFIX . "settings` WHERE name like 'scenetracker%' LIMIT 1;"), "gid");

if ($mybb->usergroup['canmodcp'] == 1) {

  // require_once "inc/plugins/scenetracker.php";

  echo "<h1>Update Script für Szenentracker Plugin November</h1>";
  echo "<p>Updatescript wurde zuletzt im Juni 2024 aktualisiert</p>";

  echo "<p>Das Skript muss nur ausgeführt werden, wenn von einer alten auf eine neue Version geupdatet wird.<br> 
  Bei Neuinstallation, muss hier nichts getan werden!</p>";
  echo "<h2>Neue Templates hinzufügen Nötig wenn unter <b>Version 1.0.4</b>:</h2>";

  echo '<form action="" method="post">';
  echo '<input type="submit" name="update" value="Update durchführen">';
  echo '</form>';

  if (isset($_POST['update'])) {
    scenetracker_add_settings("update");
    rebuild_settings();

    echo "<p>Datenbankfelder durchgehen</p>";
    $dbcheck = 0;

    if (!$db->field_exists("scenetracker_date", "threads")) {
      $db->add_column("threads", "scenetracker_date", "DATETIME NULL DEFAULT NULL");
      echo "Feld scenetracker_date wurde zu threads hinzugefügt.<br>";
      $dbcheck = 1;
    }
    if (!$db->field_exists("scenetracker_place", "threads")) {
      $db->add_column("threads", "scenetracker_place", "varchar(200) NOT NULL DEFAULT ''");
      echo "Feld scenetracker_place wurde zu threads hinzugefügt.<br>";
      $dbcheck = 1;
    }
    if (!$db->field_exists("scenetracker_user", "threads")) {
      $db->add_column("threads", "scenetracker_user", "varchar(1500) NOT NULL DEFAULT ''");
      echo "Feld scenetracker_user wurde zu threads hinzugefügt.<br>";
      $dbcheck = 1;
    }
    if (!$db->field_exists("scenetracker_trigger", "threads")) {
      $db->add_column("threads", "scenetracker_trigger", "varchar(200) NOT NULL DEFAULT ''");
      echo "Feld scenetracker_trigger wurde zu threads hinzugefügt.<br>";
      $dbcheck = 1;
    }
    if (!$db->field_exists("scenetracker_time_text", "threads")) {
      $db->add_column("threads", "scenetracker_time_text", "varchar(200) NOT NULL DEFAULT ''");
      echo "Feld scenetracker_time_text wurde zu threads hinzugefügt.<br>";
      $dbcheck = 1;
    }

    //einfügen der Kalender einstellungen
    if (!$db->field_exists("scenetracker_calendar_settings", "users")) {
      $db->add_column("users", "scenetracker_calendar_settings", "INT(1) NOT NULL DEFAULT '0'");
      echo "Feld scenetracker_calendar_settings wurde zu threads hinzugefügt.<br>";
      $dbcheck = 1;
    }
    if (!$db->field_exists("scenetracker_calendarsettings_big", "users")) {
      $db->add_column("users", "scenetracker_calendarsettings_big", "INT(1) NOT NULL DEFAULT '0'");
      echo "Feld scenetracker_calendar_settings wurde zu threads hinzugefügt.<br>";
      $dbcheck = 1;
    }
    if (!$db->field_exists("scenetracker_calendarsettings_mini", "users")) {
      $db->add_column("users", "scenetracker_calendarsettings_mini", "INT(1) NOT NULL DEFAULT '0'");
      echo "Feld scenetracker_calendar_settings wurde zu threads hinzugefügt.<br>";
      $dbcheck = 1;
    }

    // Einfügen der Trackeroptionen in die user tabelle
    if (!$db->field_exists("tracker_index", "users")) {
      $db->query("ALTER TABLE `" . TABLE_PREFIX . "users` ADD `tracker_index` INT(1) NOT NULL DEFAULT '1';");
      echo "Feld tracker_index wurde zu users hinzugefügt.<br>";
      $dbcheck = 1;
    }
    if (!$db->field_exists("tracker_indexall", "users")) {
      $db->query("ALTER TABLE `" . TABLE_PREFIX . "users` ADD `tracker_indexall` INT(1) NOT NULL DEFAULT '1'");
      echo "Feld tracker_indexall wurde zu users hinzugefügt.<br>";
      $dbcheck = 1;
    }
    if (!$db->field_exists("tracker_reminder", "users")) {
      $db->query("ALTER TABLE `" . TABLE_PREFIX . "users` ADD `tracker_reminder` INT(1) NOT NULL DEFAULT '1';");
      echo "Feld tracker_reminder wurde zu users hinzugefügt.<br>";
      $dbcheck = 1;
    }


    // Neue Tabelle um Szenen zu speichern und informationen, wie die benachrichtigungen sein sollen.
    if (!$db->table_exists("scenetracker")) {
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
      echo "Tabelle scenetracker wurde hinzugefügt hinzugefügt.<br>";
      $dbcheck = 1;
    }

    if ($dbcheck == 0) {
      echo "<p>Datenbank aktuell - keine Felder/Tabellen hinzugefügt.</p>";
    }
  }

  echo "<h2>Neue Templates hinzufügen <b>Version 1.0.6</b>:</h2>";

  echo '<form action="" method="post">';
  echo '<input type="submit" name="templates" value="Neue Templates hinzufügen">';
  echo '</form>';

  if (isset($_POST['templates'])) {
    scenetracker_add_templates("update");
  }
  echo "<h2>Templates Änderungen vornehmen:</h1>";
  echo "<h3>Änderungen für offenes Textfeld Zeit (version: 1.0.2) </h3>";
  echo '<form action="" method="post">';
  echo '<input type="submit" name="change_timetext" value="offenes textfeld Templates updaten">';
  echo '</form>';
  if (isset($_POST['change_timetext'])) {
    include MYBB_ROOT . "/inc/adminfunctions_templates.php";
    find_replace_templatesets("scenetracker_newthread", "#" . preg_quote('<input type="time" name="scenetracker_time" value="{$scenetracker_time}" />') . "#i", '<input type="{$time_input_type}" value="{$scenetracker_time}" name="{$time_input_name}" {$placeholder}/>');
    echo "scenetracker_newthread - wurde aktualisiert ";
  }
  echo "<h3>Änderungen für Überarbeitung Minikalender (version: 1.0.6) </h3>";
  echo '<form action="" method="post">';
  echo '<input type="submit" name="change_minicalender" value="minicalender Templates updaten">';
  echo '</form>';
  if (isset($_POST['change_minicalender'])) {
    include MYBB_ROOT . "/inc/adminfunctions_templates.php";
    find_replace_templatesets("scenetracker_calendar_bit", "#" . preg_quote('    </div>
	 {$kal_day}') . "#i", '{$kal_day}</div>');
    echo "scenetracker_calendar_bit - wurde aktualisiert ('	&lt;/div>&lbrace;&dollar;kal_day&rbrace; ersetzen mit &lbrace;&dollar;kal_day&rbrace;	&lt;/div><br><b>Bitte überprüfen, funktioniert oft nicht, dann händisch ändern!</b> )";
  }

  echo "<h2>Folgende Änderungen müssen manuell durchgeführt werden (vor 1.0.2) (Änderungen in vorhandenen Templates):</h2>";
  echo '<b>scenetracker_ucp_main</b>: Suche nach {$ucp_main_reminderopt} füge darunter ein: {$calendar_setting_form} <br>';

  echo 'zum Stylesheet hinzufügen:<br>
  <textarea style="width: 70%; height: 300px;"> .scenetracker_cal_setting {
    width: 92%;
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 10px;
}

.scenetracker_cal_setting .scenefilteroptions__items {
    width: 100%;
}

.st_mini_scenelink {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    padding-bottom: 4px;
}

.st_mini_scenelink span {
    text-align: center;
}

#calsettings_button {
    grid-column: 1 / -1;
    justify-self: center;
}</textarea><br><br>';

  echo "<h1>CSS hinzufügen version 1.0.6</h1>";

  echo ' <textarea style="width: 70%; height: 300px;">
 .day.st_mini_scene.lastmonth {
    opacity: 0.2;
}
 </textarea> ';

  echo "<h1>CSS Nachträglich hinzufügen?</h1>";
  echo "<p>Nach einem MyBB Upgrade fehlen die Stylesheets? <br> Hier kannst du den Standard Stylesheet neu hinzufügen.</p>";
  echo '<form action="" method="post">';
  echo '<input type="submit" name="css" value="css hinzufügen">';
  echo '</form>';
  if (isset($_POST['css'])) {
    //Stylesheets checken
    $themesids = $db->write_query("SELECT tid FROM `" . TABLE_PREFIX . "themes`");
    echo "CSS zu Masterstyle hinzufügen";
    $check_tid = $db->simple_select("themestylesheets", "*", "tid = '1' AND name = 'scenetracker.css'");

    if ($db->num_rows($check_tid) == 0) {
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


      $sid = $db->insert_query("themestylesheets", $css);
      $db->update_query("themestylesheets", array("cachefile" => "css.php?stylesheet=" . $sid), "sid = '" . $sid . "'", 1);
    }
    require_once "admin/inc/functions_themes.php";
    update_theme_stylesheet_list($theme['tid']);
  }
  echo '<br><br><br>';
  echo '<div style="width:100%; background-color: rgb(121 123 123 / 50%); display: flex; position:fixed; bottom:0;right:0; height:50px; justify-content: center; align-items:center; gap:20px;">
<div> <a href="https://github.com/katjalennartz/scenetracker" target="_blank">Github Rep</a></div>
<div> <b>Kontakt:</b> risuena (Discord)</div>
<div> <b>Support:</b>  <a href="https://storming-gates.de/showthread.php?tid=1023729">SG Thread</a> oder via Discord</div>
</div>';

  echo '</body></html>';
} else {
  echo "<h1>Kein Zugriff</h1>";
}
