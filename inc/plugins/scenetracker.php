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

//error_reporting ( -1 );
//ini_set ( 'display_errors', true );

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

//Hooks die wir brauchen
// $plugins->add_hook("search_results_thread", "trackerdate_forum_search");

//Datenbehandlung
$plugins->add_hook("newthread_start", "scenetracker_newthread");
$plugins->add_hook("newreply_do_newreply_end", "scenetracker_do_newreply");
$plugins->add_hook("editpost_end", "scenetracker_editpost");
$plugins->add_hook("editpost_do_editpost_end", "scenetracker_do_editpost");
$plugins->add_hook("member_profile_start", "scenetracker_showinprofile");
$plugins->add_hook("usercp_menu", "scenetracker_usercpmenu", 50);
$plugins->add_hook("usercp_start", "scenetracker_usercp");

//Anzeige
$plugins->add_hook("forumdisplay_thread", "scenetracker_forumdisplay_showtrackerstuff");
$plugins->add_hook("showthread_start", "scenetracker_showthread_showtrackerstuff");
$plugins->add_hook('index_start', 'scenetracker_list');

function trackerdate_info()
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

function scentracker_is_installed()
{
    global $db;
    if ($db->table_exists("scenetracker")) {
        return true;
    }
    return false;
}

function trackerdate_install()
{
    global $db;
    scentracker_uninstall();
    //TODO DB Sachen installieren
    //Threadtabelle braucht, Feld für Datum, Feld für Teilnehmer
    $db->add_column("threads", "scenetrackerdate", "varchar(200) NOT NULL");
    $db->add_column("threads", "scenetrackermembers", "varchar(200) NOT NULL");
    //new table for saving scenes and notifivation status
    $db->write_query("CREATE TABLE `" . TABLE_PREFIX . "scenetracker` (
        `id` int(10) NOT NULL AUTO_INCREMENT,
        `uid` int(10) NOT NULL,
        `username` varchar(250) NOT NULL,
        `tid` int(10) NOT NULL,
        `closed` int(1) NOT NULL DEFAULT 0,
        `alert` int(1) NOT NULL DEFAULT 1,
        `index` int(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`)
    ) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;");

    // Einstellungen
    $setting_group = array(
        'name' => 'scenetracker',
        'title' => 'Szenentracker',
        'description' => 'Einstellungen für Risus Szenentracker',
        'disporder' => 7, // The order your setting group will display
        'isdefault' => 0
    );
    $gid = $db->insert_query("settinggroups", $setting_group);

    $setting_array = array(
        'scenetracker_index' => array(
            'title' => 'Indexanzeige',
            'description' => 'Sollen die Szene auf Wunsch des Users auf dem Index angezeigt werden?',
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
        'scenetracker_alert_ingame' => array(
            'title' => 'Ingame',
            'description' => 'ID des Ingames',
            'optionscode' => 'text',
            'value' => '0', // Default
            'disporder' => 4
        ),
        'scenetracker_alert_ingame' => array(
            'title' => 'Archiv',
            'description' => 'ID des Archivs',
            'optionscode' => 'text',
            'value' => '0', // Default
            'disporder' => 5
        ),
        'scenetracker_alert_alerts' => array(
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
        'scenetracker_deactivate' => array(
            'title' => 'Deaktivierung - Behandlung der Templates',
            'description' => 'Sollen die Templates zurückgesetzt (Variablen löschen) und angelegte Templates gelöscht werden? Praktisch wenn viel geändert wurde, aber Achtung bei Updates vom Plugin!',
            'optionscode' => 'yesno',
            'value' => '1', // Default
            'disporder' => 7
        ),
    );
    foreach ($setting_array as $name => $setting) {
        $setting['name'] = $name;
        $setting['gid'] = $gid;
        $db->insert_query('settings', $setting);
    }
    rebuild_settings();
}

function scentracker_uninstall()
{
    //DB Einträge löschen
    global $db;
    if ($db->field_exists("id", "scenetracker")) {
        $db->drop_table("scenetracker");
    }
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "threads DROP scenetrackerdate");
    $db->write_query("ALTER TABLE " . TABLE_PREFIX . "threads DROP scenetrackermembers");
    //Einstellungen löschen
    $db->delete_query('settings', "name LIKE 'scenetracker_%'");
    $db->delete_query('settinggroups', "name = 'scenetracker'");
    rebuild_settings();
}

function scentracker_activate()
{
    global $db, $mybb;
    $deactivate = intval($mybb->settings['relas_npc']);
    //wenn st_deactivate == 0 
    if ($deactivate == 0) {
        //nur hauptvariablen einfügen
    } else {
        //else
        //TODO Templates einfügen
        //TODO Variablen einfügen
    }
}

function scenetracker_deactivate()
{
    global $mybb;
    $deactivate = intval($mybb->settings['relas_npc']);
    if ($deactivate == 0) {
        //nur hauptvariablen löschen
    } else {
        //else
        //TODO Templates löschen
        //TODO Variablen löschen
    }
}
/**
 * Adds the templates and variables
 */
function add_templates()
{ global $db;
    
    // überprüfe ob templates schon vorhanden, wenn ja tue nichts
    // else füge sie neu ein
    $template[0] = array(
        "title" => 'relas_accepted',
        "template" => '		',
        "sid" => "-1",
        "version" => "1.0",
        "dateline" => TIME_NOW
    );
    $template[1] = array(
        "title" => 'relas_accepted',
        "template" => '		',
        "sid" => "-1",
        "version" => "1.0",
        "dateline" => TIME_NOW
    );
    $template[2] = array(
        "title" => 'relas_accepted',
        "template" => '		',
        "sid" => "-1",
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

}

/**
 * Neuen Thread erstellen - Felder einfügen
 * //TODO Thread erstellen
 */
function scenetracker_newthread()
{
    //TODO Datum einfügen
    //TODO Teilnehmer hinzufügen
    //TODO autofill, Teilnehmer
}
/**
 * Neuen Thread erstellen - abschicken und daten speichern
 * //TODO Teilnehmer/Datum speichern
 */
function scenetracker_do_newreply()
{
    //TODO Daten speichern
    //prüfen ob eingaben korrekt sind
}

/**
 * //TODO Edit vom Threadersteller
 * Thread editieren - Datum oder/und Teilnehmer bearbeiten
 */
function scenetracker_editpost()
{
    //TODO Felder bearbeitbar machen
}

//TODO Edit speichern
function scenetracker_editpost_do()
{
    //TODO Felder speichern
}

//TODO Edit auch aus der showthread, auch wenn nicht ersteller sondern nur Teilnehmer
function scenetracker_edit_showthread()
{
    //TODO prüfen ob Teilnehmer, oder ersteller
    //mit Javascript! 
}

/*
*	UserCP Menu
*	//TODO Link im UserCP Menü 
*/
function userpages_usercpmenu()
{
}

/**
 * Verwaltung der szenen im Profil
 * //TODO Szenenverwaltung
 */
function scenetracker_usercp()
{
}
/**
 * Anzeige von Datum und Teilnehmer im Forumdisplay
 * //TODO Anzeige Forumdisplay
 */
function scenetracker_forumdisplay_showtrackerstuff()
{
    // +edit
}

/**
 * Anzeige von Datum und Teilnehmer im Forumdisplay
 * //TODO Anzeige Forumdisplay
 */
function scenetracker_showthread_showtrackerstuff()
{
    // +edit
}
/**
 * automatische Anzeige von Tracker im Profil
 * //TODO Anzeige Profil
 */
function scenetracker_showinprofile()
{
    //Threads aus der Datenbank holen
    //aufteilen in archiv / ingame -> auswählbar machen wonach sortieren? 
    //markieren ob erledigt oder nicht 
    //dort als erledigt markierbar machen
}
/**
 * 
 * //TODO Anzeige auf Indexseite
 */
function scenetracker_list()
{
}

/**
 * //TODO Reminder
 * //Erinnerung wenn man den Postpartner X Tage warten lässt
 */

function scenetracker_reminder(){
    
}