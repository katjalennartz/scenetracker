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
 * @param array zu updatende Templates
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
          echo "In Theme mit der ID {$theme['tid']} wurde CSS hinzugefügt -  <div style=\"max-height: 100px; overflow:auto;\">" . htmlentities($update_string) . "</div><br>";
        }
      }
      update_theme_stylesheet_list($theme['tid']);
    }
  }
}
/**
 * Funktion um Einstellungen zu aktualisieren oder hinzuzufügen
 * @param array $setting_array - Array mit den Einstellungen
 * @param string $type - Zu welchem Plugin gehört die Einstellung
 */
function risuenaupdatefile_update_settings($setting_array, $type)
{
  global $db;
  $gid = $db->fetch_field($db->write_query("SELECT gid FROM `" . TABLE_PREFIX . "settinggroups` WHERE name like '$type%' LIMIT 1;"), "gid");

  foreach ($setting_array as $name => $setting) {
    $setting['name'] = $name;
    $setting['gid'] = $gid;

    //alte einstellung aus der db holen
    $check = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "settings` WHERE name = '{$name}'");
    $check2 = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "settings` WHERE name = '{$name}'");
    $check = $db->num_rows($check);

    if ($check == 0) {
      $db->insert_query('settings', $setting);
      echo "$type Setting: {$name} wurde hinzugefügt.";
    } else {

      //die einstellung gibt es schon, wir testen ob etwas verändert wurde
      while ($setting_old = $db->fetch_array($check2)) {
        if (
          $setting_old['title'] != $setting['title'] ||
          stripslashes($setting_old['description']) != stripslashes($setting['description']) ||
          $setting_old['optionscode'] != $setting['optionscode'] ||
          $setting_old['disporder'] != $setting['disporder']
        ) {
          //wir wollen den value nicht überspeichern, also nur die anderen werte aktualisieren
          unset($setting['value']);
          $db->update_query('settings', $setting, "name='{$name}'");
          echo "$type Setting: {$name} wurde aktualisiert.<br>";
        }
      }
    }
  }
  rebuild_settings();
}

/**
 * Vergleicht vorhandene Tabellen mit einem vorgegebenen Schema und aktualisiert(oder installiert) sie entsprechend
 * @param array $schema - Array mit der gewünschten Tabellenstruktur (Feldname => Definition
 */
function risuenaupdatefile_sync_table($schema)
{
  global $db;

  foreach ($schema as $table => $data) {
    // DB Felder
    $fields  = $data['fields'];
    // Primary Key vorhanden? Sonst Null
    $primary = $data['primary'] ?? null;
    // DB Engine
    $engine  = $data['engine'] ?? 'InnoDB';

    $table_name = TABLE_PREFIX . $table;

    //Table existiert nicht, also muss sie erstellt werden
    if (!$db->table_exists($table)) {
      $sql_fields = [];
      //baue string zusammen mit allen feldern und ihren definitionen
      foreach ($fields as $name => $def) {
        $sql_fields[] = "`$name` $def";
      }

      if ($primary) {
        $sql_fields[] = "PRIMARY KEY (`$primary`)";
      }
      // Felder aus dem Array holen und Tabelle erstellen, wenn sie nicht existiert
      $db->write_query("
                CREATE TABLE `$table_name` (
                    " . implode(",\n", $sql_fields) . "
                ) ENGINE=$engine " . $db->build_create_table_collation() . ";
            ");

      continue;
    }

    // Felder der existierenden Tabelle holen
    $query = $db->write_query("SHOW COLUMNS FROM `$table_name`");
    $existing = [];
    //in ein Array packen
    while ($col = $db->fetch_array($query)) {
      $existing[$col['Field']] = $col;
    }

    // durch die Felder des Schemas gehen und mit den existierenden vergleichen
    foreach ($fields as $name => $def) {
      //existiert nicht, also hinzufügen
      if (!isset($existing[$name])) {
        $db->add_column($table, $name, $def);
      } else {
        //existiert, also Felddefinition vergleichen, wenn unterschiedlich -> aktualisieren
        $current = strtolower($existing[$name]['Type']);
        $target  = strtolower(preg_split('/\s+/', $def)[0]);

        if (strpos($current, $target) === false) {
          $db->modify_column($table, $name, $def);
        }
      }
    }

    $status = $db->fetch_array(
      $db->write_query("SHOW TABLE STATUS LIKE '{$table_name}'")
    );

    if (!empty($status['Engine']) && $status['Engine'] != $engine) {
      $db->query("ALTER TABLE `$table_name` ENGINE=$engine");
    }
  }
}
/**
 * Funktion um die Tabellenstruktur mit einem vorgegebenen Schema zu vergleichen
 * @param array $schema - Array mit der gewünschten Tabellenstruktur (Feldname => Definition
 * @return bool - true wenn die Tabellenstruktur mit dem Schema übereinstimmt, false wenn nicht
 */
function risuenaupdatefile_check_schema($schema)
{
  global $db;


  //jedes tabelle im Schhema durchegehn
  foreach ($schema as $table => $data) {
    //Felder einer Tabelle holen
    $fields = $data['fields'];

    //Tabellenname mit Prefix
    $table_name = TABLE_PREFIX . $table;
    //Tabelle existiert gar nicht, also false zurückliefern
    if (!$db->table_exists($table)) {
      return false;
    }

    // existierende Spalten der Tabelle holen
    $query = $db->write_query("SHOW COLUMNS FROM `$table_name`");
    $existing = [];
    //array erstellen mit existierenden Spalten zum späteren Vergleich. 
    while ($col = $db->fetch_array($query)) {
      $existing[$col['Field']] = $col;
    }

    // Felder prüfen
    foreach ($fields as $name => $def) {

      // Gibt es in der existierenden Tabelle das Feld? Wenn nicht -> gib false zurück
      if (!isset($existing[$name])) {
        return false;
      }

      // Typen des Felders vergleichen, wenn etwas anders ist -> gib false zurück
      $current = strtolower($existing[$name]['Type']);
      $target  = strtolower(preg_split('/\s+/', $def)[0]);

      if (strpos($current, $target) === false) {
        return false;
      }
    }
  }

  return true;
}
