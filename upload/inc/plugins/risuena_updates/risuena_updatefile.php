<?php

/**
 * Updatefile für Risuenas Plugins
 * Wichtige Funktionen, die in allen Plugins gleich sind.
 * Deswegen hier eine Datei
 */

// error_reporting(-1);
// ini_set('display_errors', true);

// require_once MYBB_ROOT . "inc/plugins/risuena_updates/risuena_updatefile.php";


// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
  die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/**
 * Funktion um alte Templates des Plugins bei Bedarf zu aktualisieren
 */
function risuenaupdatefile_replace_templates($updated_templates)
{
  global $db;
  //Wir wollen erst einmal die templates, die eventuellverändert werden müssen
  $update_template_all = $updated_templates;
  if (!empty($update_template_all)) {
    //diese durchgehen
    foreach ($update_template_all as $update_template) {
      //anhand des templatenames holen
      $old_template_query = $db->simple_select("templates", "tid,sid, template", "title = '" . $update_template['templatename'] . "'");
      //in old template speichern
      while ($old_template = $db->fetch_array($old_template_query)) {
        //was soll gefunden werden? das mit pattern ersetzen (wir schmeißen leertasten, tabs, etc raus)

        if ($update_template['action'] == 'replace') {
          $pattern = risuenaupdatefile_createRegexPattern($update_template['action_string']);
        } elseif ($update_template['action'] == 'add') {
          //bei add wird etwas zum template hinzugefügt, wir müssen also testen ob das schon geschehen ist
          $pattern = risuenaupdatefile_createRegexPattern($update_template['action_string']);
        } elseif ($update_template['action'] == 'overwrite') {
          $pattern = risuenaupdatefile_createRegexPattern($update_template['change_string']);
        }

        // was soll gemacht werden -> replace / add / overwrite
        if ($update_template['action'] == 'replace') {
          // wir ersetzen, wenn pattern nicht gefunden wird
          if (!preg_match($pattern, $old_template['template'])) {
            $pattern_rep = risuenaupdatefile_createRegexPattern($update_template['change_string']);
            $template = preg_replace($pattern_rep, $update_template['action_string'], $old_template['template'], -1, $count);

            if ($count > 0) {
              $update_query = array(
                "template" => $db->escape_string($template),
                "dateline" => TIME_NOW
              );
              $db->update_query("templates", $update_query, "tid='" . $old_template['tid'] . "'");
              echo ("Template {$update_template['templatename']} in {$old_template['sid']} wurde aktualisiert und der entsprechende Inhalt ersetzt (replace) <br>");
            } else {
              echo ("Kein Treffer für replace in Template {$update_template['templatename']} (SID: {$old_template['sid']}) gefunden - evt. musst du " . htmlspecialchars($update_template['action_string']) . " selbst hinzufügen.<br>");
            }
          }
        }
        if ($update_template['action'] == 'add') {
          // hinzufügen, nicht ersetzen
          if (!preg_match($pattern, $old_template['template'])) {
            $pattern_rep = risuenaupdatefile_createRegexPattern($update_template['change_string']);
            $template = preg_replace($pattern_rep, $update_template['change_string'] . $update_template['action_string'], $old_template['template'], -1, $count);

            if ($count > 0) {
              $update_query = array(
                "template" => $db->escape_string($template),
                "dateline" => TIME_NOW
              );
              $db->update_query("templates", $update_query, "tid='" . $old_template['tid'] . "'");
              echo ("Template {$update_template['templatename']} in {$old_template['sid']} wurde aktualisiert und der Inhalt hinzugefügt (add)<br>");
            } else {
              echo ("Change-String für 'add' in Template {$update_template['templatename']} (SID: {$old_template['sid']}) nicht gefunden - evt. musst du " . htmlspecialchars($update_template['action_string']) . " selbst hinzufügen.<br>");
            }
          }
        }
        if ($update_template['action'] == 'overwrite') { //komplett ersetzen
          //ist der test string im template, dann ist es schon aktuell
          if (!preg_match($pattern, $old_template['template'])) {
            //wenn nicht ersetzten wirs komplett
            $template = $update_template['action_string'];
            $update_query = array(
              "template" => $db->escape_string($template),
              "dateline" => TIME_NOW
            );
            $db->update_query("templates", $update_query, "tid='" . $old_template['tid'] . "'");
            echo ("Template -overwrite- {$update_template['templatename']} in  {$old_template['tid']} wurde aktualisiert <br>");
          }
        }
      }
    }
  }
}


/**
 * Funktion um ein pattern für preg_replace zu erstellen
 * und so templates zu vergleichen.
 * @return string - pattern für preg_replace zum vergleich
 */
function risuenaupdatefile_createRegexPattern($html)
{
  // Entkomme alle Sonderzeichen und ersetze Leerzeichen mit flexiblen Platzhaltern
  $pattern = preg_quote($html, '/');

  // Ersetze Leerzeichen in `class`-Attributen mit `\s+` (flexible Leerzeichen)
  $pattern = preg_replace('/\s+/', '\\s+', $pattern);

  // Passe das Muster an, um Anfang und Ende zu markieren
  return '/' . $pattern . '/si';
}


/** 
 * Funktion um Stylesheets zu aktualisieren
 * @param string $cssfilename - Name der CSS Datei
 * @param array $update_data_all - Array mit den Update Daten
 */
function risuenaupdatefile_update_stylesheet($cssfilename, $update_data_all)
{
  global $db;
  $theme_query = $db->simple_select('themes', 'tid, name');
  require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

  while ($theme = $db->fetch_array($theme_query)) {
    //array durchgehen mit eventuell hinzuzufügenden strings
    foreach ($update_data_all as $update_data) {
      //hinzuzufügegendes css
      $update_stylesheet = $update_data['stylesheet'];
      //String bei dem getestet wird ob er im alten css vorhanden ist
      $update_string = $update_data['update_string'];
      //updatestring darf nicht leer sein
      if (!empty($update_string)) {
        //checken ob updatestring in css vorhanden ist - dann muss nichts getan werden
        $test_ifin = $db->write_query("SELECT stylesheet FROM " . TABLE_PREFIX . "themestylesheets WHERE tid = '{$theme['tid']}' AND name = '{$cssfilename}.css' AND stylesheet LIKE '%" . $update_string . "%' ");
        //string war nicht vorhanden
        if ($db->num_rows($test_ifin) == 0) {
          //altes css holen
          $oldstylesheet = $db->fetch_field($db->write_query("SELECT stylesheet FROM " . TABLE_PREFIX . "themestylesheets WHERE tid = '{$theme['tid']}' AND name = '{$cssfilename}.css'"), "stylesheet");
          //Hier basteln wir unser neues array zum update und hängen das neue css hinten an das alte dran
          $updated_stylesheet = array(
            "cachefile" => $db->escape_string('{$cssfilename}.css'),
            "stylesheet" => $db->escape_string($oldstylesheet . "\n\n" . $update_stylesheet),
            "lastmodified" => TIME_NOW
          );
          $db->update_query("themestylesheets", $updated_stylesheet, "name='{$cssfilename}.css' AND tid = '{$theme['tid']}'");
          echo "In Theme mit der ID {$theme['tid']} wurde CSS hinzugefügt -  $update_string <br>";
        }
      }
      update_theme_stylesheet_list($theme['tid']);
    }
  }
}
