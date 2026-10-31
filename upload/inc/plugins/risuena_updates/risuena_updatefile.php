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

// gibt zurück, dass die Updatefunktion verfügbar ist (check in den plugins)
function risuenaupdatefile_updateplugin_available()
{
  return true;
}

// gibt die Version zurück, die das Updatefile hat (check in den plugins)
function risuenaupdatefile_updateplugin_version()
{
  return "1.0.0";
}

/**
 * Funktion um zu checken, ob die Version des Updatefiles größer oder gleich der übergebenen Version ist
 * @param string $version - Version die überprüft werden soll
 */
function risuenaupdatefile_updateplugin_require($version)
{
  return version_compare(risuenaupdatefile_updateplugin_version(), $version, ">=");
}

/**
 * Funktion um zu checken, ob die Version des Updatefiles größer oder gleich der übergebenen Version ist
 * @param array $plugin_info - Array mit den Plugininformationen
 */
function risuena_update_check_plugin($plugin_info)
{
  if (!isset($plugin_info['requires_risuena_updatefile'])) {
    return true;
  }

  return risuenaupdatefile_updateplugin_require($plugin_info['requires_risuena_updatefile']);
}

/**
 * Funktion um die Templates hinzuzufügen - einfachere Verwendung für Upgrades 
 * @param array $templates - Array mit den Templates des Plugins
 * @param string $pluginname - Name des Plugins, damit die Ausgabe im ACP besser verständlich ist
 */
function risuenaupdatefile_add_templates($templates, $pluginname = "")
{
  global $db;

  foreach ($templates as $row) {
    $check = $db->num_rows($db->simple_select("templates", "title", "title LIKE '{$row['title']}'"));
    if ($check == 0) {
      $db->insert_query("templates", $row);
      echo "{$pluginname}: Neues Template {$row['title']} wurde hinzugefügt.<br>";
    }
  }
}

/**
 * Funktion um alte Templates des Plugins bei Bedarf zu aktualisieren
 * @param array $updated_templates zu updatende Templates
 * 
 *  * templatename
 * -> welches Template?
 *
 * action
 * -> replace | add | add_before | overwrite
 *
 * change_string
 * -> Stelle im bestehenden Template, an der geändert werden soll
 *
 * action_string
 * -> neuer Inhalt, der eingefügt/ersetzt werden soll
 *
 * simple_check_string
 * -> optional:
 *    einfacher eindeutiger String, an dem erkannt wird,
 *    dass das Update bereits durchgeführt wurde
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
      $old_template_query = $db->simple_select("templates", "tid, sid, template", "title = '" . $update_template['templatename'] . "'");
      //in old template speichern
      while ($old_template = $db->fetch_array($old_template_query)) {
        //wir schließen die Master templates heir aus.
        $templatesetname = $db->fetch_field($db->simple_select("templatesets", "title", "sid = '" . $old_template['sid'] . "' AND sid != -2"), "title");

        // string setzen, der gesucht werden soll um zu testen ob das Update bereits vorhanden ist
        if (!empty($update_template['simple_check_string'])) {
          $check_string = $update_template['simple_check_string'];
        } elseif ($update_template['action'] == 'overwrite') {
          $check_string = $update_template['change_string'];
        } else {
          $check_string = $update_template['action_string'];
        }
        $pattern = risuenaupdatefile_createRegexPattern($check_string);

        // was soll gemacht werden -> replace / add / overwrite
        if ($update_template['action'] == 'replace') {
          // wir ersetzen, wenn pattern nicht gefunden wird
          if (!preg_match($pattern, $old_template['template'])) {
            //soll ersetzt werden -> bei replace string, was bei change_string eingetragen ist
            $pattern_rep = risuenaupdatefile_createRegexPattern($update_template['change_string']);
            //change string mit action string ersetzen
            $template = preg_replace($pattern_rep, $update_template['action_string'], $old_template['template'], -1, $count);

            if ($count > 0) {
              $update_query = array(
                "template" => $db->escape_string($template),
                "dateline" => TIME_NOW
              );
              $db->update_query("templates", $update_query, "tid='" . $old_template['tid'] . "'");
              echo ("Template '{$update_template['templatename']}' in ({$templatesetname} - {$old_template['sid']}) wurde aktualisiert und der Inhalt ersetzt (replace) <br>");
            } else {
              echo ("Kein Treffer für replace in Template '{$update_template['templatename']}' ({$templatesetname} - {$old_template['sid']}) gefunden - evt. musst du " . htmlspecialchars($update_template['action_string']) . " selbst hinzufügen.<br>");
            }
          }
        }

        if ($update_template['action'] == 'add_before') {
          if (!preg_match($pattern, $old_template['template'])) {
            // change_string soll gefunden werden
            $pattern_rep = risuenaupdatefile_createRegexPattern($update_template['change_string']);
            // action string vor den gefundenen string setzen
            $template = preg_replace($pattern_rep, $update_template['action_string'] . '$0', $old_template['template'], -1, $count);
            // Wurde ein string gefunden, dann speichern wir.
            if ($count > 0) {
              $update_query = array(
                "template" => $db->escape_string($template),
                "dateline" => TIME_NOW
              );

              $db->update_query("templates", $update_query, "tid='" . $old_template['tid'] . "'");

              echo "Template {$update_template['templatename']} in ({$templatesetname} - {$old_template['sid']}) wurde aktualisiert.<br>";
            } else {
              echo ("Change-String für 'add_before' in Template {$update_template['templatename']} ({$templatesetname} {$old_template['sid']}) nicht gefunden - evt. musst du " . htmlspecialchars($update_template['action_string']) . " selbst hinzufügen.<br>");
            }
          }
        }

        if ($update_template['action'] == 'add') {
          // dahinter hinzufügen
          if (!preg_match($pattern, $old_template['template'])) {
            //Change string soll gefunden werden
            $pattern_rep = risuenaupdatefile_createRegexPattern($update_template['change_string']);
            //action string an gefundenen string hinzufügen
            $template = preg_replace($pattern_rep, '$0' . $update_template['action_string'], $old_template['template'], -1, $count);
            //wenn gefunden, dann speichern wir
            if ($count > 0) {
              $update_query = array(
                "template" => $db->escape_string($template),
                "dateline" => TIME_NOW
              );
              $db->update_query("templates", $update_query, "tid='" . $old_template['tid'] . "'");
              echo ("Template {$update_template['templatename']} in {$templatesetname}({$old_template['sid']}) wurde aktualisiert und der Inhalt hinzugefügt (add)<br>");
            } else {
              echo ("Change-String für 'add' in Template {$update_template['templatename']} {$templatesetname}({$old_template['sid']}) nicht gefunden - evt. musst du " . htmlspecialchars($update_template['action_string']) . " selbst hinzufügen.<br>");
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
            echo ("Template -overwrite- {$update_template['templatename']} ({$templatesetname} - {$old_template['sid']}) wurde aktualisiert <br>");
          }
        }
      }
    }
  }
}

/**
 * Überschreibt die MyBB Master-Templates (sid = -2)
 * immer komplett mit dem aktuellen Stand aus dem Plugin-Templatearray.
 *
 * @param array $templates Aktuelle Plugin-Templates
 */
function risuenaupdatefile_sync_master_templates($templates)
{
  global $db;

  foreach ($templates as $template) {

    $title = $db->escape_string($template["title"]);

    $master_template = $db->fetch_array($db->simple_select("templates", "tid", "title = '{$title}' AND sid = -2"));

    if (!empty($master_template["tid"])) {

      $update = array(
        "template" => $template["template"],
        "version" => $template["version"] ?? "",
        "dateline" => TIME_NOW
      );

      $db->update_query("templates",  $update,  "tid = '" . (int)$master_template["tid"] . "'");
    } else {

      $insert = array(
        "title" => $template["title"],
        "template" => $template["template"],
        "sid" => -2,
        "version" => $template["version"] ?? "",
        "dateline" => TIME_NOW
      );

      $db->insert_query("templates",  $insert);
    }
  }
  echo "Alle Master-Templates wurden aktualisiert.";
}

// function risuenaupdatefile_normalize($string)
// {
//   return preg_replace('/\s+/', '', trim($string));
// }

/**
 * Funktion um ein pattern für preg_replace zu erstellen
 * und so templates zu vergleichen.
 * @param string - $html html der datei
 * @return string - $pattern für preg_replace zum vergleich
 */
function risuenaupdatefile_createRegexPattern($html)
{
  $html = trim($html);

  // Zwischen Tags beliebigen Whitespace erlauben
  $html = preg_replace('/>\s*</', '>___TAGSPACE___<', $html);

  // Vorhandenen Whitespace flexibel machen
  $html = preg_replace('/\s+/', '___SPACE___', $html);

  // Regex-Sonderzeichen escapen
  $pattern = preg_quote($html, '/');

  // Zwischen Tags darf Whitespace sein oder nicht
  $pattern = str_replace(
    '___TAGSPACE___',
    '\s*',
    $pattern
  );

  // Vorhandener Whitespace: mindestens ein Whitespace
  $pattern = str_replace(
    '___SPACE___',
    '\s+',
    $pattern
  );

  // Whitespace direkt vor > optional erlauben
  $pattern = str_replace(
    '\>',
    '\s*\>',
    $pattern
  );

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
 * Checkt ob es Templates gibt, die noch nicht existieren und hinzugefügt werden müssen
 * @param string $templatename - Array mit den Einstellungen
 */
function risuenaupdatefile_check_templates($templatename)
{
  global $db;
  $templatename = $db->escape_string($templatename);
  $check = $db->num_rows($db->simple_select("templates", "title", "title = '" . $templatename . "' AND sid = '-2'"));
  if ($check == 0) {
    return true;
  }
  return false;
}


function risuenaupdatefile_sync_table(array $schema)
{
  global $db;

  foreach ($schema as $table => $data) {

    //table name schema bauen z.b. (mybb_tablename) 
    $table_name = TABLE_PREFIX . $table;
    //felder aus dem array holen
    $fields = $data['fields'];
    //gibt es einen primary key? wenn nicht null
    $primary = $data['primary'] ?? null;
    //engine aus dem array holen, wenn nicht vorhanden InnoDB
    $engine = $data['engine'] ?? 'InnoDB';
    //sollen tabelle erstellt werden, wenn sie nicht existiert? default true
    $create = $data['create'] ?? true;
    //sollen nicht mehr benötigte spalten gelöscht werden? default false
    $drop_unused = $data['drop_unused'] ?? false;
    //unuses indexes löschen? default false
    $drop_unused_indexes = $data['drop_unused_indexes'] ?? false;
    //wenn gegeben indizes hinzufügen, wenn sie nicht existieren.
    $indexes = $data['indexes'] ?? [];

    //tabelle hinzufügen wenn nötig
    if (!$db->table_exists($table)) {
      if (!$create) {
        continue;
      }
      $sql = [];

      foreach ($fields as $name => $definition) {
        $sql[] = "`{$name}` {$definition}";
      }

      if ($primary) {
        $sql[] = "PRIMARY KEY (`{$primary}`)";
      }
      // Indizes direkt beim Erstellen der Tabelle hinzufügen
      foreach ($indexes as $index) {

        if (!isset($index['columns'])) {
          $columns = (array)$index;
          $unique = false;
        } else {
          $columns = (array)$index['columns'];
          $unique = !empty($index['unique']);
        }

        $name = implode("_", $columns);

        if ($unique) {
          $name .= "_unique";

          $sql[] = "UNIQUE KEY `{$name}` (`"
            . implode("`,`", $columns)
            . "`)";
        } else {
          $sql[] = "KEY `{$name}` (`"
            . implode("`,`", $columns)
            . "`)";
        }
      }
      $db->write_query("CREATE TABLE `{$table_name}` (" . implode(",\n", $sql) . ") ENGINE={$engine} " . $db->build_create_table_collation() . "; ");

      continue;
    }

    //welche spalten gibt es
    $query = $db->write_query("SHOW COLUMNS FROM `{$table_name}`");
    $existing = [];
    while ($column = $db->fetch_array($query)) {
      $existing[$column['Field']] = $column;
    }

    //welche indizes gibt es
    $query = $db->write_query("SHOW INDEX FROM `{$table_name}`");
    $existing_indexes = [];
    while ($index = $db->fetch_array($query)) {
      if ($index['Key_name'] == 'PRIMARY') {
        continue;
      }
      $existing_indexes[$index['Key_name']][] = $index['Column_name'];
    }

    //indizes hinzufügen, wenn sie nicht existieren
    // Indizes hinzufügen, wenn sie noch nicht existieren
    foreach ($indexes as $index) {
      if (!isset($index['columns'])) {
        $columns = (array)$index;
        $unique = false;
      } else {
        $columns = (array)$index['columns'];
        $unique = !empty($index['unique']);
      }

      // Indexnamen aus den Spalten bauen
      $name = implode("_", $columns);

      // Unique-Indizes eindeutiger benennen
      if ($unique) {
        $name .= "_unique";
      }

      // Index existiert noch nicht
      if (!isset($existing_indexes[$name])) {
        $index_type = $unique ? "UNIQUE INDEX" : "INDEX";
        $db->write_query("ALTER TABLE `{$table_name}` ADD {$index_type} `{$name}`	(`" . implode("`,`", $columns) . "`)");
      }
    }

    //fehlende Spalten hinzufügen
    foreach ($fields as $field => $definition) {
      if (!isset($existing[$field])) {
        $db->add_column($table, $field, $definition);
        continue;
      }

      $current = risuenaupdatefile_build_definition($existing[$field]);
      if (strtolower(trim($current)) != strtolower(trim($definition))) {
        $db->modify_column($table, $field, $definition);
      }
    }

    //Felder löschen die nicht mehr benötigt werden
    if ($drop_unused) {
      foreach ($existing as $field => $column) {
        if (!isset($fields[$field])) {
          $db->drop_column($table, $field);
        }
      }
    }
    //Indizes löschen die nicht mehr benötigt werden
    if ($drop_unused_indexes) {
      foreach ($existing_indexes as $name => $columns) {
        $found = false;
        foreach ($indexes as $wanted) {

          if (isset($wanted['columns'])) {
            $wanted_columns = (array)$wanted['columns'];
          } else {
            $wanted_columns = (array)$wanted;
          }

          if ($wanted_columns == $columns) {
            $found = true;
            break;
          }
        }

        if (!$found) {
          $db->write_query("
                ALTER TABLE `{$table_name}`
                DROP INDEX `{$name}`
            ");
        }
      }
    }

    //engine
    $status = $db->fetch_array(
      $db->write_query("SHOW TABLE STATUS LIKE '{$table_name}'")
    );

    if (!empty($status['Engine']) && $status['Engine'] != $engine) {
      $db->write_query("ALTER TABLE `{$table_name}` ENGINE={$engine}");
    }
  }
}

/**
 * Funktion um die Definition einer Spalte zu erstellen
 * @param array $column - Array mit den Spalteninformationen
 * @return string - Definition der Spalte
 */
function risuenaupdatefile_build_definition(array $column)
{
  $definition = strtolower($column['Type']);

  if ($column['Null'] == "NO") {
    $definition .= " not null";
  } else {
    $definition .= " null";
  }

  if ($column['Default'] !== null) {
    if (is_numeric($column['Default'])) {
      $definition .= " default " . $column['Default'];
    } else {
      $definition .= " default '" . $column['Default'] . "'";
    }
  }

  if (stripos($column['Extra'], "auto_increment") !== false) {
    $definition .= " auto_increment";
  }

  return trim($definition);
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

    //existierende indizes der Tabelle holen
    $query = $db->write_query("SHOW INDEX FROM `{$table_name}`");
    $existing_indexes = [];
    while ($index = $db->fetch_array($query)) {
      if ($index['Key_name'] == 'PRIMARY') {
        continue;
      }

      $existing_indexes[$index['Key_name']][] = $index['Column_name'];
    }


    // Felder prüfen
    foreach ($fields as $name => $def) {
      // Gibt es in der existierenden Tabelle das Feld? Wenn nicht -> gib false zurück
      if (!isset($existing[$name])) {
        return false;
      }

      // Typen des Felders vergleichen, wenn etwas anders ist -> gib false zurück
      $current = risuenaupdatefile_build_definition($existing[$name]);
      if (strtolower(trim($current)) != strtolower(trim($def))) {
        return false;
      }
    }
  }

  return true;
}

/**
 * Funktion um zu checken, ob die Tabellenstruktur mit den übergebenen Feldern übereinstimmt
 * @param array $fields - Array mit den Feldern (tablename, fieldname, definition)
 * @param bool $add - Wenn true, werden fehlende Felder hinzugefügt
 * @return array - Array mit zwei Werten: [0] => true/false (ob ein Update nötig ist), [1] => Text mit den fehlenden Feldern
 */
function risuenaupdatefile_check_fields($fields, $add = false)
{
  global $db;
  $needupdate = false;
  $text = "";
  foreach ($fields as $field) {
    if (!$db->table_exists($field['tablename'])) {
      $needupdate = true;
      $text .= "Die Tabelle {$field['tablename']} existiert nicht. <br>";
    } else {
      if (!$db->field_exists($field['fieldname'], $field['tablename'])) {
        if (!isset($field['remove']) || $field['remove'] == false) {
          $needupdate = true;
          $text .= "In der Tabelle {$field['tablename']} existiert das Feld {$field['fieldname']} nicht.<br>";
          if ($add) {
            $db->add_column($field['tablename'], $field['fieldname'], $field['definition']);
            echo "Das Feld {$field['fieldname']} wurde in der Tabelle {$field['tablename']} hinzugefügt.<br>";
          }
        }
      } else {

        if ($field['remove'] == true) {
          $needupdate = true;
          $text .= "In der Tabelle {$field['tablename']} existiert das Feld {$field['fieldname']} und soll entfernt werden.<br>";
          if ($add) {
            $db->drop_column($field['tablename'], $field['fieldname']);
            echo "Das Feld {$field['fieldname']} wurde in der Tabelle {$field['tablename']} entfernt.<br>";
          }
        }
      }
    }
  }
  return $return_array = array($needupdate, $text);
}
