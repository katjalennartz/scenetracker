<?php

/**
 * Szenentracker - by risuena
 * https://github.com/katjalennartz/scenetracker/
 * Erklärung in der readme und im Wiki
 */

// use Dompdf\Options;

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
      <b style=\"color: red;\">Achtung</b> Bitte die Infos in der <b>readme</b> beachten, um Szenen im Kalender angezeigt zu bekommen und Szenen exportieren zu können.",
		"website" => "https://github.com/katjalennartz",
		"author" => "risuena",
		"authorsite" => "https://github.com/katjalennartz",
		"version" => "1.1",
		"compatibility" => "18*",
		// benötigte Version der Updatefile
		"requires_risuena_updatefile" => "1.0.0"
	);
}

$plugins->add_hook("admin_home_menu", "scenetracker_check_updatefile");
function scenetracker_check_updatefile()
{
	require_once MYBB_ROOT . "inc/plugins/risuena_updates/risuena_updatefile.php";
	$info = scenetracker_info();
	if (!risuena_update_check_plugin($info)) {
		flash_message("Der Szenentracker benötigt die aktuelle Version von <a href=\"https://github.com/katjalennartz/risuena_updates/\" target=\"_blank\">Risuenas Updates</a>. Bitte runterladen und ins Plugin-Verzeichnis hochladen.", "error");
	}
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

	// RPG Stuff Modul muss vorhanden sein
	if (!file_exists(MYBB_ADMIN_DIR . "/modules/rpgstuff/module_meta.php")) {
		flash_message("Das ACP Modul <a href=\"https://github.com/little-evil-genius/rpgstuff_modul\" target=\"_blank\">\"RPG Stuff\"</a> muss vorhanden sein!", "error");
		admin_redirect("index.php?module=config-plugins");
	}

	//falls vorher nicht sauber deinstalliert
	scenetracker_uninstall();

	//datenbank kram installieren
	scenetracker_database();

	//settings hinzufügen 
	scenetracker_add_settings();

	//add templates
	scenetracker_add_templates();

	// STYLESHEET HINZUFÜGEN
	require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";
	// Funktion
	$css = scenetracker_stylesheet();
	$sid = $db->insert_query("themestylesheets", $css);
	$db->update_query("themestylesheets", array("cachefile" => "scenetracker.css"), "sid = '" . $sid . "'", 1);

	$tids = $db->simple_select("themes", "tid");
	while ($theme = $db->fetch_array($tids)) {
		update_theme_stylesheet_list($theme['tid']);
	}
}

function scenetracker_uninstall()
{
	global $db, $mybb;
	// Datenbankänderungen Einträge löschen
	if ($db->table_exists("scenetracker")) {
		$db->drop_table("scenetracker");
	}
	if ($db->table_exists("scenetracker_plots")) {
		$db->drop_table("scenetracker_plots");
	}
	if ($db->table_exists("scenetracker_plots_categories")) {
		$db->drop_table("scenetracker_plots_categories");
	}
	if ($db->table_exists("scenetracker_plots_tids")) {
		$db->drop_table("scenetracker_plots_tids");
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
	$db->delete_query("settings", "name LIKE 'scenetracker_%'");
	$db->delete_query("settinggroups", "name = 'scenetracker'");

	rebuild_settings();
}

function scenetracker_activate()
{
	global $db, $mybb, $cache;
	//add variables
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
	find_replace_templatesets("calendar_weekrow_thismonth", "#" . preg_quote('{$day_events}') . "#i", '{$day_events}{$scene_ouput}{$birthday_ouput}{$plotoutput}');
	find_replace_templatesets("footer", "#" . preg_quote('<div id="footer">') . "#i", '<div id="footer">{$scenetracker_calendar_wrapper}');

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
	find_replace_templatesets("calendar_weekrow_thismonth", "#" . preg_quote('{$plotoutput}') . "#i", '');
	find_replace_templatesets("footer", "#" . preg_quote('{$scenetracker_calendar_wrapper}') . "#i", '');

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
 * Berechtigungen im ACP initial setzen
 */
$plugins->add_hook('admin_config_settings_change', 'scenetracker_settings_change');
function scenetracker_settings_change()
{
	global $db, $mybb, $scenetracker_settings_peeker;

	$result = $db->simple_select('settinggroups', 'gid', "name='scenetracker'", array("limit" => 1));
	$group = $db->fetch_array($result);
	$scenetracker_settings_peeker = ($mybb->input['gid'] == $group['gid']) && ($mybb->request_method != 'post');
}

/**
 * Peeker für ACP Settings
 */
$plugins->add_hook('admin_settings_print_peekers', 'scenetracker_settings_peek');
function scenetracker_settings_peek(&$peekers)
{
	global $mybb, $scenetracker_settings_peeker;

	if ($scenetracker_settings_peeker) {
		$peekers[] = 'new Peeker($(".setting_scenetracker_filterusername_yesno"), $("#row_setting_scenetracker_filterusername_id"),/1/,true)';
		$peekers[] = 'new Peeker($(".setting_scenetracker_filterusername_yesno"), $("#row_setting_scenetracker_filterusername_typ"),/1/,true)';
	}

	echo "<script type=\"text/javascript\">
            async function checkPhpWordFolder() {
          const testUrl = '{$mybb->settings['bburl']}/lib/PhpOffice/PhpWord/PhpWord.php'; // irgendeine Datei in dem Ordner

          try {
              const response = await fetch(testUrl, { method: 'HEAD' }); // nur Header anfragen
              if (response.ok) {
                  $('#row_setting_scenetracker_export_word .form_row').before( '<div class=\"lib-hinweis\" style=\"color:green; font-weight:bold;\">PhpWord ist vorhanden</div>');
              
                  return true;
              } else {
                  $('#row_setting_scenetracker_export_word .form_row').before( '<div class=\"lib-hinweis\" style=\"color:red; font-weight:bold;\">Bitte zuerst die <a href=\"https://github.com/PHPOffice/PHPWord\" target=\"_blank\">PhpWord-Bibliothek</a> herunterladen! Den Ordner \'src/PhpWord\' runterladen und in den ganzen Ordner PhpWord in folgenden Ordner im FTP hochladen \'lib/PhpOffice/\'  Diese Ordnerstruktur ist <b>zwingend</b> nötig. Wenn die Meldung hier nach dem hochladen noch da ist, ist die Struktur falsch.</div>');

                  return false;
              }
          } catch (error) {
              console.log('Fehler beim Prüfen:', error);
              return false;
          }
      }

    checkPhpWordFolder();
    </script>";

	echo "<script type=\"text/javascript\">
          async function checkPhpPDFFolder() {
          const testUrl = '{$mybb->settings['bburl']}/lib/dompdf/vendor/autoload.php'; // irgendeine Datei in dem Ordner

          try {
              const response = await fetch(testUrl, { method: 'HEAD' }); // nur Header anfragen
              if (response.ok) {
                  $('#row_setting_scenetracker_export_pdf .form_row').before( '<div class=\"lib-hinweis\" style=\"color:green; font-weight:bold;\">dompdf ist vorhanden</div>');
                  return true;
              } else {
          

                  $('#row_setting_scenetracker_export_pdf .form_row').before( '<div class=\"lib-hinweis\" style=\"color:red; font-weight:bold;\">Bitte zuerst die <a href=\"https://github.com/dompdf/dompdf/releases\" target=\"_blank\">dompdf</a> herunterladen. Dazu den Zip ordner laden (z.b. dompdf-3.1.0.zip). Den Ordner \'src PhpWord\' runterladen und in den ganzen Ordner dompdf in folgenden Ordner im FTP hochladen \'lib/\'  Diese Ordnerstruktur ist <b>zwingend</b> nötig. Wenn die Meldung hier nach dem hochladen noch da ist, ist die Struktur falsch.</div>');

                  return false;
              }
          } catch (error) {
              console.log('Fehler beim Prüfen:', error);
              return false;
          }
      }
  checkPhpPDFFolder();
    </script>";
}

/**
 * Neuen Thread erstellen - Felder einfügen
 */
$plugins->add_hook("newthread_start", "scenetracker_newthread", 20);
function scenetracker_newthread()
{
	global $db, $mybb, $templates, $fid, $scenetracker_newthread, $thread, $scenetrackeredit, $post_errors, $scenetracker_date, $scenetracker_date_d, $scenetracker_date_m, $scenetracker_date_y, $scenetracker_time, $scenetracker_time_input, $scenetracker_user, $plot_select;
	$scenetrackeredit = $scenetracker_place = $scenetracker_time_input = $scenetracker_trigger = "";

	if (scenetracker_testParentFid($fid)) {
		if ($mybb->get_input('previewpost') || $post_errors) {
			$scenetracker_date = $mybb->get_input('scenetracker_date');
			$scenetracker_time = $mybb->get_input('scenetracker_time');
			$scenetracker_time_str = $mybb->get_input('scenetracker_time_str');
			$scenetracker_user = $mybb->get_input('teilnehmer');
			$scenetracker_trigger = $mybb->get_input('scenetracker_trigger');
			$scenetracker_place = $mybb->get_input('place');
			$selectedid =  $mybb->get_input('plot');
		} else {
			$selectedid = "";
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
		$scenetracker_plot = "";
		if ($mybb->settings['scenetracker_plotuse'] == 1) {
			scenetracker_build_plot_select($selectedid);
			if ($plot_select != "") {
				eval("\$scenetracker_plot = \"" . $templates->get("scenetracker_newthread_plot") . "\";");
			}
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
	$scenetrackeredit = $time_text =	$date = "";
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
			//sollte später von feste zeit auf freies umgestellt werden, speichern wir die uhrzeit einfach auch zusätzlich als string 
			$time_text = $db->escape_string($mybb->get_input('scenetracker_time'));
		} else if ($mybb->settings['scenetracker_time_text'] == 1) {
			//einstellunge Zeit als offenes textfeld
			$date = $db->escape_string($mybb->get_input('scenetracker_date'));
			$time_text = $db->escape_string($mybb->get_input('scenetracker_time_str'));
		}
		$scenetracker_place = $db->escape_string($mybb->get_input('place'));
		$teilnehmer = $db->escape_string($mybb->get_input('teilnehmer'));
		$trigger = $db->escape_string($mybb->get_input('scenetracker_trigger'));
		$plot = intval($mybb->get_input('plot'));

		//wir wollen nicht, dass das letzte zeichen in Komma ist, also löschen wir es
		if (substr($teilnehmer, -1, 1) == ",") {
			$teilnehmer = substr($teilnehmer, 0, -1);
		}
		// die uids der Teilnehmer bekommen
		$array_users = scenetracker_getUids($teilnehmer);
		if ($visible == 1) {
			$save = array(
				"scenetracker_date" => $date,
				"scenetracker_user" => $teilnehmer,
				"scenetracker_place" => $scenetracker_place,
				"scenetracker_trigger" => $trigger,
				"scenetracker_time_text" => $time_text
			);
			$db->update_query("threads", $save, "tid='{$tid}'");

			// Wenn Szene einem Plot zugeordnet wird, info speichern.
			if ($mybb->get_input('plot') != "") {
				$save_plot = array(
					"tid" => $tid,
					"plot_id" => $plot,
				);
				$db->insert_query("scenetracker_plots_tids", $save_plot);
			}
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

/**
 * Thread Beantworten - Ansicht
 * Möglichkeit einen Teilnehmer direkt beim Antworten hinzuzufügen
 */
$plugins->add_hook("newreply_end", "scenetracker_newreply");
function scenetracker_newreply()
{
	global $db, $mybb, $tid, $thread, $templates, $fid, $scenetrackerreply, $scenetrackeredit;
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

/**
 * Thread beantworten - Speichern
 */
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
					$type = $db->fetch_array($db->write_query("SELECT * FROM " . TABLE_PREFIX . "scenetracker WHERE tid = '$tid' AND uid = '$uid'"));
					// Je nach Benachrichtigungswunsch alerts losschicken // Einstellungen für den index
					//alerts
					if ($type['type_alert'] == "always") {
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
					} elseif ($type['type_alert'] == "certain" && $type['type_alert_inform_by'] == $thisuser) {
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
					}

					//Index
					if ($type['type'] == "always") {
						//reminder zurücksetzen
						$update = array(
							"index_view_reminder" => 1,
						);
						$db->update_query("scenetracker", $update, "tid = {$tid} AND uid = {$uid}");
					} elseif ($type['type'] == "certain" && $type['inform_by'] == $thisuser) {
						$update = array(
							"index_view_reminder" => 1,
							"alert" => 1,
						);
						$db->update_query("scenetracker", $update, "tid = {$tid} AND uid = {$uid}");
					} elseif ($uid == $thisuser) {
						$update = array(
							"index_view_reminder" => 1,
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

/**
 * Thread editieren 
 * Datum oder/und Teilnehmer bearbeiten - Anzeige
 */
$plugins->add_hook("editpost_end", "scenetracker_editpost");
function scenetracker_editpost()
{
	global $thread, $templates, $tid, $db, $lang, $mybb, $templates, $fid, $post_errors, $post, $scenetrackeredit, $postinfo, $plot_select;
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
				$get_plot = $db->simple_select("scenetracker_plots_tids", "*", "tid = {$thread['tid']}");
				if ($db->num_rows($get_plot) > 0) {
					$plotid = $db->fetch_field($get_plot, "id");
				}

				$scenetracker_date = $mybb->get_input('scenetracker_date');

				if ($mybb->settings['scenetracker_time_text'] != 0) {
					$scenetracker_time = $mybb->get_input('scenetracker_time');
				} else {
					$scenetracker_time = $mybb->get_input('scenetracker_time_str');
				}

				$scenetracker_user = $mybb->get_input('teilnehmer');
				$scenetracker_place = $mybb->get_input('place');
				$selectedid  = $mybb->get_input('plot');
				$scenetracker_trigger = $mybb->get_input('scenetracker_trigger');
			} else {
				$selectedid = $db->fetch_field($db->simple_select("scenetracker_plots_tids", "plot_id", "tid='{$tid}'"), "plot_id");

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
			scenetracker_build_plot_select($selectedid);
			$scenetracker_plot = "";
			if ($mybb->settings['scenetracker_plotuse'] == 1) {
				scenetracker_build_plot_select($selectedid);
				if ($plot_select != "") {
					eval("\$scenetracker_plot = \"" . $templates->get("scenetracker_newthread_plot") . "\";");
				}
			}
			$teilnehmer_alt =  array_map('trim', explode(",", $thread['scenetracker_user']));
			eval("\$scenetrackeredit = \"" . $templates->get("scenetracker_newthread") . "\";");
		} else {
			//wir bearbeiten eine antwort (kein neues thema)
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
 * Speichern vom edit
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
				$time_text = $db->escape_string($mybb->get_input('scenetracker_time'));
			} else if ($mybb->settings['scenetracker_time_text'] == 1) {
				//einstellunge Zeit als offenes textfeld
				$date = $db->escape_string($mybb->get_input('scenetracker_date'));
				$time_text = $db->escape_string($mybb->get_input('scenetracker_time_str'));
			}

			$place = $db->escape_string($mybb->get_input('place'));
			$trigger = $db->escape_string($mybb->get_input('scenetracker_trigger'));
			$teilnehmer_alt = array_map('trim', explode(",",  $thread['scenetracker_user']));
			$teilnehmer_neu = array_filter(array_map('trim', explode(",", $mybb->get_input('teilnehmer'))));
			$plot = $mybb->get_input("plot", MyBB::INPUT_INT);

			//to build the new input for scenetracker user 
			$new_userfield = array();
			//array_diff-> got the users we add new, array intersect the old ones, without the ones we want to delete. and then merge both of them! 
			$workarray = array_merge(array_intersect($teilnehmer_alt, $teilnehmer_neu), array_diff($teilnehmer_neu, $teilnehmer_alt));
			//whitespaces rauswerfen vorne und hinten
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
			//informiere wenn neu hinzugefügt
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
			//neue teilnehmer
			$to_save_str = implode(",", $new_userfield);
			//wir wollen nicht, dass das letzte zeichen ein Komma ist, also löschen wir es
			if (substr($to_save_str, -1, 1) == ",") {
				$to_save_str = substr($to_save_str, 0, -1);
			}
			//szenen infos speichern
			$save = array(
				"scenetracker_date" => $date,
				"scenetracker_time_text" => $time_text,
				"scenetracker_place" => $place,
				"scenetracker_user" =>  $to_save_str,
				"scenetracker_trigger" =>  $trigger
			);
			$db->update_query("threads", $save, "tid='{$tid}'");

			//als plot speichern
			if ($plot > 0) {
				$save_plot = array(
					"tid" => $tid,
					"plot_id" => $plot,
				);
				$db->replace_query("scenetracker_plots_tids", $save_plot);
			} else {
				//löschen wenn auswahl aufgehoben wird
				$db->delete_query("scenetracker_plots_tids", "tid = '{$tid}'");
			}

			//Teilnehmer die rausgeworfen werden sollen
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

/**
 * Anzeige von Datum, Ort und Teilnehmer im Forumdisplay
 */
$plugins->add_hook("forumdisplay_thread", "scenetracker_forumdisplay_showtrackerstuff");
function scenetracker_forumdisplay_showtrackerstuff()
{
	global $thread, $templates, $db, $fid, $scenetrackerforumdisplay, $mybb;

	if (scenetracker_testParentFid($fid)) {
		$scenedate = scenetracker_get_scenedate($thread['tid']);
		$scene_date = $scenedate['date_all'];

		$userArray = scenetracker_getUids($thread['scenetracker_user']);
		$scene_place = $thread['scenetracker_place'];

		$author = build_profile_link($thread['username'], $thread['uid']);
		$scenetracker_forumdisplay_user = "";
		$delete = "";
		$scenetrigger = "";
		if ($thread['scenetracker_trigger'] != "") {
			eval("\$scenetrigger = \"" . $templates->get("scenetracker_forumdisplay_trigger") . "\";");
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
	$sceneinfos = "";
	if (scenetracker_testParentFid($thread['fid'])) {
		$scenedate = scenetracker_get_scenedate($thread['tid']);
		$scene_date = $scenedate['date_all'];
		$scene_place = $thread['scenetracker_place'];

		$userArray = scenetracker_getUids($thread['scenetracker_user']);

		$author = build_profile_link($thread['username'], $thread['uid']);
		$scenetracker_forumdisplay_user = "";
		if ($thread['scenetracker_trigger'] != "") {
			eval("\$scenetrigger = \"" . $templates->get("scenetracker_trigger") . "\";");
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
		eval("\$sceneinfos.= \"" . $templates->get("scenetracker_search_results") . "\";");
	} else {
		$sceneinfos = "";
	}
}

// function scenetracker_loadlib($class)
// {
// 	$path = dirname(__DIR__, 2) . "/lib";
// 	require_once $path . $class . ".php";
// }

/**
 * Anzeige von Datum, Ort und Teilnehmer im showthread
 */
$plugins->add_hook("showthread_end", "scenetracker_showthread_showtrackerstuff");
function scenetracker_showthread_showtrackerstuff()
{
	global $thread, $templates, $db, $fid, $tid, $mybb, $lang, $scenetracker_showthread, $scenetracker_showthread_user, $scene_newshowtread, $scenetrigger, $exp_sel, $scenetracker_time, $edit, $plot_select;

	if (scenetracker_testParentFid($fid)) {
		$lang->load("scenetracker");
		$exp_option = $scenestatus = $edit = $uid = $scenetracker_showthread_export = $scenetracker_time = $scene_date = $scenetracker_date_thread = $scenetracker_user = $scenetracker_date = $sceneplace = $scenetriggerinput = "";


		$showexp = false;
		// $datetime = new DateTime();
		$allowclosing = false;
		$thisuser = intval($mybb->user['uid']);
		$scenedate = scenetracker_get_scenedate($tid);
		$scene_date = $scenedate['date_all'];

		$scenetracker_date = $scenedate['Y-m-d'];
		$scenetracker_date_thread = $scenedate['date'];
		$scenetracker_time_test = $scenedate['time'];
		$scenetracker_time = $scenedate['time'];

		$sceneplace = $thread['scenetracker_place'];
		$scenetriggerinput = $thread['scenetracker_trigger'];
		$scenetracker_user = $thread['scenetracker_user'];
		//select für plotauswahl bauen
		$selectedid = $db->fetch_field($db->simple_select("scenetracker_plots_tids", "plot_id", "tid='{$tid}'"), "plot_id");
		$plot_select = "";
		$scene_plot = "";
		if ($mybb->settings['scenetracker_plotuse'] == 1) {
			scenetracker_build_plot_select($selectedid);
			$plot = $db->simple_select("scenetracker_plots_tids", "*", "tid = {$tid}");

			if ($db->num_rows($plot) > 0) {
				$plotid = $db->fetch_field($plot, "plot_id");
				$plotname = $db->fetch_field($db->simple_select("scenetracker_plots", "name", "id = {$plotid}"), "name");
				eval("\$scene_plot = \"" . $templates->get("scenetracker_showthread_plot") . "\";");
			}
		}

		//alle user der szene
		$userArray = scenetracker_getUids($thread['scenetracker_user']);
		foreach ($userArray as $uid => $username) {
			$delete = "";
			if ($uid != $username) {
				$user = build_profile_link($username, $uid);
				if ($mybb->usergroup['canmodcp'] == 1 || scenetracker_change_allowed($thread['scenetracker_user'])) {
					$allowclosing = true; //teilnehmer, darf die szene öffnen und schließen
					$delete = "<a href=\"showthread.php?tid=" . $tid . "&delete=" . $uid . "\">{$lang->scenetracker_delete}</a>";
				}
			} else {
				$user = $username;
				$delete = "";
			}
			eval("\$scenetracker_showthread_user.= \"" . $templates->get("scenetracker_showthread_user") . "\";");
		}
		if ($thread['scenetracker_trigger'] != "") {
			eval("\$scenetrigger = \"" . $templates->get("scenetracker_showthread_trigger") . "\";");
		} else {
			$scenetrigger = "";
		}
		if ($allowclosing || $mybb->usergroup['canmodcp'] == 1) {
			if ($thread['closed'] != 1) {
				$mark = "<a href=\"showthread.php?tid=" . $tid . "&scenestate=close\">{$lang->scenetracker_closescene}</a></span>";
				$scenestatus = "<span class=\"scenestate bl-btn bl-btn--scenetracker\">{$lang->scenetracker_openscenestatus} " . $mark;
			} else {
				$mark = "<a href=\"showthread.php?tid=" . $tid . "&scenestate=open\">{$lang->scenetracker_openscene}</a></span>";
				$scenestatus = "<span class=\"scenestate bl-btn bl-btn--scenetracker\">{$lang->scenetracker_closescenestatus} " . $mark;
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

			$edit = "";

			if ($mybb->settings['scenetracker_export_word'] == 1 && $mybb->settings['scenetracker_export_pdf_all'] == 1) {
				if (scenetracker_testParentFid($fid)) {
					$exp_option .= '<option value="word" {$sel_m[\'word\']}>Word</option>';
					$showexp = true;
				}
			}
			if ($mybb->settings['scenetracker_export_pdf'] == 1 && $mybb->settings['scenetracker_export_pdf_all'] == 1) {
				if (scenetracker_testParentFid($fid)) {
					$exp_option .= '<option value="pdf" {$sel_m[\'pdf\']}>PDF</option>';
					$showexp = true;
				}
			}
			eval("\$edit = \"" . $templates->get("scenetracker_showthread_edit") . "\";");
		}
		if ($mybb->settings['scenetracker_export_word'] == 1 && $mybb->settings['scenetracker_export_word_all'] == 0) {
			if (scenetracker_testParentFid($fid)) {
				$exp_option .= '<option value="word" {$sel_m[\'word\']}>Word</option>';
				$showexp = true;
			} else {
				$exp_option .= '';
				$showexp = false;
			}
		}
		if ($mybb->settings['scenetracker_export_pdf'] == 1 && $mybb->settings['scenetracker_export_pdf_all'] == 0) {
			if (scenetracker_testParentFid($fid)) {
				$exp_option .= '<option value="pdf" {$sel_m[\'pdf\']}>PDF</option>';
				$showexp = true;
			} else {
				$exp_option .= '';
				$showexp = false;
			}
		}

		if ($showexp) {
			$exp_sel = '<select name="export_sel" id="scenetracker_export">' . $exp_option . '</select>';
			eval("\$scenetracker_showthread_export.= \"" . $templates->get("scenetracker_showthread_export") . "\";");
		}

		eval("\$scenetracker_showthread = \"" . $templates->get("scenetracker_showthread") . "\";");

		//delete a participant
		if ($mybb->get_input('delete')) {
			$uiddelete = intval($mybb->get_input('delete'));
			$userdelete = $db->fetch_field($db->simple_select("users", "username", "uid = $uiddelete"), "username");
			if ($mybb->usergroup['canmodcp'] == 1 || scenetracker_check_switcher($uid)) {
				//Charakter löschen
				$teilnehmer = str_replace($userdelete, "", $thread['scenetracker_user']);
				//ab hier schauen dass die Kommas passen. wir wollen am anfang kein ', '
				$teilnehmer = preg_replace('/^' . preg_quote(', ', '/') . '/', '', $teilnehmer);
				// mitten drin wollen wir kein ', ,'
				$teilnehmer = preg_replace('/' . preg_quote(', ,', '/') . '/', ',', $teilnehmer);
				//und am ende auch kein ', '
				$teilnehmer = rtrim($teilnehmer, ', ');
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

		//Szene exportieren
		if ($mybb->settings['scenetracker_export_word'] == 1 || $mybb->settings['scenetracker_export_pdf'] == 1) {
			if ($mybb->get_input('export_scene')) {
				//Gäste dürfen nicht exportieren
				if ($mybb->user['uid'] == 0) {
					error_no_permission();
				}

				if ($mybb->get_input('export_sel') == "word") {
					//autoload klasse um Bibliothek zu laden, workaround weil nicht mit composer
					spl_autoload_register(function ($class) {
						//prefix 
						$prefix = 'PhpOffice\\';
						if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
							return;
						}

						// Pfad
						$relative_class = substr($class, strlen($prefix));
						$relative_path = str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';

						// Suchen/testen vom Pfad 
						$base_dirs = [
							dirname(__DIR__, 2) . '/PhpWord/',        // z.B. /inc/plugins/PhpWord/
							dirname(__DIR__, 2) . '/lib/PhpOffice/',  // z.B. /inc/plugins/lib/PhpOffice/
						];

						foreach ($base_dirs as $base_dir) {
							$file = $base_dir . $relative_path;
							if (file_exists($file)) {
								require_once $file;
								return;
							}
						}
					});

					//Lets do the magic
					$phpWord = new \PhpOffice\PhpWord\PhpWord();
					$section = $phpWord->addSection();

					//Thread infos
					$thread = get_thread($tid);
					//Title
					$section->addText($thread['subject'], array('name' => 'Arial', 'size' => 20, 'bold' => 'true'));
					$section->addTextBreak();
					//Szenentracker Infos
					$section->addText("Teilnehmende: " . $thread['scenetracker_user']);
					$section->addText("Ort: " . $thread['scenetracker_place']);

					$scenedate = scenetracker_get_scenedate($thread['tid']);
					$scene_date = $scenedate['date_all'];
					$scene_date = preg_replace('/(\d{2})\.(\d{2})\.(0)(\d{1,4})/', '$1.$2.$4', $scene_date);

					$section->addText("Datum: " . $scene_date);
					$section->addTextBreak();
					$lineStyle = array('weight' => 1, 'width' => 500, 'height' => 0, 'color' => 000000);
					$section->addLine($lineStyle);
					$section->addTextBreak();
					// Posts auslesen
					$query = $db->simple_select("posts", "*", "tid=" . (int)$tid, ["order_by" => "dateline", "order_dir" => "ASC"]);
					while ($post = $db->fetch_array($query)) {
						$username = htmlspecialchars_uni($post['username']);
						$message  = strip_tags($post['message']);
						$section->addText($username . " schrieb:", ['bold' => true]);
						$section->addText($message);
						$section->addTextBreak();
						$section->addLine($lineStyle);
						$section->addTextBreak();
						$section->addTextBreak();
					}

					// titel für Download
					$safeTitle = preg_replace('/[^A-Za-z0-9 _-]/u', '_', $thread['subject']);
					// Unterstriche/Leerzeichen auf einen reduzieren
					$safeTitle = preg_replace('/_+/', '_', $safeTitle);

					header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
					header('Content-Disposition: attachment;filename="' . $safeTitle . '.docx"');

					// Datei ausgeben
					$writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
					$writer->save('php://output');
					exit;
				}
				// export als pdf
				if ($mybb->get_input('export_sel') == "pdf") {
					require_once "./lib/dompdf/autoload.inc.php";

					$dompdf = new \Dompdf\Dompdf();

					$tid = (int)$mybb->input['tid'];
					$thread = get_thread($tid);

					/* Teilnehmer hübsch machen */
					$teilnehmer = $thread['scenetracker_user'];
					$teilnehmer = rtrim($teilnehmer, " ,");
					$namen = array_map('trim', explode(',', $teilnehmer));
					$teilnehmer = implode(' & ', $namen);

					/* Datum formatieren */
					$scenedate = scenetracker_get_scenedate($thread['tid']);
					$scene_date = $scenedate['date_all'];
					$scene_date = preg_replace('/(\d{2})\.(\d{2})\.(0)(\d{1,4})/', '$1.$2.$4', $scene_date);

					$place = $thread['scenetracker_place'];

					/* Posts holen */
					$posts_html = "";
					$post_query = $db->simple_select("posts", "message,username", "tid='$tid'", array(
						"order_by" => 'pid',
						"order_dir" => 'ASC'
					));
					require_once MYBB_ROOT . "inc/class_parser.php";
					$parser = new postParser;
					$parser_options = array(
						"allow_html" => 1,
						"allow_mycode" => 1,
						"allow_smilies" => 0,
						"allow_imgcode" => 0,
						"allow_videocode" => 0,
						"nl2br" => 1
					);
					while ($post = $db->fetch_array($post_query)) {
						$message = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $post['message']);
						$message = $parser->parse_message($message, $parser_options);

						$posts_html .= "
              <div class='post-block'>
                  <h3>{$post['username']}</h3>
                  <p>{$message}</p>
              </div>
            ";
					}

					/* HTML fürs PDF */
					$html = '<html>
          <head>
            <meta charset="UTF-8">
            <style>
              /* Reserviere Platz oben/bottom für Kopf-/Fußzeile auf normalen Seiten */
              @page {
                margin: 80px 40px 60px 40px; /* top right bottom left */
              }
              /* Erste Seite (Cover) bekommt keine obere Margin */
              @page :first {
                margin-top: 300px;
              }

              body {
                font-family: "DejaVu Sans", sans-serif;
                font-size: 12px;
                margin: 0;
                padding: 0;
              }

              /* Header, der auf allen Seiten erscheint, auf denen dieses Element im Fluss liegt.
                Weil wir das Header-Element *nach* der Cover-Seite platzieren, erscheint er erst ab Seite 2. */
              .header {
                position: fixed;
                top: -40px;
                left: 40px;
                right: 40px;
                height: 50px;
                text-align: center;
                font-family: "Courier", serif;
                font-size: 10px;
                padding-bottom: 6px;
              }

              h1 { 
                font-size:14px;
              }

              h3 {
                margin:0;
              }
              
              .cover {
                /* Vollseiten-Cover */
                height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                text-align: center;
                page-break-after: always; /* zwingt Umbruch nach der Titelseite */
              }
              .cover .meta {
                font-family: "Courier", serif;
              }
              .post-block p{
                    margin:0;
                    margin-top:0;
                    padding-top:0;
                    margin-bottom: 20px;
              }
            </style>
          </head>
          <body>
            <div class="cover">
              <div class="centerheader">
                <h1>' . $thread['subject'] . '</h1>
                <div class="meta">' . $teilnehmer . '<br>
                ' . $scenedate['datel_all'] . '<br>
               ' . $place . '</div>
              </div>
            </div>

            <div class="header">' . $thread['subject'] . '</div>
            ' . $posts_html . '
          </body>
          </html>';

					/* PDF erzeugen */
					$dompdf->loadHtml($html, 'UTF-8');
					$dompdf->setPaper('A4', 'portrait');
					$dompdf->render();

					// Footer mit Seitenzahlen (unten rechts)
					$canvas = $dompdf->getCanvas();
					$canvas->page_text(520, 820, "{PAGE_NUM}/{PAGE_COUNT}", $font, 9, [0, 0, 0]);

					/* PDF ausgeben */
					$filename = preg_replace('/[^a-zA-Z0-9-_]/', '_', $thread['subject']) . ".pdf";
					$dompdf->stream($filename, ["Attachment" => true]);
					exit;
				}
			}
		}
	}
}

/************
 * Verwaltung der Einstellungen und Szenen im UCP 
 *********** */
$plugins->add_hook("usercp_start", "scenetracker_usercp");
function scenetracker_usercp()
{
	global $mybb, $db, $templates, $lang, $cache, $templates, $themes, $theme, $headerinclude, $header, $footer, $usercpnav, $ucp_main_calendarsettings, $scenetracker_ucp_main, $scenetracker_ucp_bit_char, $scenetracker_ucp_bit_chara_new, $scenetracker_ucp_bit_chara_old, $scenetracker_ucp_bit_chara_closed, $scenetracker_ucp_filterscenes_username, $ucp_main_reminderopt;

	global $scenetracker_popup_select_options_index, $scenetracker_popup_select_options_alert;

	global $yes_ind, $no_ind, $yes_rem, $no_rem, $yes_indall, $no_indall, $ucp_main_reminderopt, $setting_calendar, $get_calsettings, $setforalls_all, $setforalls_this, $scenetracker_calendarview_all, $scenetracker_calendarview_mini, $mini_view_all, $mini_view_all_own, $mini_view_all_this, $big_view_all, $big_view_all_own, $big_view_all_this, $calendar_setting_form;

	if ($mybb->get_input('action') != "scenetracker") {
		return false;
	}
	$lang->load('scenetracker');

	//Variablen initialisieren
	$hidden = $yes_ind = $no_ind = $yes_rem = $no_rem = $yes_indall =  $no_indall = $move = $status = $player = $always = $scenetracker_ucp_bit_chara = $scenetracker_calendarview_ownall = $solvefield = $setting_calendar = $solved_tozero = $solved_toone = $asstring =  $always_always_opt = $player_query_str =  $close = $reminder_popup = $selected = $oldest = $newest =  $sel_s["both"] = $sel_s['open'] = $sel_s["closed"] = $sel_m["beides"] = $sel_m["ja"] = $sel_m["nein"] = $divclass = "";

	//$thisuser user setzen
	$thisuser = $mybb->user['uid'];
	if ($mybb->user['uid'] == 0) {
		error_no_permission();
	}

	// Eventverwaltung
	if ($mybb->get_input("view") === "events") {
		scenetracker_ucp_events();
		return;
	}

	//kalendereinstellungen auslesen und form bauen, ausgelagert weil übersichtlicher
	scenetracker_get_calendar_settings_ucp();

	//charaktere
	$charas = scenetracker_get_accounts($mybb->user['uid']);

	//welche filter sind ausgewählt
	$filters = scenetracker_ucp_get_scene_filters();
	//ausgewählte Charas 
	$selected_charas = scenetracker_ucp_get_selected_characters($charas, $filters['character_uid']);

	//select mit eigenen Charakteren bauen
	$selectchara = "<select name=\"charakter\" id=\"charakter\">
    <option value=\"0\">{$lang->scenetracker_select_allChars}</option>";
	foreach ($charas as $uid_sel => $username) {
		if ($uid_sel == $filters['character_uid']) {
			$charsel_[$filters['character_uid']] = "SELECTED";
		} else {
			$charsel_[$filters['character_uid']] = "";
		}
		$selectchara .= "<option value=\"{$uid_sel}\" {$charsel_[$filters['character_uid']]} >{$username}</option>";
	}
	$selectchara .= "</select>";

	//Szenen laden, wenn uid = 0 -> alle eigenen Charas, sonst ausgewählter
	$all_scenes_user = scenetracker_ucp_load_scenes($selected_charas);


	// Szenen mit bestimmtem Spieler filtern
	$partner_charas = array();

	if ($filters['player'] !== '') {
		$player = trim($filters['player']);
		$player_escaped = $db->escape_string($player);

		$player_type = (int)$mybb->settings['scenetracker_filterusername_typ'];
		$player_field = $mybb->settings['scenetracker_filterusername_id'];

		if ($player_type === 0) {
			// Spielername steht in einem MyBB-Profilfeld
			$field = 'fid' . (int)$player_field;

			$query = $db->write_query("
            SELECT u.uid
            FROM " . TABLE_PREFIX . "users u
            INNER JOIN " . TABLE_PREFIX . "userfields f
                ON f.ufid = u.uid
            WHERE f.{$field} = '{$player_escaped}'
        ");
		} else {
			// Spielername steht in einem Application-UCP-Feld
			$query = $db->write_query("
            SELECT af.uid
            FROM " . TABLE_PREFIX . "application_ucp_userfields af
            INNER JOIN " . TABLE_PREFIX . "application_ucp_fields fld
                ON fld.id = af.fieldid
            WHERE fld.fieldname = '{$db->escape_string($player_field)}'
              AND af.value = '{$player_escaped}'
        ");
		}

		while ($player_account = $db->fetch_array($query)) {
			$accounts = scenetracker_get_accounts((int)$player_account['uid']);

			foreach ($accounts as $account_uid => $account_name) {
				$partner_charas[$account_uid] = $account_name;
			}
		}
	}


	//nach Status filtern
	$filtered_scenes = scenetracker_ucp_filter_scenes($all_scenes_user,	$filters, $partner_charas);

	// szenen nach last post sortieren
	$filtered_scenes = scenetracker_ucp_sort_scenes($filtered_scenes,	$filters['sort']);

	if ($filters['view'] === 'all') {
		$scene_groups = array(
			0 => array(
				'uid'      => 0,
				'username' => $lang->scenetracker_showstring_all,
				'scenes'   => $filtered_scenes,
			),
		);
	} else {
		//Szenen nach Charaktere sortieren
		$scene_groups = scenetracker_ucp_group_scenes_by_character($filtered_scenes, $selected_charas);
	}

	//variable leeren
	$scenetracker_ucp_bit_chara = "";
	$cnt = count($filtered_scenes);

	// Texte für überschrift, was wurde gefiltert 
	switch ($filters['status']) {
		case "closed":
			$status_str = "closed";
			break;

		case "both":
			$status_str = "open & closed";
			break;

		case "open":
		default:
			$status_str = "open";
			break;
	}

	switch ($filters['turn']) {
		case "ja":
			$move_str = "Ja";
			break;

		case "nein":
			$move_str = "Nein";
			break;

		case "beides":
		default:
			$move_str = "Beides";
			break;
	}

	//kein postpartner ausgewählt
	if ($filters['player'] !== '') {
		$player_str = $filters['player'];
	} else {
		$player_str = "egal";
	};

	//Array durchgehen.
	foreach ($scene_groups as $group) {
		$uid = (int)$group['uid'];
		$charname = $group['username'];
		$scenes = $group['scenes'];

		//Gruppen ohne Szenen nicht ausgeben
		if (empty($scenes)) {
			continue;
		}

		$chara_cnt = count($scenes);
		$divclass = '';

		//Überschrift setzen
		if ($filters['view'] === "all") {
			$charname_str = $lang->scenetracker_showstring_all;
			$charaname = $lang->scenetracker_showstring_all;
			$divclass = ' allcharacters';
		} else {
			$charname_str = $charname;
			$charaname = build_profile_link($charname, $uid);
		}

		$charaname .= $lang->sprintf($lang->scenetracker_showstring_characount, $chara_cnt);

		$scenes_title = $lang->sprintf($lang->scenetracker_showstring, $charname_str, $status_str, $move_str, $player_str, $cnt);

		// Variable leeren
		$scenetracker_ucp_bit_scene = "";

		foreach ($scenes as $entry) {
			$data = $entry['scene'];

			//Grupiert nach charakter
			if ($filters['view'] === "grouped") {
				$scene_uid = (int)$entry['character_uid'];
				$is_turn = !empty($entry['is_turn']);
			} else {
				//Oder alle
				$scene_uid = 0;
				$is_turn = !empty($entry['is_turn']);
			}

			$statusclass = "";

			if ($is_turn) {
				$statusclass = "<span class=\"yourturn\">" . $lang->scenetracker_yourturn . "</span>";
			}

			$edit = "";
			$alert = $lang->scenetracker_alert;

			$tid = (int)$data['tid'];
			$id = (int)$data['id'];
			$info_by = (int)$data['inform_by'];

			//Szene gelesen oder nicht
			$isread = "newscene";

			if (!empty($data['read_dateline']) && (int)$data['read_dateline'] >= (int)$data['lastpost']) {
				$isread = "oldscene";
			}

			//uid der aktuellen szene
			$current_scene_uid = (int)$data['uid'];
			$user = get_user($current_scene_uid);

			$username = !empty($user['username'])
				? build_profile_link($user['username'], $current_scene_uid)
				: '';

			$lastpostdate = date('d.m.Y', (int)$data['lastpost']);
			$lastposter = get_user($data['lastposteruid']);
			$alerttype_index = $data['type'];

			$scenedate = scenetracker_get_scenedate((int)$data['tid'])['date_all'];

			if (!empty($lastposter['uid'])) {
				$lastposterlink = build_profile_link($lastposter['username'],	$lastposter['uid']);
			} else {
				$lastposterlink = htmlspecialchars_uni($data['lastposter']);
			}
			//ausgabe schöner machen
			$users = $sceneusers = str_replace(',', ', ', $data['scenetracker_user']);

			$username = "";
			$sceneplace = $data['scenetracker_place'];

			//infos von Szeneneinstellung Index
			if ($alerttype_index === "certain") {
				$info = get_user((int)$data['inform_by']);
				$alertclass = "certain";
				$username = !empty($info['uid']) ? build_profile_link($info['username'], $info['uid']) : '';
				$alerttype = $lang->scenetracker_ucp_alerttype_index . $username;
			} elseif ($alerttype_index === "always") {
				$alerttype = $lang->scenetracker_ucp_alerttype_index .  $lang->scenetracker_alerttypealways;
				$alertclass = "always";
			} elseif ($alerttype_index === "always_always") {
				$alerttype = $lang->scenetracker_ucp_alerttype_index . $lang->scenetracker_alerttypealways_always;
				$alertclass = "always_always";
			} elseif ($alerttype_index === "never") {
				$alerttype = $lang->scenetracker_ucp_alerttype_index . $lang->scenetracker_alerttypenever;
				$alertclass = "never";
			} elseif ($alerttype_index === "fieldorder") {
				$alerttype = $lang->scenetracker_ucp_alerttype_index . "nach eingetragener Reihenfolge";
				$alertclass = "fieldorder";
			} else {
				$alerttype = "";
				$alertclass = "";
			}

			//Szeneneinstellungen für Alert
			$alerttype_alert_data = $data['type_alert'];
			if ($alerttype_alert_data === "certain") {
				$info = get_user((int)$data['type_alert_inform_by']);
				$alertclass = 'certain';
				$username = !empty($info['uid']) ? build_profile_link($info['username'], $info['uid']) : '';
				$alerttype_alert =
					$lang->scenetracker_ucp_alerttype_alert	. $username;
			} elseif ($alerttype_alert_data === "always") {
				$alerttype_alert = $lang->scenetracker_ucp_alerttype_alert	. $lang->scenetracker_alerttypealways;
				$alertclass = 'index always';
			} elseif ($alerttype_alert_data === "always_always") {
				$alerttype_alert =
					$lang->scenetracker_ucp_alerttype_alert	. $lang->scenetracker_alerttypealways_always;
				$alertclass = 'always_always';
			} elseif ($alerttype_alert_data === "never") {
				$alerttype_alert =
					$lang->scenetracker_ucp_alerttype_alert	. $lang->scenetracker_alerttypenever;
				$alertclass = "never";
			} else {
				$alerttype_alert = '';
			}
			//szenenlink bauen
			$scene = '<a href="showthread.php?tid=' . $tid . '&action=lastpost" class="scenelink">' . $data['subject']	. '</a>';

			//anzeige Profil
			if ((int)$data['profil_view'] === 1) {
				eval("\$hide = \"" . $templates->get('scenetracker_displaystatus_shown') . "\";");
			} else {
				eval("\$hide = \"" . $templates->get('scenetracker_displaystatus_hidden') .	"\";");
			}

			// Status der Szene
			if ((int)$data['closed'] !== 1) {
				eval("\$close = \"" . $templates->get('scenetracker_sceneisopen') . "\";");
			} else {
				eval("\$close = \"" . $templates->get('scenetracker_sceneisclosed') . "\";");
			}

			// reminder einstellungen
			if ((int)$data['index_view_reminder'] === 1) {
				$rem_numdays = (int)$data['index_view_reminder_days'];
				if ($rem_numdays === 0) {
					$rem_numdays = (int)$mybb->settings['scenetracker_reminder'];
				}
				$index_reminder = $lang->sprintf($lang->scenetracker_index_view_reminder_on, $rem_numdays);
				$rem_sel_on = "selected";
				$rem_sel_off = "";
				$days_reminder = $rem_numdays;
			} else {
				$index_reminder =	$lang->scenetracker_index_view_reminder_off;
				$rem_sel_on = "";
				$rem_sel_off = "selected";
			}

			//popups 
			scenetracker_build_popup_form($data, "index");
			scenetracker_build_popup_form($data, "alert");

			eval("\$certain = \"" . $templates->get("scenetracker_popup") . "\";");
			eval("\$scenereminder = \"" . $templates->get("scenetracker_ucp_reminder") . "\";");
			eval("\$scenetracker_ucp_bit_scene .= \"" .	$templates->get("scenetracker_ucp_bit_scene") . "\";");
		}

		// Charas ausgeben
		eval("\$scenetracker_ucp_bit_chara .= \"" . $templates->get("scenetracker_ucp_bit_chara") .	"\";");
	}

	if ($mybb->settings['scenetracker_filterusername_yesno'] == 1) {
		$scenetracker_ucp_filterscenes = "";
		eval("\$scenetracker_ucp_filterscenes_username .= \"" . $templates->get("scenetracker_ucp_filterscenes_username") . "\";");
	}
	eval("\$scenetracker_ucp_filterscenes .= \"" . $templates->get("scenetracker_ucp_filterscenes") . "\";");

	//Speichern von Settings
	if ($mybb->get_input("opt_index")) {
		$index = $mybb->get_input("index");
		foreach ($charas as $uid => $chara) {
			$db->query("UPDATE " . TABLE_PREFIX . "users SET tracker_index = " . $index . " WHERE uid = " . $uid . " ");
		}
		redirect("usercp.php?action=scenetracker");
	}
	if ($mybb->get_input("opt_indexall")) {
		$index = $mybb->get_input("indexall");
		$db->query("UPDATE " . TABLE_PREFIX . "users SET tracker_indexall = " . $index . " WHERE uid = " . $mybb->user['uid'] . " ");
		redirect("usercp.php?action=scenetracker");
	}
	if ($mybb->get_input("opt_reminder")) {
		//speichert allgemein ob Szenenerinnerung ja oder nein
		$reminder = $mybb->get_input("reminder");
		foreach ($charas as $uid => $chara) {
			$db->query("UPDATE " . TABLE_PREFIX . "users SET tracker_reminder = " . $reminder . " WHERE uid = " . $uid . " ");
		}
		redirect("usercp.php?action=scenetracker");
	}

	//einstellungen benachrichtigung für szenen - scheiße benannt
	if ($mybb->get_input("certainuser")) {
		$typearray = array("index", "alert");
		//scene id
		$id = intval($mybb->get_input("getid"));
		//wir haben zwei typen von alerteinstellungen, index und alert
		foreach ($typearray as $t) {
			$type = $mybb->get_input($t . "_type");
			if ($mybb->get_input($type . "_mode" . $id) == "character") {
				$value = get_user_by_username($mybb->get_input($type . "_select" . $id))['uid'];
			} else {
				$value = $mybb->get_input($type . "_mode" . $id);
			}
			scenetracker_scene_inform_status($id, $type, $value);
		}
		redirect("usercp.php?action=scenetracker");
	}

	//einstellungen benachrichtigung für reminder
	if ($mybb->get_input('remindersettings')) {
		//welche id in szenentracker tabelle
		$id = intval($mybb->get_input('getid'));
		//Einstellung für Reminder
		$reminder = intval($mybb->get_input('reminder'));
		$reminder_days = intval($mybb->get_input('reminder_days'));
		scenetracker_scene_inform_status($id, "reminder", $reminder, $reminder_days);
		redirect("usercp.php?action=scenetracker");
	}

	if ($mybb->get_input("showsceneprofil") == "0") {
		$id = intval($mybb->get_input("getsid"));
		$uid = $db->fetch_field($db->simple_select("scenetracker", "uid", "id = $id"), "uid");
		scenetracker_scene_change_view(0, $id, $uid);
		redirect("usercp.php?action=scenetracker");
	} elseif ($mybb->get_input('showsceneprofil') == "1") {
		$id = intval($mybb->get_input("getsid"));
		$uid = $db->fetch_field($db->simple_select("scenetracker", "uid", "id = $id"), "uid");
		scenetracker_scene_change_view(1, $id, $uid);
		$uid = $db->fetch_field($db->simple_select("scenetracker", "uid", "id = $id"), "uid");
		redirect("usercp.php?action=scenetracker");
	}

	if ($mybb->get_input('closed') == "1") {
		$tid = intval($mybb->get_input("getsid"));
		$uid = intval($mybb->get_input("getuid"));
		scenetracker_scene_change_status(1,  $tid,  $uid);
		redirect("usercp.php?action=scenetracker");
	} elseif ($mybb->get_input('closed') == "0") {
		$tid = intval($mybb->get_input("gettid"));
		$uid = intval($mybb->get_input("getuid"));
		scenetracker_scene_change_status(0,  $tid,  $uid);
		redirect("usercp.php?action=scenetracker");
	}

	eval("\$scenetracker_ucp_main =\"" . $templates->get("scenetracker_ucp_main") . "\";");
	output_page($scenetracker_ucp_main);
}

/**
 * Funktion um die Filter einzulesen und als array zurückzugeben.
 *
 * Rückgabe:
 *     'status'        => 'open|closed|both',
 *     'turn'          => 'yes|no|both',
 *     'character_uid' => 0 oder UID,
 *     'player'        => Spielername,
 *     'view'          => 'grouped|all',
 *     'sort'          => 'asc|desc'
 * @return array
 */
function scenetracker_ucp_get_scene_filters()
{
	global $mybb;
	// setting defaults
	$filters = [
		"status"        => "open",
		"turn"          => "both",
		"character_uid" => 0,
		"player"        => "",
		"view"          => "grouped",
		"sort"          => "desc",
	];

	// normales filtern, ausgabe pro Charakter.
	if ($mybb->get_input("scenefilter")) {
		$filters['character_uid'] = $mybb->get_input("charakter");
		$filters['status'] = $mybb->get_input("status");
		$filters['turn']   = $mybb->get_input("move");
		$filters['player'] = trim($mybb->get_input("player"));
	}

	// alle charaktere zusammengefasst, älteste Szene zuerst.
	if ($mybb->get_input("oldest")) {
		$filters['character_uid'] = 0;
		$filters['status'] = $mybb->get_input("status");
		$filters['turn']   = $mybb->get_input("move");
		$filters['player'] = trim($mybb->get_input("player"));
		$filters['view']   = "all";
		$filters['sort']   = "asc";
	}

	// alle charaktere zusammengefasst, neueste Szene zuerst.
	if ($mybb->get_input("newest")) {
		$filters['character_uid'] = 0;
		$filters['status'] = $mybb->get_input("status");
		$filters['turn']   = $mybb->get_input("move");
		$filters['player'] = trim($mybb->get_input("player"));
		$filters['view']   = "all";
		$filters['sort']   = "desc";
	}
	//default status setzen -> open
	if (!in_array($filters['status'], ['open', 'closed', 'both'], true)) {
		$filters['status'] = "open";
	}
	//default turn setzen -> open
	if (!in_array($filters['turn'], ['ja', 'nein', 'beides'], true)) {
		$filters['turn'] = "beides";
	}

	return $filters;
}

/**
 * Bestimmt, für welche eigenen Charaktere Szenen geladen werden sollen.
 *
 * @param array $all_characters Alle eigenen Charaktere [uid => username]
 * @param int   $selected_uid   0 für alle oder konkrete UID
 *
 * @return array
 */
function scenetracker_ucp_get_selected_characters(array $all_characters, $selected_uid)
{
	$selected_uid = (int)$selected_uid;

	if ($selected_uid === 0) {
		return $all_characters;
	}

	if (!isset($all_characters[$selected_uid])) {
		return [];
	}

	$selected = [$selected_uid => $all_characters[$selected_uid]];
	return $selected;
}

/**
 * Funktionen um Szenen zu laden
 * @param array $characters Array [uid => username]
 * @param boolean $open - true wenn nur offene szenen geladen werden sollen
 * @return array
 */
function scenetracker_ucp_load_scenes(array $characters, $open = false)
{
	global $db, $mybb;

	if (empty($characters)) {
		return [];
	}

	$uids = array_map('intval', array_keys($characters));
	$uids = array_filter($uids);

	if (empty($uids)) {
		return [];
	}

	$uid_list = implode(',', $uids);

	// Threadsolved nur wenn vorhanden
	$threadsolved_select = '';

	if (!empty($mybb->settings['scenetracker_solved']) && $db->field_exists('threadsolved', 'threads')) {
		$threadsolved_select = ', t.threadsolved';
	}

	// Threads holen, inklusive info ob schon gelesen oder nicht.
	$viewer_uid = (int)$mybb->user['uid'];
	$options = array(
		"add_table" => "scenetracker",
		"threadsread" => true,
	);

	$open_str  = "";
	if ($open) {
		$options['closed'] = 0;
	}

	$query_str = scenetracker_build_sql_threadtable("all", $characters, $options);
	$query = $db->write_query($query_str);

	$entries = [];

	while ($scene = $db->fetch_array($query)) {
		$scene_uid = (int)$scene['uid'];
		$entries[] = [
			"character_uid" => $scene_uid,
			"character_username" => $scene['username'],
			"scene" => $scene,
		];
	}

	return $entries;
}

/**
 * Funktion um zu prüfen ob der Charakter dran ist.
 *
 * @param array $scene Bereits geladene Thread- und Scenetracker-Daten
 * @param int   $uid
 * @return bool
 */
function scenetracker_ucp_is_characters_turn(array $scene, $uid)
{
	$uid = (int)$uid;
	//default setzen
	$type = $scene['type'] ?? "always";

	if ($type === "always") {
		return (int)$scene['lastposteruid'] !== $uid;
	}
	if ($type === "always_always") {
		//noch checken, wie wir das genau berücksichgtigen wollen, eigentlich ist always_always nur für die anzeige
		return true;
	}
	//wenn ein bestimmter user gepostet hat
	if ($type === "certain") {
		return (int)$scene['alert'] === 1;
	}
	if ($type === "fieldorder") {
		return scenetracker_chara_turn_fieldorder_from_scene($scene, $uid);
	}
	if ($type === "never") {
		return false;
	}

	return false;
}

/**
 * Funktion um die Teilnehmerreihenfolge zu bekommen,
 * gelöschte Benutzer werden aus der Reihenfolge entfernt
 *
 * @param array $scene
 * @param int   $uid
 * @return bool
 */
function scenetracker_chara_turn_fieldorder_from_scene(array $scene, $uid)
{
	$character = get_user((int)$uid);

	if (empty($character['uid']) || empty($scene['scenetracker_user'])) {
		return false;
	}

	$participants = array_values(array_filter(array_map("trim", explode(",", $scene['scenetracker_user']))));

	//gelöschte Charas entfernen - filter funktion aufrufen um zu überprüfen ob es den Charakter noch gibt
	$participants = array_values(array_filter($participants, "scenetracker_username_exists"));

	$count = count($participants);

	if ($count < 2) {
		return false;
	}

	$position = array_search($character['username'], $participants, true);

	if ($position === false) {
		return false;
	}

	// Wenn der aktuelle Charakter der erste in der Liste ist, springen wir zum letzten Teilnehmer zurück.
	$previous_position = ($position - 1 + $count) % $count;
	$previous_character = $participants[$previous_position];

	$lastposter = trim($scene['lastposter']);
	// Hat der vorherige Charakter zuletzt gepostet?
	if (strcasecmp($lastposter, $previous_character) === 0) {
		return true;
	}

	// Nein, also ist der aktuelle Charakter nicht dran
	return false;
}

/**
 * Checkt ob ein User noch existiert.
 *
 * @param string $username
 * @return bool
 */
function scenetracker_username_exists($username)
{
	global $db;

	static $user_cache = [];

	$username = trim($username);

	if ($username === "") {
		return false;
	}

	$cache_key = mb_strtolower($username, 'UTF-8');

	//schonmal geprüft -> im cache
	if (array_key_exists($cache_key, $user_cache)) {
		return $user_cache[$cache_key];
	}

	//noch nicht geprüft -> aus der DB ziehen.
	$uid = get_user_by_username($username)['uid'];

	// in chache speicehrn 
	$user_cache[$cache_key] = !empty($uid);

	return $user_cache[$cache_key];
}

/**
 * Sortiert Szeneneinträge nach dem letzten Beitrag.
 *
 * @param array $entries Szeneneinträge
 * @param string $direction asc oder desc
 * @return array
 */
function scenetracker_ucp_sort_scenes(array $entries, $direction = "desc")
{
	// Aufsteigend sortieren: ältester Beitrag zuerst
	if ($direction === 'asc') {
		usort($entries, function ($a, $b) {
			$lastpost_a = (int)$a['scene']['lastpost'];
			$lastpost_b = (int)$b['scene']['lastpost'];
			return $lastpost_a <=> $lastpost_b;
		});
	} else {
		// Absteigend sortieren: neuester Beitrag zuerst
		usort($entries, function ($a, $b) {
			$lastpost_a = (int)$a['scene']['lastpost'];
			$lastpost_b = (int)$b['scene']['lastpost'];
			return $lastpost_b <=> $lastpost_a;
		});
	}

	return $entries;
}

/**
 * Filtert die Szenen
 *
 * @param array $entries            Geladene Szeneneinträge
 * @param array $filters            Ausgewählte Filter
 * @param array $partner_characters Charaktere des Postpartners
 *
 * @return array
 */
function scenetracker_ucp_filter_scenes(array $entries,	array $filters,	array $partner_characters = [])
{

	$filtered = [];
	$partner_lookup = [];

	foreach ($partner_characters as $username) {
		$key = mb_strtolower(trim($username), "UTF-8");

		if ($key !== "") {
			$partner_lookup[$key] = true;
		}
	}

	foreach ($entries as $entry) {
		$scene = $entry['scene'];
		$character_uid = (int)$entry['character_uid'];

		if (!empty($filters['character_uid'])	&& $character_uid !== (int)$filters['character_uid']) {
			continue;
		}
		// Status
		$is_closed = (int)$scene['closed'] === 1;

		// threadsolved
		if (array_key_exists("threadsolved", $scene)) {
			$is_closed = $is_closed
				|| (int)$scene['threadsolved'] === 1;
		}

		if ($filters['status'] === "open" && $is_closed) {
			continue;
		}

		if ($filters['status'] === "closed" && !$is_closed) {
			continue;
		}

		// turn
		if ($filters['turn'] !== "beides") {
			$is_turn = scenetracker_ucp_is_characters_turn($scene,	$character_uid);
			if ($filters['turn'] === "ja" && !$is_turn) {
				continue;
			}
			if ($filters['turn'] === "nein" && $is_turn) {
				continue;
			}
		}

		// Postpartner
		if ($filters['player'] !== '') {
			$participants = array_filter(
				array_map("trim",	explode(",", (string)$scene['scenetracker_user']))
			);

			$contains_partner = false;

			foreach ($participants as $participant) {
				$key = mb_strtolower($participant, "UTF-8");

				if (isset($partner_lookup[$key])) {
					$contains_partner = true;
					break;
				}
			}

			if (!$contains_partner) {
				continue;
			}
		}

		$entry['is_turn'] = scenetracker_ucp_is_characters_turn($scene,	$character_uid);

		$filtered[] = $entry;
	}

	return $filtered;
}

/**
 * Szenen nach Charakter gruppieren
 *
 * @param array $entries
 * @param array $characters Reihenfolge der eigenen Charaktere
 *
 * @return array
 */
function scenetracker_ucp_group_scenes_by_character(array $entries, array $characters)
{
	$groups = [];

	foreach ($characters as $uid => $username) {
		$groups[(int)$uid] = [
			"uid" => (int)$uid,
			"username" => $username,
			"scenes" => [],
		];
	}

	foreach ($entries as $entry) {
		$uid = (int)$entry['character_uid'];

		if (!isset($groups[$uid])) {
			$groups[$uid] = [
				"uid" => $uid,
				"username" => $entry['character_username'],
				"scenes" => [],
			];
		}

		$groups[$uid]['scenes'][] = $entry;
	}

	return $groups;
}

/**
 * Übersicht der eigenen Charakterevents und Geburtstage.
 *
 * URL:
 * usercp.php?action=scenetracker&view=events
 */
function scenetracker_ucp_events()
{
	global $db, $mybb, $templates, $lang, $header, $headerinclude, $footer, $usercpnav;
	$page = "";
	if ((int)$mybb->user["uid"] === 0) {
		error_no_permission();
	}

	//Variablen leeren
	$scenetracker_ucp_events_list = "";
	$scenetracker_ucp_events_calendar = "";
	$scenetracker_ucp_events_empty = "";
	$character_options = "";

	//Charaktere des users
	$characters = scenetracker_get_accounts((int)$mybb->user["uid"]);

	//Filter
	$selected_uid = $mybb->get_input("character_uid", MyBB::INPUT_INT);

	$view = $mybb->get_input("event_view");

	if ($view !== "calendar") {
		$view = "list";
	}

	//Nur eigene Charaktere 
	$selected_characters = scenetracker_ucp_get_selected_characters($characters, $selected_uid);

	//ungültige uid -> also alle
	if (empty($selected_characters)) {
		$selected_uid = 0;
		$selected_characters = $characters;
	}

	//Unser Select bauen
	$selected = $selected_uid === 0 ? "selected" : "";
	$character_options .= "<option value=\"0\" {$selected}>Alle Charaktere</option>";
	foreach ($characters as $character_uid => $character_name) {
		$selected = ($selected_uid === (int)$character_uid) ? "selected" : "";
		$character_name = htmlspecialchars_uni($character_name);
		$character_options .= "<option value=\"" . (int)$character_uid . "\" {$selected}>{$character_name}</option> ";
	}

	// Events und Geburtstag laden
	$events = scenetracker_ucp_load_events($selected_characters);
	$birthdays = scenetracker_ucp_load_birthdays($selected_characters);

	//Ansicht Liste oder Kalender
	if ($view === "calendar") {
		$scenetracker_ucp_events_calendar = scenetracker_ucp_build_event_calendar($events, $birthdays);
	} else {
		$scenetracker_ucp_events_list = scenetracker_ucp_build_event_list($events, $birthdays, 	$characters);
	}

	//filter links
	$list_active = $view === "list" ? "active" : "";

	$calendar_active = $view === "calendar" ? "active" 	: "";

	// event link hinzufügen
	$event_add_link = 	"misc.php?action=calendar" . "&amp;do=add_event";

	eval("\$page = \"" .	$templates->get("scenetracker_ucp_events") . "\";");

	output_page($page);
}

/**
 * Lädt die Events bestimmter eigener Charaktere.
 *
 * @param array $characters [uid => username]
 * @return array
 */
function scenetracker_ucp_load_events(array $characters)
{
	global $db;

	if (empty($characters)) {
		return array();
	}

	$uids = array_map("intval", array_keys($characters));

	$uid_list = implode(",", $uids);
	$events = array();
	$query = $db->simple_select("scenetracker_event", "*", "uid IN ({$uid_list})", array("order_by" => "startdate", "order_dir" => "ASC"));

	while ($event = $db->fetch_array($query)) {
		$events[] = $event;
	}

	return $events;
}

/**
 * Lädt die Geburtstage bestimmter eigener Charaktere.
 *
 * @param array $characters [uid => username]
 * @return array
 */
function scenetracker_ucp_load_birthdays(array $characters)
{
	global $db;

	$birthdays = array();

	if (empty($characters)) {
		return $birthdays;
	}

	$birthday_query = scenetracker_get_birthdays();

	if ($birthday_query === "") {
		return $birthdays;
	}

	$query = $db->write_query($birthday_query);

	while ($birthday = $db->fetch_array($query)) {
		$uid = (int)$birthday["uid"];
		if (!isset($characters[$uid])) {
			continue;
		}
		if (empty($birthday["birthday"])) {
			continue;
		}
		$birthdays[] = $birthday;
	}

	return $birthdays;
}

/**
 * Baut die Listenansicht für Events und Geburtstage.
 *
 * @param array $events
 * @param array $birthdays
 * @param array $characters
 * @return string
 */
function scenetracker_ucp_build_event_list(array $events, array $birthdays, array $characters)
{
	global $templates, $mybb;
	$output = "";

	$recurring_labels = array(
		0 => "Einmalig",
		1 => "Wöchentlich",
		2 => "Monatlich",
		3 => "Quartalsweise",
		4 => "Halbjährlich",
		5 => "Jährlich",
		6 => "Alle X Tage"
	);

	//Events
	foreach ($events as $event) {
		$event_id = (int)$event["id"];
		$character_uid = (int)$event["uid"];

		$character_name = $characters[$character_uid] ?? "";

		$character_name = htmlspecialchars_uni($character_name);
		$event_name = htmlspecialchars_uni($event["name"]);
		$start = scenetracker_event_create_date($event["startdate"]);
		$end = scenetracker_event_create_date($event["enddate"]);

		$event_startdate = $start ? $start->format("d.m.Y") : "";
		$event_enddate = $end ? $end->format("d.m.Y") : "";

		if ($event_startdate === $event_enddate) {
			$event_date_output = $event_startdate;
		} else {
			$event_date_output = $event_startdate . " - " . $event_enddate;
		}

		$recurring = (int)$event["recurring"];

		$event_recurring = $recurring_labels[$recurring] ?? "Einmalig";

		//spezialfall kennzeichnen
		if ($recurring === 5 && (int)$event["recurring_occurrence"] !== 0) {
			$event_recurring = scenetracker_ucp_get_special_repeat_label($event);
		}

		if ($recurring === 6) {
			$days = (int)$event["recurring_interval"];
			$event_recurring = "Alle {$days} Tage";
		}

		$event_visibility = (int)$event["private"] === 1 ? "Privat" : "Öffentlich";
		$event_visibility_class = (int)$event["private"] === 1 ? "private" : "public";


		if (!empty($event["official"])) {
			$event_visibility = "Offiziell";
		}

		$event_detail_link = "misc.php?action=calendar" . "&amp;do=view_event" . "&amp;id={$event_id}&amp;";
		$event_edit_link = "misc.php?action=calendar" . "&amp;do=edit_event" . "&amp;id={$event_id}&amp;src=ucp";
		$event_delete_link = "misc.php?action=calendar" . "&amp;do=delete_event" . "&amp;id={$event_id}&amp;src=ucp";

		eval("\$output .= \"" . $templates->get("scenetracker_ucp_events_bit") . "\";");
	}

	//Geburtstage
	foreach ($birthdays as $birthday) {
		$character_uid = (int)$birthday["uid"];
		$character_name = htmlspecialchars_uni($birthday["username"]);
		$birthday_object = DateTime::createFromFormat("Y-m-d", $birthday["birthday"]);
		if (!$birthday_object) {
			continue;
		}
		$birthday_date = $birthday_object->format("d.m.");
		eval("\$output .= \"" . $templates->get("scenetracker_ucp_events_birthday_bit") . "\";");
	}

	if ($output === "") {
		eval("\$output = \"" . $templates->get("scenetracker_ucp_events_empty") . "\";");
	}

	return $output;
}


/**
 * Gibt einen Spezialfall lesbar aus.
 *
 * z.B. "Jährlich am 3. Montag im Januar"
 */
function scenetracker_ucp_get_special_repeat_label(array $event)
{
	$weekdays = array(
		1 => "Montag",
		2 => "Dienstag",
		3 => "Mittwoch",
		4 => "Donnerstag",
		5 => "Freitag",
		6 => "Samstag",
		7 => "Sonntag"
	);

	$months = array(
		1 => "Januar",
		2 => "Februar",
		3 => "März",
		4 => "April",
		5 => "Mai",
		6 => "Juni",
		7 => "Juli",
		8 => "August",
		9 => "September",
		10 => "Oktober",
		11 => "November",
		12 => "Dezember"
	);

	$occurrence = (int)$event["recurring_occurrence"];

	if ($occurrence === -1) {
		$occurrence_label = "letzten";
	} else {
		$occurrence_label = $occurrence . ".";
	}

	$weekday = 	$weekdays[(int)$event["recurring_weekday"]] ?? "";

	$month = 	$months[(int)$event["recurring_month"]] ?? "";

	return "Jährlich am {$occurrence_label} {$weekday} im {$month}";
}

/**
 * Baut den UCP-Eventkalender.
 *
 * Enthält nur eigene Charakterevents und Geburtstage.
 */
function scenetracker_ucp_build_event_calendar(array $events, array $birthdays)
{
	global $mybb, $templates, $lang, $scenetracker_calendar_day;

	$lang->load("calendar");

	$month = $mybb->get_input("month", MyBB::INPUT_INT);
	$year = $mybb->get_input("year", MyBB::INPUT_INT);

	//default: erster ingame monat
	if ($month < 1 || $month > 12 || $year <= 0) {
		$ingame = scenetracker_get_ingame_range();
		$first_month = $ingame["months"][0];
		$default_date = DateTime::createFromFormat("!Y-m-d", $first_month . "-01");
		$month = (int)$default_date->format("n");
		$year = (int)$default_date->format("Y");
	}

	//kalender bereich
	$month_start = DateTime::createFromFormat("!Y-n-j", "{$year}-{$month}-1");

	$weekday = (int)$month_start->format("N");

	$calendar_start = clone $month_start;
	$calendar_start->modify("-" . ($weekday - 1) . " days");

	$month_end = clone $month_start;
	$month_end->modify("last day of this month");

	$calendar_end = clone $month_end;

	$last_weekday = (int)$calendar_end->format("N");

	if ($last_weekday < 7) {
		$calendar_end->modify("+" . (7 - $last_weekday) . " days");
	}

	//event array
	$calendar_events = array();

	//events des charakters und wiederholungen
	foreach ($events as $event) {

		$occurrences = scenetracker_calendar_build_event_occurrences($event, $calendar_start, $calendar_end);

		foreach ($occurrences as $occurrence) {
			$calendar_events[] = array(
				"id" => (int)$event["id"],
				"type" => "event",
				"title" => $event["name"],
				"description" => $event["description"],
				"uid" => (int)$event["uid"],
				"private" => (int)$event["private"],
				"official" => (int)($event["official"] ?? 0),
				"recurring" => (int)$event["recurring"],
				"startdate" => clone $occurrence["start"],
				"enddate" => clone $occurrence["end"]
			);
		}
	}

	//Geburtstage anzeigen
	foreach ($birthdays as $birthday) {
		$birthday_original = DateTime::createFromFormat("Y-m-d", $birthday["birthday"]);

		if (!$birthday_original) {
			continue;
		}

		$start_year = (int)$calendar_start->format("Y");

		$end_year = (int)$calendar_end->format("Y");

		for ($birthday_year = $start_year; $birthday_year <= $end_year; $birthday_year++) {
			$birthday_date =
				DateTime::createFromFormat("!Y-n-j", sprintf("%04d-%d-%d", $birthday_year, (int)$birthday_original->format("n"), (int)$birthday_original->format("j")));

			if (!$birthday_date) {
				continue;
			}

			if ($birthday_date < $calendar_start || $birthday_date > $calendar_end) {
				continue;
			}
			$calendar_events[] = array(
				"id" => (int)$birthday["uid"],
				"type" => "birthday",
				"title" => $birthday["username"],
				"description" => null,
				"startdate" => clone $birthday_date,
				"enddate" => clone $birthday_date,
				"birthyear" => (int)$birthday_original->format("Y")
			);
		}
	}

	//Tag bauen
	$scenetracker_calendar_day = "";
	$get_days = scenetracker_calendar_build_day_array($month, $year, $calendar_events);

	foreach ($get_days as $day) {
		scenetracker_calendar_build_day_template($day, "page");
	}

	//Navigation ucp kalender
	$previous = clone $month_start;
	$previous->modify("-1 month");

	$next = clone $month_start;
	$next->modify("+1 month");

	$character_uid = $mybb->get_input("character_uid", MyBB::INPUT_INT);

	$prev_link = "usercp.php?action=scenetracker" . "&amp;view=events" . "&amp;event_view=calendar" . "&amp;character_uid={$character_uid}" . "&amp;month=" . (int)$previous->format("n") . "&amp;year=" . (int)$previous->format("Y");

	$next_link = "usercp.php?action=scenetracker" . "&amp;view=events" . "&amp;event_view=calendar" . "&amp;character_uid={$character_uid}" . "&amp;month=" . (int)$next->format("n") . "&amp;year=" . (int)$next->format("Y");

	$calendar_month_name = $lang->{"alt_month_" . $month};

	$calendar_year = $year;
	$calendar = "";
	eval("\$calendar = \"" . $templates->get("scenetracker_ucp_events_calendar") . "\";");
	return $calendar;
}

/**
 * automatische Anzeige von Tracker im Profil
 */
$plugins->add_hook("member_profile_end", "scenetracker_showinprofile");
function scenetracker_showinprofile()
{
	global $db, $mybb, $memprofile, $templates, $scenetracker_profil;
	$thisuser = intval($mybb->user['uid']);
	$userprofil = intval($memprofile['uid']);
	$scenetracker_profil_bit = "";
	$solved = "";
	$dateYear = "";

	$allowmanage = scenetracker_check_switcher($userprofil);

	//hide scene in profile
	if ($mybb->get_input("show") == "0") {
		$id = intval($mybb->get_input("getsid"));
		scenetracker_scene_change_view(0, $id, $userprofil);
		redirect("member.php?action=profile&uid=" . $userprofil);
	}
	//close scene from profile
	if ($mybb->get_input("closed") == "1") {
		$tid = intval($mybb->get_input("gettid"));
		scenetracker_scene_change_status(1,  $tid,  $userprofil);
		redirect("member.php?action=profile&uid=" . $userprofil);
	} elseif ($mybb->get_input("closed") == "0") {
		$tid = intval($mybb->get_input("gettid"));
		scenetracker_scene_change_status(0,  $tid,  $userprofil);
		redirect("member.php?action=profile&uid=" . $userprofil);
	}

	$scenequerie = scenetracker_build_sql_threadtable("all", $userprofil, array("add_table" => "scenetracker", "order_by" => "scenetracker_date DESC", "profil_view" => 1));

	$scene_query = $db->write_query($scenequerie);

	while ($scenes = $db->fetch_array($scene_query)) {
		$tid = $scenes['tid'];
		$sid = $scenes['id'];
		$subject = $scenes['subject'];
		$sceneusers = str_replace(",", ", ", $scenes['scenetracker_user']);
		$sceneplace = $scenes['scenetracker_place'];
		if ($scenes['scenetracker_trigger'] != "") {
			eval("\$scenetrigger = \"" . $templates->get("scenetracker_trigger") . "\";");
		} else {
			$scenetrigger = "";
		}

		if ($scenes['closed'] == 1) {
			if ($allowmanage || $mybb->usergroup['canmodcp'] == 1) {
				eval("\$scenestatus = \"" . $templates->get("scenetracker_scenestatus_solved_mod") . "\";");
			} else {
				eval("\$scenestatus = \"" . $templates->get("scenetracker_scenestatus_solved") . "\";");
			}
		} else {
			if ($allowmanage || $mybb->usergroup['canmodcp'] == 1) {
				eval("\$scenestatus = \"" . $templates->get("scenetracker_scenestatus_unsolved_mod") . "\";");
			} else {
				eval("\$scenestatus = \"" . $templates->get("scenetracker_scenestatus_unsolved") . "\";");
			}
		}

		if ($allowmanage || $mybb->usergroup['canmodcp'] == 1) {
			eval("\$scenehide = \"" . $templates->get("scenetracker_scenestatus_hide") . "\";");
		} else {
			$scenehide = "";
		}

		$scenedate_arr = scenetracker_get_scenedate($tid);
		$scenedateMonthYear = $scenedate_arr['mY'];
		$scenedate = $scenedate_arr['date_all'];
		if ($dateYear != $scenedateMonthYear) {
			$scenedatetitle = $scenedate_arr['mY'];
			eval("\$scenetracker_profil_bit_mY = \"" . $templates->get("scenetracker_profil_bit_mY") . "\";");
			$dateYear = $scenedate_arr['mY'];
		} else {
			$scenetracker_profil_bit_mY = "";
		}
		eval("\$scenetracker_profil_bit .= \"" . $templates->get("scenetracker_profil_bit") . "\";");
	}

	scenetracker_profile_events($userprofil);

	eval("\$scenetracker_profil= \"" . $templates->get("scenetracker_profil") . "\";");
}

/**
 * Charakterereignisse im Profil anzeigen
 *
 * Aktuelle und kommende Ereignisse:
 * Ab Beginn des aktuellen Ingame-Zeitraums
 *
 * Vergangene Ereignisse:
 * Vor Beginn des aktuellen Ingame-Zeitraums
 *
 * @param int $userprofil UID des Profils
 */
function scenetracker_profile_events($userprofil)
{
	global $db, $mybb, $templates, $scenetracker_profil_events;
	global $event_id, $event_name, $event_description, $event_date, $event_startdate, $event_enddate, $event_visibility, $event_recurring_text;
	$scenetracker_profil_events = "";
	$scenetracker_profil_events_upcoming = "";
	$scenetracker_profil_events_past = "";

	$events_upcoming = array();
	$events_past = array();

	$past_count = 0;

	$userprofil = (int)$userprofil;

	// Ingame-Zeitraum ermitteln
	$ingame_range = scenetracker_get_ingame_range();

	if ($ingame_range === null) {
		return;
	}

	$ingame_start = clone $ingame_range["start"];

	// Berechtigungen prüfen
	$allowmanage = scenetracker_check_switcher($userprofil);

	$can_view_private = false;

	if ($allowmanage || (int)$mybb->usergroup["canmodcp"] === 1) {
		$can_view_private = true;
	}

	// Nur Ereignisse dieses Charakters
	$where = "uid = '{$userprofil}'";

	// Private Ereignisse nur für berechtigte User
	if (!$can_view_private) {
		$where .= " AND private = 0";
	}

	$query = $db->simple_select("scenetracker_event", "*", $where, array("order_by" => "startdate", "order_dir" => "ASC"));

	// Alle Ereignisse durchgehen
	while ($event = $db->fetch_array($query)) {

		$event_start = scenetracker_event_create_date($event["startdate"]);
		$event_end = scenetracker_event_create_date($event["enddate"]);

		if (!$event_start) {
			continue;
		}

		if (!$event_end) {
			$event_end = clone $event_start;
		}

		if ($event_end < $event_start) {
			continue;
		}

		$recurring = (int)$event["recurring"];

		// Einmalige Ereignisse
		if ($recurring === 0) {

			$event["display_start"] = clone $event_start;
			$event["display_end"] = clone $event_end;

			if ($event_end < $ingame_start) {

				// Vor dem aktuellen Ingame-Zeitraum: Archiv
				$events_past[] = $event;
			} else {

				// Aktuell oder zukünftig
				$events_upcoming[] = $event;
			}

			continue;
		}

		// Wiederkehrende Ereignisse:
		// Alle Vorkommen innerhalb des aktuellen Ingame-Zeitraums
		$occurrences = scenetracker_calendar_build_event_occurrences(
			$event,
			$ingame_range["start"],
			$ingame_range["end"]
		);

		foreach ($occurrences as $occurrence) {

			$event_entry = $event;

			$event_entry["display_start"] = clone $occurrence["start"];
			$event_entry["display_end"] = clone $occurrence["end"];

			$events_upcoming[] = $event_entry;
		}
	}
	// sortieren 
	usort($events_upcoming, function ($a, $b) {
		return $a["display_start"] <=> $b["display_start"];
	});

	// vergangene Ereignisse: neueste zuerst
	usort($events_past, function ($a, $b) {
		return $b["display_start"] <=> $a["display_start"];
	});

	$past_count = count($events_past);

	// ausgabe kommende
	$last_year = "";

	foreach ($events_upcoming as $event) {

		$event_year = $event["display_start"]->format("Y");

		// Neues Jahr?
		if ($last_year !== $event_year) {

			// vorherige Jahresgruppe abschließen
			if ($last_year !== "") {
				eval("\$scenetracker_profil_events_upcoming .= \"" . $templates->get("scenetracker_profil_events_year_end") . "\";");
			}

			$last_year = $event_year;
			eval("\$scenetracker_profil_events_upcoming .= \"" . $templates->get("scenetracker_profil_events_year_start") . "\";");
		}

		// eventdaten für das Template vorbereiten
		scenetracker_profile_event_prepare($event);

		eval("\$scenetracker_profil_events_upcoming .= \"" . $templates->get("scenetracker_profil_events_bit") . "\";");
	}

	// Letzte Jahresgruppe schließen
	if ($last_year !== "") {
		eval("\$scenetracker_profil_events_upcoming .= \"" . $templates->get("scenetracker_profil_events_year_end") . "\";");
	}

	$last_year = "";
	foreach ($events_past as $event) {

		$event_year = $event["display_start"]->format("Y");

		if ($last_year !== $event_year) {
			if ($last_year !== "") {
				eval("\$scenetracker_profil_events_past .= \"" . $templates->get("scenetracker_profil_events_year_end") . "\";");
			}

			$last_year = $event_year;
			eval("\$scenetracker_profil_events_past .= \"" . $templates->get("scenetracker_profil_events_year_start") . "\";");
		}

		// eventdaten für das Template vorbereiten
		scenetracker_profile_event_prepare($event);

		eval("\$scenetracker_profil_events_past .= \"" . $templates->get("scenetracker_profil_events_bit") . "\";");
	}

	if ($last_year !== "") {
		eval("\$scenetracker_profil_events_past .= \"" . $templates->get("scenetracker_profil_events_year_end") . "\";");
	}

	if ($scenetracker_profil_events_upcoming === "") {
		eval("\$scenetracker_profil_events_upcoming = \"" . $templates->get("scenetracker_profil_events_empty") . "\";");
	}

	$scenetracker_profil_events_archive = "";
	if ($past_count > 0) {
		eval("\$scenetracker_profil_events_archive = \"" . $templates->get("scenetracker_profil_events_archive") . "\";");
	}

	eval("\$scenetracker_profil_events = \"" . $templates->get("scenetracker_profil_events") . "\";");
}

/**
 * Bereitet die Variablen eines Charakterereignisses
 * für die Profiltemplates vor.
 */
function scenetracker_profile_event_prepare($event)
{
	global $event_id, $event_name, $event_description, $event_date, $event_startdate, $event_enddate, $event_visibility, $event_recurring_text;

	$event_id = (int)$event["id"];

	$event_name = htmlspecialchars_uni($event["name"]);
	$event_description = nl2br(htmlspecialchars_uni($event["description"]));

	$start = $event["display_start"];
	$end = $event["display_end"];

	$event_startdate = $start->format("d.m.Y");
	$event_enddate = $end->format("d.m.Y");

	// Datum für die Ausgabe
	if ($start->format("Y-m-d") === $end->format("Y-m-d")) {
		$event_date = $event_startdate;
	} else {
		$event_date = $event_startdate . " – " . $event_enddate;
	}

	$event_visibility = "";

	if ((int)$event["private"] === 1) {
		$event_visibility = "Privat";
	}

	$event_recurring_text = "";

	$recurring = (int)$event["recurring"];

	if ($recurring === 1) {
		$event_recurring_text = "Wöchentlich";
	} elseif ($recurring === 2) {
		$event_recurring_text = "Monatlich";
	} elseif ($recurring === 3) {
		$event_recurring_text = "Quartalsweise";
	} elseif ($recurring === 4) {
		$event_recurring_text = "Halbjährlich";
	} elseif ($recurring === 5) {
		$event_recurring_text = "Jährlich";
	} elseif ($recurring === 6) {
		$days = (int)$event["recurring_interval"];
		$event_recurring_text = "Alle {$days} Tage";
	}

	// Spezialregel bei jährlichen Ereignissen
	if ($recurring === 5 && (int)$event["recurring_occurrence"] !== 0) {
		$event_recurring_text = "Jährlich (besonderer Termin)";
	}
}

/**
 * Szenenanzahl global - user der online ist
 * verwendung:
 * $counter: Anzeige alle Szenen des users alle/offene
 * $counter_chara: Anzeige des eingeloggten charas alle/offene
 */
$plugins->add_hook('global_intermediate', 'scenetracker_global_counter_thisuser');

function scenetracker_global_counter_thisuser()
{
	global $db, $mybb, $lang, $counter, $cnts, $templates, $counter_chara;

	$counter = '';
	$counter_chara = '';

	$user = (int)($mybb->user['uid'] ?? 0);

	// Für Gäste keine Szenen zählen
	if ($user === 0) {
		return;
	}

	$charas = scenetracker_get_accounts($user);
	$cnts = scenetracker_count_scenes($charas);

	// Fehlende Zählerwerte absichern
	$counter_chara_open = (int)($cnts[$user]['open'] ?? 0);
	$counter_chara_all = (int)($cnts[$user]['all'] ?? 0);

	$counter = eval($templates->render("scenetracker_counter_all"));
	$counter_chara = eval($templates->render("scenetracker_counter_character"));
}


/**
 * Szenenanzahl Memberlist 
 * verwendung:
 * $counter: Anzeige alle Szenen des users alle/offene
 * $counter_chara: Anzeige des eingeloggten charas alle/offene
 */
$plugins->add_hook('memberlist_user', 'scenetracker_global_counter_memberlist');
function scenetracker_global_counter_memberlist(&$user)
{
	global $db, $mybb, $lang, $counter, $cnts, $templates, $counter_chara;
	$useruid = $user['uid'];
	$charas = scenetracker_get_accounts($useruid);
	$cnts = scenetracker_count_scenes($charas);

	$counter_chara_open = $cnts[$useruid]['open'];
	$counter_chara_all = $cnts[$useruid]['all'];

	$counter = eval($templates->render("scenetracker_counter_all"));
	$counter_chara = eval($templates->render("scenetracker_counter_character"));
}

/**
 * Szenenanzahl Memberprofile  
 * Verwendung:
 * $counter: Anzeige alle Szenen des users alle/offene
 * $counter_chara: Anzeige des eingeloggten charas alle/offene
 */
$plugins->add_hook('member_start', 'scenetracker_global_counter_memberprofile');
function scenetracker_global_counter_memberprofile()
{
	global $db, $mybb, $lang, $counter, $cnts, $templates, $counter_chara, $memprofile;
	$useruid = $memprofile['uid'];
	$charas = scenetracker_get_accounts($useruid);
	$cnts = scenetracker_count_scenes($charas);

	$counter_chara_open = $cnts[$useruid]['open'];
	$counter_chara_all = $cnts[$useruid]['all'];

	$counter = eval($templates->render("scenetracker_counter_all"));
	$counter_chara = eval($templates->render("scenetracker_counter_character"));
}

/**
 * Szenenanzahl im Postbit
 * $post['scene_counter_all'] Anzeige alle Szenen des users alle/offene
 * $post['scene_counter_chara'] Anzeige des eingeloggten charas alle/offene
 */
$plugins->add_hook('postbit', 'scenetracker_global_counter_post');
function scenetracker_global_counter_post(&$post)
{
	global $db, $mybb, $lang, $pid, $templates, $counter;
	$post['scene_counter_all'] = "";
	$post['scene_counter_chara'] = "";

	if ($post['uid'] != 0) {
		$charas = scenetracker_get_accounts($post['uid']);
		$cnts = scenetracker_count_scenes($charas);
		$poster = $post['uid'];
		$counter_chara_open = $cnts[$poster]['open'];
		$counter_chara_all = $cnts[$poster]['all'];

		$post['scene_counter_all'] = eval($templates->render('scenetracker_counter_all'));
		$post['scene_counter_chara'] = eval($templates->render('scenetracker_counter_character'));
	}
}

/**
 * Anzeige der Szenen auf dem Index
 * -> abhängig von Indexeinstellung
 */
$plugins->add_hook('index_start', 'scenetracker_index_list');
function scenetracker_index_list()
{
	global $templates, $db, $mybb, $scenetracker_index_main, $scenetracker_index_bit_chara, $expthead, $lang, $expcolimage, $expaltext, $expaltext, $expdisplay, $theme, $collapse, $collapsed, $collapsedimg, $collapsedthead, $counter, $sel_array;
	//set as uid
	$counter = "";
	$uid = $mybb->user['uid'];
	$index = $db->fetch_field($db->simple_select("users", "tracker_index", "uid = $uid"), "tracker_index");
	$index_all = $db->fetch_field($db->simple_select("users", "tracker_indexall", "uid = $uid"), "tracker_indexall");

	if ($uid != 0 && $index == 1) {
		if ($index_all == 1) {
			$charas = scenetracker_get_accounts($mybb->user['uid']);
		} else {
			$charas[$uid] = $mybb->user['username'];
		}

		//show new scenes
		scenetracker_get_scenes($charas, "index");

		//change inform status always/never/certain user
		if ($mybb->get_input('certainuser')) {
			//info by
			$certained = intval($mybb->get_input('charakter'));
			//for which scene
			$id = intval($mybb->get_input('getid'));
			//Einstellung für Indexanzeige:
			$certained = intval($mybb->get_input('charakter'));
			scenetracker_scene_inform_status($id, "index", $certained);

			//Einstellung für alert
			$alert = intval($mybb->get_input('alert'));
			scenetracker_scene_inform_status($id, "alert", $alert);

			//Einstellung für Reminder
			$reminder = intval($mybb->get_input('reminder'));
			$reminder_days = intval($mybb->get_input('reminder_days'));
			scenetracker_scene_inform_status($id, "reminder", $reminder, $reminder_days);
			redirect('index.php');
		}

		//ausklappbar / einklappbar machen
		if (!empty($mybb->cookies['collapsed'])) {
			$colcookie = $mybb->cookies['collapsed'];

			// Preserve and don't unset $collapse, will be needed globally throughout many pages
			$collapse = explode("|", $colcookie);
			foreach ($collapse as $val) {
				$collapsed[$val . "_e"] = "display: none;";
				$collapsedimg[$val] = "_collapsed";
				$collapsedthead[$val] = " thead_collapsed";
			}
		}
		if (!isset($collapsedthead['szenenindex'])) {
			$collapsedthead['szenenindex'] = '';
		}
		if (!isset($collapsedimg['szenenindex'])) {
			$collapsedimg['szenenindex'] = '';
		}
		$expaltext = (in_array("szenenindex", $collapse ?? [])) ? $lang->expcol_expand : $lang->expcol_collapse;

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
		$counter = eval($templates->render('scenetracker_counter_all'));

		eval("\$scenetracker_index_main =\"" . $templates->get("scenetracker_index_main") . "\";");
	}
}

/******************************
 * Reminder
 * Erinnerung, wenn man den Postpartner X Tage warten lässt
 ******************************/
$plugins->add_hook('index_start', 'scenetracker_reminder');

function scenetracker_reminder()
{
	global $mybb, $db, $templates, $scenetracker_index_reminder;

	$scenetracker_index_reminder = "";
	$scenetracker_index_reminder_bit = "";

	$uid = (int)$mybb->user['uid'];

	// Reminder für eine einzelne Szene ausschalten
	if ($mybb->get_input("action") === "reminder") {
		$sceneid = $mybb->get_input("sceneid", MyBB::INPUT_INT);
		$db->update_query("scenetracker", array("index_view_reminder" => 0,), "id = '{$sceneid}'");
		redirect("index.php", "Die Anzeige kannst du in deiner Szenentracker Verwaltung wieder aktivieren.");
	}
	//Gast
	if ($uid === 0) {
		return;
	}

	$charas = scenetracker_get_accounts($uid);
	// Reminder für alle ausschalten
	if ($mybb->get_input('action') === "reminder_all") {

		foreach ($charas as $chara_uid => $chara) {
			$db->update_query("users", array("tracker_reminder" => 0,), "uid = '" . (int)$chara_uid . "'");
		}

		redirect("usercp.php?action=scenetracker", "Du hast den Reminder allgemein ausgeschaltet. Die Anzeige kannst du in deinem UCP wieder aktivieren.");
	}

	// Reminder eingeschaltet?
	$reminder = (int)$mybb->user['tracker_reminder'];

	if ($reminder !== 1) {
		return;
	}

	// Standardanzahl der Reminder-Tage aus dem ACP
	$days_admin = (int)$mybb->settings['scenetracker_reminder'];

	//Alle Szenen des Uses
	$scenes = scenetracker_ucp_load_scenes($charas);

	//heute
	$today = new DateTime();
	$has_reminder = false;

	//check Scenes
	foreach ($scenes as $entry) {
		$character_uid = (int)$entry['character_uid'];
		$scene = $entry['scene'];

		//reminder einstellungen für diese szene
		if ((int)$scene['index_view_reminder'] !== 1) {
			continue;
		}
		if ((int)$scene['closed'] === 1) {
			continue;
		}
		//ist der Charakter dran? 
		if (!scenetracker_ucp_is_characters_turn($scene, $character_uid)) {
			continue;
		}

		$days = (int)$scene['index_view_reminder_days'];

		// 0 = Standard wert
		if ($days === 0) {
			$days = $days_admin;
		}

		$postdate = new DateTime();
		$postdate->setTimestamp((int)$scene['lastpost']);
		$interval = $postdate->diff($today);

		$lastpostdays = (int)$interval->days;
		// all good, szene ist neuer
		if ($lastpostdays < $days) {
			continue;
		}

		$has_reminder = true;
		$sceneid = (int)$scene['id'];

		$userarr = get_user($character_uid);

		eval("\$scenetracker_index_reminder_bit .= \"" . $templates->get("scenetracker_index_reminder_bit") . "\";");
	}

	if ($has_reminder) {
		eval("\$scenetracker_index_reminder = \"" .	$templates->get("scenetracker_index_reminder") . "\";");
	}
}

/**
 * Übersicht der vorhandenen Plots
 *
 * URLs:
 * misc.php?action=scenetracker_plots
 * misc.php?action=scenetracker_plots&category=1
 * misc.php?action=scenetracker_plots&detail=1
 * 
 */
$plugins->add_hook("misc_start", "scenetracker_plots");
function scenetracker_plots()
{
	global $db, $mybb, $templates, $lang, $header, $headerinclude, $footer, $scenetracker_plots;

	if ($mybb->get_input("action") !== "scenetracker_plots") {
		return;
	}

	if (!empty($mybb->get_input("detail"))) {
		scenetracker_plots_detail($mybb->get_input("detail"));
		return;
	}
	$lang->load("scenetracker");
	$scenetracker_plots_tabs = $scenetracker_plots_list = $scenetracker_plots_empty = "";
	$categories = [];
	add_breadcrumb("Plots", "misc.php?action=misc.php?action=scenetracker_plots");

	//kategorien bekommen
	$plot_cats = $db->simple_select("scenetracker_plots_categories", "*");
	while ($plot_cat = $db->fetch_array($plot_cats)) {
		$categories[$plot_cat['id']] = $plot_cat['name'];
	}

	// Kategorie aus URL
	$selected_category = $mybb->get_input("category", MyBB::INPUT_INT);

	// Archiv ausgewählt?
	$show_archive = $mybb->get_input("archive", MyBB::INPUT_INT);

	// Wenn weder Kategorie noch Archiv angegeben,
	// standardmäßig "Alle" anzeigen
	if (!$mybb->get_input("category") && !$show_archive) {
		$selected_category = -1;
	}

	// "Alle"-Tab
	$category_id = -1;
	$category_name = "Alle";

	$category_active = ($selected_category === -1 && !$show_archive) ? "active" : "";

	$category_link = "misc.php?action=scenetracker_plots";

	eval("\$scenetracker_plots_tabs .= \"" . $templates->get("scenetracker_plots_tab") . "\";");

	// Einzelne Kategorien
	foreach ($categories as $category_id => $category_name) {

		//mark active tab
		$category_active = (!$show_archive && $selected_category === (int)$category_id) ? 'active' : '';

		//set link
		$category_link = "misc.php?action=scenetracker_plots" . "&category=" . (int)$category_id;

		eval("\$scenetracker_plots_tabs .= \"" . $templates->get("scenetracker_plots_tab") . "\";");
	}

	// Archiv-Tab
	$category_id = "archive";
	$category_name = "Archiv";
	//klasse setzen für aktives Tabe
	$category_active = ($show_archive ? "active" : "");
	$category_link = "misc.php?action=scenetracker_plots&archive=1";
	eval("\$scenetracker_plots_tabs .= \"" . $templates->get("scenetracker_plots_tab") . "\";");

	$where = array();

	//später noch filter einbauen für inaktive/abgeschlossene plots
	if ($show_archive) {
		$where[] = "active = 0";
	} else {
		$where[] = "active = 1";
		// Bestimmte Kategorie
		if ($selected_category !== -1) {
			$where[] = "category = " . (int)$selected_category;
		}
	}

	$where_string = implode(" AND ", $where);

	$query = $db->simple_select("scenetracker_plots", "*", $where_string, array("order_by"  => "startdate", "order_dir" => "ASC"));

	$plot_count = 0;

	//plot ausgeben
	while ($plot = $db->fetch_array($query)) {
		$plot_count++;
		$plot_id = (int)$plot['id'];
		$plot_name = htmlspecialchars_uni($plot['name']);

		//parser mybb
		$parser = new postParser;

		$parser_options = array(
			"allow_html" => 1,
			"allow_mycode" => 1,
			"allow_smilies" => 1,
			"allow_imgcode" => 1,
			"filter_badwords" => 0,
		);
		$plot_summary = $parser->parse_message($plot['summary'], $parser_options);

		$plot_description = $parser->parse_message($plot['description'], $parser_options);
		$plot_startdate = "";

		if (!empty($plot['startdate'])) {
			$startdate = DateTime::createFromFormat("!Y-m-d", $plot['startdate']);
			if ($startdate) {
				$plot_startdate = $startdate->format("d.m.Y");
			}
		}

		$plot_enddate = "";
		if (!empty($plot['enddate'])) {
			$enddate = DateTime::createFromFormat("!Y-m-d", $plot['enddate']);

			if ($enddate) {
				$plot_enddate = $enddate->format("d.m.Y");
			}
		}

		if ($plot_startdate == $plot_enddate) {
			$plot_date = $plot_startdate;
		} else {
			$plot_date = $plot_startdate . " - " . $plot_enddate;
		}

		$plot_category_id = (int)$plot['category'];
		$plot_category = $categories[$plot_category_id] ?? "Allgemein";

		//image
		$plot_image = '';

		if (!empty($plot['image'])) {
			$plot_image = htmlspecialchars_uni($plot['image']);
		}

		//Link zur Detailansicht 
		$plot_link = "misc.php?action=scenetracker_plots" . "&detail=" . $plot_id;
		eval("\$scenetracker_plots_list .= \"" . $templates->get("scenetracker_plots_bit") .	"\";");
	}

	if ($plot_count === 0) {
		eval("\$scenetracker_plots_empty = \"" . $templates->get("scenetracker_plots_empty") .	"\";");
	}

	eval("\$scenetracker_plots = \"" . $templates->get("scenetracker_plots") . "\";");
	output_page($scenetracker_plots);
}

/**
 * Detailansicht eines einzelnen Plots
 *
 * @param int $plot_id ID des Plots
 */
function scenetracker_plots_detail($plot_id)
{
	global $db, $mybb, $templates, $lang, $header, $headerinclude, $footer;

	$plot_id = (int)$plot_id;
	$query = $db->simple_select("scenetracker_plots", "*", "id = '{$plot_id}'");

	$plot = $db->fetch_array($query);

	if (empty($plot['id'])) {
		error("Dieser Plot existiert nicht.");
	}

	$plot_name = htmlspecialchars_uni($plot['name']);
	add_breadcrumb("Plots", "misc.php?action=scenetracker_plots");
	add_breadcrumb($plot_name, "misc.php?action=scenetracker_plots&detail={$plot_id}");

	require_once MYBB_ROOT . 'inc/class_parser.php';
	$parser = new postParser;
	$parser_options = array(
		"allow_html" => 1,
		"allow_mycode" => 1,
		"allow_smilies" => 1,
		"allow_imgcode" => 1,
		"filter_badwords" => 0,
	);

	$plot_description = $parser->parse_message($plot['description'], $parser_options);

	$plot_startdate = "";
	$plot_enddate = "";

	if (!empty($plot['startdate'])) {
		$startdate = DateTime::createFromFormat("!Y-m-d", $plot['startdate']);
		if ($startdate) {
			$plot_startdate = $startdate->format("d.m.Y");
		}
	}

	if (!empty($plot['enddate'])) {
		$enddate = DateTime::createFromFormat("!Y-m-d", $plot['enddate']);
		if ($enddate) {
			$plot_enddate = $enddate->format("d.m.Y");
		}
		$plot_date = $plot_startdate . " - " . $plot_enddate;
	}

	if ((int)$plot['active'] === 1) {
		$plot_status = "aktiv";
		$plot_status_class = "active";
	} else {
		$plot_status = "beendet";
		$plot_status_class = "inactive";
	}

	//kategorien bekommen
	$categories = [];
	$plot_cats = $db->simple_select("scenetracker_plots_categories", "*");
	while ($plot_cat = $db->fetch_array($plot_cats)) {
		$categories[$plot_cat['id']] = $plot_cat['name'];
	}

	$plot_category_id = (int)$plot['category'];

	$plot_category = $categories[$plot_category_id] ?? "Allgemein";

	$plot_image_bit = "";
	if (!empty($plot['image'])) {
		$plot_image = htmlspecialchars_uni($plot['image']);
		eval("\$plot_image_bit = \"" . $templates->get("scenetracker_plots_detail_image") . "\";");
	}

	$plot_scenes = "";
	$scene_count = 0;

	$query = $db->write_query("
		SELECT * FROM " . TABLE_PREFIX . "scenetracker_plots_tids pt
		INNER JOIN " . TABLE_PREFIX . "threads t 
			ON t.tid = pt.tid
		WHERE pt.plot_id = {$plot_id}
		  AND t.visible = 1
		ORDER BY t.scenetracker_date ASC
	");

	while ($scene = $db->fetch_array($query)) {
		$scene_count++;
		$scene_tid = (int)$scene['tid'];
		$scene_subject = htmlspecialchars_uni($scene['subject']);

		$scene_link =	"showthread.php?tid=" .	$scene_tid;

		$scene_users = str_replace(",",	", ",	$scene['scenetracker_user']);
		$scene_place = htmlspecialchars_uni($scene['scenetracker_place']);
		$scene_date = "";
		$scenedate = scenetracker_get_scenedate($thread['tid']);
		$scene_date = $scenedate['date_all'];

		if ((int)$scene['closed'] === 1) {
			$scene_status = "geschlossen";
			$scene_status_class = "closed";
		} else {
			$scene_status = "offen";
			$scene_status_class = "open";
		}

		eval("\$plot_scenes .= \"" . $templates->get("scenetracker_plots_detail_scene") . "\";");
	}

	//keine Szenen
	$plot_scenes_noscenes = "";
	if ($scene_count === 0) {
		eval("\$plot_scenes_noscenes = \"" . $templates->get("scenetracker_plots_detail_scenes_empty") . "\";");
	}

	//back
	$back_link = 'misc.php?action=scenetracker_plots';

	eval("\$scenetracker_plots_detail = \"" . $templates->get("scenetracker_plots_detail") . "\";");
	output_page($scenetracker_plots_detail);
}

/**
 * Funktion für einen Ingame Kalender - basierend auf dem Ingamezeitraum
 * Eintragen von Plots und Ereignissen
 * ähnlich dem mybb Kalender nur auch für frühere und spätere Zeitpunkte
 */
$plugins->add_hook("misc_start", "scenetracker_ingame_calendar");
function scenetracker_ingame_calendar()
{
	global $db, $mybb, $header, $footer, $templates, $lang, $headerinclude, $scenetracker_calendar_day, $year;


	$page = $year = $month = "";

	if (!($mybb->get_input("action") == "calendar")) return;

	if ($mybb->get_input("action") == "calendar") {
		add_breadcrumb("Ingame Kalender", "misc.php?action=calendar");
		$scenetracker_calendar_typ = "st-calendar--full";
		//action bekommen
		$calendar_action = $mybb->get_input('do');

		//Detailansicht Event
		if ($calendar_action === "view_event") {
			$event_id = $mybb->get_input("id", MyBB::INPUT_INT);
			scenetracker_calendar_event_detail($event_id);
			return;
		}

		//event hinzufügen
		if ($calendar_action === "add_event") {
			scenetracker_calendar_event_form();
			return;
		}
		//event bearbeiten
		if ($calendar_action === "edit_event") {
			$event_id = $mybb->get_input('id', MyBB::INPUT_INT);
			$src = $mybb->get_input('src', MyBB::INPUT_STRING);
			scenetracker_calendar_event_form($event_id, $src);
			return;
		}
		//event löschen
		if ($mybb->get_input("do") === "delete_event") {
			$src = $mybb->get_input('src', MyBB::INPUT_STRING);
			$id = $mybb->get_input('id', MyBB::INPUT_INT);
			scenetracker_calendar_event_delete($src);
			return;
		}

		$scenetracker_calendar_day = "";
		$lang->load("calendar");

		$monthnames = array(
			"offset",
			$lang->alt_month_1,
			$lang->alt_month_2,
			$lang->alt_month_3,
			$lang->alt_month_4,
			$lang->alt_month_5,
			$lang->alt_month_6,
			$lang->alt_month_7,
			$lang->alt_month_8,
			$lang->alt_month_9,
			$lang->alt_month_10,
			$lang->alt_month_11,
			$lang->alt_month_12
		);

		//Welchen Monat wollen wir, wenn keiner übergeben, nehmen wir den ersten aus dem Ingamezeitraum
		if (!$mybb->get_input("month") || !$mybb->get_input("year")) {
			$ingame_array = scenetracker_get_ingame_range();
			$calendardate =  $ingame_array['start'];
		} else {
			$month = $mybb->get_input("month");
			$year = $mybb->get_input("year");
			$calendardate = DateTime::createFromFormat("!Y-m-d",	$year . "-" . $month . "-01");
		}
		$option_month = "";
		foreach (array_slice($monthnames, 1, null, true) as $month_i => $name) {
			$selected = $month_i == $month ? "selected" : "";
			$option_month .= "<option value=\"{$month_i}\" {$selected}>{$name}</option>";
		}

		$month = $calendardate->format('m');
		$year = $calendardate->format('Y');

		$prevarray = scenetracker_calendar_build_prev_month($month, $year);
		$nextarray = scenetracker_calendar_build_next_month($month, $year);
		$next = "<a href=\"{$mybb->settings['bburl']}/misc.php?action=calendar&amp;month={$nextarray['month']}&amp;year={$nextarray['year']}\">&raquo;</a>";
		$prev = "<a href=\"{$mybb->settings['bburl']}/misc.php?action=calendar&amp;month={$prevarray['month']}&amp;year={$prevarray['year']}\">&laquo;</a>";
		$monthindex = (int) $calendardate->format("n");
		$page = "";
		$get_days = scenetracker_calendar_build_day_array($month, $year);

		// var_dump($get_days);
		//tag bauen
		foreach ($get_days as $day) {
			scenetracker_calendar_build_day_template($day, "page");
		}

		if ($mybb->user["uid"] != 0) {
			eval("\$scenetracker_calendar_event_add_button = \"" . $templates->get("scenetracker_calendar_event_add_button") . "\";");
		} else {
			$scenetracker_calendar_event_add_button = "";
		}
		eval("\$scenetracker_calendar_month = \"" . $templates->get("scenetracker_calendar_month") . "\";");


		eval("\$page = \"" . $templates->get("scenetracker_calendar_full") . "\";");
		output_page($page);
	}
}

/**
 * Funktion um das Tag Template zu bauen
 * wir brauchen das sowieso mehrmals und wollen es nur einmal schreiben
 * @param array $day_array Array mit den Infos zum Tag.
 * @param string $type welchen Typ? Also Default oder Minikalender
 */
function scenetracker_calendar_build_day_template($day_array, $type = "page")
{
	global $templates, $mybb, $scenetracker_calendar_day, $day_date;
	$ingame = $thismonth = $monthflag = $next = $prev = "";
	$plotflag = false;
	$day_date = $day_array['day'];

	if ($day_array['currentmonth']) {
		$monthflag = "thismonth activeingame";
	} else {
		$monthflag = "other";
	}
	//kein Event - Nur Zahl ausgeben - ohne verlinkung 
	eval("\$day_date = \"" . $templates->get("scenetracker_calendar_title") .	"\";");

	// $count = $hidedate = $scenetracker_calender_event_typ = $scenetracker_calendar_event_typ_bit = $scenetracker_calendar_event_typ_bit_post = $scenetracker_calendar_event_typ_bit_scene = $scenetracker_calendar_event_typ_bit_birthday = $modalcsscode = $modalclass = $scenetracker_calendar_event_typ = "";

	$count = 0;

	$hidedate = "";
	$modalcsscode = "";
	$modalclass = "";
	$caltype = "";

	$scenetracker_calendar_event_typ = "";
	$scenetracker_calendar_event_typ_bit = "";
	$day_modal = "";

	$sceneevent = "";
	$birthdayevent = "";
	$plotevent = "";
	$eventevent = "";

	if (!empty($day_array['events'])) {
		//Events nach Typ Gruppieren
		$events_by_type = [];
		foreach ($day_array['events'] as $event) {
			$type_key = $event['type'];
			if (!isset($events_by_type[$type_key])) {
				$events_by_type[$type_key] = [];
			}
			$events_by_type[$type_key][] = $event;
		}

		//labels setzen 
		$event_labels = [
			"plot" => [
				"singular" => "Plot",
				"plural" => "Plots",
			],
			"scene" => [
				"singular" => "Szene",
				"plural" => "Szenen",
			],
			"birthday" => [
				"singular" => "Geburtstag",
				"plural" => "Geburtstage",
			],
			"event" => [
				"singular" => "Event",
				"plural" => "Events",
			],
		];

		//gruppiertes Array durchgehen
		foreach ($events_by_type as $event_type => $events) {
			// $caltype = "";
			if (empty($events)) {
				//keine events, abbruch
				continue;
			}

			$count = count($events);
			//singular oder plural ausgabe
			if ($count === 1) {
				$eventtyp = "<span class=\"st-calendar__typlabel\">" . $event_labels[$event_type]['singular'] . "</span>";
			} else {
				$eventtyp = "<span class=\"st-calendar__typlabel\">" . $event_labels[$event_type]['plural'] . "</span>";
			}
			//eindeutige Id für modal basteln
			// $modal_id = sprintf('%s_%d_%d_%d', $event_type, $day_array['year'], $day_array['month'], $day_array['day']);
			if ($type === "mini") {
				$modal_id = sprintf(
					'calendar_%d_%d_%d',
					$day_array['year'],
					$day_array['month'],
					$day_array['day']
				);
			} else {
				$modal_id = sprintf(
					'%s_%d_%d_%d',
					$event_type,
					$day_array['year'],
					$day_array['month'],
					$day_array['day']
				);
			}
			if ($type === "page") {
				$caltype = "st_full_scene";
				//full wollen wir die Zahl, nicht verlinkt haben - modals getrennt
				eval("\$day_date = \"" . $templates->get("scenetracker_calendar_event_title") .	"\";");
				//Für full wollen wir einzelne Modals, also hier für jeden Typen einmal leeren.
				$scenetracker_calendar_event_typ_bit = "";
			} else {

				$caltype = "st_mini_scene";

				//mini sollen die events gebündelt bei klick auf Zahl kommen
				if ($mybb->settings['scenetracker_calendar_mini_eventview'] == 1) {
					$modalclass = "st-popinfo ";
					eval("\$day_date = \"" . $templates->get("scenetracker_calendar_event_title") .	"\";");
				} else {
					$modalclass = "modal ";
					$modalcsscode = "display: none;";
					eval("\$day_date = \"" . $templates->get("scenetracker_calendar_event_title_modal") .	"\";");
				}
			}
			$eventtitle_mini = "";
			$scenecnt = $birthdaycnt = $plotcnt = $eventcnt = 0;
			//links bauen für inhalt im Modal
			foreach ($events as $event) {
				$link = "";
				$contentwarning = "";

				if ($event_type == "scene") {
					$scenecnt++;
					$eventtitle_mini = $scenecnt === 1 ?  $eventtyp : "";
					$sceneevent = "ownscene";
					$link = "{$mybb->settings['bburl']}/showthread.php?tid={$event['id']}";
					if (!empty($event['contentwarning'])) {
						$contentwarning = eval($templates->render("scenetracker_calendar_event_typ_bit_scene_contentwarning"));
					}
				}
				if ($event_type == "birthday") {
					$birthdaycnt++;
					$eventtitle_mini = $birthdaycnt === 1 ? $eventtyp : "";
					$birthdayevent = "birthdaycal";
					$link = "{$mybb->settings['bburl']}/member.php?action=profile&uid={$event['id']}";
				}
				if ($event_type == "plot") {
					$plotcnt++;
					$eventtitle_mini = $plotcnt === 1 ? $eventtyp : "";

					$plotevent = "event";

					$link = "{$mybb->settings['bburl']}/misc.php?action=scenetracker_plots&detail={$event['id']}";
					if ($event['flag'] == 'jule') {
						$link = "{$mybb->settings['bburl']}/plottracker.php?action=view&plid={$event['id']}";
					}
				}
				if ($event_type == "event") {
					$eventcnt++;
					$eventtitle_mini = $eventcnt === 1 ? $eventtyp : "";
					$eventevent = "event";
					$link = "{$mybb->settings['bburl']}/misc.php"  . "?action=calendar"  . "&do=view_event" . "&id={$event['id']}";
				}
				if ($type == "page") {
					$eventtitle_mini = "";
				}
				//event modal inhalt bauen - je nach typ unterschiedliche Templates
				eval("\$scenetracker_calendar_event_typ_bit .= \"" . $templates->get("scenetracker_calendar_event_typ_bit_" . $event_type) . "\";");
			}

			//wenn Vollansicht, holen wir das scenetracker_calendar_event_typ template. Einzelen Modals für jeden Typ
			if ($type === "page") {
				eval("\$scenetracker_calendar_event_typ .= \"" . $templates->get("scenetracker_calendar_event_typ") .	"\";");
			}
		}

		// $event_classes = [];

		// foreach (array_keys($events_by_type) as $event_type) {
		// 	$event_classes[] = "st-calendar__event--" . $event_type;
		// }

		// $event_classes = implode(" ", $event_classes);

		//Bei der Miniversion, wollen wir alle events in einem Modal oder popup
		if ($type === "mini") {
			eval("\$day_modal .= \"" . $templates->get("scenetracker_calendar_event_typ_mini_event") . "\";");
			eval("\$scenetracker_calendar_event_typ .= \"" . $templates->get("scenetracker_calendar_event_typ_mini") . "\";");
		}
		if ($type === "mini") {
			$hidedate = "display:none;";
		} else {
			$hidedate = "";
		}
	}

	eval("\$scenetracker_calendar_day .= \"" . $templates->get("scenetracker_calendar_day_bit") . "\";");
}

/**
 * Funktion um die Daten von einem Tag zu bauen
 *
 * @param int $month Für welchen Monat soll der Kalender gebaut werden
 * @param int $year Für welches Jahr soll der Kalender gebaut werden
 */
function scenetracker_calendar_build_day_array($month, $year, $calendar_events = null)
{
	global $lang, $monthnames, $mybb;

	//ingame zeitraum holen;
	$ingame_range = scenetracker_get_ingame_range();

	//erster Tag Monat
	$date = new DateTime(sprintf("%04d-%02d-01", $year, $month));
	//letzter tag des Monats
	$lastday = clone $date;
	$lastday->modify("last day of this month");

	//welcher Wochentag ist der erste Tag des Monats?
	$weekday = (int)$date->format("N");

	//Berechnen Anzeige Start des Kalenders (evt. Tage aus dem letzten Monat)
	$calendar_start = clone $date;
	$calendar_start->modify("-" . ($weekday - 1) . " days");

	//Berechnen Anzeige Ende des Kalenders
	$weekday = (int)$lastday->format("N");

	$calendar_end = clone $lastday;
	$calendar_end->modify("+" . (7 - $weekday) . " days");

	$current = clone $calendar_start;
	//einmal alle events aus dem Zeitraum holen
	// Wenn keine Events übergeben wurden, normale Kalenderevents holen
	if ($calendar_events === null) {
		$calendar_events = scenetracker_calendar_build_get_events($calendar_start, $calendar_end);
	}
	$days = [];
	while ($current <= $calendar_end) {
		$is_ingame = false;
		//checken ob im ingame zeitraum. 
		$is_ingame = (
			$ingame_range !== null
			&& $current >= $ingame_range['start']
			&& $current <= $ingame_range['end']
		);
		//events für diesen tag holen
		$events = scenetracker_calendar_build_get_events_for_day($current, $calendar_events);
		$days[] = [
			"date" => clone $current,
			"year" => (int)$current->format("Y"),
			"month" => (int)$current->format("n"),
			"day" => (int)$current->format("j"),
			"weekday" => (int)$current->format("N"),
			// tag im aufgerufenen monat?
			"currentmonth" => ((int)$current->format("n") === (int)$month && (int)$current->format("Y") === (int)$year),
			"ingame" => $is_ingame,
			"events" => $events
		];

		$current->modify("+1 day");
	}

	return $days;
}


/**
 * Berechnet einen bestimmten Wochentag eines Monats.
 *
 * Beispiele:
 * 3, Montag, Januar
 * letzter, Freitag, Mai
 *
 * weekday:
 * 1 = Montag
 * 2 = Dienstag
 * 3 = Mittwoch
 * 4 = Donnerstag
 * 5 = Freitag
 * 6 = Samstag
 * 7 = Sonntag
 *
 * occurrence:
 * 1-5 = erster bis fünfter
 * -1  = letzter
 *
 * @return DateTime|false
 */
function scenetracker_event_get_occurrence_date($year, $month, $weekday, $occurrence)
{
	$year = (int)$year;
	$month = (int)$month;
	$weekday = (int)$weekday;
	$occurrence = (int)$occurrence;

	if ($month < 1 || $month > 12) {
		return false;
	}

	if ($weekday < 1 || $weekday > 7) {
		return false;
	}

	if (!in_array($occurrence, array(-1, 1, 2, 3, 4, 5), true)) {
		return false;
	}

	//letzter gewünschter Tag im Monat
	if ($occurrence === -1) {

		$date = DateTime::createFromFormat("!Y-n-j", sprintf("%04d-%d-1", $year, $month));

		if (!$date) {
			return false;
		}

		$date->modify("last day of this month");

		while ((int)$date->format("N") !== $weekday) {
			$date->modify("-1 day");
		}

		return $date;
	}

	$first_day = DateTime::createFromFormat("!Y-n-j", sprintf("%04d-%d-1", $year, $month));

	if (!$first_day) {
		return false;
	}

	$first_weekday = (int)$first_day->format("N");

	// Abstand vom ersten Monatstag zum gesuchten Wochentag
	$offset = ($weekday - $first_weekday + 7) % 7;

	$day = 1 + $offset + (($occurrence - 1) * 7);

	// Nicht jeder Monat hat z.B. einen 5. Montag
	if (!checkdate($month, $day, $year)) {
		return false;
	}

	return DateTime::createFromFormat("!Y-n-j", sprintf("%04d-%d-%d", $year, $month, $day));
}

/**
 * Erzeugt alle Vorkommen eines Events
 * innerhalb des sichtbaren Kalenderzeitraums.
 *
 * recurring:
 * 0 = einmalig
 * 1 = wöchentlich
 * 2 = monatlich
 * 3 = quartalsweise
 * 4 = halbjährlich
 * 5 = jährlich
 *
 * @param array $event
 * @param DateTime $calendar_start
 * @param DateTime $calendar_end
 * @return array
 */
function scenetracker_calendar_build_event_occurrences(array $event, DateTime $calendar_start, DateTime $calendar_end)
{
	$occurrences = array();

	$event_start = scenetracker_event_create_date($event["startdate"]);
	$event_end = scenetracker_event_create_date($event["enddate"]);

	if (!$event_start || !$event_end) {
		return $occurrences;
	}

	if ($event_end < $event_start) {
		return $occurrences;
	}

	$recurring = (int)$event["recurring"];

	// Keine Wiederholung
	if ($recurring === 0) {
		if ($event_start <= $calendar_end && $event_end >= $calendar_start) {
			$occurrences[] = array(
				"start" => clone $event_start,
				"end" => clone $event_end
			);
		}

		return $occurrences;
	}

	// Spezialfall, z.B. "3. Montag im Januar"
	if ($recurring === 5 && (int)$event["recurring_occurrence"] !== 0) {
		return scenetracker_calendar_build_special_occurrences($event, $calendar_start, $calendar_end);
	}

	// Normale Wiederholungen
	if ($recurring === 1) {
		$interval = "+1 week";
	} elseif ($recurring === 2) {
		$interval = "+1 month";
	} elseif ($recurring === 3) {
		$interval = "+3 months";
	} elseif ($recurring === 4) {
		$interval = "+6 months";
	} elseif ($recurring === 5) {
		$interval = "+1 year";
	} elseif ($recurring === 6) {

		// Individueller Abstand in Tagen
		$days = (int)($event["recurring_interval"] ?? 0);

		if ($days < 1) {
			return $occurrences;
		}

		$interval = "+{$days} days";
	} else {
		return $occurrences;
	}

	// Dauer des ursprünglichen Events erhalten
	$duration = $event_start->diff($event_end);

	$current_start = clone $event_start;

	while ($current_start <= $calendar_end) {
		$current_end = clone $current_start;
		$current_end->add($duration);

		if ($current_start <= $calendar_end && $current_end >= $calendar_start) {
			$occurrences[] = array(
				"start" => clone $current_start,
				"end" => clone $current_end
			);
		}
		$current_start->modify($interval);
	}

	return $occurrences;
}

/**
 * Erzeugt jährliche Spezialwiederholungen wie
 * "3. Montag im Januar" oder "letzter Freitag im Mai".
 *
 * @param array $event
 * @param DateTime $calendar_start
 * @param DateTime $calendar_end
 * @return array
 */
function scenetracker_calendar_build_special_occurrences(array $event, DateTime $calendar_start, DateTime $calendar_end)
{
	$occurrences = array();

	$weekday = (int)$event["recurring_weekday"];
	$occurrence = (int)$event["recurring_occurrence"];
	$month = (int)$event["recurring_month"];

	if ($weekday < 1 || $weekday > 7) {
		return $occurrences;
	}

	if (!in_array($occurrence, array(-1, 1, 2, 3, 4, 5), true)) {
		return $occurrences;
	}

	if ($month < 1 || $month > 12) {
		return $occurrences;
	}

	$event_start = scenetracker_event_create_date($event["startdate"]);
	$event_end = scenetracker_event_create_date($event["enddate"]);

	if (!$event_start || !$event_end) {
		return $occurrences;
	}

	// Auch mehrtägige Events behalten ihre ursprüngliche Dauer
	$duration = $event_start->diff($event_end);

	$start_year = (int)$calendar_start->format("Y");
	$end_year = (int)$calendar_end->format("Y");

	for ($year = $start_year; $year <= $end_year; $year++) {

		$current_start = scenetracker_event_get_occurrence_date($year, 	$month, 	$weekday, $occurrence);

		if (!$current_start) {
			continue;
		}

		// Wiederholung soll nicht vor dem ursprünglichen Event beginnen
		if ($current_start < $event_start) {
			continue;
		}

		$current_end = clone $current_start;
		$current_end->add($duration);

		if ($current_start <= $calendar_end && $current_end >= $calendar_start) {
			$occurrences[] = array(
				"start" => clone $current_start,
				"end" => clone $current_end
			);
		}
	}

	return $occurrences;
}

/**
 * Ermittelt Start und Ende des aktuell eingestellten Ingame-Zeitraums.
 * @return array|null Array mit start und end als DateTime-Objekte oder null bei ungültigen Einstellungen.
 */
function scenetracker_get_ingame_range()
{
	global $mybb;

	$ingame_setting = trim((string)$mybb->settings['scenetracker_ingametime']);

	if ($ingame_setting === "") {
		return null;
	}
	//array basteln und eventuelle leertasten rauswerfen
	$months = array_values(array_filter(array_map("trim", explode(",", $ingame_setting))));

	if (empty($months)) {
		return null;
	}
	//erster und letzte monat
	$first_month = $months[0];
	$last_month  = $months[count($months) - 1];

	// formatierung checken
	if (
		!preg_match('/^\d{1,4}-(0[1-9]|1[0-2])$/', $first_month)
		|| !preg_match('/^\d{1,4}-(0[1-9]|1[0-2])$/', $last_month)
	) {
		return null;
	}

	//start und end holen - Tag
	$start_day = (int)$mybb->settings['scenetracker_ingametime_tagstart'];
	$end_day = (int)$mybb->settings['scenetracker_ingametime_tagend'];
	//datum basteln - uhrzeit auf 00:00
	$start = DateTime::createFromFormat('!Y-m-d', sprintf('%s-%02d', $first_month, $start_day));
	$end = DateTime::createFromFormat('!Y-m-d', sprintf('%s-%02d', $last_month, $end_day));

	if (!$start || !$end || $end < $start) {
		return null;
	}

	// prüfen ob datum von php korrigiert wurde
	if ($start->format('Y-m') !== str_pad(explode('-', $first_month)[0], 4, '0', STR_PAD_LEFT) . '-' . explode('-', $first_month)[1] || $end->format('Y-m') !== str_pad(explode('-', $last_month)[0], 4, '0', STR_PAD_LEFT) . '-' . explode('-', $last_month)[1]) {
		return null;
	}

	return [
		"start" => $start,
		"end" => $end,
		"months" => $months,
	];
}

/**
 * Funktion für Events / Szenen / Geburtstage etc
 * @param DateTime $calendar_start Anfang Zeitraum
 * @param DateTime $calendar_end ende Zeitraum
 * 
 * @return array $eventarray mit Events
 */
function scenetracker_calendar_build_get_events(DateTime $calendar_start, DateTime $calendar_end, $type = "page")
{
	global $db, $mybb;
	$eventarray = [];
	$start = $calendar_start->format('Y-m-d');
	$end   = $calendar_end->format('Y-m-d');
	$thisuser = (int)($mybb->user['uid'] ?? 0);
	$thisuseras_id = (int)($mybb->user['as_uid'] ?? 0);

	//PLOTS	 Szenentracker
	if ($db->table_exists("scenetracker_plots")) {
		$get_plots = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "scenetracker_plots WHERE startdate <= '{$end}' AND enddate >= '{$start}' AND calendar = 1 ORDER BY startdate ASC");

		while ($plot = $db->fetch_array($get_plots)) {
			$event_start = DateTime::createFromFormat('!Y-m-d', $plot['startdate']);
			$event_end = DateTime::createFromFormat('!Y-m-d',	$plot['enddate']);

			if (!$event_start || !$event_end) {
				continue;
			}

			$eventarray[] = [
				"id" => (int)$plot['id'],
				"type" => "plot",
				"title" => $plot['name'],
				"description" => $plot['description'],
				"category" => $plot['category'],
				"status" => $plot['active'],
				"image" => $plot['image'],
				"startdate" => $event_start,
				"enddate" => $event_end,
			];
		}
	}

	//Plots aus Jules Tracker
	if ($db->table_exists("plots")) {
		$unixtimestamp_start = $calendar_start->getTimestamp();
		$unixtimestamp_end   = $calendar_end->getTimestamp();

		$plotquery_jule = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "plots WHERE startdate <= {$unixtimestamp_end} AND enddate >= {$unixtimestamp_start} order by startdate ASC");
		while ($plot = $db->fetch_array($plotquery_jule)) {
			$event_start = new DateTime('@' . $plot['startdate']);
			$event_end   = new DateTime('@' . $plot['enddate']);
			$event_start->setTimezone(new DateTimeZone('UTC'));
			$event_end->setTimezone(new DateTimeZone('UTC'));

			$event_start->setTime(0, 0, 0);
			$event_end->setTime(23, 59, 59);

			if (!$event_start || !$event_end) {
				continue;
			}

			$eventarray[] = [
				"id" => (int)$plot['plid'],
				"type" => "plot",
				"flag" => "jule",
				"title" => $plot['name'],
				"description" => $plot['text'],
				"category" => $plot['type'],
				"status" => $plot['active'],
				"image" => $plot['image'],
				"startdate" => $event_start,
				"enddate" => $event_end,
			];
		}
	}

	//SZENEN bekommen
	//Einstellungen bekommen
	if ($type == "page") {
		$caltyp = "big";
	} else {
		$caltyp = "mini";
	}
	$viewsetting = 0;

	if ($thisuser > 0) {
		$settings_query = $db->simple_select(
			"users",
			"scenetracker_calendarsettings_{$caltyp}",
			"uid={$thisuser}",
			["limit" => 1]
		);

		if ($db->num_rows($settings_query) > 0) {
			$viewsetting = (int)$db->fetch_field(
				$settings_query,
				"scenetracker_calendarsettings_{$caltyp}"
			);
		}
	}
	//Settings des Users holen und entsprechend die Szenen holen
	if ($viewsetting == 1) {
		// 1 von den eigenen charakteren des Users der online ist
		$chararray = scenetracker_get_accounts($thisuser);
		$scene_querie = scenetracker_build_sql_threadtable("all", $chararray, array("date" => array($start, $end)));
	} else if ($viewsetting == 2) {
		// 2 von allen Charakteren des Forums
		$scene_querie = scenetracker_build_sql_threadtable("all", 0, array("date" => array($start, $end)));
	} else {
		// 0 Szenen des Charas der online ist
		$scene_querie = scenetracker_build_sql_threadtable("all", $thisuser, array("date" => array($start, $end)));
	}

	$get_scenes = $db->write_query($scene_querie);
	if (is_string($scene_querie) && trim($scene_querie) !== '') {
		while ($scene = $db->fetch_array($get_scenes)) {
			$scenedate = scenetracker_get_scenedate((int)$scene['tid']);
			// var_dump($scenedate['dateobject']);
			$eventarray[] = [
				"id" => (int)$scene['tid'],
				"type" => "scene",
				"title" => $scene['subject'],
				"place" => $scene['scenetracker_place'],
				"contentwarning" => $scene['scenetracker_trigger'],
				"time" => $scenedate['time'],
				"user" => htmlspecialchars($scene['scenetracker_user']),
				"status" => $scene['closed'] != 1 ? 'open' : 'closed',
				// "image" => $plot['image'],
				"startdate" => clone $scenedate['dateobject'],
				"enddate" => clone $scenedate['dateobject'],
			];
		}
	}

	//events bekommen

	$character_events = scenetracker_calendar_get_character_events($calendar_start, $calendar_end);

	$eventarray = array_merge($eventarray, $character_events);

	//Geburtstage bekommen
	$get_birthdays_string = scenetracker_get_birthdays();
	if (is_string($get_birthdays_string) && trim($get_birthdays_string) !== '') {

		$get_birthdays = $db->write_query($get_birthdays_string);

		while ($birthday = $db->fetch_array($get_birthdays)) {
			$birthday_original = DateTime::createFromFormat('Y-m-d', $birthday['birthday']);
			if ($birthday_original === false) {
				continue;
			}

			$start_year = (int)$calendar_start->format('Y');
			$end_year   = (int)$calendar_end->format('Y');

			for ($birthday_year = $start_year; $birthday_year <= $end_year; $birthday_year++) {
				$birthday_date = DateTime::createFromFormat('!Y-n-j', sprintf('%d-%d-%d', $birthday_year, (int)$birthday_original->format('n'), (int)$birthday_original->format('j')));

				if ($birthday_date === false) {
					continue;
				}

				// Nur Geburtstage innerhalb des sichtbaren Kalenders übernehmen.
				if ($birthday_date < $calendar_start || $birthday_date > $calendar_end) {
					continue;
				}

				$eventarray[] = [
					"id" => (int)$birthday['uid'],
					"type" => "birthday",
					"title" => $birthday['username'],
					"description" => null,
					"status" => 1,
					"image" => null,
					"startdate" => clone $birthday_date,
					"enddate" => clone $birthday_date,
					"birthyear" => (int)$birthday_original->format('Y'),
				];
			}
		}
	}
	return $eventarray;
}

/**
 * Funktion für Events / Szenen / Geburtstage etc
 * @param DateTime $date Welcher Tag
 * @param array $calendar_events ende Zeitraum
 * @return array $eventarray mit Events des Tages
 */
function scenetracker_calendar_build_get_events_for_day(DateTime $date, array $calendar_events)
{
	$eventarray = [];

	$current_day = clone $date;
	$current_day->setTime(0, 0, 0);

	foreach ($calendar_events as $event) {

		$start = clone $event['startdate'];
		$end   = clone $event['enddate'];

		$start->setTime(0, 0, 0);
		$end->setTime(0, 0, 0);

		if ($current_day >= $start && $current_day <= $end) {
			$eventarray[] = $event;
		}
	}

	return $eventarray;
}

/**
 * Den vorherigen Monat bekommen für den Kalender
 * @param int $month der monat
 * @param int $year das jahr
 * @return array Array mit vorherigen monat und jahr
 */
function scenetracker_calendar_build_prev_month($month, $year)
{
	global $monthnames;
	if ($month == 1) {
		$prevmonth = 12;
		$prevyear = $year - 1;
	} else {
		$prevmonth = $month - 1;
		$prevyear = $year;
	}
	return array("month" => $prevmonth, "year" => $prevyear, "name" => $monthnames[$prevmonth]);
}
/**
 * Den nächsten Monat bekommen für den Kalender
 * @param int $month der monat
 * @param int $year das jahr
 * @return array array mit nächsten monat und jahr
 */
function scenetracker_calendar_build_next_month($month, $year)
{
	global $monthnames;

	if ($month == 12) {
		$nextmonth = 1;
		$nextyear = $year + 1;
	} else {
		$nextmonth = $month + 1;
		$nextyear = $year;
	}

	return array("month" => $nextmonth, "year" => $nextyear, "name" => $monthnames[$nextmonth]);
}

/**
 * Liefert die Geburtstage von charas zu einem gegebenen Datums Zeitraum
 * @return string für Geburtstage Querie
 */
function scenetracker_get_birthdays()
{
	global $mybb, $db;

	//Geburtstage holen - welche einstellungen
	$setting_birhtday = $mybb->settings['scenetracker_birhday'];
	$get_birthdays = "";

	if ($setting_birhtday == "0") {
		// MYBB Profilfeld
		$setting_fid = $mybb->settings['scenetracker_birhdayfid'];
		$get_birthdays = "SELECT username, uid, DATE_FORMAT(STR_TO_DATE(fid{$setting_fid}, '%d.%m.%Y'), '%Y-%m-%d') AS birthday FROM " . TABLE_PREFIX . "userfields LEFT JOIN " . TABLE_PREFIX . "users ON ufid = uid WHERE fid{$setting_fid}";
		//Format birthdays: 12.07.1993
	} elseif ($setting_birhtday == "1") {
		// MyBB Geburtstagsfeld Monat ohne führende 0
		$get_birthdays = "
	           SELECT username, uid, DATE_FORMAT(
                STR_TO_DATE(birthday, '%e-%c-%Y'),
                '%Y-%m-%d'
            ) AS birthday FROM " . TABLE_PREFIX . "users WHERE birthday IS NOT NULL
          AND birthday != ''";
		//Format 7-3-1990
	} elseif ($setting_birhtday == "3") {
		// application ucp
		$identifier = $db->escape_string($mybb->settings['scenetracker_birhdayfid']);
		$feldid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '{$identifier}'"), "id");
		$get_birthdays = "SELECT uf.uid, username, uf.value as birthday FROM " . TABLE_PREFIX . "application_ucp_userfields uf LEFT JOIN " . TABLE_PREFIX . "users u ON uf.uid = u.uid WHERE fieldid = '{$feldid}'";
	}

	return $get_birthdays;
}

/**
 * Holt Charakterevents für einen sichtbaren Kalenderzeitraum.
 *
 * @param DateTime $calendar_start
 * @param DateTime $calendar_end
 * @return array
 */
function scenetracker_calendar_get_character_events(DateTime $calendar_start, DateTime $calendar_end)
{
	global $db, $mybb;

	$events = array();

	$this_uid = (int)$mybb->user['uid'];

	$where = array();

	// Eigene Events immer anzeigen
	if ($this_uid > 0) {
		$where[] = "uid = {$this_uid}";
	}

	// Offizielle Events immer anzeigen
	$where[] = "official = 1";

	// Öffentliche Charakterevents nur,
	// wenn sie laut ACP im Kalender erscheinen dürfen
	if ((int)$mybb->settings["scenetracker_plots_member_public_view"] === 1) {
		$where[] = "(private = 0 AND official = 0)";
	}

	$where_string = "(" . implode(" OR ", $where) . ")";
	if ($db->table_exists("scenetracker_event")) {
		$query = $db->simple_select("scenetracker_event", "*", $where_string, array("order_by"  => "startdate", "order_dir" => "ASC"));

		while ($event = $db->fetch_array($query)) {
			$occurrences = scenetracker_calendar_build_event_occurrences($event, $calendar_start, $calendar_end);
			foreach ($occurrences as $occurrence) {
				$events[] = array(
					"id" => (int)$event['id'],
					"type" => "event",
					"title" => $event['name'],
					"description" => $event['description'],
					"uid" => (int)$event['uid'],
					"private" => (int)$event['private'],
					"recurring" => (int)$event['recurring'],
					"startdate" => $occurrence['start'],
					"enddate" => $occurrence['end'],
				);
			}
		}
	}
	return $events;
}

/**
 * Erstellt ein DateTime aus einem Datum oder DATETIME.
 * Unterstützt auch Jahre < 1000.
 *
 * Unterstützte Formate:
 * YYYY-MM-DD
 * YYYY-MM-DD HH:MM:SS
 *
 * @param string $date
 * @return DateTime|false
 */
function scenetracker_event_create_date($date)
{
	$date = trim((string)$date);

	if ($date === '') {
		return false;
	}

	// Datum + optionale Uhrzeit zerlegen
	if (!preg_match('/^(\d{1,4})-(\d{1,2})-(\d{1,2})(?:\s+(\d{1,2}):(\d{1,2}):(\d{1,2}))?$/', $date, $parts)) {
		return false;
	}

	$year = (int)$parts[1];
	$month = (int)$parts[2];
	$day = (int)$parts[3];

	$hour = isset($parts[4]) ? (int)$parts[4] : 0;
	$minute = isset($parts[5]) ? (int)$parts[5] : 0;
	$second = isset($parts[6]) ? (int)$parts[6] : 0;

	if (!checkdate($month, $day, $year)) {
		return false;
	}

	if ($hour > 23 || $minute > 59 || $second > 59) {
		return false;
	}

	$formatted = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);

	return DateTime::createFromFormat('!Y-m-d H:i:s', $formatted);
}

/**
 * Detailansicht eines Charakterevents
 *
 * URL:
 * misc.php?action=calendar&do=view_event&id=12
 *
 * @param int $event_id ID des Events
 */
function scenetracker_calendar_event_detail($event_id)
{
	global $db, $mybb, $templates;
	global $header, $headerinclude, $footer;

	$event_id = (int)$event_id;

	if ($event_id <= 0) {
		error("Das Event wurde nicht gefunden.");
	}

	//event bekommen
	$event = $db->fetch_array($db->simple_select("scenetracker_event", "*", "id = '{$event_id}'"));

	if (empty($event['id'])) {
		error("Das Event wurde nicht gefunden.");
	}

	//check sichtbarkeit
	$is_admin = !empty($mybb->usergroup['canmodcp']);
	$is_owner = ((int)$mybb->user['uid'] > 0 && (int)$event['uid'] === (int)$mybb->user['uid']);

	if ((int)$event['private'] === 1 && !$is_owner && !$is_admin) {
		error_no_permission();
	}

	$event_name = htmlspecialchars_uni($event['name']);

	require_once MYBB_ROOT . 'inc/class_parser.php';
	$parser = new postParser;
	$parser_options = array(
		"allow_html" => 0,
		"allow_mycode" => 1,
		"allow_smilies" => 1,
		"allow_imgcode" => 1,
		"allow_videocode" => 0,
		"filter_badwords" => 1,
	);
	$event_description = $parser->parse_message($event['description'], $parser_options);

	//autor
	$event_user = get_user((int)$event['uid']);
	if (!empty($event_user['uid'])) {
		$event_username = htmlspecialchars_uni($event_user['username']);
		$event_userlink = build_profile_link($event_username, (int)$event_user['uid']);
	} else {
		$event_username = "Unbekannt";
		$event_userlink = $event_username;
	}

	//Date
	$event_startdate = "";
	$event_enddate = "";
	$event_outputdate = "";
	$start = scenetracker_event_create_date($event['startdate']);
	$end = scenetracker_event_create_date($event['enddate']);

	if ($start) {
		$event_startdate = $start->format('d.m.Y');
	}
	if ($end) {
		$event_enddate = $end->format('d.m.Y');
	}
	if ($start != $end) {
		$event_outputdate = $event_startdate . " - " . $event_enddate;
	} else {
		$event_outputdate = $event_startdate;
	}
	if ((int)$event["official"] === 1) {
		$official_checked = "checked";
	}
	//uhrzeiten
	$event_starttime = htmlspecialchars_uni($event['starttime'] ?? "");
	$event_endtime = htmlspecialchars_uni($event['endtime'] ?? "");

	// wer darf es sehen?
	if ((int)$event['private'] === 1) {
		$event_visibility = "Privat";
		$event_visibility_class = "private";
	} else {
		$event_visibility = "Öffentlich";
		$event_visibility_class = "public";
	}

	//Wiederholungen
	$recurring_labels = array(
		0 => "Einmalig",
		1 => "Wöchentlich",
		2 => "Monatlich",
		3 => "Quartalsweise",
		4 => "Halbährlich",
		5 => "Jährlich",
		6 => "Alle X Tage",
	);

	$event_recurring = $recurring_labels[(int)$event['recurring']] ?? "Einmalig";

	if ((int)$event["recurring"] === 6) {
		$days = (int)$event["recurring_interval"];
		$event_recurring = "Alle {$days} Tage";
	}

	//bearbeiten und löschen
	$event_delete_button = $event_edit_button = "";
	if (scenetracker_event_can_edit($event)) {
		eval("\$event_delete_button = \"" . $templates->get("scenetracker_calendar_event_delete_button") . "\";");
		eval("\$event_edit_button = \"" . $templates->get("scenetracker_calendar_event_edit_button") . "\";");
	}

	//zurück zum monat
	$back_link = "{$mybb->settings['bburl']}/misc.php" . "?action=calendar";

	if ($start) {
		$back_link .= "&amp;month=" . (int)$start->format("n") . "&amp;year=" . (int)$start->format("Y");
	}

	eval("\$page = \"" . $templates->get("scenetracker_calendar_event_detail") . "\";");
	output_page($page);
}
/**
 * Formular zum Erstellen/Bearbeiten eines Charakterevents.
 *
 * @param int $event_id 0 = neues Event
 */
function scenetracker_calendar_event_form($event_id = 0, $src = "")
{
	global $db, $mybb, $templates, $header, $headerinclude, $footer;

	$page = "";

	if ((int)$mybb->user["uid"] === 0) {
		error_no_permission();
	}
	$event_official_option = "";

	if ($mybb->usergroup["canmodcp"] == 1) {
		eval("\$event_official_option = \"" . $templates->get("scenetracker_calendar_event_form_official") . "\";");
	}

	$event_id = (int)$event_id;
	$is_edit = $event_id > 0;
	// Standarddatum aus dem aktuellen Ingame-Zeitraum
	$ingame_range = scenetracker_get_ingame_range();

	$default_date = $ingame_range !== null
		? $ingame_range["start"]->format("Y-m-d")
		: date("Y-m-d");

	// Defaultwerte für neues Event
	$event = array(
		"id" => 0,
		"name" => "",
		"description" => "",
		"startdate" => $default_date,
		"enddate" => $default_date,
		"private" => 1,
		"recurring" => 0,
		"recurring_interval" => 0,
		"recurring_occurrence" => 0,
		"recurring_weekday" => 0,
		"recurring_month" => 0,
		"uid" => (int)$mybb->user["uid"]
	);

	// Event beim Bearbeiten laden
	if ($is_edit) {
		$event = $db->fetch_array($db->simple_select("scenetracker_event", "*", "id = '{$event_id}'"));
		if (empty($event["id"])) {
			error("Das Event wurde nicht gefunden.");
		}
		if (!scenetracker_event_can_edit($event)) {
			error_no_permission();
		}
	}

	$errors = array();
	if ($mybb->request_method === "post") {

		verify_post_check($mybb->get_input("my_post_key"));

		$name = trim($mybb->get_input("name"));
		$description = trim($mybb->get_input("description"));
		$startdate = trim($mybb->get_input("startdate"));
		$enddate = trim($mybb->get_input("enddate"));

		$private = $mybb->get_input("private", MyBB::INPUT_INT);

		// Wiederholung
		$recurring = $mybb->get_input("recurring", 	MyBB::INPUT_INT);
		$recurring_interval = $mybb->get_input("recurring_interval", MyBB::INPUT_INT);

		// Sonderfall
		$recurring_occurrence = $mybb->get_input("recurring_occurrence", MyBB::INPUT_INT);
		$recurring_weekday = $mybb->get_input("recurring_weekday", 	MyBB::INPUT_INT);
		$recurring_month = $mybb->get_input("recurring_month", MyBB::INPUT_INT);

		$official = 0;
		if ($mybb->usergroup["canmodcp"] == 1) {
			$official = $mybb->get_input("official", MyBB::INPUT_INT) === 1 ? 1 : 0;
		}

		/*
		 * -----------------------------------------------------
		 * Wiederholung validieren
		 * 0 = keine
		 * 1 = wöchentlich
		 * 2 = monatlich
		 * 3 = quartalsweise
		 * 4 = halbjährlich
		 * 5 = jährlich
		 * 6 = nach x tagen
		 * -----------------------------------------------------
		 */
		if (!in_array($recurring, array(0, 1, 2, 3, 4, 5, 6), true)) {
			$recurring = 0;
		}

		//Validierung 
		if (!in_array($recurring_occurrence, array(-1, 0, 1, 2, 3, 4, 5), true)) {
			$recurring_occurrence = 0;
		}

		// Individuellen Tagesabstand prüfen
		if ($recurring === 6) {

			if ($recurring_interval < 1) {
				$errors[] = "Bitte gib einen gültigen Wiederholungsabstand von mindestens einem Tag ein.";
			}
		} else {

			// Bei anderen Wiederholungsarten nicht benötigt
			$recurring_interval = 0;
		}
		if ($recurring_weekday < 0 || $recurring_weekday > 7) {
			$recurring_weekday = 0;
		}

		if ($recurring_month < 0 || $recurring_month > 12) {
			$recurring_month = 0;
		}

		//Sonderfall nur bei jährlicher wiederholung 
		if ($recurring !== 5) {
			$recurring_occurrence = 0;
			$recurring_weekday = 0;
			$recurring_month = 0;
		}

		//Sonderfall braucht wochentag und monat
		if ($recurring_occurrence !== 0) {
			if ($recurring_weekday === 0 || $recurring_month === 0) {
				$errors[] = "Bitte wähle für den Spezialfall auch einen Wochentag und einen Monat aus.";
			}
		} else {
			$recurring_weekday = 0;
			$recurring_month = 0;
		}

		if ($name === "") {
			$errors[] = "Bitte gib einen Namen für das Event an.";
		}

		$start_object = scenetracker_event_create_date($startdate);
		$end_object = scenetracker_event_create_date($enddate);

		if (!$start_object) {
			$errors[] = "Das Startdatum ist ungültig.";
		}

		if (!$end_object) {
			$errors[] = "Das Enddatum ist ungültig.";
		}

		if ($start_object && $end_object && $end_object < $start_object) {
			$errors[] = "Das Enddatum darf nicht vor dem Startdatum liegen.";
		}

		//Wer darf es sehen (privat oder öffentlich)
		if ($private === 0 && !scenetracker_event_can_public()) {
			$private = 1;
		}

		//Speichern wenn es keine fehler gab
		if (empty($errors)) {
			$save = array(
				"name" => $db->escape_string($name),
				"description" => $db->escape_string($description),
				"startdate" => $start_object->format("Y-m-d"),
				"enddate" => $end_object->format("Y-m-d"),
				"uid" => (int)$event["uid"],
				"private" => $private,
				"recurring" => $recurring,
				"recurring_interval" => $recurring_interval,
				"recurring_occurrence" => $recurring_occurrence,
				"recurring_weekday" => $recurring_weekday,
				"recurring_month" => $recurring_month,
				"official" => $official,
			);

			if ($is_edit) {
				$db->update_query("scenetracker_event", $save, "id = '{$event_id}'");
			} else {
				$save["uid"] = (int)$mybb->user["uid"];
				$db->insert_query("scenetracker_event", $save);
			}
			if ($src !== "ucp") {
				redirect("misc.php?action=calendar", "Das Event wurde gespeichert.");
			} else {
				redirect("usercp.php?action=scenetracker&view=events&event_view=list", "Das Event wurde gespeichert.");
			}
		}

		//Felder speichern um sie nach Fehlern wieder anzuzeigen
		$event = array_merge(
			$event,
			array(
				"name" => $name,
				"description" => $description,
				"startdate" => $startdate,
				"enddate" => $enddate,
				"private" => $private,
				"recurring" => $recurring,
				"recurring_interval" => $recurring_interval,
				"recurring_occurrence" => $recurring_occurrence,
				"recurring_weekday" => $recurring_weekday,
				"recurring_month" => $recurring_month
			)
		);
	}

	$event_name = htmlspecialchars_uni($event["name"]);
	$event_description = htmlspecialchars_uni($event["description"]);
	$event_startdate = htmlspecialchars_uni($event["startdate"]);
	$event_enddate = htmlspecialchars_uni($event["enddate"]);

	//privat oder öffentlich
	$private_selected = "";
	$public_selected = "";

	if ((int)$event["private"] === 1) {
		$private_selected = "selected";
	} else {
		$public_selected = "selected";
	}

	$public_option = "";

	if (scenetracker_event_can_public()) {
		$public_option = "<option value=\"0\" {$public_selected}>Öffentlich</option>";
	}

	//Normale wiederholung
	$recurring_selected = array(
		0 => "",
		1 => "",
		2 => "",
		3 => "",
		4 => "",
		5 => "",
		6 => ""
	);

	$recurring_value = (int)$event["recurring"];

	if (isset($recurring_selected[$recurring_value])) {
		$recurring_selected[$recurring_value] = "selected";
	}

	//besondere Wiederholugn (jeder 3. montag)
	$occurrence_selected = array(
		0 => "",
		1 => "",
		2 => "",
		3 => "",
		4 => "",
		5 => ""
	);

	$occurrence_last_selected = "";
	$weekday_selected = array(
		0 => "",
		1 => "",
		2 => "",
		3 => "",
		4 => "",
		5 => "",
		6 => "",
		7 => ""
	);

	$month_selected = array(
		0 => "",
		1 => "",
		2 => "",
		3 => "",
		4 => "",
		5 => "",
		6 => "",
		7 => "",
		8 => "",
		9 => "",
		10 => "",
		11 => "",
		12 => ""
	);

	$occurrence = (int)$event["recurring_occurrence"];
	$weekday = (int)$event["recurring_weekday"];
	$recurring_month = (int)$event["recurring_month"];

	if ($occurrence === -1) {

		$occurrence_last_selected = "selected";
	} elseif (isset($occurrence_selected[$occurrence])) {

		$occurrence_selected[$occurrence] = "selected";
	}

	if (isset($weekday_selected[$weekday])) {
		$weekday_selected[$weekday] = "selected";
	}

	if (isset($month_selected[$recurring_month])) {
		$month_selected[$recurring_month] = "selected";
	}

	//formulardaten
	if ($is_edit) {
		$event_form_action = "misc.php?action=calendar&amp;do=edit_event&amp;id={$event_id}";
		$event_form_title = "Event bearbeiten";
	} else {
		$event_form_action = "misc.php?action=calendar&amp;do=add_event";
		$event_form_title = "Event erstellen";
	}
	$event_recurring_interval = (int)($event["recurring_interval"] ?? 0);

	//Fehlerausgabe
	$event_errors = "";

	if (!empty($errors)) {
		$event_errors = "<div class=\"error\">" . implode("<br>", $errors) . "</div>";
	}

	eval("\$page = \"" . $templates->get("scenetracker_calendar_event_form") . "\";");

	output_page($page);
}


/**
 * Darf der aktuelle User ein öffentliches Charakterevent erstellen?
 * Admins dürfen immer.
 * Normale User nur, wenn die ACP-Einstellung es erlaubt.
 *
 * @return bool
 */
function scenetracker_event_can_public()
{
	global $mybb;
	// Admins dürfen immer
	if ($mybb->usergroup['canmodcp'] == 1) {
		return true;
	}
	return (int)$mybb->settings['scenetracker_plots_member_public'] === 1;
}

/**
 * Darf ein User ein bestimmtes Event bearbeiten?
 * Besitzer oder Admin.
 *
 * @param array $event
 * @return bool
 */
function scenetracker_event_can_edit(array $event)
{
	global $mybb;
	//mods können immer
	if ($mybb->usergroup['canmodcp'] == 1) {
		return true;
	}
	//kein gast und user = autor -> true
	return ((int)$mybb->user['uid'] > 0 && (int)$event['uid'] === (int)$mybb->user['uid']);
}

/**
 * Charakterevent löschen
 */
function scenetracker_calendar_event_delete($src = "")
{
	global $db, $mybb;

	// Nur POST erlauben
	if ($mybb->request_method !== "post") {
		error_no_permission();
	}

	// Gast
	if ((int)$mybb->user['uid'] === 0) {
		error_no_permission();
	}

	verify_post_check($mybb->get_input("my_post_key"));
	$event_id = $mybb->get_input("id", MyBB::INPUT_INT);

	// Event holen
	$event = $db->fetch_array(
		$db->simple_select("scenetracker_event", "*", "id = '{$event_id}'")
	);

	// Event existiert nicht
	if (empty($event['id'])) {
		error("Das Event wurde nicht gefunden.");
	}

	// Darf der User dieses Event löschen?
	if (!scenetracker_event_can_edit($event)) {
		error_no_permission();
	}

	// Löschen
	$db->delete_query("scenetracker_event",	"id = '{$event_id}'");
	if ($src !== "ucp") {
		redirect("misc.php?action=calendar", "Das Event wurde erfolgreich gelöscht.");
	} else {
		redirect("usercp.php?action=scenetracker&view=events&event_view=list", "Das Event wurde erfolgreich gelöscht.");
	}
}

/**
 * Show Minicalender forumbit
 * @param array $forum reference to forum array
 */
$plugins->add_hook("build_forumbits_forum", "scenetracker_minicalendar_forum");
function scenetracker_minicalendar_forum(&$forum)
{
	global $mybb;
	$forum['minicalender'] = "";
	if ($mybb->settings['scenetracker_forumbit'] != 0 && $forum['fid'] == $mybb->settings['scenetracker_forumbit']) {
		$forum['minicalender'] = scenetracker_minicalendar_build();
	}
}

/**
 * Show Minicalender global
 */
$plugins->add_hook("global_intermediate", "scenetracker_minicalendar_global");
function scenetracker_minicalendar_global()
{
	global $scenetracker_calendar_wrapper, $scenetracker_calendar;
	$scenetracker_calendar_wrapper = scenetracker_minicalendar_build();
	$scenetracker_calendar = scenetracker_minicalendar_build();
}
/**
 * Build Minicalender global
 */
function scenetracker_minicalendar_build()
{
	global $db, $mybb, $templates, $lang, $scenetracker_calendar_wrapper, $scenetracker_calendar_day;
	if ($mybb->settings['scenetracker_forumbit'] != 0) {
		global $forum;
	}
	//monatnamen bekommen
	$lang->load("calendar");
	$monthnames = array(
		"offset",
		$lang->alt_month_1,
		$lang->alt_month_2,
		$lang->alt_month_3,
		$lang->alt_month_4,
		$lang->alt_month_5,
		$lang->alt_month_6,
		$lang->alt_month_7,
		$lang->alt_month_8,
		$lang->alt_month_9,
		$lang->alt_month_10,
		$lang->alt_month_11,
		$lang->alt_month_12
	);
	// Ingamezeitraum
	$scenetracker_calendar_month = '';

	$ingame_array = scenetracker_get_ingame_range();
	$cal_columns = count($ingame_array['months']);
	foreach ($ingame_array['months'] as $month) {
		$scenetracker_calendar_day = "";
		$get_days = array();
		$calendardate = DateTime::createFromFormat("!Y-m-d",	$month . "-01");
		$month = $calendardate->format("m");
		$year = $calendardate->format("Y");
		$monthindex = (int) $calendardate->format("n");
		$get_days = scenetracker_calendar_build_day_array($month, $year);
		// var_dump(get_days);
		//tag bauen
		foreach ($get_days as $day) {
			scenetracker_calendar_build_day_template($day, "mini");
		}
		$scenetracker_calendar_typ = "st-calendar--mini";

		$prev = '';
		$next = '';

		eval("\$scenetracker_calendar_month .= \"" . $templates->get("scenetracker_calendar_month") . "\";");
	}
	$scenetracker_calendar_wrapper = eval($templates->render("scenetracker_calendar_mini"));
	return $scenetracker_calendar_wrapper;
}


/**
 * Was passiert wenn ein User gelöscht wird
 * Einträge aus scenetracker Tabelel löschen
 */
$plugins->add_hook("admin_user_users_delete_commit_end", "scenetracker_userdelete");
function scenetracker_userdelete()
{
	global $db, $cache, $mybb, $user;
	$todelete = (int)$user['uid'];
	$db->delete_query("scenetracker", "uid = " . (int)$user['uid'] . "");
}

/**
 * Auflistung von allen Szenen auf misc Seite
 * misc.php?action=scenelist
 */
$plugins->add_hook("misc_start", "scenetracker_misc_list");
function scenetracker_misc_list()
{
	global $mybb, $db, $templates, $header, $footer, $theme, $headerinclude, $scenes;

	if (!($mybb->get_input("action") == "scenelist")) {
		return;
	}

	if ($mybb->get_input("action") == "scenelist") {
		$page = $scenetracker_profil_bit = $dateYear = "";

		$scene_querie_str = scenetracker_build_sql_threadtable("all", 0, array("add_table" => "scenetracker", "order_by" => "scenetracker_date", "profil_view" => 1));
		$scene_query = $db->write_query($scene_querie_str);

		while ($scenes = $db->fetch_array($scene_query)) {
			$tid = $scenes['tid'];
			$sid = $scenes['id'];
			$subject = $scenes['subject'];
			$sceneusers = str_replace(",", ", ", $scenes['scenetracker_user']);
			$sceneplace = $scenes['scenetracker_place'];

			if ($scenes['scenetracker_trigger'] != "") {
				eval("\$scenetrigger = \"" . $templates->get("scenetracker_trigger") . "\";");
			} else {
				$scenetrigger = "";
			}

			if ($scenes['threadclosed'] == 1 or $scenes['threadsolved'] == 1) {
				eval("\$scenestatus = \"" . $templates->get("scenetracker_scenestatus_solved") . "\";");
			} else {
				$scenestatus = "";
			}

			$scenedate_array = scenetracker_get_scenedate((int)$scenes['tid']);
			$scenedate = $scenedate_array['date_all'];

			$scenedateMonthYear = $scenedate_array['mY'];

			if ($dateYear != $scenedateMonthYear) {
				$scenedatetitle_m = $scenedate_array['m'];
				$scenedatetitle_y = $scenedate_array['year'];
				$scenedatetitle = $scenedate_array['mY'];
				eval("\$scenetracker_profil_bit_mY = \"" . $templates->get("scenetracker_profil_bit_mY") . "\";");
				$dateNew = scenetracker_get_scenedate((int)$scenes['tid']);
				$dateYear = $dateNew['mY'];
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
 * einträge in der Szenentracker Tabelle löschen, wenn ein thread gelöscht wird
 * @param $tid Thread ID vom Thread der gelöscht werden soll
 **/
$plugins->add_hook("class_moderation_delete_thread", "scenetracker_class_moderation_delete_thread");
function scenetracker_class_moderation_delete_thread($tid)
{
	global $db;
	$db->delete_query("scenetracker", "tid = '{$tid}'");
}

/**
 * Gibt Szenendatum und Zeit zurück
 * @param int $tid  threadid of scene
 * @return array $dateinfos
 */
function scenetracker_get_scenedate($tid)
{
	global $mybb, $db;
	$threaddata = get_thread($tid);
	$scenedate = "";

	$dateinfos = array();

	/* Datum formatieren */
	$datetime = new DateTime($threaddata['scenetracker_date']);
	$scenedate_dmy = $datetime->format("d.m.Y");
	$scenedate_dmy = preg_replace('/0*([0-9]+)\.0*([0-9]+)\.0*([0-9]+)/', '$1.$2.$3', $scenedate_dmy);
	$scenedatetitle_m = $datetime->format("F");
	$scenedatetitle_y = $datetime->format("Y");
	$scenedatetitle_y = preg_replace("/^0+/", "", $scenedatetitle_y);
	$scenedatetitle = $scenedatetitle_m . " " . $scenedatetitle_y;

	if ($mybb->settings['scenetracker_time_text'] == 0) {
		$dateinfos = array(
			"date_all" => $datetime->format('d.m.Y - H:i'),
			"date" => $scenedate_dmy,
			"time" => $datetime->format('H:i'),
			"mY" => $scenedatetitle,
			"m" => $scenedatetitle_m,
			"year" => $scenedatetitle_y,
			"dateobject" => $datetime,
			"Y-m-d" => $datetime->format('Y-m-d')
		);
	} else if ($mybb->settings['scenetracker_time_text'] == 1) {
		//einstellunge Zeit als offenes textfeld
		$datetime = new DateTime($threaddata['scenetracker_date']);
		$dateall = $datetime->format('d.m.Y') . " " . $threaddata['scenetracker_time_text'];

		$dateinfos = array(
			"date_all" => $dateall,
			"date" => $scenedate_dmy,
			"time" => $threaddata['scenetracker_time_text'],
			"mY" => $scenedatetitle,
			"m" => $scenedatetitle_m,
			"year" => $scenedatetitle_y,
			"dateobject" => $datetime,
		);
	}
	if ($threaddata['scenetracker_date'] == "0000-00-00 00:00:00") {
		if ($db->field_exists("communication_time", "posts")) {
			//sms 
			$postdate = $db->fetch_field($db->simple_select("posts", "communication_time", "replyto = 0 AND tid = $tid AND communication_time IS NOT NULL"), "communication_time");

			if ($postdate == "") {
				$postdateobject = "";
			} else {
				$postdateobject = new DateTime('@' . $postdate);
			}

			$scenedatetitle_m = date('F', $postdate);
			$scenedatetitle_y = date('Y', $postdate);
			$scenedatetitle_y = preg_replace('/^0+/', '', $scenedatetitle_y);
			$scenedatetitle = $scenedatetitle_m . " " . $scenedatetitle_y;

			$dateinfos = array(
				"date_all" => date('d.m.Y H:i:s', $postdate),
				"date" => date('d.m.Y', $postdate),
				"Y-m-d" => date('Y-m-d', $postdate),
				"time" => date('H:i:s', $postdate),
				"mY" => $scenedatetitle,
				"m" => $scenedatetitle_m,
				"year" => $scenedatetitle_y,
				"dateobject" => $postdateobject,
			);
		} else {
			$dateinfos = array(
				"date_all" => "Kein korrektes Datum angegeben",
				"error" => true,
			);
		}
	}
	return $dateinfos;
}

/**
 * Szene speichern - über ajax request
 * Function for ajax requests (jscripts/scenetracker.js)
 */
$plugins->add_hook("xmlhttp", "scenetracker_savescene");
function scenetracker_savescene()
{
	global $db, $mybb;
	if ($mybb->get_input("action") == "xml_st_savescenes") {
		$thisuser = $mybb->user['uid'];
		$date = $db->escape_string($mybb->input['date']);
		$time_text = "";

		if ($mybb->settings['scenetracker_time_text'] == 0) {
			//einstellungen Zeit als feste Uhrzeit
			$date = $db->escape_string($date) . " " . $db->escape_string($mybb->get_input("time"));
			$time_text = $db->escape_string($mybb->get_input("time"));
		} else if ($mybb->settings['scenetracker_time_text'] == 1) {
			//einstellunge Zeit als offenes textfeld
			$date = $db->escape_string($mybb->get_input("date"));
			$time_text = $db->escape_string($mybb->get_input("time"));
		}

		$scenetracker_place = $db->escape_string($mybb->input['place']);
		$scenetracker_plot = intval($mybb->input['plot']);

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

		if ($mybb->get_input("plot") != "") {
			$save_plot = array(
				"tid"     => (int)$tid,
				"plot_id" => (int)$scenetracker_plot,
			);

			$db->replace_query(
				"scenetracker_plots_tids",
				$save_plot
			);
		}
	}

	if ($mybb->get_input("action") == "xml_st_getusers") {
		$get_users = $db->query("SELECT username From " . TABLE_PREFIX . "users ORDER by username");
		$user = array();
		while ($row = $db->fetch_array($get_users)) {
			$user[] = $row;
		}
		echo json_encode($user, JSON_UNESCAPED_UNICODE);
	}

	if ($mybb->get_input("action") == "scenetracker_get_player") {
		$username = $db->escape_string_like($mybb->input['query']);
		$data = scenetracker_get_player_charas($username);
		//als JSON ausgeben, weil damit unser javascript arbeitet
		echo json_encode($data);
		exit;
	}
	if ($mybb->get_input("action") == "scenetracker_get_player_uid") {
		$username = $db->escape_string_like($mybb->input['query']);
		$data = scenetracker_get_player_charas($username);
		//als JSON ausgeben, weil damit unser javascript arbeitet
		$newData = array_map(function ($item) {
			$item['id'] = $item['uid'];
			return $item;
		}, $data);
		echo json_encode($newData);
		exit;
	}
}

/**
 * Funktion um den Spieler von einem Charakter zu bekommen
 * get the profilefeld id where name of player of chara is given
 * @param string $username  
 * @return array key = uid, id = playnerme in JSON for our Javascript, array empty wenn ungültig
 * 
 */
function scenetracker_get_player_charas($username)
{
	global $mybb, $db;
	//action definieren (adresse für xml request in javascript)
	$data = array();
	$username = $db->escape_string($username);
	if ($mybb->settings['scenetracker_filterusername_yesno']) {
		if (!$db->field_exists("as_uid", "users")) {
			$asstring = "";
		} else {
			$asstring = " AND as_uid = 0 ";
		}
		if ($mybb->settings['scenetracker_filterusername_typ'] == 0) {
			$playerfieldid = "fid" . $mybb->settings['scenetracker_filterusername_id'];

			$query = $db->query("
            SELECT distinct({$playerfieldid}), ufid as uid
            FROM " . TABLE_PREFIX . "users u
            LEFT JOIN " . TABLE_PREFIX . "userfields f ON (f.ufid=u.uid)
            WHERE {$playerfieldid} LIKE '%{$username}%' {$asstring} LIMIT 1
          ");
		}
		if ($mybb->settings['scenetracker_filterusername_typ'] == 1) {
			$playerfieldid = $mybb->settings['scenetracker_filterusername_id'];
			// $playerfieldid = "vorname";
			$query = $db->write_query(
				"SELECT uid, value as {$playerfieldid} from " . TABLE_PREFIX . "application_ucp_userfields af, 
              mybb_users u 
              WHERE af.uid = u.uid 
							{$asstring}
              AND fieldid = 
                (SELECT id FROM " . TABLE_PREFIX . "application_ucp_fields WHERE fieldname = '{$playerfieldid}') 
              AND value LIKE '%{$username}%' 
							LIMIT 1"
			);
		}
	}

	//array zusammenbauen
	if (!empty($query)) {
		while ($user = $db->fetch_array($query)) {
			$data[] = array("uid" => $user['uid'], "id" => $user[$playerfieldid], "text" => $user[$playerfieldid]);
		}
	} else {
		return $data;
	}

	return ($data);
}

/**
 * get all attached account to a given user
 * @param int $this_user the user whose attached account we want  
 * @return array with uids(key) and names(value)
 * */
function scenetracker_get_accounts($this_user)
{
	global $mybb, $db;
	$charas = array();

	if (!$db->field_exists("as_uid", "users")) {
		$get_all_users = $db->write_query("SELECT uid, username FROM " . TABLE_PREFIX . "users WHERE uid = '$this_user' ORDER BY username");
	} else {
		//as_uid bekommen
		$as_uid = $db->fetch_field($db->write_query("SELECT as_uid FROM " . TABLE_PREFIX . "users WHERE uid = '$this_user' ORDER BY username"), "as_uid");
		if ($as_uid == 0) {
			$get_all_users = $db->write_query("SELECT uid,username FROM " . TABLE_PREFIX . "users WHERE (as_uid = '$this_user') OR (uid = '$this_user') ORDER BY username");
		} else {
			//id des users holen wo alle angehangen sind 
			$get_all_users = $db->write_query("SELECT uid,username FROM " . TABLE_PREFIX . "users WHERE (as_uid = '$as_uid') OR (uid = '$this_user') OR (uid = '$as_uid') ORDER BY username");
		}
	}

	while ($users = $db->fetch_array($get_all_users)) {
		$uid = $users['uid'];
		$charas[$uid] = $users['username'];
	}
	return $charas;
}

/**
 * Counting Numbers of Scenes of a character
 * @param array $charas array of characters
 * @return array with all scenes and open scenes
 */
/**
 * Zählt die Szenen eines oder mehrerer Charaktere.
 *
 * Pro Charakter werden gezählt:
 * - all      = alle Szenen
 * - turn     = Szenen, in denen der Charakter dran ist
 * - not_turn = Szenen, in denen der Charakter nicht dran ist
 *
 * Zusätzlich werden unter den gleichen Keys
 * die Gesamtzahlen aller übergebenen Charaktere gespeichert.
 *
 * Beispiel:
 *
 * [
 *     'all'      => 14,
 *     'turn'     => 7,
 *     'not_turn' => 7,
 *
 *     26 => [
 *         'all'      => 5,
 *         'turn'     => 3,
 *         'npt' => 2,
 *     ],
 *
 *     92 => [
 *         'all'      => 5,
 *         'turn'     => 2,
 *         'not' => 3,
 *     ],
 * ]
 *
 * @param array $charas Array mit Charakteren [uid => username]
 * @return array
 */
function scenetracker_count_scenes($charas)
{
	// Gesamtcounter vorbereiten
	$counter = array(
		"all" => 0,
		"open" => 0,
		"not" => 0,
	);

	foreach ($charas as $uid => $username) {
		$uid = (int)$uid;
		$counter[$uid] = array(
			"all" => 0,
			"open" => 0,
			"not" => 0,
		);
	}

	//Alle Szenen der übergebenen Charaktere laden.
	$scenes = scenetracker_ucp_load_scenes($charas, true);

	// Szenen durchgehen
	foreach ($scenes as $entry) {
		$uid   = (int)$entry['character_uid'];
		$scene = $entry['scene'];

		// Nur Szenen der angefragten Charaktere zählen
		if ($uid <= 0 || !array_key_exists($uid, $counter)) {
			continue;
		}

		//gesamtzahl Charakter
		$counter[$uid]['all']++;
		//gesammtzahl alle Szenen
		$counter['all']++;

		//ist der Charakter dran? 
		$is_turn = scenetracker_ucp_is_characters_turn($scene, $uid);

		if ($is_turn) {
			// Charakter ist dran
			$counter[$uid]['open']++;
			$counter['open']++;
		} else {
			// Charakter ist nicht dran
			$counter[$uid]['not']++;
			$counter['not']++;
		}
	}

	return $counter;
}

/**
 * checkt ob der Charakter in einer Szene dran wäre
 * @param int $tid threadid der szene
 * @param int $uid charakter der gechecked werden soll
 * @return boolean true/false
 */
function scenetracker_chara_turn_fieldorder($tid, $uid)
{
	global $mybb, $db;

	$userdata = get_user($uid);
	$threaddata = get_thread($tid);
	//teilnehmer bekommen
	$teilnehmer = $db->fetch_field($db->simple_select("threads", "scenetracker_user", "tid='{$tid}'"), "scenetracker_user");

	$teilnehmer_array = explode(",", trim($teilnehmer));

	// nur zweiteilnehmer -> reicht check nach letzter poster
	if (count($teilnehmer_array) == 2) {
		return ($threaddata['lastposter'] == $userdata['username']) ? false : true;
	}

	// Mehr als 2 Teilnehmer -> Rotation nötig
	$position = array_search($userdata['username'], $teilnehmer_array);

	// Falls User nicht in Teilnehmerliste -> kann nicht dran sein
	if ($position === false) {
		return false;
	}

	// Rotation erstellen: Alle hinter dem chara, dann alle davor, dann der chara selbst
	$before = array_slice($teilnehmer_array, $position + 1);
	$after = array_slice($teilnehmer_array, 0, $position);
	$teilnehmer_sorted = array_merge($before, $after, [$teilnehmer_array[$position]]);
	// Zeiger ans Ende setzen (aktueller Chara), einen Schritt zurück für den vorherigen chara
	end($teilnehmer_sorted);
	$lastposter_shouldbe = prev($teilnehmer_sorted);

	// to be save, gelöschte user überspringen
	while ($lastposter_shouldbe !== false && empty(get_user_by_username($lastposter_shouldbe))) {
		$lastposter_shouldbe = prev($teilnehmer_sorted);
	}

	// fallback
	if ($lastposter_shouldbe === false) {
		return false;
	}

	// hat der vorherige chara zuletzt gepostet? dann true zurückgeben
	return ($lastposter_shouldbe == $threaddata['lastposter']);
}

/**
 * Check if an uid belongs to the user which is online (switcher)
 * @param int $uid uid to check
 * @return boolean true/false
 * */
function scenetracker_check_switcher($uid)
{
	global $mybb, $db;
	if ($mybb->user['uid'] != 0) {
		$chars = scenetracker_get_accounts($mybb->user['uid']);
		return array_key_exists($uid, $chars);
	} else return false;
}

/**
 * Check if an fid belong to ingame/archiv
 * @param int$fid to check
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
	foreach ($excludedfids as $fidid) {
		if (strpos($parents, "," . $fidid . ",")) {
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
 * Die Userids + usernames der Teilnehmer bekommen
 * @param string $string_usernames string of usernames, seperated by , 
 * @return array key: uid value: username
 * */
function scenetracker_getUids($string_usernames)
{
	global $db;

	$array_user = array();
	//no whitespace at beginning and end of name
	$array_usernames = array_map("trim", explode(",", $string_usernames));
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
 * Szenen eines Users bekommen
 * @param array $charas array mit uids
 * @param string template/place to return
 **/
function scenetracker_get_scenes($charas, $tplstring = "index")
{
	global $db, $mybb, $templates, $lang, $scenetracker_popup_select_options_index, $sel_array, $scenetracker_popup_select_options_alert, $scenetracker_ucp_bit_chara_new, $scenetracker_ucp_bit_chara_old, $scenetracker_ucp_bit_chara_closed, $scenetracker_ucp_bit_scene, $scenetracker_index_bit_chara, $scenetracker_index_bit_scene, $rem_sel_on, $rem_sel_off, $type;

	//fürs select feld, alle usernamen suchen 
	$solvplugin = $mybb->settings['scenetracker_solved'];
	$lang->load("scenetracker");
	$hidden = $solved = $solvefield = $query = $always_always_opt = "";
	$users_options_bit = "";

	$cnt = scenetracker_count_scenes($charas);

	foreach ($charas as $uid => $charname) {

		$scenequerie_string = scenetracker_build_sql_threadtable("all", $uid, array(
			"closed" => 0,
			"add_table" => "scenetracker",
			"index_alert" => true,
		));

		$charaname = build_profile_link($charname, $uid);
		$scenes = $db->write_query($scenequerie_string);

		$scenetracker_ucp_bit_scene = "";
		$scenetracker_index_bit_scene = "";
		$tplcount = 1;

		if ($db->num_rows($scenes) == 0) {
			$tplcount = 0;
		} else {
			$tplcount = 1;
			$info_by = $selected = $users_options_bit = $scenetracker_popup_select_options_index = "";
			$users_options_bit = "";

			while ($data = $db->fetch_array($scenes)) {
				$id = $data['id'];

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
				$username = build_profile_link($user['username'], $uid);

				$lastpostdate = date('d.m.Y', $data['lastpost']);
				$lastposter = get_user($data['lastposteruid']);
				$alerttype = $data['type'];
				$scenedate_array = scenetracker_get_scenedate($tid);
				$scenedate = $scenedate_array['date_all'];
				$lastposterlink = '<a href="member.php?action=profile&uid=' . $lastposter['uid'] . '">' .  $lastposter['username'] . '</a>';
				$users = $sceneusers = str_replace(",", ", ", $data['scenetracker_user']);
				$sceneplace = $data['scenetracker_place'];

				$scene = '<a href="showthread.php?tid=' . $data['tid'] . '&action=lastpost" class="scenelink">' . $data['subject'] . '</a>';
				if ($data['profil_view'] == 1) {
					eval("\$hide = \"" . $templates->get("scenetracker_displaystatus_shown") . "\";");
				} else {
					eval("\$hide = \"" . $templates->get("scenetracker_displaystatus_hidden") . "\";");
				}
				if ($data['closed'] == 1) {
					eval("\$close .= \"" . $templates->get("scenetracker_sceneisclosed") . "\";");
				} else {
					eval("\$close = \"" . $templates->get("scenetracker_sceneisopen") . "\";");
				}

				scenetracker_build_popup_form($data, "index");
				scenetracker_build_popup_form($data, "alert");

				eval("\$certain =\"" . $templates->get("scenetracker_popup") . "\";");
				if ($tplstring == "index") {
					eval("\$scenetracker_index_bit_scene .= \"" . $templates->get("scenetracker_index_bit_scene") . "\";");
				} else {
					eval("\$scenetracker_ucp_bit_scene .= \"" . $templates->get("scenetracker_ucp_bit_scene") . "\";");
				}
			}
		}

		if ($tplcount == 1) {
			if ($tplstring == "index") {
				$cnt_chara = "(" . $cnt[$uid]['open'] . "/" . $cnt[$uid]['all'] . ")";
				eval("\$scenetracker_index_bit_chara .=\"" . $templates->get("scenetracker_index_bit_chara") . "\";");
			} else {
				eval("\$scenetracker_ucp_bit_chara_{$tplstring} .=\"" . $templates->get("scenetracker_ucp_bit_chara") . "\";");
			}
		}
	}
}

/**
 * Change alert type for scenes/posts (always/never/certain user)
 * @param int $id of scenetracker entry
 * @param string $type or alert or
 * @param int $value  uid or 0 for another user, -1 for never, -2 for always
 ****** */
function scenetracker_scene_inform_status($id, $type, $value, $remdays = 0)
{
	global $db;

	if ($type == "index") {
		if ($value == "0") {
			//always
			$db->query("UPDATE " . TABLE_PREFIX . "scenetracker SET inform_by = '0', type='always' WHERE id = '" . $id . "' ");
		} else if ($value == "-2") {
			//always always
			$db->query("UPDATE " . TABLE_PREFIX . "scenetracker SET inform_by = '0', type='always_always' WHERE id = '" . $id . "' ");
		} else if ($value == "-1") {
			//never
			$db->query("UPDATE " . TABLE_PREFIX . "scenetracker SET inform_by = '0', type='never' WHERE id = '" . $id . "' ");
		} else {
			//certain user
			//wir gehen erst einmal davon aus, der user hat als letztes gepostet und will die szene noch nicht auf dem index
			$alert = 0;
			//jetzt testen wir ob das wirklich so ist:
			//wir brauchen die threadid 
			$tid = $db->fetch_field($db->simple_select("scenetracker", "tid", "id = '{$id}'"), "tid");
			//und die uid
			$uid = $db->fetch_field($db->simple_select("scenetracker", "uid", "id = '{$id}'"), "uid");
			//das datum des  letzten posts im Thread vom chara der gerade den alert einstellt
			$getlastpostdate = $db->fetch_field($db->write_query("SELECT uid, username, dateline FROM  " . TABLE_PREFIX . "posts WHERE tid = '{$tid}' AND uid = '{$uid}' ORDER by dateline DESC LIMIT 1"), "dateline");

			//der user hat hier noch nie gepostet, er möchte also informiert werden, sobald der certainuser gepostet hat
			if ($getlastpostdate == "" || empty($getlastpostdate)) {
				//wir setzen das datum auf 0, weil dateline dann immer größer ist
				$getlastpostdate = 0;
			}
			//wir holen uns jetzt alle posts, wo das datum größer ist als der letzte post des users
			$alert_query = $db->write_query("SELECT uid, username, dateline FROM " . TABLE_PREFIX . "posts WHERE tid ={$tid} and dateline > {$getlastpostdate} ORDER by dateline");
			// Jetzt gehen wir durch ob der certain user schon gepostet hat
			while ($d = $db->fetch_array($alert_query)) {
				if ($d['uid'] == $value) {
					$alert = 1;
				}
			}
			$db->write_query("UPDATE " . TABLE_PREFIX . "scenetracker SET alert = '{$alert}', inform_by = '" . $value . "', type='certain' WHERE  id = '" . $id . "' ");
		}
	}
	if ($type == "alert") {
		if ($value == 0) {
			//Immer einen Alert losschicken
			$db->write_query("UPDATE " . TABLE_PREFIX . "scenetracker SET type_alert_inform_by = '0', type_alert='always' WHERE id = '" . $id . "' ");
		} else if ($value == '-1') {
			//Niemals einen Alert losschicken
			$db->write_query("UPDATE " . TABLE_PREFIX . "scenetracker SET type_alert_inform_by = '0', type_alert='never' WHERE id = '" . $id . "' ");
		} elseif ($value == '-2') {

			$db->write_query("UPDATE " . TABLE_PREFIX . "scenetracker SET type_alert_inform_by = '0', type_alert='always_always' WHERE id = '" . $id . "' ");
		} else {

			//nur wenn ein bestimmter User gepostet hat
			$db->write_query("UPDATE " . TABLE_PREFIX . "scenetracker SET type_alert_inform_by = '" . $value . "', type_alert='certain' WHERE  id = '" . $id . "' ");
		}
	}
	if ($type == "reminder") {
		//Reminder anzeigen
		$db->write_query("UPDATE " . TABLE_PREFIX . "scenetracker SET index_view_reminder = '$value' WHERE id = '" . $id . "' ");
		if ($remdays > 0) {
			$db->write_query("UPDATE " . TABLE_PREFIX . "scenetracker SET index_view_reminder_days = '$remdays' WHERE id = '" . $id . "' ");
		}
	}
}

/**
 * Überprüft ob der User den Status der Szene ändern darf
 * @param string $str_teilnehmer der Teilnehmer
 * @return boolean true if allowed, false if not
 */
function scenetracker_change_allowed($str_teilnehmer)
{
	global $mybb, $db;

	$chars = scenetracker_get_accounts($mybb->user['uid']);
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

/**
 * Change status from scene (open/close)
 * @param int $close status of scene
 * @param int $tid threadid
 * @param int $uid user id
 */
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

			// Nur bei erlaubter Statusänderung zur Archivierung weiterleiten
			$fid = (int)$db->fetch_field(
				$db->simple_select("threads", "fid", "tid = " . (int)$tid),
				"fid"
			);

			if ($db->field_exists('archiving_inplay', 'forums')) {
				redirect("misc.php?action=archiving&fid={$fid}&tid={$tid}");
			}
		}

		// Ansonsten zurück zur Szene
		redirect("showthread.php?tid={$tid}");
	} elseif ($close == 0) {
		if (scenetracker_change_allowed($teilnehmer)) {
			$db->write_query("UPDATE " . TABLE_PREFIX . "threads SET closed = '0' WHERE tid = " . $tid . " ");
			if ($solvplugin == "1") {
				$db->write_query("UPDATE " . TABLE_PREFIX . "threads SET threadsolved = '0' WHERE tid = " . $tid . " ");
			}
		}
	}
}

/**
 * Change View of Scene (show in profil or not)
 */
function scenetracker_scene_change_view($hidescene, $id, $uid)
{
	global $db, $mybb;
	//security check, is this user allowes to change entry?
	if (scenetracker_check_switcher($uid)) {
		$db->write_query("UPDATE " . TABLE_PREFIX . "scenetracker SET profil_view = '" . $hidescene . "' WHERE id = " . $id . " ");
	}
}

/**
 * Baut eine SQL-Abfrage für Szenen auf.
 *
 * @param string    $type    all, open, closed oder user
 * @param int|array $uid     Einzelne UID oder Array mit Usernamen
 * @param array     $options Zusätzliche Filter
 *
 * Mögliche Optionen:
 *
 * add_table:
 *     "scenetracker" – Daten aus der Scenetracker-Tabelle mitladen
 *
 * order_by:
 *     SQL-Ausdruck für die Sortierung, beispielsweise:
 *     t.lastpost DESC
 *
 * profil_view:
 *     1 – nur Szenen mit profil_view = 1
 *
 * closed:
 *     1      – nur geschlossene Szenen
 *     0      – nur offene Szenen
 *     "both" – beide
 *
 * index_alert:
 *     1 – nur Szenen, in denen der Charakter laut Indexeinstellung dran ist
 *
 * alert_alert:
 *     1 – nur Szenen, in denen der Charakter laut Alerteinstellung dran ist
 *
 * fieldorder:
 *     1 – nur Szenen, in denen der Charakter laut Teilnehmerreihenfolge
 *         an der Reihe ist. Gelöschte User werden dabei übersprungen.
 *
 * extra_fids:
 *     Kommagetrennte Liste um nur bestimmte Foren zu berücksichtigen.
 *
 * timerange:
 *     "ingame"    – innerhalb des Ingamezeitraums
 *     "notingame" – außerhalb des Ingamezeitraums
 *     "2026-04"   – bestimmter Monat
 *
 * date:
 *     Datumsbereich:
 *     [
 *         "2023-03-01",
 *         "2023-05-29"
 *     ]
 *
 * @return string Fertige SQL-Abfrage
 */
function scenetracker_build_sql_threadtable($type = "all", $uid = 0, $options = array())
{
	global $db, $mybb;
	$add_st = $date = $order = $profile_view = $closed = $excluded = $ingamestr = $archivstr = $areastring = $timerange = $index_alert = $participant_join = $fieldorder = "";
	$type_string = "";
	$forenquerie = "";
	$threadsread = "";

	$ingame = $mybb->settings['scenetracker_ingame'];
	$archiv = $mybb->settings['scenetracker_archiv'];

	//Default selector - nur Threadfeler und Parentlist
	$selectors = "t.*, parentlist";

	if ($ingame == "") $ingame = "0";
	if ($archiv == "") $archiv = "0";
	if ($mybb->settings['scenetracker_exludedfids'] != "") {
		$excluded = " AND t.fid not in ({$mybb->settings['scenetracker_exludedfids']}) ";
	}

	//Standard anzeigen, je nach einstellung im ACP

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
		$forenquerie = " WHERE ($ingamestr $archivstr) ";
	}
	//Um sicher zu gehen, dass wir ein where haben, wenn beim forenquerie was schief geht
	if ($forenquerie === "") {
		$forenquerie = "WHERE 1 = 1";
	}

	//Zusätzliche Optionen verarbeiten
	if (!empty($options)) {
		// Szenentracker Tabelle hinzufügen
		if (!empty($options['add_table']) && $options['add_table'] == "scenetracker") {
			$selectors .= ", s.*";
			$add_st = " LEFT JOIN " . TABLE_PREFIX . "scenetracker s 
				ON t.tid = s.tid ";

			// Einzelner Charakter
			if (!is_array($uid) && (int)$uid > 0) {
				$tracker_uid = (int)$uid;
				$add_st .= " AND s.uid = {$tracker_uid}";
				// Mehrere Charaktere [uid => username]
			} elseif (is_array($uid)) {
				$tracker_uids = array_map("intval",	array_keys($uid));
				$tracker_uids = array_filter($tracker_uids);

				if (!empty($tracker_uids)) {
					$tracker_uid_list = implode(",", $tracker_uids);
					$add_st .= " AND s.uid IN ({$tracker_uid_list}) ";
				}
			}
		}

		if (!empty($options['order_by'])) {
			$order = " ORDER BY {$options['order_by']}";
		}
		if (!empty($options['profil_view'])) {
			$profile_view = " AND profil_view = 1 ";
		}
		if (isset($options['closed'])) {
			if ($options['closed'] === 1) {
				$closed = " AND closed = 1 ";
			} elseif ($options['closed'] === 0) {
				$closed = " AND closed != 1 ";
			}
		}
		if (!empty($options["threadsread"])) {
			$selectors .= ", tr.dateline AS read_dateline ";
			$threadsread = "
					LEFT JOIN " . TABLE_PREFIX . "threadsread tr
            ON tr.tid = t.tid
           AND tr.uid = {$mybb->user["uid"]} ";
		}

		if (!empty($options['index_alert'])) {
			$index_alert = " AND ((t.lastposteruid != $uid and s.type ='always') OR (s.type ='always_always') OR (s.alert = 1 and s.type = 'certain')) ";
		}
		if (!empty($options['alert_alert'])) {
			$index_alert = " AND ((t.lastposteruid != $uid and s.type_alert ='always') OR (s.type_alert ='always_always') OR (s.alert = 1 and s.type_alert = 'certain')) ";
		}
		if (!empty($options['extra_fids'])) {
			// nur übergebene areas
			$areaexplode = explode(",", $options['extra_fids']);
			foreach ($areaexplode as $areafid) {
				$areastring .= " concat(',',parentlist,',') LIKE '%," . $areafid . ",%' OR ";
			}

			//letztes OR rauswerfen
			$areastring = substr($areastring, 0, -3);
			$forenquerie = " WHERE ($areastring) ";
		}
		if ($options['timerange']) {
			$ingame_range = $mybb->settings['scenetracker_ingametime'];
			$months = explode(",", $ingame_range);

			$in = implode(',', array_map(function ($m) {
				return "'" . trim($m) . "'";
			}, $months));
			if (!empty($options['timerange'])) {
				$timerange = " AND DATE_FORMAT(scenetracker_date, '%Y-%m') IN ($in) ";
			} elseif ($options['timerange'] == "notingame") {
				$timerange = " AND DATE_FORMAT(scenetracker_date, '%Y-%m') NOT IN ($in) ";
			} else {
				$timerange = " AND DATE_FORMAT(scenetracker_date, '%Y-%m') like '{$options['timerange']}' ";
			}
		}

		if (
			isset($options['date']) &&
			is_array($options['date']) &&
			count($options['date']) >= 2
		) {
			$date = " AND DATE_FORMAT(scenetracker_date, '%Y-%m-%d') between '{$options['date'][0]}' AND '{$options['date'][1]}'";
		}


		//Teilnehmerreihenfolge berücksichtigen
		if (!empty($options['fieldorder'])) {
			if (is_array($uid) || (int)$uid <= 0) {
				//fieldorder geht nur bei einer einzelnen UID, ansonsten wird die Reihenfolge nicht berücksichtigt
				$fieldorder = "";
			} else {

				$fieldorder_uid = (int)$uid;
				$fieldorder_user = get_user($fieldorder_uid);
				if (!empty($fieldorder_user['uid'])) {
					$fieldorder_username = $db->escape_string(
						$fieldorder_user['username']
					);

					//  Unterabfrage: baut pro Thread eine neue Liste auf mit nur existierenden Charakteren  FIND_IN_SET erhält dabei die ursprüngliche Reihenfolge.
					$participant_join = "
						INNER JOIN (
							SELECT
							participant_thread.tid,
							GROUP_CONCAT(
								participant_user.username
								ORDER BY FIND_IN_SET(
									participant_user.username,
									REPLACE(
											participant_thread.scenetracker_user,', ',','
										)
									)
									SEPARATOR ','
										) AS existing_participants
										FROM " . TABLE_PREFIX . "threads participant_thread
									INNER JOIN " . TABLE_PREFIX . "users participant_user
									ON FIND_IN_SET(
										participant_user.username,
										REPLACE(
											participant_thread.scenetracker_user,
											', ',
											','
										)
									) > 0				
								GROUP BY participant_thread.tid
							) participant_data
						ON participant_data.tid = t.tid
					";

					//  Position des aktuellen Charakters in der bereinigten Teilnehmerliste.FIND_IN_SET zählt ab 1.
					$character_position = "
										FIND_IN_SET(
											'{$fieldorder_username}',
											participant_data.existing_participants
										)
									";

					// Teilnehmer direkt vor dem aktuellen Charakter. Steht der Charakter an erster Stelle, wird der letzte Teilnehmer als Vorgänger verwendet.
					$previous_character = " 
									CASE
									WHEN {$character_position} = 1
									THEN TRIM(
										SUBSTRING_INDEX(
											participant_data.existing_participants, ',', -1 )
										)
										ELSE TRIM(
											SUBSTRING_INDEX(
												SUBSTRING_INDEX(
													participant_data.existing_participants,
													',', {$character_position} - 1 ), ',',	-1 
												) 
											) 
										END";

					// Der Charakter muss in der bereinigten Liste stehen, mind. 2 ex. Teilnehmer, der vorherig stehene muss zuletzt gepostet haben
					if ($options['fieldorder'] == "dran") {
						$fieldorder = "
							AND {$character_position} > 0
							AND participant_data.existing_participants LIKE '%,%'
							AND t.lastposter = {$previous_character}
							";
					} elseif ($options['fieldorder'] == "nichtdran") {
					}
				}
			}
		}
	}

	if ($type == "all") {
		$type_string = " ";
	} elseif ($type == "user") {
	}
	if ($uid != 0) {
		if (!is_array($uid)) {
			$user = get_user((int)$uid);
			$username = $db->escape_string($user['username']);

			$type_string = " AND scenetracker_user LIKE '%{$username}%'";
		} else {
			if (is_array($uid)) {
				$conditions = array_map(function ($username) use ($db) {
					$username = $db->escape_string($username);
					return "scenetracker_user LIKE '%{$username}%'";
				}, $uid);

				$type_string = " AND (" . implode(" OR ", $conditions) . ")";
			}
		}
	}

	$scene_query_string = "SELECT {$selectors} FROM
          " . TABLE_PREFIX . "threads t INNER JOIN " . TABLE_PREFIX . "forums fo ON t.fid = fo.fid 
          {$add_st}
					{$threadsread}
					{$participant_join}
          {$forenquerie}
          {$type_string}
          {$excluded}
          {$profile_view}
          {$closed}
          {$timerange}
          {$date}
					{$index_alert}
					{$fieldorder}
					AND t.visible = 1 
          {$order};
  ";

	return $scene_query_string;
}
/****
 * Online Location
 ****/
$plugins->add_hook("fetch_wol_activity_end", "scenetracker_online_activity");
function scenetracker_online_activity($user_activity)
{
	global $parameters, $user;

	$split_loc = explode(".php", $user_activity['location']);
	if (isset($user['location']) && $split_loc[0] == $user['location']) {
		$filename = "";
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

/**
 * Funktion um das Formular im Popup zu bilden, um die Einstellungen für die Seznen Alerts zu ändern.
 * @param array $data - die Daten der Szene
 * @param string $typ - der Typ der Szene (index, alert)
 */
function scenetracker_build_popup_form($data, $typ)
{
	global $scenetracker_popup_select_options_index, $scenetracker_popup_select_options_alert, $templates, $mybb;
	$id = $data['id'];
	$sel_array = array(
		"character" => "",
		"charactername" => "",
		"always" => "",
		"another" => "",
		"never" => "",
		"fieldorder" => "",
	);
	$info_by = $data['inform_by'];
	if ($typ == "alert") {
		$field_selector = "type_alert";
	} else {
		$field_selector = "type";
	}

	if ($data[$field_selector] == "certain" && $info_by != 0) {
		$sel_array['character'] = "CHECKED";

		$info_user = get_user((int)$info_by);
		$sel_array['charactername'] = !empty($info_user['username'])
			? $info_user['username']
			: '';
	}
	if ($data[$field_selector] == "always") {
		$sel_array['another'] = "CHECKED";
	}
	if ($data[$field_selector] == "always_always") {
		$sel_array['always'] = "CHECKED";
	}
	if ($data[$field_selector] == "never") {
		$sel_array['never'] = "CHECKED";
	}
	if ($data[$field_selector] == "fieldorder") {
		$sel_array['fieldorder'] = "CHECKED";
	}
	$type = $typ;
	eval("\$scenetracker_popup_select_options_{$type} = \"" . $templates->get("scenetracker_popup_select_options") . "\";");
}

function scenetracker_get_calendar_settings_ucp()
{
	global $mybb, $db, $lang, $templates;
	global $yes_ind, $no_ind, $yes_rem, $no_rem, $yes_indall, $no_indall, $ucp_main_reminderopt, $setting_calendar, $get_calsettings, $setforalls_all, $setforalls_this, $scenetracker_calendarview_all, $scenetracker_calendarview_mini, $mini_view_all, $mini_view_all_own, $mini_view_all_this, $big_view_all, $big_view_all_own, $big_view_all_this, $calendar_setting_form;

	$ucp_main_reminderopt = $big_view_all = $big_view_all_own = $big_view_all_this = $setforalls_all = 	$setforalls_this =	$get_calsettings = $mini_view_all = $mini_view_all_own = $mini_view_all_this = "";

	$scenetracker_calendarview_ownall = '';
	$scenetracker_calendarview_all = '';
	$scenetracker_calendarview_mini = '';
	$scenetracker_calendarview = '';
	$setting_calendar = 0;

	//Einstellung - Soll die Szenen auf dem Index angezeigt werden
	$index_settinguser = $db->fetch_field($db->simple_select("users", "tracker_index", "uid = " . $mybb->user['uid']), "tracker_index");
	$thisuser = $mybb->user['uid'];

	if ($index_settinguser == 1) {
		$yes_ind = "checked";
		$no_ind = "";
	} else if ($index_settinguser == 0) {
		$yes_ind = "";
		$no_ind = "checked";
	}

	//Soll an alte Szenen im allgemeinen erinnert werden? 
	$index_reminder = $db->fetch_field($db->simple_select("users", "tracker_reminder", "uid= " . $mybb->user['uid']), "tracker_reminder");

	if ($index_reminder == 1) {
		$yes_rem = "checked";
		$no_rem = "";
	} else if ($index_reminder == 0) {
		$yes_rem = "";
		$no_rem = "checked";
	}

	//Szenen aller Charaktere des Users anzeigen, oder nur die Szenen des eingeloggten charas
	$index_settingall = $db->fetch_field($db->simple_select("users", "tracker_indexall", "uid = " . $mybb->user['uid']), "tracker_indexall");
	if ($index_settingall == 1) {
		$yes_indall = "checked";
		$no_indall = "";
	} else if ($index_settingall == 0) {
		$yes_indall = "";
		$no_indall = "checked";
	}

	// Wieviele Tage bis der Reminder angezeigt wird - wenn 0 hat der Admin entschieden gibt es nicht
	$days_reminder =  $mybb->settings['scenetracker_reminder'];
	$lang->scenetracker_reminderopt = $lang->sprintf($lang->scenetracker_reminderopt, $days_reminder);
	if ($days_reminder != 0) {
		eval("\$ucp_main_reminderopt =\"" . $templates->get("scenetracker_ucp_options_reminder") . "\";");
	}

	if ($mybb->settings['scenetracker_calendarview_all'] || $mybb->settings['scenetracker_calendarview_ownall'] || $mybb->settings['scenetracker_calendarview_own']) {
		$setting_calendar = 1;
	}

	//Kalender Einstellungen
	if ($setting_calendar != 0) {
		$get_calsettings = $db->fetch_array($db->simple_select("users", "scenetracker_calendar_settings,scenetracker_calendarsettings_big,scenetracker_calendarsettings_mini", "uid = '{$thisuser}'"));
		if ($mybb->settings['scenetracker_calendarview_all'] == 1) {

			if ($get_calsettings['scenetracker_calendar_settings'] == 0) {
				$setforalls_all = "";
				$setforalls_this = " CHECKED";
			} else {
				$setforalls_all = " CHECKED";
				$setforalls_this = "";
			}

			//Template laden, ob die Einstellung für alle Charas des users übernommen werden soll
			eval("\$scenetracker_calendarview_all =\"" . $templates->get("scenetracker_ucp_options_calendar_all") . "\";");
		} else {
			$scenetracker_calendarview_all = "";
		}
		if ($mybb->settings['scenetracker_calendarview_ownall'] == 1) {
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
			eval("\$scenetracker_calendarview_mini =\"" . $templates->get("scenetracker_ucp_options_minicalendar") . "\";");
		} else {
			$scenetracker_calendarview_mini = "";
		}

		//Einstellungen großer Kalender
		if ($mybb->settings['scenetracker_calendarview_own'] == 1) {
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
			eval("\$scenetracker_calendarview =\"" . $templates->get("scenetracker_ucp_options_calendar") . "\";");
		} else {
			$scenetracker_calendarview = "";
		}

		//Speichern der Kalender Settings
		if ($mybb->get_input("calendar_settings")) {
			//angehangene bekommen
			$chararray = array_keys(scenetracker_get_accounts($thisuser));
			//string zusammensetzen
			$charstring = implode(",", $chararray);

			//inputs ins array speichern
			$save = array(
				"scenetracker_calendar_settings" => $mybb->get_input("calendar_setforalls", MYBB::INPUT_INT),
				"scenetracker_calendarsettings_big" => $mybb->get_input("big_view", MYBB::INPUT_INT),
				"scenetracker_calendarsettings_mini" =>  $mybb->get_input("mini_view", MYBB::INPUT_INT),
			);

			//Nur für diesen Charakter
			if ($mybb->get_input("calendar_setforalls") == 0) {
				//checken wie die einstellung vorher war, wenn sie für alle war, müssen wir sie, wenn sie nun nur noch für diesen charakter gelten soll auf den neuen input setzen
				$get_calsettings_check = $db->fetch_field($db->simple_select("users", "scenetracker_calendar_settings", "uid = '{$thisuser}'"), "scenetracker_calendar_settings");
				if ($get_calsettings_check == 1) {
					$save2 = array(
						"scenetracker_calendar_settings" => $mybb->get_input("calendar_setforalls", MYBB::INPUT_INT),
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

		eval("\$calendar_setting_form =\"" . $templates->get("scenetracker_ucp_options_calendarform") . "\";");
	} else {
		$calendar_setting_form = "";
	}
}

function scenetracker_filter_scenes_ucp()
{

	global $mybb, $db, $lang, $templates;
	// Alle Szenen && Alle Charaktere  Sortiert nach last post
	$options = array();

	// Filter für beide Möglichkeiten
	if ($mybb->settings['scenetracker_count'] == 2) {
		//dran, nach eingetragener reihenfolge
		$options['fieldorder'] = "1";
	} else if ($mybb->settings['scenetracker_count'] == 1) {
		//dran wie alert einstellung
		$options['index_alert'] = "1";
	} else {
		//dran wie index einstellung
		$options['alert_alert'] = "1";
	}

	//Status der Szene 
	if ($mybb->get_input("status")) {
		//both, open, closed
		if ($mybb->get_input("status") == "both") {
			$options['closed'] = "both";
		} else if ($mybb->get_input("status") == "open") {
			$options['closed'] = 0;
		} else if ($mybb->get_input("status") == "closed") {
			$options['closed'] = 1;
		}
	}

	//Dran oder nicht
	if ($mybb->get_input("move")) {
		$move = $mybb->get_input("move");
		// beides, ja, nein
		if ($move == "ja") {
		} else if ($move == "nein") {
		}
		if (($move != "ja") && ($move != "nein")) {
		}
	}

	//Postpartner
	if ($mybb->get_input("player")) {
		$spieler = (int)$mybb->get_input("player");
		$data = scenetracker_get_player_charas($spieler);
		$uids_array = scenetracker_get_accounts($data[0]['uid']);
		//username
	}

	//nur nach bestimmten Charakteren filtern
	if ($mybb->get_input("charakter")) {
	}

	//Charakter
	//Szenen Filtern pro Charakter
	if ($mybb->get_input("scenefilter")) {
	}

	// //Alle Szenen, aller Charaktere, soritert nach last post
	// if ($mybb->get_input("newest")) {
	// }

	// if ($mybb->get_input("oldest")) {
	// }
}
/**************************** 
 *  My Alert Integration
 **************************** */
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
			$this->lang->load("scenetracker");
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

/*********************
 * UPDATE KRAM
 *********************/

// #####################################
// ### LARAS BIG MAGIC - RPG STUFF MODUL - THE FUNCTIONS ###
// #####################################

$plugins->add_hook("admin_rpgstuff_action_handler", "scenetracker_admin_rpgstuff_action_handler");
function scenetracker_admin_rpgstuff_action_handler(&$actions)
{
	$actions['scenetracker'] = array("active" => "scenetracker", "file" => "scenetracker");
	$actions['scenetracker_updates'] = array("active" => "scenetracker_updates", "file" => "scenetracker_updates");
}

// Benutzergruppen-Berechtigungen im ACP
$plugins->add_hook("admin_rpgstuff_permissions", "scenetracker_admin_rpgstuff_permissions");
function scenetracker_admin_rpgstuff_permissions(&$admin_permissions)
{
	global $lang;
	$lang->load("scenetracker");

	$admin_permissions['scenetracker'] = $lang->scenetracker_permission;

	return $admin_permissions;
}

// im Menü einfügen
$plugins->add_hook("admin_rpgstuff_menu", "scenetracker_admin_rpgstuff_menu");
function scenetracker_admin_rpgstuff_menu(&$sub_menu)
{
	global $lang;
	$lang->load("scenetracker");

	$sub_menu[] = [
		"id" => "scenetracker",
		"title" => $lang->scenetracker_manage,
		"link" => "index.php?module=rpgstuff-scenetracker"
	];
}

$plugins->add_hook("admin_load", "scenetracker_admin_manage_scenetracker");
function scenetracker_admin_manage_scenetracker()
{
	global $mybb, $db, $lang, $page, $run_module, $action_file, $cache, $theme;

	if ($page->active_action != "scenetracker") {
		return false;
	}

	if ($run_module == "rpgstuff" && $action_file == "scenetracker") {
		if ($mybb->get_input("action") == "" || !$mybb->get_input("action")) {
			$links = "";
			$lang->load("scenetracker");
			$page->add_breadcrumb_item("Szenentracker", "index.php?module=rpgstuff-scenetracker");
			$page->output_header("Szenentracker Verwaltung");
			$sub_tabs = scenetracker_do_submenu();
			$page->output_nav_tabs($sub_tabs, "scenetracker");
			// fehleranzeige
			if (isset($errors)) {
				$page->output_inline_error($errors);
				$search = new Form("index.php?module=rpgstuff-scenetracker&action=browsefield", "post", "search_form");
			}

			$scenes_all = $db->write_query(scenetracker_build_sql_threadtable($type = "all", 0));

			$scenes_closed = $db->write_query(scenetracker_build_sql_threadtable($type = "all", 0, array("closed" => 1)));

			$scenes_open = $db->write_query(scenetracker_build_sql_threadtable($type = "all", 0, array("closed" => 0)));
			$excluded = "";
			if ($mybb->settings['scenetracker_exludedfids'] != "") {
				$excluded = "<br>(ausgeschlossene Areas: {$mybb->settings['scenetracker_exludedfids']})";
			}
			$table = new Table;
			$table->construct_header("alle Szenen", array("class" => "align_center", "width" => "33%"));
			$table->construct_header("davon aktiv", array("class" => "align_center", "width" => "33%"));
			$table->construct_header("davon geschlossen", array("class" => "align_center", "width" => "33"));

			$table->construct_row(array("id" => "test"));

			$table->construct_cell('<fieldset style="text-align:center; padding: 15px;">'
				. $db->num_rows($scenes_all) . '<br>
          <span class="smalltext">alle Threads im Ingame und Archiv' . $excluded . '</span>
          </fieldset>');
			$table->construct_cell('<fieldset style="text-align:center; padding: 15px;">'
				. $db->num_rows($scenes_open) . '<br>
          <span class="smalltext">nicht geschlossene Threads im Ingame und Archiv' . $excluded . '</span>
          </fieldset>');
			$table->construct_cell('<fieldset style="text-align:center; padding: 15px;">'
				. $db->num_rows($scenes_closed) . '<br>
          <span class="smalltext">geschlossene Threads im Ingame und Archiv' . $excluded . '</span>
          </fieldset>');
			$table->construct_row();

			$table->output("");

			$ingame = $mybb->settings['scenetracker_ingame'];
			// echo scenetracker_build_sql_threadtable($type = 'all', 0, array("extra_fids" =>  $ingame));
			$scenes_ingame = $db->write_query(scenetracker_build_sql_threadtable($type = "all", 0, array("extra_fids" => $ingame)));

			$scenes_ingame_open = $db->write_query(scenetracker_build_sql_threadtable($type = "all", 0, array("extra_fids" => $ingame, "closed" => 0)));

			$scenes_ingame_closed = $db->write_query(scenetracker_build_sql_threadtable($type = "all", 0, array("extra_fids" => $ingame, "closed" => 1)));

			$table2 = new Table;
			$table2->construct_header("Szenen im Ingame", array("class" => "align_center", "width" => "33%"));
			$table2->construct_header("davon aktiv", array("class" => "align_center", "width" => "33%"));
			$table2->construct_header("davon geschlossen", array("class" => "align_center", "width" => "33"));
			$table2->construct_row();
			$table2->construct_cell('<fieldset style="text-align:center; padding: 15px;">'
				. $db->num_rows($scenes_ingame) . '<br>
          <span class="smalltext">alle Threads im Ingame' . $excluded . '</span>
          </fieldset>');
			if ($db->num_rows($scenes_ingame_open) > 0) {
				$links = "<ul style='max-height:100px; overflow: auto; text-align: left;'>";
				while ($scene = $db->fetch_array($scenes_ingame_open)) {
					$scenedate = scenetracker_get_scenedate($scene['tid']);
					$links .= "<li><a href=\"{$mybb->settings['bburl']}/showthread.php?tid={$scene['tid']}\" target=\"_blank\">{$scene['subject']}</a> - " . $scenedate['date_all'] . "</li>";
				}
				$links .= "</ul>";
			} else {
				$links = "";
			}

			$table2->construct_cell('<fieldset style="text-align:center; padding: 15px;">'
				. $db->num_rows($scenes_ingame_open) . '<br>
          <span class="smalltext">nicht geschlossene Threads im Ingame' . $excluded . $links .  '</span>
          </fieldset>');

			if ($db->num_rows($scenes_ingame_closed) > 0) {
				$links = "<ul style='max-height:100px; overflow: auto; text-align: left;'>";
				while ($scene = $db->fetch_array($scenes_ingame_closed)) {
					$scenedate = scenetracker_get_scenedate($scene['tid']);
					$links .= "<li><a href=\"{$mybb->settings['bburl']}/showthread.php?tid={$scene['tid']}\" target=\"_blank\">{$scene['subject']}</a> - " . $scenedate['date_all'] . "</li>";
				}
				$links .= "</ul>";
			} else {
				$links = "";
			}

			$table2->construct_cell('<fieldset style="text-align:center; padding: 15px;">'
				. $db->num_rows($scenes_ingame_closed) . '<br>
          <span class="smalltext">geschlossene Threads im Ingame' . $excluded . $links . '</span>
          
          </fieldset>');
			$table2->construct_row();

			$table2->output("");

			$archive = $mybb->settings['scenetracker_archiv'];

			$scenes_archive = $db->write_query(scenetracker_build_sql_threadtable($type = "all", 0, array("extra_fids" => $archive)));

			$scenes_archive_open = $db->write_query(scenetracker_build_sql_threadtable($type = "all", 0, array("extra_fids" => $archive, "closed" => 0)));

			$scenes_archive_closed = $db->write_query(scenetracker_build_sql_threadtable($type = "all", 0, array("extra_fids" => $archive, "closed" => 1)));

			$table3 = new Table;
			$table3->construct_header("Szenen im Archiv", array("class" => "align_center", "width" => "33%"));
			$table3->construct_header("davon nicht geschlossen", array("class" => "align_center", "width" => "33%"));
			$table3->construct_header("davon geschlossen", array("class" => "align_center", "width" => "33"));
			$table3->construct_row();
			$table3->construct_cell('<fieldset style="text-align:center; padding: 15px;">'
				. $db->num_rows($scenes_archive) . '<br>
          <span class="smalltext">alle Threads im Archiv' . $excluded . '</span>
          </fieldset>');

			if ($db->num_rows($scenes_archive_open) > 0) {
				$links = "<ul style='max-height:100px; overflow: auto; text-align: left;'>";
				while ($scene = $db->fetch_array($scenes_archive_open)) {
					$scenedate = scenetracker_get_scenedate($scene['tid']);
					$links .= "<li><a href=\"{$mybb->settings['bburl']}/showthread.php?tid={$scene['tid']}\" target=\"_blank\">{$scene['subject']}</a> - " . $scenedate['date_all'] . "</li>";
				}
				$links .= "</ul>";
			} else {
				$links = "";
			}

			$table3->construct_cell('<fieldset style="text-align:center; padding: 15px;">'
				. $db->num_rows($scenes_archive_open) . '<br>
          <span class="smalltext">nicht geschlossene Threads im Archiv' . $excluded . $links .  '</span>
          </fieldset>');

			if ($db->num_rows($scenes_archive_closed) > 0) {
				$links = "<ul style='max-height:100px; overflow: auto; text-align: left;'>";
				while ($scene = $db->fetch_array($scenes_archive_closed)) {
					$scenedate = scenetracker_get_scenedate($scene['tid']);
					$links .= "<li><a href=\"{$mybb->settings['bburl']}/showthread.php?tid={$scene['tid']}\" target=\"_blank\">{$scene['subject']}</a> - " . $scenedate['date_all'] . "</li>";
				}
				$links .= "</ul>";
			} else {
				$links = "";
			}

			$table3->construct_cell('<fieldset style="text-align:center; padding: 15px;">'
				. $db->num_rows($scenes_archive_closed) . '<br>
          <span class="smalltext">geschlossene Threads im Archiv' . $excluded . $links . '</span>
          
          </fieldset>');
			$table3->construct_row();
			$table3->output("");
			$page->output_footer();
			exit;
		}
	}
}

$plugins->add_hook("admin_load", "scenetracker_admin_manage_scenetracker_overview");
function scenetracker_admin_manage_scenetracker_overview()
{
	global $mybb, $db, $lang, $page, $run_module, $action_file, $cache, $theme;

	if ($page->active_action != "scenetracker") {
		return false;
	}

	if ($run_module == "rpgstuff" && $action_file == "scenetracker") {
		if ($mybb->get_input("action") == "scenetracker_overview") {

			$queryarray["orderby"] = "t.lastpost DESC";
			$uid = 0;

			$links = "";
			$lang->load("scenetracker");
			$page->add_breadcrumb_item("Szenentracker", "index.php?module=rpgstuff-scenetracker&action=scenetracker_overview");
			$page->output_header("Szenentracker Übersicht");
			$sub_tabs = scenetracker_do_submenu();
			$page->output_nav_tabs($sub_tabs, "scenetracker_overview");

			if ($mybb->request_method == "post") {
				//alle szenen
				if ($mybb->get_input("status") == 0) {
					$queryarray['closed'] = "both";
				}
				//offene szenen
				if ($mybb->get_input("status") == 1) {
					$queryarray['closed'] =  0;
				}
				//geschlossene szenen
				if ($mybb->get_input("status") == 2) {
					$queryarray['closed'] = 1;
				}
				//nach Charakter filtern
				if ($mybb->get_input("username") != "") {
					$uid = get_user_by_username($mybb->get_input("username"))['uid'];
				}
				//nach Charakter filtern
				if ($mybb->get_input("timerange") == "0") {
				} elseif ($mybb->get_input("timerange") == "1") {
					$queryarray['timerange'] = "ingame";
				} elseif ($mybb->get_input("timerange") == "2") {
					$queryarray['timerange'] = "notingame";
				} else {
					$queryarray['timerange'] = $mybb->get_input("timerange");
				}

				if ($mybb->get_input("month") != "") {
					$queryarray['timerange'] = $mybb->get_input("month");
				}

				if ($mybb->get_input("player") != "") {
					$spieler = $db->escape_string($mybb->get_input("player"));
					$data = scenetracker_get_player_charas($spieler);
					$uid = scenetracker_get_accounts($data[0]['uid']);
				}
			}
			// fehleranzeige
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}


			$form = new Form("index.php?module=rpgstuff-scenetracker&action=scenetracker_overview", "post", "scene_form");
			$form_container = new FormContainer("Szenen filtern");

			$form_container->output_row("Teilnehmer", $lang->autocomplete_enabled, $form->generate_text_box("username", $mybb->get_input("username"), array("id" => "username")), "username");

			$status = array("alle", "offen", "geschlossen");
			$form_container->output_row("Status", "", $form->generate_select_box("status", $status, $mybb->get_input("status"), array("id" => "status_scene")), "status");

			$ingame_range = $mybb->settings['scenetracker_ingametime'];
			$ingame_range_array = explode(",", $ingame_range);
			$timerange = array("Egal", "Im Ingame Zeitraum", "Außerhalb Ingame Zeitraum");

			$ingame_range_string = "Aktueller Ingamezeitraum: ";
			foreach ($ingame_range_array as $monthyear) {
				$date = DateTime::createFromFormat("Y-m", trim($monthyear));
				$ingame_range_string .= $date->format("F Y") . ", ";
				$timerange[trim($monthyear)] = $date->format("F Y");
			}

			$form_container->output_row(
				"Ingame Zeitraum",
				$ingame_range_string,
				$form->generate_select_box("timerange", $timerange, $mybb->get_input("timerange"), array("id" => "timerange")),
				"timerange"
			);

			$form_container->output_row(
				"Bestimmter Monat",
				"Achtung: Nur ein Monat und Format muss(!) 2026-07 sein",
				$form->generate_text_box("month", $mybb->get_input("month"), array("id" => "month")),
				"month"
			);

			$form_container->output_row(
				"Forum",
				"",
				$form->generate_forum_select("forum", $mybb->get_input("forum"))
			);

			$form_container->output_row("Spieler", $lang->autocomplete_enabled, $form->generate_text_box("player", $mybb->get_input("player"), array("id" => "player")), "player");

			// Autocompletion for usernames and player
			$form_container->end();
			echo '
        <link rel="stylesheet" href="../jscripts/select2/select2.css">
        <script type="text/javascript" src="../jscripts/select2/select2.min.js?ver=1804"></script>
        <script type="text/javascript">
        <!--
        $("#username").select2({
          placeholder: "' . $lang->search_for_a_user . '",
          minimumInputLength: 2,
          multiple: false,
          ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
            url: "../xmlhttp.php?action=get_users",
            dataType: \'json\',
            data: function (term, page) {
              return {
                query: term, // search term
              };
            },
            results: function (data, page) { // parse the results into the format expected by Select2.
              // since we are using custom formatting functions we do not need to alter remote JSON data
              return {results: data};
            }
          },
          initSelection: function(element, callback) {
            var query = $(element).val();
            if (query !== "") {
              $.ajax("../xmlhttp.php?action=get_users&getone=1", {
                data: {
                  query: query
                },
                dataType: "json"
              }).done(function(data) { callback(data); });
            }
          },
        });

          $(\'[for=username]\').on(\'click\', function(){
          $("#username").select2(\'open\');
          return false;
        });
        // -->
        <!--
        $("#player").select2({
          placeholder: "Nach Spieler suchen",
          minimumInputLength: 2,
          multiple: false,
          ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
            url: "../xmlhttp.php?action=scenetracker_get_player",
            dataType: \'json\',
            data: function (term, page) {
              return {
                query: term, // search term
                fieldid: "player",
              };
            },
            results: function (data, page) { // parse the results into the format expected by Select2.
              // since we are using custom formatting functions we do not need to alter remote JSON data
              return {results: data};
            }
          },
          initSelection: function(element, callback) {
            var value = $(element).val();
            if (value !== "") {
              callback({
                id: value,
                text: value
              });
            }
          },
        });

          $(\'[for=player]\').on(\'click\', function(){
          $("#player").select2(\'open\');
          return false;
        });
        // -->
        </script>';
			// $buttons[] = $form->generate_submit_button($lang->ban_user);
			$buttons[] = $form->generate_submit_button("Szenen filtern", array("name" => "filterscenes"));
			$form->output_submit_wrapper($buttons);
			$form->end();

			echo "<br />";


			$querystring = scenetracker_build_sql_threadtable($type = "all", $uid, $queryarray);
			// echo $querystring;
			$all_ingame = $db->write_query($querystring);

			$table = new Table;
			$table->construct_header("Szene", array("class" => "align_center", "width" => "20%"));
			$table->construct_header("Szeneninfos", array("class" => "align_center", "width" => "20%"));
			$table->construct_header("Forum", array("class" => "align_center", "width" => "20%"));
			$table->construct_header("Letzter Post", array("class" => "align_center", "width" => "20%"));

			$table->construct_row();

			while ($scene = $db->fetch_array($all_ingame)) {

				$tidlink = '<a href="' . get_thread_link($scene['tid'], 0, 'lastpost') . '">' . $scene['subject'] . '</a>';
				$array_teilnehmer = explode(",", $scene['scenetracker_user']);
				$charalinks = "<b>Teilnehmer:</b> ";
				foreach ($array_teilnehmer as $charakter) {
					$chararrray = get_user_by_username(trim($charakter));
					// var_dump($charaarray);
					$charalinks .= build_profile_link($charakter, $chararrray['uid']) . "<br> ";
				}
				if ($scene['scenetracker_trigger'] != "") {
					$scenetrigger = "<span><b>Inhaltswarnung:</b> " . $scene['scenetracker_trigger'] . "</span>";
				} else {
					$scenetrigger = "";
				}
				$forum = get_forum($scene['fid']);
				$forumlink = '<a href="' . $mybb->settings['bburl'] . "/" . get_forum_link($scene['fid']) . '">' . $forum['name'] . '</a>';
				$lastpost_info = "Letzter Post am <b>" . date('d.m.Y', $scene['lastpost']) . "</b> von " . build_profile_link($scene['lastposter'], $scene['lastposteruid']);
				$scenedate = scenetracker_get_scenedate($scene['tid']);

				$table->construct_cell($tidlink);
				$table->construct_cell(
					'<div style="display:flex; flex-direction: column; gap: 5px;">
             <span>' . $charalinks . '</span>' .
						'<span><b>Ort:</b> ' . $scene['scenetracker_place'] . '</span>' .
						'<span><b>Datum:</b> ' . $scenedate['date_all'] . '</span>' .
						$scenetrigger .
						'</div>'
				);
				$table->construct_cell($forumlink);
				$table->construct_cell($lastpost_info);

				$table->construct_row();
			}
			$table->output("");
			$page->output_footer();

			exit;
		}
	}
}

$plugins->add_hook("admin_load", "scenetracker_admin_manage_scenetracker_plots");
function scenetracker_admin_manage_scenetracker_plots()
{
	global $mybb, $db, $lang, $page, $run_module, $action_file, $cache, $theme;

	if ($page->active_action != "scenetracker") {
		return false;
	}

	if ($run_module == "rpgstuff" && $action_file == "scenetracker") {

		//plot overview
		if ($mybb->get_input("action") == "scenetracker_plots") {

			//einen Plot hinzufügen
			if ($mybb->get_input("do") == "scenetracker_plot_delete") {
				$plotid = $mybb->get_input("id", MyBB::INPUT_INT);
				if (empty($plotid)) {
					flash_message("Es wurde keine ID übergeben. Hast du einen Plot ausgewählt, der gelöscht werden soll?", "error");
					admin_redirect("index.php?module=rpgstuff-scenetracker&action=scenetracker_plots");
				}
				if (isset($mybb->input['no']) && $mybb->input['no']) {
					admin_redirect("index.php?module=rpgstuff-scenetracker&action=scenetracker_plots");
				}
				if (!verify_post_check($mybb->input['my_post_key'])) {
					flash_message($lang->invalid_post_verify_key2, "error");
					admin_redirect("index.php?module=rpgstuff-scenetracker&action=scenetracker_plots");
				} else {
					if ($mybb->request_method == "post") {
						$db->delete_query("scenetracker_plots", "id='{$plotid}'");
						$db->delete_query("scenetracker_plots_tids", "plot_id='{$plotid}'");
					} else {
						$page->output_confirm_action(
							"index.php?module=rpgstuff-scenetracker&amp;action=scenetracker_plots&do=scenetracker_plot_delete&amp;id={$plotid}",
							"Möchtest du dieseen Plot wirklich löschen, das kann nicht rückgängig gemacht werden?"
						);
					}
				}
			}

			if ($mybb->get_input("do") == "scenetracker_plot_activate") {
				$plotid = $mybb->get_input("id", MyBB::INPUT_INT);
				if (empty($plotid)) {
					flash_message("Es wurde keine ID übergeben. Hast du einen Plot ausgewählt, der gelöscht werden soll?", "error");
					admin_redirect("index.php?module=rpgstuff-scenetracker&action=scenetracker_plots");
				}
				if (!verify_post_check($mybb->input['my_post_key'])) {
					flash_message($lang->invalid_post_verify_key2, "error");
					admin_redirect("index.php?module=rpgstuff-scenetracker&action=scenetracker_plots");
				} else {

					$db->update_query("scenetracker_plots", array("active" => 1), "id='{$plotid}'",);
				}
			}

			if ($mybb->get_input("do") == "scenetracker_plot_close") {
				$plotid = $mybb->get_input("id", MyBB::INPUT_INT);
				if (empty($plotid)) {
					flash_message("Es wurde keine ID übergeben. Hast du einen Plot ausgewählt, der gelöscht werden soll?", "error");
					admin_redirect("index.php?module=rpgstuff-scenetracker&action=scenetracker_plots");
				}
				if (!verify_post_check($mybb->input['my_post_key'])) {
					flash_message($lang->invalid_post_verify_key2, "error");
					admin_redirect("index.php?module=rpgstuff-scenetracker&action=scenetracker_plots");
				} else {
					$db->update_query("scenetracker_plots", array("active" => 0), "id='{$plotid}'");
				}
			}

			$page->output_header("Szenentracker Plots Verwalten");
			$sub_tabs = scenetracker_do_submenu();
			$page->output_nav_tabs($sub_tabs, "scenetracker_plots");
			$page->add_breadcrumb_item("Plots hinzufügen");

			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

			$infotable = new Table;
			$infotable->construct_cell("Hier findest du eine Übersicht deiner aktuellen und vergangenen Plots mit den wichtigsten Infos und auch einer Auflistung der Szenen, die zu den Plots eröffnet wurden. <br> Vorhandene Plots kannst du hier bearbeiten, sie als beendet markieren und natürlich neue Plots erstellen.<br><br>
      
      Du kannst hier außerdem Kategorien für Plots anlegen und ihnen eine Sortierung geben. 
      <br><br>
      <a href=\"index.php?module=rpgstuff-scenetracker&action=scenetracker_plots_add\" class=\"popup_button\" style=\"font-size: 1.2em;\">&#10133; Plot hinzufügen</a> 
			<a href=\"index.php?module=rpgstuff-scenetracker&action=scenetracker_plots_add_cat\" class=\"popup_button\" style=\"font-size: 1.2em;\">&#10133; Plot-Kategorie hinzufügen</a> 
      ");

			$infotable->construct_row();

			$infotable->output("Plots erstellen und verwalten");

			//Übersicht Plot Tabellenreihe aktiv(1) und beendet(0)
			scenetracker_construct_plot_overview(1);
			scenetracker_construct_plot_overview(0);

			$form = new Form("index.php?module=rpgstuff-scenetracker&amp;action=scenetracker_plots&amp;do=add", "post", "add_cat", 1);

			$cattable = new Table;
			$cattable->construct_header("Kategoriebezeichnung", array("width" => "20%"));
			$cattable->construct_header("Kategoriebeschreibung", array("width" => "70%"));
			$cattable->construct_header("Action", array("width" => "10%"));
			$cattable->construct_row();
			$cat_query = $db->simple_select("scenetracker_plots_categories", "*", "");

			while ($cat = $db->fetch_array($cat_query)) {
				$cattable->construct_cell("<strong>" . $cat["name"] . "</strong>", array("style" => "vertical-align: top;"));
				$cattable->construct_cell($cat['description'], array("style" => "vertical-align: top;"));

				$popup_overview = new PopupMenu("opt_cat_{$cat['id']}", "Optionen");
				$popup_overview->add_item(
					"löschen",
					"index.php?module=rpgstuff-scenetracker&action=scenetracker_plots&amp;do=scenetracker_plot_cat_delete&amp;id={$cat['id']}"
						. "&amp;my_post_key={$mybb->post_code}"
				);
				$popup_overview->add_item(
					"bearbeiten",
					"index.php?module=rpgstuff-scenetracker&action=scenetracker_plot_cat_edit&amp;id={$cat['id']}"
						. "&amp;my_post_key={$mybb->post_code}"
				);
				$cattable->construct_cell($popup_overview->fetch(), array("width" => "150", "style" => "text-align: center;vertical-align: top; padding-top:15px;"));
				$cattable->construct_row();
			}
			$cattable->output("Plot Kategorien ");

			$page->output_footer();
			$buttons_add[] = $form->generate_submit_button("Speichern");
			$form->output_submit_wrapper($buttons_add);
			$form->end();
			exit;
		}

		//Plot Hinzufügen
		if ($mybb->get_input("action") == "scenetracker_plots_add") {
			//speichern des Plots
			if ($mybb->request_method == "post") {
				// Fehler abfangen
				if ($mybb->get_input("name") == "") {
					//Kein Name
					if (empty($mybb->get_input("name"))) {
						$errors[] = "Du musst einen Namen für den Plot bestimmen.";
					}
					$startdate = $mybb->get_input("startdate_year") . "-" . $mybb->get_input("startdate_month") . "-" . $mybb->get_input("startdate_day");
					$enddate = $mybb->get_input("enddate_year") . "-" . $mybb->get_input("enddate_month") . "-" . $mybb->get_input("enddate_day");
					$startvgl = new DateTime($startdate);
					$endvgl = new DateTime($enddate);
					//Fehler wenn enddatum kleiner als startdatum
					if ($endvgl < $startvgl) {
						$errors[] = "Das Enddatum darf nicht vor dem Startdatum liegen.";
					}
				}

				//keine Fehler also speichern
				if (empty($errors)) {
					$startdate = $mybb->get_input("startdate_year") . "-" . $mybb->get_input("startdate_month") . "-" . $mybb->get_input("startdate_day");
					$enddate = $mybb->get_input("enddate_year") . "-" . $mybb->get_input("enddate_month") . "-" . $mybb->get_input("enddate_day");

					$data = [
						"name" => $db->escape_string($mybb->get_input("name")),
						"image" => $db->escape_string($mybb->get_input("image")),
						"description" => $db->escape_string($mybb->get_input("description")),
						"calendar" => intval($mybb->get_input("calendar")),
						"startdate" => $startdate,
						"enddate" => $enddate,
						"category" => intval($mybb->get_input("category")),
						"active" => intval($mybb->get_input("active")),
					];
					$db->insert_query("scenetracker_plots", $data);
					$mybb->input['module'] = "scenetracker";
					$mybb->input['action'] = "Plot erfolgreich gespeichert";
					log_admin_action(htmlspecialchars_uni($mybb->input['fieldname']));

					flash_message("Plot erfolgreich gespeichert", "success");
					admin_redirect("index.php?module=rpgstuff-scenetracker&amp;action=scenetracker_plots");
				}
			}

			$page->extra_header .= <<<EOF
        <link rel="stylesheet" href="../jscripts/sceditor/themes/mybb.css" type="text/css" media="all" />
        <script type="text/javascript" src="../jscripts/sceditor/jquery.sceditor.bbcode.min.js?ver=1832"></script>
        <script type="text/javascript" src="../jscripts/bbcodes_sceditor.js?ver=1837"></script>
        <script type="text/javascript" src="../jscripts/sceditor/plugins/undo.js?ver=1832"></script>
      EOF;

			$page->output_header("Szenentracker Plots Verwalten - Hinzufügen");
			$sub_tabs = scenetracker_do_submenu();
			$page->output_nav_tabs($sub_tabs, "scenetracker_plots");
			$page->add_breadcrumb_item("Plots hinzufügen - Hinzufügen");
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}
			scenetracker_construct_plot_input();
			exit;
		}
	}

	if ($mybb->get_input("action") == "scenetracker_plots_add_cat") {

		$errors = array();

		if ($mybb->request_method == "post") {

			$name = trim($mybb->get_input("name"));
			$sort = $mybb->get_input("sort", MyBB::INPUT_INT);
			$$summary = trim($mybb->get_input("summary"));
			$description = trim($mybb->get_input("description"));

			// Name fehlt
			if ($name === "") {
				$errors[] = "Du musst einen Namen für die Kategorie angeben.";
			}

			// optional: gleiche Kategorie nicht doppelt anlegen
			if ($name !== "") {
				$escaped_name = $db->escape_string($name);
				$exists = $db->fetch_field($db->simple_select("scenetracker_plots_categories", "id", "name = '{$escaped_name}'"), "id");
				if ($exists) {
					$errors[] = "Eine Kategorie mit diesem Namen existiert bereits.";
				}
			}

			// Keine Fehler -> speichern
			if (empty($errors)) {
				$save_category = array(
					"name" => $db->escape_string($name),
					"sort" => $sort,
					"summary" => $db->escape_string($summary),
					"description" => $db->escape_string($description),
				);
				$db->insert_query("scenetracker_plots_categories", $save_category);
				flash_message("Die Plot-Kategorie wurde erfolgreich erstellt.", "success");
				admin_redirect("index.php?module=rpgstuff-scenetracker&action=scenetracker_plots");
			}
		}

		$page->output_header("Szenentracker Plots Verwalten - Plot Kategorien erstellen");

		$sub_tabs = scenetracker_do_submenu();
		$page->output_nav_tabs($sub_tabs, "scenetracker_plots");
		scenetracker_construct_plot_cat_input();

		$page->output_footer();

		exit;
	}

	if ($mybb->get_input("action") == "scenetracker_plot_cat_edit") {

		$errors = array();

		if ($mybb->request_method == "post") {

			$id = trim($mybb->get_input("id"));
			$name = trim($mybb->get_input("name"));
			$sort = $mybb->get_input("sort", MyBB::INPUT_INT);
			$description = $mybb->get_input("description");


			// Name fehlt
			if ($name === "") {
				$errors[] = "Du musst einen Namen für die Kategorie angeben.";
			}

			// optional: gleiche Kategorie nicht doppelt anlegen
			if ($name !== "") {
				$escaped_name = $db->escape_string($name);
				$exists = $db->fetch_field($db->simple_select("scenetracker_plots_categories", "id", "name = '{$escaped_name}' AND id != {$id}"), "id");
				if ($exists) {
					$errors[] = "Eine Kategorie mit diesem Namen existiert bereits.";
				}
			}
			// Keine Fehler -> speichern
			if (empty($errors)) {

				$save_category = array(
					"name" => $db->escape_string($name),
					"sort" => intval($sort),
					"description" => $db->escape_string($description),
				);

				$db->update_query("scenetracker_plots_categories", $save_category, "id = $id");
				flash_message("Die Plot-Kategorie wurde erfolgreich erstellt.", "success");
				echo "erfolg?";
				// die();
				admin_redirect("index.php?module=rpgstuff-scenetracker&action=scenetracker_plots");
			} else {
				// Fehler anzeigen
				$page->output_inline_error($errors);
			}
			// echo "haifisch<br>";
			// var_dump($mybb->input);
			// die();
		}

		$page->output_header("Szenentracker Plots Verwalten - Plot Kategorien erstellen");

		$sub_tabs = scenetracker_do_submenu();
		$page->output_nav_tabs($sub_tabs, "scenetracker_plots");
		scenetracker_construct_plot_cat_input(0);

		$page->output_footer();

		exit;
	}

	//Plot editieren
	if ($mybb->get_input("action") == "scenetracker_plot_edit") {
		if ($mybb->request_method == "post") {
			// Fehler abfangen
			if ($mybb->get_input("id") == "") {
				$errors[] = "Es konnte nicht erkannt werden, welchen Plot du bearbeiten willst.";
			}

			//Kein Name
			if (empty($mybb->get_input("name"))) {
				$errors[] = "Du musst einen Namen für den Plot bestimmen.";
			}
			$startdate = $mybb->get_input("startdate_year") . "-" . $mybb->get_input("startdate_month") . "-" . $mybb->get_input("startdate_day");
			$enddate = $mybb->get_input("enddate_year") . "-" . $mybb->get_input("enddate_month") . "-" . $mybb->get_input("enddate_day");
			$startvgl = new DateTime($startdate);
			$endvgl = new DateTime($enddate);
			//Fehler wenn enddatum kleiner als startdatum
			if ($endvgl < $startvgl) {
				$errors[] = "Das Enddatum darf nicht vor dem Startdatum liegen.";
			}
			//keine Fehler also speichern
			if (empty($errors)) {
				$id = $mybb->get_input("id");
				$startdate = $mybb->get_input("startdate_year") . "-" . $mybb->get_input("startdate_month") . "-" . $mybb->get_input("startdate_day");
				$enddate = $mybb->get_input("enddate_year") . "-" . $mybb->get_input("enddate_month") . "-" . $mybb->get_input("enddate_day");

				$data = [
					"name" => $db->escape_string($mybb->get_input("name")),
					"image" => $db->escape_string($mybb->get_input("image")),
					"summary" => $db->escape_string($mybb->get_input("summary")),
					"description" => $db->escape_string($mybb->get_input("description")),
					"calendar" => intval($mybb->get_input("calendar")),
					"startdate" => $startdate,
					"enddate" => $enddate,
					"category" => intval($mybb->get_input("category")),
					"active" => intval($mybb->get_input("active")),
				];
				$db->update_query("scenetracker_plots", $data, "id = {$id}");
				$mybb->input['module'] = "scenetracker";
				$mybb->input['action'] = "Plot erfolgreich gespeichert";
				log_admin_action(htmlspecialchars_uni($mybb->input['fieldname']));

				flash_message("Plot erfolgreich gespeichert", "success");
				admin_redirect("index.php?module=rpgstuff-scenetracker&amp;action=scenetracker_plots");
			}
		}
		$page->extra_header .= <<<EOF
        <link rel="stylesheet" href="../jscripts/sceditor/themes/mybb.css" type="text/css" media="all" />
        <script type="text/javascript" src="../jscripts/sceditor/jquery.sceditor.bbcode.min.js?ver=1832"></script>
        <script type="text/javascript" src="../jscripts/bbcodes_sceditor.js?ver=1837"></script>
        <script type="text/javascript" src="../jscripts/sceditor/plugins/undo.js?ver=1832"></script>
      EOF;

		$page->output_header("Szenentracker Plots Verwalten - Plot editieren");
		$sub_tabs = scenetracker_do_submenu();
		$page->output_nav_tabs($sub_tabs, "scenetracker_plots");
		$page->add_breadcrumb_item("Plots hinzufügen - Hinzufügen");
		if (isset($errors)) {
			$page->output_inline_error($errors);
		}
		scenetracker_construct_plot_input(0);
		exit;
	}
}

/**
 * Funktion um die Tabellenreihe für Plots zu bauen.
 * brauchen wir zweimal für aktiv und beendet und wir wollen kein Copy & Paste kram
 */
function scenetracker_construct_plot_overview($active = 1)
{
	global $db, $mybb;
	$plot_query = $db->simple_select("scenetracker_plots", "*", "active = {$active}");
	$date = $category = "";

	if ($db->num_rows($plot_query) > 0) {
		$overviewtable = new Table;

		$overviewtable->construct_header("Plotname", array("width" => "10%"));
		$overviewtable->construct_header("Bild", array("width" => "10%"));
		$overviewtable->construct_header("Zusammenfassung", array("width" => "20%"));
		$overviewtable->construct_header("Beschreibung", array("width" => "20%"));
		$overviewtable->construct_header("Szenen", array("width" => "25%"));
		$overviewtable->construct_header("Optionen", array("width" => "5%"));

		$overviewtable->construct_row();

		while ($plot = $db->fetch_array($plot_query)) {
			if ($plot['calendar'] == "1") {
				$calendar = "<br><span>Kalender &#10004;</span>";
			} else {
				$calendar = "<br><span>Kalender &#10008;</span>";
			}
			if (!empty($plot['startdate'])) {
				$datetime = new DateTime($plot['startdate']);
				$start = $datetime->format("d.m.Y");
				$startdate = "{$start}";
			} else {
				$startdate = "";
			}
			if (!empty($plot['enddate']) && ($plot['enddate'] != $plot['startdate'])) {
				$datetime = new DateTime($plot['enddate']);
				$end = $datetime->format("d.m.Y");
				$enddate = " - {$end}";
			} else {
				$enddate = "</span>";
			}
			if ($startdate != "" || $enddate != "") {
				$date = "<br><span>&#128198; {$startdate}{$enddate}</span>";
			}

			if ($plot['category'] != "0") {
				$catname = $db->fetch_field($db->simple_select("scenetracker_plots_categories", "name", "id = {$plot['category']}"), "name");
				$category = "<br><span>Kategorie: {$catname}</span>";
			}
			$overviewtable->construct_cell('<strong>' . $plot['name'] . '</strong>' . $calendar . $date . $category, array('style' => "vertical-align: top;"));

			if ($plot['image'] != "") {
				$plotimg = "<img src=\"" . $plot['image'] . "\" style=\"max-width:100%;\">";
			} else {
				$plotimg = "Kein Plot-Bild";
			}

			$overviewtable->construct_cell($plotimg, array("class" => "align_center", "style" => "vertical-align: top;"));

			require_once MYBB_ROOT . "inc/class_parser.php";
			$parser = new postParser;

			$parser_options = array(
				"allow_html" => 1,
				"allow_mycode" => 1,
				"allow_smilies" => 1,
				"allow_imgcode" => 1,
				"allow_videocode" => 1,
				"filter_badwords" => 1,
				"me_username" => 0,
				"shorten_urls" => 0,
				"highlight" => 0,
			);

			$overviewtable->construct_cell('<fieldset style="padding: 15px; max-height: 200px; overflow: auto; text-align: justify;">' . $parser->parse_message($plot['summary'], $parser_options) . '</fieldset>', array('style' => "vertical-align: top;"));

			$overviewtable->construct_cell('<fieldset style="padding: 15px; max-height: 200px; overflow: auto; text-align: justify;">' . $parser->parse_message($plot['description'], $parser_options) . '</fieldset>', array('style' => "vertical-align: top;"));

			$scenes = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "scenetracker_plots_tids st INNER JOIN " . TABLE_PREFIX . "threads t on st.tid = t.tid WHERE plot_id = {$plot['id']} order by lastpost");
			$scenediv = "";

			while ($scene = $db->fetch_array($scenes)) {
				$tidinfo = $array_teilnehmer = $charalinks = $lastpost_info = "";
				$tidinfo = '<a href="' . get_thread_link($scene['tid'], 0, 'lastpost') . '">' . $scene['subject'] . '</a>';
				$array_teilnehmer = explode(",", $scene['scenetracker_user']);
				$charalinks = "<b>Teilnehmer:</b> ";
				foreach ($array_teilnehmer as $charakter) {
					$chararrray = get_user_by_username(trim($charakter));
					$charalinks .= build_profile_link($charakter, $chararrray['uid']) . ", ";
				}
				$forum = get_forum($scene['fid']);
				$forumlink = '<a href="' . $mybb->settings['bburl'] . "/" . get_forum_link($scene['fid']) . '">' . $forum['name'] . '</a>';
				$lastpost_info = "<b>Letzter Post:</b> " . date('d.m.Y', $scene['lastpost']) . "</b> von " . build_profile_link($scene['lastposter'], $scene['lastposteruid']);
				$scenedate = scenetracker_get_scenedate($scene['tid']);

				$scenediv .=  '<fieldset style="padding: 15px; display:flex; flex-direction: column; gap: 5px;">
            <span><b>' . $tidinfo . '</b></span>
             <span>' . $charalinks . '</span>' .
					'<span><b>Ort:</b> ' . $scene['scenetracker_place'] . '</span>' .
					'<span><b>Datum:</b> ' . $scenedate['date_all'] . '</span>' .
					'<span>' . $lastpost_info . '</span>' .
					'</fieldset>';
			}

			$overviewtable->construct_cell('<div style="max-height: 200px; overflow: auto; paddign:5px; text-align: justify;">' . $scenediv . '</div>');

			$popup_overview = new PopupMenu("opt_{$plot['id']}", "Optionen");
			$popup_overview->add_item(
				"löschen",
				"index.php?module=rpgstuff-scenetracker&action=scenetracker_plots&amp;do=scenetracker_plot_delete&amp;id={$plot['id']}"
					. "&amp;my_post_key={$mybb->post_code}"
			);
			$popup_overview->add_item(
				"bearbeiten",
				"index.php?module=rpgstuff-scenetracker&action=scenetracker_plot_edit&amp;id={$plot['id']}"
					. "&amp;my_post_key={$mybb->post_code}"
			);
			if ($active) {
				$popup_overview->add_item(
					"beenden",
					"index.php?module=rpgstuff-scenetracker&action=scenetracker_plots&amp;do=scenetracker_plot_close&amp;id={$plot['id']}"
						. "&amp;my_post_key={$mybb->post_code}"
				);
			} else {
				$popup_overview->add_item(
					"aktivieren",
					"index.php?module=rpgstuff-scenetracker&action=scenetracker_plots&amp;do=scenetracker_plot_activate&amp;id={$plot['id']}"
						. "&amp;my_post_key={$mybb->post_code}"
				);
			}
			$overviewtable->construct_cell($popup_overview->fetch(), array("width" => "150", "style" => "text-align: center;vertical-align: top; padding-top:15px;"));
			$overviewtable->construct_row();
		}

		if ($active == 0) {
			$status = "(beendet)";
		} else {
			$status = "(aktiv)";
		}

		$overviewtable->output("Plot Übersicht " . $status);
	}
}

/**
 * Funktion um das Plotselect zu bauen
 */
function scenetracker_build_plot_select($selectedid = "")
{
	global $db, $plot_select, $mybb;
	$plot_select = "";

	if ($mybb->settings['scenetracker_plotuse']) {
		$plots = $db->simple_select("scenetracker_plots", "*", "", ["order_by" => "name"]);

		$plot_opt = "";
		while ($plot = $db->fetch_array($plots)) {
			$select = "";
			if ($selectedid != "") {
				if ($selectedid == $plot['id']) {
					$select = "SELECTED";
				} else {
					$select = "";
				}
			}

			$plot_opt .= "<option value=\"{$plot['id']}\" $select>{$plot['name']}</option>";
		}
		$plot_select = "<select name=\"plot\" id=\"plot\">
		<option>Kein Plot</option>{$plot_opt}</select>";
		if ($db->num_rows($plots) == 0) {
			$plot_select = "";
		}
	}
}

/***
 * Funktion um Inputs zu generieren für edit/add plot
 */
function scenetracker_construct_plot_input($add = 1)
{
	global $mybb, $db, $lang;
	if ($add == 1) {
		$action_str = "scenetracker_plots_add";
		$formtitle = "Plot Hinzufügen";
		$id = 0;
		//defaults, bzw. gespeicherter Input bei Fehler
		$name = $mybb->get_input("name");
		$image = $mybb->get_input("image");
		$description = $mybb->get_input("summary");
		$description = $mybb->get_input("description");
		$calendar = $mybb->get_input("calendar");
		$startdate_day = $mybb->get_input("startdate_day");
		$startdate_month = $mybb->get_input("startdate_month");
		$startdate_year = $mybb->get_input("startdate_year");
		$enddate_day = $mybb->get_input("enddate_day");
		$enddate_month = $mybb->get_input("enddate_month");
		$enddate_year = $mybb->get_input("enddate_year");
		$category = $mybb->get_input("category");
		$active = $mybb->get_input("active");
	} else {
		$action_str = "scenetracker_plot_edit";
		$formtitle = "Plot Bearbeiten";
		$id = $mybb->get_input("id", MyBB::INPUT_INT);
		$data =  $db->fetch_array($db->simple_select("scenetracker_plots", "*", "id='{$id}'"));
		$name = $data['name'];
		$image = $data['image'];
		$summary = $data['summary'];
		$description = $data['description'];
		$calendar = $data['calendar'];
		// $startdate = $data['startdate'];
		$startdate = new DateTime($data['startdate']);
		$startdate_day = $startdate->format("d");
		$startdate_month = $startdate->format("m");
		$startdate_year = $startdate->format("Y");
		$enddate = new DateTime($data['enddate']);
		$enddate_day = $enddate->format("d");
		$enddate_month = $enddate->format("m");
		$enddate_year = $enddate->format("Y");
		$category = $data['category'];
		$active = $data['active'];
	}

	$form = new Form("index.php?module=rpgstuff-scenetracker&amp;action=" . $action_str, "post", "", 1);
	$form_container = new FormContainer($formtitle);
	if ($add != 1) {
		echo $form->generate_hidden_field("id", $id);
	}

	//Plotname
	$form_container->output_row(
		"Plotname <em>*</em>",
		"Gib hier den Namen für den Plot ein",
		$form->generate_text_box("name", $name)
	);

	//Plotbild
	$form_container->output_row(
		"Image",
		"Gib hier die URL zum Plotbild an, wenn du eines hast.",
		$form->generate_text_box("image", $image)
	);
	//Plot Zusammenfassung
	$summary_editor = $form->generate_text_area("summary", $summary, array("id" => "plot_summary", "rows" => 15, "cols" => "70", "style" => "height: 250px; width: 95%"));
	$summary_editor .= build_mycode_inserter("plot_summary");
	$form_container->output_row(
		"Zusammenfassung",
		"Füge hier eine kurze Zusammenfassung für die Übersichtsseite der Plots für diesen Plot hinzu.",
		$summary_editor
	);

	//Plot Beschreibung
	$description_editor = $form->generate_text_area("description", $description, array("id" => "plot_descr", "rows" => 15, "cols" => "70", "style" => "height: 250px; width: 95%"));
	$description_editor .= build_mycode_inserter("plot_descr");
	$form_container->output_row(
		"Beschreibung",
		"Füge hier den Beschreibungstext für den Plot hinzu.",
		$description_editor
	);
	//Anzeige im Kalender
	$form_container->output_row(
		"Kalenderanzeige <em>*</em>",
		"Gib an ob der Plot im Kalender angezeigt werden soll.",
		$form->generate_yes_no_radio("calendar", $calendar, true)
	);
	//Anzeige im Kalender
	$form_container->output_row(
		"Startdatum <em>*</em>",
		"Gib das Ingame Startdatum an.",
		$form->generate_date_select("startdate", $startdate_day, $startdate_month, $startdate_year)
	);
	$form_container->output_row(
		"Enddatum",
		"Gib das Ingame Enddatum an - Wenn es nur ein Tag ist, gib das gleiche Datum wie beim Startdatum an.",
		$form->generate_date_select("enddate", $enddate_day, $enddate_month, $enddate_year)
	);
	$cats = array();
	$get_cats = $db->simple_select("scenetracker_plots_categories", "*", "", array("order_by" => "sort"));
	while ($cat = $db->fetch_array($get_cats)) {
		$id = $cat['id'];
		$name = $cat['name'];
		$cats[$id] = $name;
	}
	$form_container->output_row(
		"Plot Kategorie",
		"Zu welcher Kategorie gehört der Plot?",
		$form->generate_select_box("category", $cats, $category, array("id" => "cat" . $category, "style" => "width: 30px;", "min" => 0))
	);

	//Anzeige im Kalender
	$form_container->output_row(
		"Plot Status <em>*</em>",
		"Ist der Plot gerade aktiv?",
		$form->generate_yes_no_radio("active", $active, true)
	);

	$form_container->end();
	$buttons[] = $form->generate_submit_button("speichern");
	$form->output_submit_wrapper($buttons);
	$form->end();
}

/***
 * Funktion um Inputs zu generieren für edit/add plot category
 */
function scenetracker_construct_plot_cat_input($add = 1)
{
	global $mybb, $db, $page;
	if ($add == 1) {
		$id = 0;
		$action_str = "scenetracker_plots_add_cat";
		$formtitle = "Plot Kategorie Hinzufügen";
		$name = $mybb->get_input("name");
		$description = $mybb->get_input("description");
		$sort = $mybb->get_input("sort");
	} else {
		$action_str = "scenetracker_plot_cat_edit";
		$formtitle = "Plot Kategorie Bearbeiten";
		$id = $mybb->get_input("id", MyBB::INPUT_INT);
		$data =  $db->fetch_array($db->simple_select("scenetracker_plots_categories", "*", "id='{$id}'"));
		$name = $data['name'];
		$description = $data['description'];
		$sort = $data['sort'];
	}
	$form = new Form("index.php?module=rpgstuff-scenetracker&amp;action=" . $action_str, "post", "", 1);
	$form_container = new FormContainer($formtitle);
	$page->add_breadcrumb_item("Plot Kategorien erstellen");

	if (!empty($errors)) {
		$page->output_inline_error($errors);
	}

	if ($add != 1) {
		echo $form->generate_hidden_field("id", $id);
	}
	//name
	$form_container->output_row("Kategoriename <em>*</em>", "Name der Plot-Kategorie.", $form->generate_text_box("name", $name, array("id" => "name")), "name");
	//sortierung
	$form_container->output_row("Sortierung", "Niedrigere Zahlen werden zuerst angezeigt.", $form->generate_numeric_field("sort", $sort, array("id" => "sort", "min" => 0)), "sort");

	//beschreibung
	$form_container->output_row("Beschreibung", "Optionaler Beschreibungstext für diese Kategorie.", $form->generate_text_area("description", $description, array("id" => "description", "rows" => 6)), "description");

	$form_container->end();
	$buttons = array();
	$buttons[] = $form->generate_submit_button("Kategorie erstellen");
	$form->output_submit_wrapper($buttons);
	$form->end();
}


function scenetracker_do_submenu()
{
	global $lang;
	$sub_tabs['scenetracker'] = [
		"title" => "Szenentracker Übersicht",
		"link" => "index.php?module=rpgstuff-scenetracker",
		"description" => "Ein paar Fakten und Zahlen von euren Szenen im Forum."
	];
	$sub_tabs['scenetracker_overview'] = [
		"title" => "Übersicht der Szenen",
		"link" => "index.php?module=rpgstuff-scenetracker&amp;action=scenetracker_overview",
		"description" => "Hier eine Übersicht von allen Szenen des Forums."
	];
	$sub_tabs['scenetracker_plots'] = [
		"title" => "Plots erstellen und verwalten",
		"link" => "index.php?module=rpgstuff-scenetracker&amp;action=scenetracker_plots",
		"description" => "Hier kannst du Plots erstellen und verwalten"
	];
	// $sub_tabs['scenetracker_cats'] = [
	// 	"title" => "Kategorien erstellen und verwalten",
	// 	"link" => "index.php?module=rpgstuff-scenetracker&amp;action=scenetracker_cats",
	// 	"description" => $lang->application_ucp_createfieldtype_dscr
	// ];
	$sub_tabs['scenetracker_transfer'] = [
		"title" => $lang->scenetracker_import,
		"link" => "index.php?module=rpgstuff-scenetracker&amp;action=scenetracker_transfer",
		"description" => $lang->application_ucp_createfieldtype_dscr
	];
	return $sub_tabs;
}

$plugins->add_hook("admin_load", "scenetracker_admin_manage_transfer");
function scenetracker_admin_manage_transfer()
{
	global $mybb, $db, $lang, $page, $run_module, $action_file, $cache, $theme;

	if ($page->active_action != "scenetracker") {
		return false;
	}

	if ($run_module == "rpgstuff" && $action_file == "scenetracker") {
		//Szenen übertragen
		if ($mybb->get_input("action") == "scenetracker_transfer") {
			$lang->load("scenetracker");
			$page->add_breadcrumb_item("szenentracker", "index.php?module=rpgstuff-scenetracker&action=scenetracker_transfer");

			if ($mybb->request_method == "post") {
				if ($mybb->get_input("transfer_type") === "scenes") {
					$selected_tracker = $mybb->get_input("type");
					if (empty($selected_tracker)) {
						$errors[] = $lang->scenetracker_error_select_tracker;
					}
					if ($selected_tracker == "ipt3") {
						if (!$db->table_exists("ipt_scenes")) {
							$errors[] = $lang->scenetracker_error_ipt3;
						}
					}
					if ($selected_tracker == "ipt2") {
						if (!$db->field_exists("partners", "threads")) {
							$errors[] = $lang->scenetracker_error_ipt2;
						}
					}
					if (empty($errors)) {
						if ($selected_tracker == "ipt3") {
							//Daten aus der Tracker Tabelle bekommen
							$get_sceneinfos = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "ipt_scenes");
							//daten durchgehen und übertragen
							while ($scene = $db->fetch_array($get_sceneinfos)) {
								$names = "";
								//Daten der Teilnehmer bekommen
								$get_user = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "ipt_scenes_partners WHERE tid= '{$scene['tid']}'");
								while ($user = $db->fetch_array($get_user)) {
									$userinfo = get_user($user['uid']);
									$names .= $userinfo['username'] . ",";
									$scenesave = array(
										"uid"  => $userinfo['uid'],
										"tid"  => $scene['tid']
									);
									//In unsere Tracker Tabelle eintragen
									$db->insert_query("scenetracker", $scenesave);
									//letzteskomma entfernen
									$names = substr_replace($names, "", -1);
								}

								//threadtabelle updaten
								$import = array(
									"scenetracker_date" => date("Y-m-d", $scene['date']),
									"scenetracker_place" => $db->escape_string($scene['iport']),
									"scenetracker_user" => $db->escape_string($names),
								);
								$db->update_query("threads", $import, "tid = '{$scene['tid']}'");
							}

							// if ($success) {
							//   // Log admin action           
							//   log_admin_action("Szenen von IPT3 zu Tracker übertragen");
							flash_message($lang->scenetracker_success, "success");
							admin_redirect("index.php?module=rpgstuff-scenetracker&action=scenetracker_transfer");
							// }
						}

						if ($selected_tracker == "ipt2") {
							//Daten aus der Tracker Tabelle bekommen, wo Partner eingetragen sind.
							$get_sceneinfos = $db->write_query("SELECT * FROM " . TABLE_PREFIX . "threads WHERE partners !='' ");
							//daten durchgehen und übertragen
							while ($scene = $db->fetch_array($get_sceneinfos)) {
								$names = "";
								$partners = $scene['partners'];
								$partners_array = explode(",", $partners);

								//szenentracker tabelle füllen
								foreach ($partners_array as $partner_uid) {
									$userinfo = get_user($partner_uid);
									$names .= $userinfo['username'] . ",";
									$scenesave = array(
										"uid"  => $userinfo['uid'],
										"tid"  => $scene['tid']
									);
									$db->insert_query("scenetracker", $scenesave);
								}

								$import = array(
									"scenetracker_date" => date("Y-m-d", $scene['ipdate']),
									"scenetracker_place" => $db->escape_string($scene['iport']),
									"scenetracker_user" => $db->escape_string($names),
								);
								$db->update_query("threads", $import, "tid = '{$scene['tid']}'");
							}
							flash_message($lang->scenetracker_success, "success");
							admin_redirect("index.php?module=rpgstuff-scenetracker&action=scenetracker_transfer");
						}
					}
				}
				if ($mybb->get_input("transfer_type") === "events") {
					verify_post_check($mybb->get_input("my_post_key"));
					$result = scenetracker_transfer_calendar_events();

					$message = "Es wurden {$result["imported"]} Kalender-Events übertragen.";

					if ($result["skipped"] > 0) {
						$message .= " {$result["skipped"]} bereits vorhandene Events wurden übersprungen.";
					}

					flash_message($message, "success");
					admin_redirect("index.php?module=rpgstuff-scenetracker&action=scenetracker_transfer");
				}
			}

			$page->output_header($lang->scenetracker_import);
			$sub_tabs = scenetracker_do_submenu();
			$page->output_nav_tabs($sub_tabs, "scenetracker_transfer");
			$page->add_breadcrumb_item("Szenen oder Events importieren");

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

			$setting_types = array(
				"ipt3" => "Jules Inplaytracker 3",
				"ipt2" => "Jules Inplaytracker 2",
				// "ales1" => "Ales" Inplaytracker 2",
				// "ales2" => "Ales" Inplaytracker 1",
				// "laras" => "Laras Szenenmanager",
			);

			$form = new Form("index.php?module=rpgstuff-scenetracker&action=scenetracker_transfer", "post", "", 1);
			$form_container = new FormContainer($lang->scenetracker_import);

			echo $form->generate_hidden_field("transfer_type", "scenes");
			$form_container->output_row(
				$lang->scenetracker_tracker_select . "<em>*</em>",
				$lang->scenetracker_tracker_select_descr,
				$form->generate_select_box(
					"type",
					$setting_types,
					$mybb->get_input("type"),
					array("id" => "type")
				),
				"type"
			);
			$form_container->end();

			$buttons = array($form->generate_submit_button($lang->scenetracker_btnsubmit));
			$form->output_submit_wrapper($buttons);

			$form->end();


			//Kalender Events übertragen - erst einmal testen ob es überhaupt welche gibt.
			$event_count = $db->fetch_field($db->simple_select("events",	"COUNT(*) AS count"), "count");
			if ($event_count > 0) {
				echo "<br><br><hr><br><br>";
				$form_event = new Form("index.php?module=rpgstuff-scenetracker&action=scenetracker_transfer", "post", "", 1);
				$form_container_event = new FormContainer("Events importieren");
				echo $form_event->generate_hidden_field("transfer_type", "events");

				$form_container_event->output_row(
					"Events",
					"Es wurden <strong>{$event_count}</strong> Events im Mybb Kalender gefunden, wenn du willst kannst du sie hier automatisch in den Kalender des Szenentrackers übertragen.<br><br>Übernommen werden: <br>
					<ul>
						<li>Name</li>
						<li>Beschreibung</li>
						<li>Start- und Enddatum</li>
						<li>öffentlich / privat</li>
						<li>Wiederholungen</li>
					</ul>",
					"",
					"type"
				);
				$form_container_event->end();

				$button_event = array($form_event->generate_submit_button("Events übertragen"));
				$form_event->output_submit_wrapper($button_event);

				$form_event->end();
			}

			$page->output_footer();

			exit;
		}
	}
}

/**
 * Überträgt Events aus dem MyBB Standardkalender
 * in den Szenentracker Kalender.
 *
 * @return array Anzahl importierter / übersprungener Events
 */
function scenetracker_transfer_calendar_events()
{
	global $db;

	$result = array(
		"imported" => 0,
		"skipped" => 0,
		"warnings" => array()
	);

	if (!$db->table_exists("events")) {
		$result["warnings"][] = "Die MyBB Event-Tabelle existiert nicht.";
		return $result;
	}

	$query = $db->simple_select("events", "*", "visible = 1", array("order_by" => "starttime", "order_dir" => "ASC"));
	while ($event = $db->fetch_array($query)) {

		//datum
		$startdate = new DateTime();
		$startdate->setTimestamp((int)$event["starttime"]);

		$enddate = new DateTime();
		$enddate->setTimestamp((int)$event["endtime"]);

		$startdate_string = $startdate->format("Y-m-d");
		$enddate_string = $enddate->format("Y-m-d");

		/*
		 * -----------------------------------------------------
		 * Wiederholung
		 *
		 * Szenentracker:
		 *
		 * 0 = keine
		 * 1 = wöchentlich
		 * 2 = monatlich
		 * 3 = quartalsweise
		 * 4 = halbjährlich
		 * 5 = jährlich
		 * -----------------------------------------------------
		 */
		$recurring = 0;
		$recurring_occurrence = 0;
		$recurring_weekday = 0;
		$recurring_month = 0;

		$repeat_data = @unserialize($event["repeats"]);

		if (!is_array($repeat_data)) {
			$repeat_data = array();
		}

		$mybb_repeat = isset($repeat_data["repeats"]) ? (int)$repeat_data["repeats"] 	: 0;

		//Jede X Wochen
		if ($mybb_repeat === 3) {
			$weeks = isset($repeat_data["weeks"]) ? (int)$repeat_data["weeks"] : 1;
			//jede Woche
			if ($weeks === 1) {
				$recurring = 1;
			} else {
				$result["warnings"][] = "Event \"{$event["name"]}\": Wiederholung alle {$weeks} Wochen konnte nicht exakt übernommen werden.";
			}
		}
		//monatliche wiederholungen
		elseif ($mybb_repeat === 4) {

			$months = isset($repeat_data["months"]) 	? (int)$repeat_data["months"] 	: 1;

			if ($months === 1) {
				$recurring = 2;
			} elseif ($months === 3) {
				$recurring = 3;
			} elseif ($months === 6) {
				$recurring = 4;
			} elseif ($months === 12) {
				$recurring = 5;
			} else {
				$result["warnings"][] =	"Event \"{$event["name"]}\": Wiederholung alle {$months} Monate konnte nicht exakt übernommen werden.";
			}

			// Der Szenentracker Sonderfall unterstützt momentan nur - jährliche Wiederholungen.
			if (isset($repeat_data["occurance"])) {
				if ($months === 12) {
					$recurring = 5;
					$recurring_occurrence = scenetracker_transfer_mybb_occurrence($repeat_data["occurance"]);
					$recurring_weekday = scenetracker_transfer_mybb_weekday($repeat_data["weekday"]);
					$recurring_month = (int)$startdate->format("n");
				} else {
					$recurring = 0;
					$result["warnings"][] =	"Event \"{$event["name"]}\": Spezialwiederholung innerhalb mehrerer Monate konnte nicht übernommen werden.";
				}
			}
		}

		//jährliche wiederholung
		elseif ($mybb_repeat === 5) {

			$years = isset($repeat_data["years"])	? (int)$repeat_data["years"] : 1;

			//der szenentracker unterstützt nur jährlich nicht so etwas wie alle 3 Jahre
			if ($years === 1) {
				$recurring = 5;
				//Unsere sonderfälle 
				if (isset($repeat_data["occurance"])) {
					$recurring_occurrence = scenetracker_transfer_mybb_occurrence($repeat_data["occurance"]);
					$recurring_weekday = scenetracker_transfer_mybb_weekday($repeat_data["weekday"]);

					$recurring_month = isset($repeat_data["month"]) ? (int)$repeat_data["month"] : (int)$startdate->format("n");
				}
			} else {
				$result["warnings"][] = "Event \"{$event["name"]}\": Wiederholung alle {$years} Jahre konnte nicht exakt übernommen werden.";
			}
		} elseif ($mybb_repeat === 1) {
			//Wiederholungen Mybb, die nicht im szenentracker unterstützt werden
			// alle X Tage
			$days = isset($repeat_data["days"]) ? (int)$repeat_data["days"] : 0;
			// 7 Tage können wir als wöchentlich behandeln.
			if ($days === 7) {
				$recurring = 1;
			} else {
				$result["warnings"][] = "Event \"{$event["name"]}\": Wiederholung alle {$days} Tage konnte nicht übernommen werden.";
			}
		} elseif ($mybb_repeat === 2) {
			// nur Werktage
			$result["warnings"][] = "Event \"{$event["name"]}\": Wiederholung an Werktagen konnte nicht übernommen werden.";
		}

		//wurde das event bereits übertragen? Doppelungen abfangen
		$name_escaped = $db->escape_string($event["name"]);
		$existing = $db->fetch_field($db->simple_select("scenetracker_event", "id", "name = '{$name_escaped}' AND uid = " . (int)$event["uid"] . " AND DATE(startdate) = '{$startdate_string}'"), "id");

		if ($existing) {
			$result["skipped"]++;
			continue;
		}

		//speichern
		$save = array(
			"name" => $db->escape_string($event["name"]),
			"description" => $db->escape_string($event["description"]),
			"startdate" => $startdate_string,
			"enddate" => $enddate_string,
			"uid" => (int)$event["uid"],
			"private" => (int)$event["private"],
			"recurring" => $recurring,
			"recurring_occurrence" => $recurring_occurrence,
			"recurring_weekday" => $recurring_weekday,
			"recurring_month" => $recurring_month
		);

		$db->insert_query("scenetracker_event", $save);
		$result["imported"]++;
	}

	return $result;
}

/**
 * MyBB occurrence in unser Format übersetzen.
 *
 * @param mixed $occurrence
 * @return int
 */
function scenetracker_transfer_mybb_occurrence($occurrence)
{
	if ($occurrence === "last") {
		return -1;
	}

	$occurrence = (int)$occurrence;

	if ($occurrence >= 1 && $occurrence <= 5) {
		return $occurrence;
	}

	return 0;
}
/**
 * MyBB Wochentag in DateTime::format("N") umrechnen.
 * 
 * @param int $weekday
 * @return int
 */
function scenetracker_transfer_mybb_weekday($weekday)
{
	$weekday = (int)$weekday;
	if ($weekday === 0) {
		return 7;
	}
	if ($weekday >= 1 && $weekday <= 6) {
		return $weekday;
	}
	return 0;
}

$plugins->add_hook("admin_rpgstuff_update_plugin", "scenetracker_admin_update_plugin");
// scenetracker_admin_update_plugin
function scenetracker_admin_update_plugin(&$table)
{
	global $db, $mybb, $lang;

	$lang->load("rpgstuff_plugin_updates");

	// UPDATE KRAM
	// Update durchführen
	if ($mybb->input['action'] == "add_update" and $mybb->get_input("plugin") == "scenetracker") {
		//templates bearbeiten wenn nötig
		require_once MYBB_ROOT . "inc/plugins/risuena_updates/risuena_updatefile.php";
		$update_template_all = scenetracker_updated_templates();
		risuenaupdatefile_replace_templates($update_template_all);
		$scenetracker_templates = scenetracker_templates();
		//Master templates ersetzen
		risuenaupdatefile_sync_master_templates($scenetracker_templates);
		//Settings updaten
		scenetracker_add_settings("update");
		//Master Templates aktualisieren
		rebuild_settings();

		//templates hinzufügen
		scenetracker_add_templates("update");

		//Datenbank updaten
		scenetracker_database("update");

		//Stylesheets Updaten wen nötig - hinzufügen von neuem css
		//array mit updates bekommen.
		$update_data_all = scenetracker_stylesheet_update();
		require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

		//alle Themes bekommen in der es die scenetracker.css gibt - sonst erbt sie von irgendwo
		$theme_query = $db->simple_select("themestylesheets", "*", "name='scenetracker.css'");
		// $theme_query = $db->simple_select('themes', 'tid, name');

		//styles durchgehen

		while ($theme = $db->fetch_array($theme_query)) {
			//schauen ob es csss zum updaten gibt
			$update_data_all = scenetracker_stylesheet_update();

			//array durchgehen mit eventuell hinzuzufügenden strings
			foreach ($update_data_all as $update_data) {
				//hinzuzufügegendes css
				$update_stylesheet = $update_data['stylesheet'];
				//String bei dem getestet wird ob er im alten css vorhanden ist
				$update_string = $update_data['update_string'];
				//updatestring darf nicht leer sein
				if (!empty($update_string)) {
					//checken ob updatestring in css vorhanden ist - dann muss nichts getan werden
					$test_ifin = $db->write_query("SELECT stylesheet FROM " . TABLE_PREFIX . "themestylesheets WHERE tid = '{$theme['tid']}' AND name = 'scenetracker.css' AND stylesheet LIKE '%" . $update_string . "%' ");
					//string war nicht vorhanden
					if ($db->num_rows($test_ifin) == 0) {
						//altes css holen
						$oldstylesheet = $theme['stylesheet'];
						//Hier basteln wir unser neues array zum update und hängen das neue css hinten an das alte dran
						$updated_stylesheet = array(
							"cachefile" => $db->escape_string('scenetracker.css'),
							"stylesheet" => $db->escape_string($oldstylesheet . "\n\n" . $update_stylesheet),
							"lastmodified" => TIME_NOW
						);
						$themename = $db->fetch_field($db->simple_select("themes", "name", "tid = '{$theme['tid']}'"), "name");

						$db->update_query("themestylesheets", $updated_stylesheet, "name='scenetracker.css' AND tid = '{$theme['tid']}'");
						echo "Im Theme {$themename}(ID:{$theme['tid']})  wurde CSS hinzugefügt -  '$update_string' <br>";
					}
				}
				update_theme_stylesheet_list($theme['tid']);
			}
		}
	}

	// Zelle mit dem Namen des Themes
	$table->construct_cell("<b>" . htmlspecialchars_uni("Szenentracker") . "</b>", array('width' => '70%'));

	// Überprüfen, ob Update nötig ist 
	$update_check = scenetracker_is_updated();

	if ($update_check) {
		$table->construct_cell($lang->plugins_actual, array("class" => "align_center"));
	} else {
		$table->construct_cell("<a href=\"index.php?module=rpgstuff-plugin_updates&action=add_update&plugin=scenetracker\">" . $lang->plugins_update . "</a>", array("class" => "align_center"));
	}

	$table->construct_row();
}

/**
 * Funktion um CSS nachträglich oder nach einem MyBB Update wieder hinzuzufügen
 */
$plugins->add_hook("admin_rpgstuff_update_stylesheet", "scenetracker_admin_update_stylesheet");
function scenetracker_admin_update_stylesheet(&$table)
{
	global $db, $mybb, $lang;

	$lang->load("rpgstuff_stylesheet_updates");

	require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

	// HINZUFÜGEN
	if ($mybb->input['action'] == "add_master" and $mybb->get_input("plugin") == "scenetracker") {

		$css = scenetracker_stylesheet();

		$sid = $db->insert_query("themestylesheets", $css);
		$db->update_query("themestylesheets", array("cachefile" => "scenetracker.css"), "sid = '" . $sid . "'", 1);

		$tids = $db->simple_select("themes", "tid");
		while ($theme = $db->fetch_array($tids)) {
			update_theme_stylesheet_list($theme['tid']);
		}

		flash_message($lang->stylesheets_flash, "success");
		admin_redirect("index.php?module=rpgstuff-stylesheet_updates");
	}

	// Zelle mit dem Namen des Themes
	$table->construct_cell("<b>" . htmlspecialchars_uni("Szenentracker-Manager") . "</b>", array("width" => "70%"));

	// Ob im Master Style vorhanden
	$master_check = $db->query("SELECT tid FROM " . TABLE_PREFIX . "themestylesheets 
    WHERE name = 'scenetracker.css' 
    AND tid = 1");

	if ($db->num_rows($master_check) > 0) {
		$masterstyle = true;
	} else {
		$masterstyle = false;
	}

	if (!empty($masterstyle)) {
		$table->construct_cell($lang->stylesheets_masterstyle, array("class" => "align_center"));
	} else {
		$table->construct_cell("<a href=\"index.php?module=rpgstuff-stylesheet_updates&action=add_master&plugin=scenetracker\">" . $lang->stylesheets_add . "</a>", array("class" => "align_center"));
	}
	$table->construct_row();
}

/**
 * Aktueller Stylesheet
 * @param int $themeid des themes das hinzugefügt werden soll. Default: 1 -> Masterstylesheet
 * @return array - css array zum eintragen in die db
 */
function scenetracker_stylesheet($themeid = 1)
{
	global $db;
	$css = array(
		'name' => 'scenetracker.css',
		'tid' => $themeid,
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

        .scene_ucp.scenefilteroptions {
          display: flex;
          flex-wrap: wrap;
          justify-content: center;
          gap: 10px;
        }

        .scene_ucp.scenefilteroptions h2 {
          width: 100%
        }

        .scenefilteroptions__items {
          width: 32%;
        }

        .scene_ucp.container {
          box-sizing: border-box;
        }

        fieldset.scenefilteroptions__items {
          box-sizing: border-box;
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

        .calendar-container .calendar {
          background-color: var(--background-light);
          padding-left: 5px;
          padding: 5px;
          border: 1px solid var(--background-dark);
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

        .day.st_mini_scene.lastmonth {
          opacity: 0.1;
        }
        /* calendar-update - kommentar nicht entfernen */
        .scenetracker_cal_setting {
          width: 100%;
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
        }
        /* update-userfilter - kommentar nicht entfernen */
        .scenefilteroptions__items.button {
            text-align: center;
            width: 100%;
        }

									/* update-st-calender - kommentar nicht entfernen */

					.st-calendar *, .st-calendar-mini * {
							box-sizing: border-box;
					}

					.st-calendar {
						box-sizing: border-box;
						padding: 20px;
					}

					.st-calendar__monthtitle {
						text-align: center;
						display: flex;
						justify-content: center;
						gap: 10px;
						font-size: 1.2em;
					}

					.st-calendar__day {
						min-height: 50px;
						border: 1px solid rgb(204, 204, 204);
						width: 100%;
						padding: 5px;
					}
					.st-calendar__weekdays {
						width: 100%;
					}

					.st-calendar__day--title {
						min-height: initial;
						text-align: center;
					}

					.st-calendar__month {
						display: grid;
						grid-template-columns: repeat(7, 1fr);
						justify-items: end;
					}

					.st-calendar__day-title {
						justify-self: center;
					}

					.st-calendar__day.other {
						opacity: 0.3;
					}
					.modal .st_calender__popup {
						display: flex;
						flex-direction: column;
						gap: 10px;
						padding: 10px;
						max-height: 450px;
						overflow: auto;
					}

					.st-calendar__daybit {
						display: flex;
						flex-direction: column;
						gap: 2px;
					}

					.modal.st-calendar__event-mini {
							text-align: center;
							margin: auto;
							max-height: 500px;
					}


					.st-calendar__event:has(.st-popinfo) {
						cursor: pointer;
							position: relative;
					}

					.st-popinfo {
						padding: 10px;
						width: 200px;
						max-height: 200px;
						position: absolute;   /* <-- dadurch kein Platz im Layout */
						top: 100%;
						left: 50%;
						transform: translateX(-50%) translateY(-10%);
						opacity: 0;
						visibility: hidden;
						pointer-events: none;
						transition: opacity .15s ease;
						z-index: 1000;
						background: #fff;
						vertical-align: middle;
						-webkit-box-sizing: border-box;
						-moz-box-sizing: border-box;
						box-sizing: border-box;
						-webkit-border-radius: 8px;
						-moz-border-radius: 8px;
						-o-border-radius: 8px;
						-ms-border-radius: 8px;
						border-radius: 8px;
						-webkit-box-shadow: 0 0 10px #000;
						-moz-box-shadow: 0 0 10px #000;
						-o-box-shadow: 0 0 10px #000;
						-ms-box-shadow: 0 0 10px #000;
						box-shadow: 0 0 10px #000;
					}

					.st-calendar__event:has(.st-popinfo):hover .st-popinfo {
							opacity: 1;
							visibility: visible;
							pointer-events: auto;
					}

					.st-popinfo .st_calender__popup {
							max-height:185px;
							overflow:auto;
					}
					.st-ucp-events__views {
							display: flex;
							justify-content: space-between;
					}
    ',
		'cachefile' => $db->escape_string(str_replace('/', '', 'scenetracker.css')),
		'lastmodified' => time()
	);
	return $css;
}

/**
 * Stylesheet der eventuell hinzugefügt werden muss
 */
function scenetracker_stylesheet_update()
{
	// Update-Stylesheet
	// wird an bestehende Stylesheets immer ganz am ende hinzugefügt
	//arrays initialisieren
	$update_array_all = array();

	//array für css welches hinzugefügt werden soll - neuer eintrag in array für jedes neue update
	$update_array_all[] = array(
		'stylesheet' => "
    /* calendar-update - kommentar nicht entfernen */
    .scenetracker_cal_setting {
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
      }",
		'update_string' => 'calendar-update'
	);

	$update_array_all[] = array(
		'stylesheet' => "
      /* update-userfilter - kommentar nicht entfernen */
        .scenefilteroptions__items.button {
            text-align: center;
            width: 100%;
        }
    ",
		'update_string' => 'update-userfilter'
	);

	$update_array_all[] = array(
		'stylesheet' => "
      /* update-plots - kommentar nicht entfernen */
        .scenetracker-plots-tabs {
					display: flex;
					flex-wrap: wrap;
					gap: 5px;
				}

				.scenetracker-plots-tab {
					display: inline-block;
					padding: 6px 12px;
					text-decoration: none;
				}

				.scenetracker-plots-tab.active {
					font-weight: bold;
				}

				.scenetracker-plot-meta {
					margin-bottom: 10px;
				}

				.scenetracker-plot-description {
					margin-top: 10px;
				}

				.st-calendar__weekdays {
					width: 100%;
				}
    ",
		'update_string' => 'update-plots'
	);

	$update_array_all[] = array(
		'stylesheet' => "
					/* update-st-calender - kommentar nicht entfernen */

					.st-calendar *, .st-calendar-mini * {
							box-sizing: border-box;
					}

					.st-calendar {
						box-sizing: border-box;
						padding: 20px;
					}

					.st-calendar__monthtitle {
						text-align: center;
						display: flex;
						justify-content: center;
						gap: 10px;
						font-size: 1.2em;
					}

					.st-calendar__day {
						min-height: 50px;
						border: 1px solid rgb(204, 204, 204);
						width: 100%;
						padding: 5px;
					}
					.st-calendar__weekdays {
						width: 100%;
					}

					.st-calendar__day--title {
						min-height: initial;
						text-align: center;
					}

					.st-calendar__month {
						display: grid;
						grid-template-columns: repeat(7, 1fr);
						justify-items: end;
					}

					.st-calendar__day-title {
						justify-self: center;
					}

					.st-calendar__day.other {
						opacity: 0.3;
					}
					.modal .st_calender__popup {
						display: flex;
						flex-direction: column;
						gap: 10px;
						padding: 10px;
						max-height: 450px;
						overflow: auto;
					}

					.st-calendar__daybit {
						display: flex;
						flex-direction: column;
						gap: 2px;
					}

					.modal.st-calendar__event-mini {
							text-align: center;
							margin: auto;
							max-height: 500px;
					}


					.st-calendar__event:has(.st-popinfo) {
						cursor: pointer;
							position: relative;
					}

					.st-popinfo {
						padding: 10px;
						width: 200px;
						max-height: 200px;
						position: absolute;   /* <-- dadurch kein Platz im Layout */
						top: 100%;
						left: 50%;
						transform: translateX(-50%) translateY(-10%);
						opacity: 0;
						visibility: hidden;
						pointer-events: none;
						transition: opacity .15s ease;
						z-index: 1000;
						background: #fff;
						vertical-align: middle;
						-webkit-box-sizing: border-box;
						-moz-box-sizing: border-box;
						box-sizing: border-box;
						-webkit-border-radius: 8px;
						-moz-border-radius: 8px;
						-o-border-radius: 8px;
						-ms-border-radius: 8px;
						border-radius: 8px;
						-webkit-box-shadow: 0 0 10px #000;
						-moz-box-shadow: 0 0 10px #000;
						-o-box-shadow: 0 0 10px #000;
						-ms-box-shadow: 0 0 10px #000;
						box-shadow: 0 0 10px #000;
					}

					.st-calendar__event:has(.st-popinfo):hover .st-popinfo {
							opacity: 1;
							visibility: visible;
							pointer-events: auto;
					}

					.st-popinfo .st_calender__popup {
							max-height:185px;
							overflow:auto;
					}
					.st-ucp-events__views {
							display: flex;
							justify-content: space-between;
					}
    ",
		'update_string' => 'update-st-calender'
	);

	return $update_array_all;
}

/**
 * Settingarray
 * @return array - array mit den einstellungen
 */
function scenetracker_settingarray()
{
	$setting_array = array(
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
			'description' => 'Bitte Ingamezeitraum eingeben - Monat und Jahr. Bei mehreren mit , getrennt. Z.b für April, Juni und Juli "1997-04, 1997-06, 1997-07. <b>Achtung genauso wie im Beispiel!</b> (Wichtig für Minikalender)."',
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
		'scenetracker_calendar_mini_eventview' => array(
			'title' => 'Minikalender Eventanzeige',
			'description' => 'Sollen die Events als Modal oder als Hovercard angezeigt werden?',
			'optionscode' => "select\n0=Modal\n1=Hovercard",
			'value' => '0', // Default
			'disporder' => 17
		),
		'scenetracker_plots_member_public' => array(
			'title' => 'Chara Events',
			'description' => 'Können User Events/Ereignisse für ihre Charaktere eintragen und sie auf öffentlich stellen?',
			'optionscode' => "yesno",
			'value' => '0', // Default
			'disporder' => 18
		),
		'scenetracker_plots_member_public_view' => array(
			'title' => 'Chara Events',
			'description' => 'Sollen diese auch im offzielen Ingamekalender angezeigt werden oder nur auf ihrem Profil?',
			'optionscode' => "select\n0=Profil\n1=Kalender",
			'value' => '0', // Default
			'disporder' => 19
		),
		'scenetracker_time_text' => array(
			'title' => 'Angabe Tageszeit',
			'description' => 'Soll das Datum für eine Szene mit fester Zeit (Datum + Zeit z.B. 24.02.01 - 11:00) oder mit offener Zeit, als Textfenster (z.B. Mittags) angegeben werden?',
			'optionscode' => "select\n0=feste Zeit\n1=offene Texteingabe",
			'value' => '0', // Default
			'disporder' => 20
		),
		// 'scenetracker_time_period' => array(
		// 	'title' => 'Angabe Zeit - Zeitraum',
		// 	'description' => 'Soll es möglich sein, einen Zeitraum anzugeben (z.B. 24.02.01 - 11:00 bis 24.02.01 - 12:00) oder nur ein Zeitpunkt (z.B. 24.02.01 - 11:00)?',
		// 	'optionscode' => "select\n0=feste Zeit\n1=offene Texteingabe",
		// 	'value' => '0', // Default
		// 	'disporder' => 21
		// ),
		// 'scenetracker_time_period_open_end' => array(
		// 	'title' => 'Angabe Zeit - Zeitraum mit offenen Ende',
		// 	'description' => 'Soll es möglich sein das einer Szene auch als offen einzustellen (zum Beispiel für aktive Mailverläufe)?',
		// 	'optionscode' => "select\n0=feste Zeit\n1=offene Texteingabe",
		// 	'value' => '0', // Default
		// 	'disporder' => 22
		// ),
		'scenetracker_forumbit' => array(
			'title' => 'Anzeige Mini-Kalender?',
			'description' => "Soll der Mini-Kalender über dem Ingame angezeigt werden? Dann die FID des Ingames eingeben, sonst 0. In forumbit_depth1_cat &lbrace;&dollar;forum[&apos;minicalender&apos;]&rbrace; hinzufügen.",
			'optionscode' => "numeric",
			'value' => '0', // Default
			'disporder' => 23
		),
		'scenetracker_filterusername_yesno' => array(
			'title' => 'Nach Usernamen filtern?',
			'description' => "Sollen User im UCP die Szenen auch nach Spielern filtern können?",
			'optionscode' => "yesno",
			'value' => 'yes', // Default
			'disporder' => 24
		),
		'scenetracker_filterusername_typ' => array(
			'title' => 'Feldtyp für Username?',
			'description' => "Wo wird der Username gespeichert?",
			'optionscode' => "select\n0=Profilfeld\n1=Steckbriefplugin",
			'value' => '0', // Default
			'disporder' => 25
		),
		'scenetracker_filterusername_id' => array(
			'title' => 'ID/Bezeichner?',
			'description' => "Gib hier bei Profilfeld die ID und beim Steckbriefplugin den Bezeichner ein.",
			'optionscode' => "text",
			'value' => '0', // Default
			'disporder' => 26
		),
		'scenetracker_export_word' => array(
			'title' => 'Word: Exportieren von Szenen',
			'description' => "Soll man Szenen als Worddatei exportieren können?",
			'optionscode' => "yesno",
			'value' => 'yes', // Default
			'disporder' => 27
		),
		'scenetracker_export_word_all' => array(
			'title' => 'Exportieren von Szenen - Nur Teilnehmer?',
			'description' => "Dürfen nur Teilnehmer ihre Szenen als word datei exportieren? (Bei Nein, dürfen alle)",
			'optionscode' => "yesno",
			'value' => 'yes', // Default
			'disporder' => 28
		),
		'scenetracker_export_pdf' => array(
			'title' => 'PDF: Exportieren von Szenen',
			'description' => "Soll man Szenen als PDF exportieren können?",
			'optionscode' => "yesno",
			'value' => 'yes', // Default
			'disporder' => 29
		),
		'scenetracker_export_pdf_all' => array(
			'title' => 'Exportieren von Szenen - Nur Teilnehmer?',
			'description' => "Dürfen nur Teilnehmer ihre Szenen als pdf exportieren? (Bei Nein, dürfen alle)",
			'optionscode' => "yesno",
			'value' => 'yes', // Default
			'disporder' => 30
		),
		// 'scenetracker_categories' => array(
		// 	'title' => 'Verwenden von Kategorienn?',
		// 	'description' => "Möchtest du verschiedene Kategorien für Szenen verwenden? (z.B. Ingame, Communication)",
		// 	'optionscode' => "yesno",
		// 	'value' => 'no', // Default
		// 	'disporder' => 31
		// ),
		'scenetracker_plotuse' => array(
			'title' => 'Plots über den Szenentracker?',
			'description' => "Möchtest du Plots über den Szenentracker anlegen und verwalten können?",
			'optionscode' => "yesno",
			'value' => 'yes', // Default
			'disporder' => 31
		),
		// 'scenetracker_count' => array(
		// 	'title' => 'Szenen zählen',
		// 	'description' => "Nach welcher Art sollen die Szenen der User/Charas gezählt werden, bzw. wie soll bestimmt werden, ob der Charakter gerade dran ist oder nicht.",
		// 	'optionscode' => "select\n0=Wie Index-Alert Einstellung\n1=Wie Alert-Einstellung\n2=Nach Reihenfogle im Teilnehmerfeld",
		// 	'value' => '2', // Default
		// 	'disporder' => 32
		// ),

	);
	return $setting_array;
}

/**
 * Settings hinzufügen oder updaten
 */
function scenetracker_add_settings($type = 'install')
{
	global $db;

	if ($type == 'install') {
		// Admin Einstellungen
		$setting_group = array(
			'name' => 'scenetracker',
			'title' => 'Szenentracker',
			'description' => 'Einstellungen für Risuenas Szenentracker.<br/> <b>Achtung</b> Bitte die Infos in der Readme beachten um Szenen im Kalender angezeigt zu bekommen.',
			'disporder' => 7, // The order your setting group will display
			'isdefault' => 0
		);
		$gid = $db->insert_query("settinggroups", $setting_group);
	} else {
		$gid = $db->fetch_field($db->write_query("SELECT gid FROM `" . TABLE_PREFIX . "settinggroups` WHERE name like 'scenetracker%' LIMIT 1;"), "gid");
	}

	$setting_array = scenetracker_settingarray();

	if ($type == 'install') {
		foreach ($setting_array as $name => $setting) {
			$setting['name'] = $name;
			$setting['gid'] = $gid;
			$db->insert_query('settings', $setting);
		}
	}
	if ($type == 'update') {
		require_once MYBB_ROOT . "inc/plugins/risuena_updates/risuena_updatefile.php";
		risuenaupdatefile_update_settings($setting_array, "scenetracker");
	}
	rebuild_settings();
}

/**
 * Liefert die DB Struktur der Tabellen zurück, damit diese für die Install Routine oder Update Dateien genutzt werden kann
 * @return array - DB Struktur der Tabellen
 */
function scenetracker_db_schema()
{
	global $db;
	return [
		"scenetracker" => [
			"create" => true,
			"fields" => [
				"id" => "int(10) NOT NULL AUTO_INCREMENT",
				"uid" => "int(10) NOT NULL",
				"tid" => "int(10) NOT NULL",
				"alert" => "int(1) NOT NULL DEFAULT 0",
				"type" => "varchar(50) NOT NULL DEFAULT 'always'",
				"inform_by" => "int(10) NOT NULL DEFAULT 0",
				"type_alert" => "varchar(50) NOT NULL DEFAULT 'always'",
				"type_alert_inform_by" => "int(10) NOT NULL DEFAULT 0",
				"index_view_reminder" => "int(1) NOT NULL DEFAULT 1",
				"index_view_reminder_days" => "int(10) NOT NULL DEFAULT 0",
				"profil_view" => "int(1) NOT NULL DEFAULT 1",
				"category_id" => "int(10) NOT NULL DEFAULT 0"
			],
			"index" => [
				"uid",
				"tid"
			],
			"primary" => "id",
			"engine" => "InnoDB",
			"drop_unused" => true
		],
		"scenetracker_categories" => [
			"create" => true,
			"fields" => [
				"id" => "int(10) NOT NULL AUTO_INCREMENT",
				"categoryname" => "int(10) NOT NULL",
				"count" => "int(1) NOT NULL DEFAULT 1",
			],
			"primary" => "id",
			"engine" => "InnoDB",
			"drop_unused" => true
		],
		"scenetracker_plots" => [
			"create" => true,
			"fields" => [
				"id" => "int(10) NOT NULL AUTO_INCREMENT",
				"image" => "varchar(200) NOT NULL DEFAULT ''",
				"name" => "varchar(200)  NOT NULL DEFAULT ''",
				"description" => "longtext NOT NULL DEFAULT ''",
				"summary" => "text NOT NULL DEFAULT ''",
				"calendar" => "int(1) NOT NULL DEFAULT 1",
				"startdate" => "DATE NULL DEFAULT NULL",
				"enddate" => "DATE NULL DEFAULT NULL",
				"category" => "varchar(200) NOT NULL DEFAULT ''",
				"active" => "int(1) NOT NULL DEFAULT 1",
			],
			"primary" => "id",
			"engine" => "InnoDB",
			"drop_unused" => true
		],
		"scenetracker_event" => [
			"create" => true,
			"fields" => [
				"id" => "int(10) NOT NULL AUTO_INCREMENT",
				"name" => "varchar(200) NOT NULL DEFAULT ''",
				"description" => "text NOT NULL DEFAULT ''",
				"startdate" => "DATE NULL DEFAULT NULL",
				"enddate" => "DATE NULL DEFAULT NULL",
				"uid" => "int(10) NOT NULL DEFAULT 0",
				"private" => "int(1) NOT NULL DEFAULT 0",

				// 0 = keine, 1 = wöchentlich, 2 = monatlich,
				// 3 = quartalsweise, 4 = jährlich
				"recurring" => "int(1) NOT NULL DEFAULT 0",
				"recurring_interval" => "int(10) NOT NULL DEFAULT 0",

				// Sonderregel wie "3. Montag"
				"recurring_weekday" => "int(1) NOT NULL DEFAULT 0",
				"recurring_occurrence" => "int(1) NOT NULL DEFAULT 0",
				"recurring_month" => "int(2) NOT NULL DEFAULT 0",
				"official" => "int(1) NOT NULL DEFAULT 0",
			],
			"primary" => "id",
			"engine" => "InnoDB",
			"drop_unused" => true
		],
		"scenetracker_plots_categories" => [
			"create" => true,
			"fields" => [
				"id" => "int(10) NOT NULL AUTO_INCREMENT",
				"name" => "varchar(200) NOT NULL DEFAULT ''",
				"summary" => "varchar(500) NOT NULL DEFAULT ''",
				"description" => "text NOT NULL DEFAULT ''",
				"sort" => "int(10) NOT NULL DEFAULT 0",
			],
			"primary" => "id",
			"engine" => "InnoDB",
			"drop_unused" => true
		],
		"scenetracker_plots_tids" => [
			"create" => true,
			"fields" => [
				"id" => "int(10) NOT NULL AUTO_INCREMENT",
				"tid" => "int(10) NOT NULL",
				"plot_id" => "int(10) NOT NULL"
			],
			"primary" => "id",
			"indexes" => [
				[
					"columns" => ["tid"],
					"unique" => true
				]
			],
			"engine" => "InnoDB",
			"drop_unused" => true,
			"drop_unused_indexes" => true
		],
	];
}

function scenetracker_added_dbfields()
{
	global $db;
	$fields[] = array(
		"tablename" => 'threads',
		"fieldname" => 'scenetracker_date',
		"definition" => "DATETIME NULL DEFAULT NULL",
	);
	$fields[] = array(
		"tablename" => 'threads',
		"fieldname" => 'scenetracker_time_text',
		"definition" => "varchar(200) NOT NULL DEFAULT ''",
	);
	$fields[] = array(
		"tablename" => 'threads',
		"fieldname" => 'scenetracker_place',
		"definition" => "varchar(200) NOT NULL DEFAULT ''",
	);
	$fields[] = array(
		"tablename" => 'threads',
		"fieldname" => 'scenetracker_user',
		"definition" => "varchar(1500) NOT NULL DEFAULT ''",
	);
	$fields[] = array(
		"tablename" => 'threads',
		"fieldname" => 'scenetracker_trigger',
		"definition" => "varchar(200) NOT NULL DEFAULT ''",
	);
	$fields[] = array(
		"tablename" => 'users',
		"fieldname" => 'tracker_index',
		"definition" => "INT(1) NOT NULL DEFAULT '1'",
	);
	$fields[] = array(
		"tablename" => 'users',
		"fieldname" => 'tracker_indexall',
		"definition" => "INT(1) NOT NULL DEFAULT '1'",
	);
	$fields[] = array(
		"tablename" => 'users',
		"fieldname" => 'tracker_reminder',
		"definition" => "INT(1) NOT NULL DEFAULT '1'",
	);
	return $fields;
}

/**
 * Add / Update Database
 */
function scenetracker_database($type = 'install')
{
	global $db;
	require_once MYBB_ROOT . "inc/plugins/risuena_updates/risuena_updatefile.php";
	//installiert oder updated die nötigen Tabellen
	risuenaupdatefile_sync_table(scenetracker_db_schema());
	risuenaupdatefile_check_fields(scenetracker_added_dbfields(), true);
}

/**
 * Templates hinzufügen
 */
function scenetracker_add_templates($type = 'install')
{
	global $db;

	require_once MYBB_ROOT . "inc/plugins/risuena_updates/risuena_updatefile.php";
	$templates = array();
	//add templates and stylesheets
	// Add templategroup
	//templategruppe nur beim installieren hinzufügen
	if ($type == "install") {
		$templategrouparray = array(
			"prefix" => "scenetracker",
			"title"  => $db->escape_string("Szenentracker"),
			"isdefault" => 1
		);
		$db->insert_query("templategroups", $templategrouparray);
	}
	$templates = scenetracker_templates();
	//nur template hinzufügen, wenn es im masterstyle nicht existiert
	foreach ($templates as $template) {
		$check = $db->num_rows($db->simple_select("templates", "title", "title = '" . $template['title'] . "' AND sid ='-2'"));
		if ($check == 0) {
			$db->insert_query("templates", $template);
		}
	}
}

/**
 * Array mit den Templates für den Szenentracker
 */
function scenetracker_templates()
{
	global $db;
	$templates = array();


	// $templates[] = array(
	// 	"title" => '',
	// 	"template" => $db->escape_string('

	// 	'),
	// 	"sid" => "-2",
	// 	"version" => "",
	// 	"dateline" => TIME_NOW
	// );

	// $templates[] = array(
	// 	"title" => '',
	// 	"template" => $db->escape_string('

	// 	'),
	// 	"sid" => "-2",
	// 	"version" => "",
	// 	"dateline" => TIME_NOW
	// );

	// $templates[] = array(
	// 	"title" => '',
	// 	"template" => $db->escape_string('

	// 	'),
	// 	"sid" => "-2",
	// 	"version" => "",
	// 	"dateline" => TIME_NOW
	// );

	// $templates[] = array(
	// 	"title" => '',
	// 	"template" => $db->escape_string('

	// 	'),
	// 	"sid" => "-2",
	// 	"version" => "",
	// 	"dateline" => TIME_NOW
	// );

	// $templates[] = array(
	// 	"title" => '',
	// 	"template" => $db->escape_string('

	// 	'),
	// 	"sid" => "-2",
	// 	"version" => "",
	// 	"dateline" => TIME_NOW
	// );

	$templates[] = array(
		"title" => 'scenetracker_profil_events_empty',
		"template" => $db->escape_string('
			<div class="st-profile-events__empty">
				Keine aktuellen oder kommenden Ereignisse vorhanden.
			</div>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_profil_events_bit',
		"template" => $db->escape_string('
				<div class="st-profile-event">

					<div class="st-profile-event__date">
						{$event_date}
					</div>

					<div class="st-profile-event__content">

						<div class="st-profile-event__name">
							{$event_name}
						</div>

						<div class="st-profile-event__description">
							{$event_description}
						</div>

						<div class="st-profile-event__recurring">
							{$event_recurring_text}
						</div>

						<div class="st-profile-event__visibility">
							{$event_visibility}
						</div>

					</div>

				</div>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_profil_events_year_end',
		"template" => $db->escape_string('
				</div>
			</div>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_profil_events_year_start',
		"template" => $db->escape_string('
			<div class="st-profile-events__year">

				<h4 class="st-profile-events__year-title">
					{$event_year}
				</h4>

				<div class="st-profile-events__list">
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_profil_events_archive',
		"template" => $db->escape_string('
			<details class="st-profile-events__past">
				<summary>
					Vergangene Ereignisse ({$past_count})
				</summary>

				<div class="st-profile-events__past-content">
					{$scenetracker_profil_events_past}
				</div>

			</details>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_profil_events',
		"template" => $db->escape_string('
			<div class="st-profile-events">

				<div class="st-profile-events__upcoming">

					<h3>Aktuelle und kommende Ereignisse</h3>

					{$scenetracker_profil_events_upcoming}

				</div>

				{$scenetracker_profil_events_archive}

			</div>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_plots_detail_scenes_empty',
		"template" => $db->escape_string('
			<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder" >
				<tr>
					<td class="trow1" align="center">
						Es wurden keine Szenen gefunden.
					</td>
				</tr>
			</table>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => "scenetracker_plots_detail_scene",
		"template" => $db->escape_string('
		<div class="scenetracker-plot-scene {$scene_status_class}">
			<div>
				<strong><a href="{$scene_link}">{$scene_subject}</a></strong>
				<span class="smalltext">({$scene_status})</span>
			</div>
			<div class="smalltext">{$scene_users}</div>
			<div class="smalltext">{$scene_date}</div>
			<div class="smalltext">{$scene_place}</div>
		</div>
	'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => "scenetracker_plots_detail_image",
		"template" => $db->escape_string('
			<img src="{$plot_image}" alt="{$plot_name}" style="max-width: 100%; height: auto;" />
	'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => "scenetracker_plots_detail",
		"template" => $db->escape_string('
				<html>
				<head>
					<title>{$mybb->settings[\'bbname\']} - {$plot_name}</title>
					{$headerinclude}
				</head>
				<body>
				{$header}

				<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
					<tr>
						<td class="thead" ><strong>{$plot_name}</strong></td>
					</tr>
					<tr>
						<td class="tcat" >
							{$plot_category}
							<span class="smalltext">
								&mdash; <span class="scenetracker-plot-status {$plot_status_class}">{$plot_status}</span>
							</span>
						</td>
					</tr>
					<tr>
					<td class="trow1" valign="top">
							{$plot_image_bit}
							<div class="scenetracker-plot-detail-meta">
								<strong>Zeitraum:</strong>
								{$plot_startdate} &ndash; {$plot_enddate}
							</div>
						
							<div class="scenetracker-plot-detail-description">
								{$plot_description}
							</div>
						</td>
					</tr>

				</table>

				<br />

				<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
					<tr>
						<td class="thead"><strong>Szenen</strong></td>
					</tr>
					<tr>
						<td class="trow1">
							{$plot_scenes}
						</td>
					</tr>
				</table>

				<br />

				<div style="text-align: right;">
					<a href="{$back_link}" class="button">&laquo; Zurück zur Plotübersicht</a>
				</div>

				{$footer}

				</body>
				</html>
	'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_title',
		"template" => $db->escape_string('
			<strong>{$day_array[\'day\']}.</strong>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_month',
		"template" => $db->escape_string('<div  class="st-calendar__month-wrap">
					<div class="st-calendar__monthtitle thead">
						{$prev}<strong>{$monthnames[$monthindex]} {$year}</strong>{$next}
					</div>
					<div class="st-calendar__month " style="grid-template-columns: repeat(7, 1fr);">
						<div class="st-calendar__day st-calendar__day--title tcat"><strong>M</strong></div>
						<div class="st-calendar__day st-calendar__day--title tcat"><strong>T</strong></div>
						<div class="st-calendar__day st-calendar__day--title tcat"><strong>W</strong></div>
						<div class="st-calendar__day st-calendar__day--title tcat"><strong>T</strong></div>
						<div class="st-calendar__day st-calendar__day--title tcat"><strong>F</strong></div>
						<div class="st-calendar__day st-calendar__day--title tcat"><strong>S</strong></div>
						<div class="st-calendar__day st-calendar__day--title tcat"><strong>S</strong></div>
						{$scenetracker_calendar_day}
					</div>
				</div>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_mini',
		"template" => $db->escape_string('<div class="st-calendar-mini calendar-container">{$scenetracker_calendar_month}</div>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_full',
		"template" => $db->escape_string('<html>
			<head>
				<title>{$mybb->settings[\'bbname\']} - Ingamekalender</title>
				{$headerinclude}
			</head>
			<body>
				{$header}
				<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
					<tr>
						<td class="thead" >{$prev}<strong>Ingamekalender</strong>{$next}</td>
					</tr>
					<tr>
						<td class="trow1">
							<div class="st-calendar ">
								{$scenetracker_calendar_month}
							</div>
						</td>
					</tr>
					<tr>
						<td class="trow1" align="right">
							<form action="misc.php" method="get" class="st-calendar__navigation">
								<input type="hidden" name="action" value="calendar">
								<span>
									<label for="month_sel">Monat</label>
									<select name="month" id="month_sel">
										{$option_month}
									</select>
								</span>
								<span>
									<label for="year_inp">Jahr</label>
									<input type="number" name="year" id="year_inp" value="{$year}" min="1" step="1">
								</span>
								<button type="submit" class="button">Monat wechseln</button>
							</form>
							{$calender_select}
							{$scenetracker_calendar_event_add_button}
						</td>
					</tr>
				</table>

				{$footer}
			</body>
			</html>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_ucp_events_calendar',
		"template" => $db->escape_string('
			<div class="st-ucp-calendar st-calendar"> 
				<div class="st-calendar__monthtitle thead">
					<a href="{$prev_link}">&laquo;</a>
					<strong>{$calendar_month_name}	{$calendar_year}</strong>
					<a href="{$next_link}">&raquo;</a>
				</div>
				<div class="st-calendar__month">
					<div class="st-calendar__day st-calendar__day--title tcat">Mo</div>
					<div class="st-calendar__day st-calendar__day--title tcat">Di</div>
					<div class="st-calendar__day st-calendar__day--title tcat">Mi</div>
					<div class="st-calendar__day st-calendar__day--title tcat">Do</div>
					<div class="st-calendar__day st-calendar__day--title tcat">Fr</div>
					<div class="st-calendar__day st-calendar__day--title tcat">Sa</div>
					<div class="st-calendar__day st-calendar__day--title tcat">So</div>
					{$scenetracker_calendar_day}
				</div>
			</div>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_ucp_events_empty',
		"template" => $db->escape_string('
			<div class="pm_alert">
				Für diese Auswahl wurden keine Events oder Geburtstage gefunden.
			</div>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_ucp_events_birthday_bit',
		"template" => $db->escape_string('
				<fieldset class="st-ucp-event st-ucp-event--birthday">
					<div class="st-ucp-event__title">
						<strong>{$character_name}</strong>
					</div>
					<div>
						Geburtstag: {$birthday_date}
					</div>
				</fieldset>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_ucp_events_bit',
		"template" => $db->escape_string('
				<fieldset class="st-ucp-event">
					<div class="st-ucp-event__title">
						<a href="{$event_detail_link}"><strong>{$event_name}</strong> </a>
					</div>
					<div class="smalltext">
						{$character_name}
					</div>
					<div class="st-ucp-event__date">
						{$event_date_output}
					</div>
					<div class="st-ucp-event__repeat">
						{$event_recurring}
					</div>
					<div class="st-ucp-event__visibility">
						{$event_visibility}
					</div>
					<button type="button" class="button" onclick="location.href=\'{$event_edit_link}\'">bearbeiten</button>
					<form method="post" action="misc.php?action=calendar&amp;do=delete_event&amp;src=ucp" style="display:inline;" onsubmit="return confirm(\'Möchtest du dieses Event wirklich löschen?\');">
						<input type="hidden" name="my_post_key" value="{$mybb->post_code}">
						<input type="hidden" name="id" value="{$event_id}">
						<button type="submit" class="button">löschen</button>
					</form>
				</fieldset>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_ucp_events',
		"template" => $db->escape_string('
				<html>
					<head>
						<title>{$mybb->settings[\'bbname\']} - Events</title>
						{$headerinclude}
					</head>
					<body>
						{$header}
						<table width="100%" border="0" align="center">
							<tr>
								{$usercpnav}
								<td valign="top">
									<table border="0" cellspacing="0" cellpadding="5" class="tborder">
										<tbody>
											<tr>
												<td class="thead" colspan="2"><strong>Szenentracker Charakter Events</strong></td>
											</tr>
										</tbody>
										<tr>	
											<td class="trow1" valign="top">
												<div class="scene_ucp container">
													<div class="scene_ucp manage alert_item">

														<h1><i class="fas fa-calendar"></i>Events</h1>
														<p>Hier findest du die Events und Geburtstage deiner Charaktere.</p>

														<div class="st-ucp-events__actions">
															<button type="button" class="button" onclick="location.href=\'{$event_add_link}\'" >Event eintragen </button><br><br>
														</div>
														<div class="st-ucp-events__views">
															<form method="get" action="usercp.php" class="st-ucp-events__filter" >
																<input type="hidden" name="action" value="scenetracker" >
																<input type="hidden" name="view" value="events" >
																<input type="hidden" name="event_view" value="{$view}" >
																<label> Charakter:<select name="character_uid"> {$character_options} </select> </label>
																<input type="submit" class="button" alue="Filtern">
															</form>
															<div class="st-ucp-events__views">
																<a class="{$list_active} button" href="usercp.php?action=scenetracker&amp;view=events&amp;event_view=list&amp;character_uid={$selected_uid}" ><strong>Listenansicht</strong></a>
																<a class="{$calendar_active} button" href="usercp.php?action=scenetracker&amp;view=events&amp;event_view=calendar&amp;character_uid={$selected_uid}"><strong>Kalender</strong></a>
															</div>
														</div>

														{$scenetracker_ucp_events_list}
														{$scenetracker_ucp_events_calendar}
													</div>
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
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_event_form_official',
		"template" => $db->escape_string('<tr>
					<td class="trow1"><strong>Offizielles Event</strong></td>
					<td class="trow1">
						<label><input type="checkbox" name="official" value="1" {$official_checked}>
							Dieses Event immer öffentlich im Kalender anzeigen.
						</label>
					</td>
				</tr>'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_day_bit',
		"template" => $db->escape_string('
				<div class="st-calendar__day {$caltype} {$monthflag} {$sceneevent} {$plotevent} {$birthdayevent} {$ingame}">
					<div class="st-calendar__date" style="{$hidedate}">{$day_date}</div>
					<div class="st-calendar__info">{$scenetracker_calendar_event_typ}</div>
				</div>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);


	$templates[] = array(
		"title" => 'scenetracker_calendar_event_typ_mini',
		"template" => $db->escape_string('
			<div class="st-calendar__event {$event[\'type\']} st-calendar__event--{$event[\'type\']}">
				{$day_date}
				{$day_modal}
			</div>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_event_typ_mini_event',
		"template" => $db->escape_string('
			<div class="{$modalclass}st-calendar__event-mini" id="{$modal_id}_{$type}" style="{$modalcsscode}">
				<div class="st_calender__popup">
					{$scenetracker_calendar_event_typ_bit}
				</div>
			</div>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_event_typ',
		"template" => $db->escape_string('
					<div class="st-calendar__event st-calendar__event--{$event[\'type\']}"><a onclick="$(\'#{$modal_id}_{$type}\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \'undefined\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;"><strong>{$eventtyp}<sup>({$count})</sup></strong></a>
						<div class="modal st-calendar__modal-full" id="{$modal_id}_{$type}" style="display: none; margin: auto; text-align: center;">
							<div class="st_calender__popup">
								<div class="st_calender__popup-title"><strong>{$eventtyp}</strong></div>
								{$scenetracker_calendar_event_typ_bit}
							</div>
						</div>
					</div>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_event_typ_bit_plot',
		"template" => $db->escape_string('
				{$eventtitle_mini}
				<fieldset><a href="{$link}">{$event[\'title\']}</a></fieldset>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_event_typ_bit_birthday',
		"template" => $db->escape_string('
			{$eventtitle_mini}
			<fieldset><a href="{$link}">{$event[\'title\']}</a></fieldset>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_event_title_modal',
		"template" => $db->escape_string('<a onclick="$(\'#{$modal_id}_{$type}\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \'undefined\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;"><strong>{$day_array[\'day\']}.</strong></a>'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_event_title',
		"template" => $db->escape_string('
				<strong>{$day_array[\'day\']}.</strong>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_event_detail',
		"template" => $db->escape_string('<html>
						<head>
							<title>{$mybb->settings[\'bbname\']} - {$event_name}</title>
							{$headerinclude}
						</head>
						<body>
						{$header}
						<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder" >
							<tr>
							<td class="thead"><strong>{$event_name}</strong></td>
							</tr>
							<tr>
								<td class="trow1">
									<div class="st-event-detail__meta">
										<div>
											<strong>Von:</strong> {$event_userlink}
										</div>
										<div>
											<strong>Zeitraum:</strong>
											{$event_outputdate}
										</div>
										<div>
											<strong>Wiederholung:</strong>
											{$event_recurring}
										</div>
										<div>
											<strong>Sichtbarkeit:</strong>
											<span class="st-event-detail__visibility  st-event-detail__visibility--{$event_visibility_class}">
												{$event_visibility}
											</span>
										</div>
									</div>
									<div class="st-event-detail__description">
										{$event_description}
									</div>
									<div class="st-event-detail__controls">
									{$event_delete_button}
									</div>
								</td>
							</tr>
						</table>

						<br>
						<a href="{$back_link}">&laquo; Zurück zum Kalender</a>

						{$footer}

						</body>
						</html>'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_event_edit_button',
		"template" => $db->escape_string('
				<button type="button" class="button" onclick="location.href=\'misc.php?action=calendar&amp;do=edit_event&amp;id={$event_id}\'">
					bearbeiten
				</button>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_event_delete_button',
		"template" => $db->escape_string('
			<form method="post" action="misc.php?action=calendar&amp;do=delete_event" style="display:inline;" onsubmit="return confirm(\'Möchtest du dieses Event wirklich löschen?\');">
				<input type="hidden" name="my_post_key" value="{$mybb->post_code}">
				<input type="hidden" name="id" value="{$event[\'id\']}">
				<button type="submit" class="button">löschen</button>
			</form>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_event_add_button',
		"template" => $db->escape_string('
			<a href="misc.php?action=calendar&amp;do=add_event" class="button">Event hinzufügen </a>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_event_typ_bit_event',
		"template" => $db->escape_string('
			{$eventtitle_mini}
			<fieldset class="st-calendar__daybit st-calendar__evententry">
					<a href="{$link}">{$event[\'title\']}</a>
			</fieldset>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_event_typ_bit_scene',
		"template" => $db->escape_string('{$eventtitle_mini}
				<fieldset class="st-calendar__daybit st_calender__scene {$event[\'status\']}">
					<span><a href="{$link}">{$event[\'title\']}</a><span>({$event[\'time\']})</span></span>
					{$contentwarning}
					<span>Inhaltswarnung: {$event[\'contentwarning\']}</span>
					<span>Teilnehmer: {$event[\'user\']}</span>
					<span>Ort: {$event[\'place\']}</span>
				</fieldset>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_calendar_event_typ_bit_scene_contentwarning',
		"template" => $db->escape_string('<span>Inhaltswarnung: {$event[\'contentwarning\']}</span>'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);


	$templates[] = array(
		"title" => 'scenetracker_calendar_event_form',
		"template" => $db->escape_string('<html>
					<head>
						<title>{$mybb->settings[\'bbname\']} - {$event_form_title}</title>
						{$headerinclude}
					</head>
					<body>
					{$header}
					{$event_errors}

					<form method="post" action="{$event_form_action}">
						<input type="hidden" name="my_post_key" value="{$mybb->post_code}">
						<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
							<tr>
								<td class="thead" colspan="2"><strong>{$event_form_title}</strong></td>
							</tr>
							<tr>
								<td class="trow1"><strong>Name</strong></td>
								<td class="trow1">
									<input type="text" name="name" value="{$event_name}" class="textbox">
								</td>
							</tr>
							<tr>
								<td class="trow2"><strong>Beschreibung</strong></td>
								<td class="trow2">
									<textarea name="description" rows="8" cols="60" >{$event_description}</textarea>
								</td>
							</tr>
							<tr>
								<td class="trow1"><strong>Beginn</strong></td>
								<td class="trow1">
									<input type="date" name="startdate" value="{$event_startdate}" placeholder="2026-06-21" class="textbox" >
								</td>
							</tr>
							<tr>
								<td class="trow2">
									<strong>Ende</strong>
								</td>
								<td class="trow2">
									<input type="date" name="enddate" value="{$event_enddate}" placeholder="2026-06-21" class="textbox" >
								</td>
							</tr>
							<tr>
								<td class="trow1"><strong>Sichtbarkeit</strong></td>
								<td class="trow1">
									<select name="private">
										<option value="1" {$private_selected} >Privat</option>
										{$public_option}
									</select>
								</td>
							</tr>
							<tr>
								<td class="trow2"><strong>Wiederholung</strong></td>
								<td class="trow2">
									<select name="recurring">
										<option value="0" {$recurring_selected[0]} >Keine </option>
										<option value="1" {$recurring_selected[1]} > Wöchentlich </option>
										<option value="2" {$recurring_selected[2]} > Monatlich </option>
										<option value="3" {$recurring_selected[3]} > Quartalsweise </option>
										<option value="4" {$recurring_selected[4]} > Halbjährlich </option>
										<option value="5" {$recurring_selected[5]} > Jährlich </option>
										<option value="6" {$recurring_selected[6]} > alle X Tage </option>
									</select>
								</td>
							</tr>
							<tr id="st-recurring-interval-row">
									<td class="trow1">
											<strong>Wiederholungsabstand</strong>
									</td>
									<td class="trow1">
											<input type="number" name="recurring_interval" id="st-recurring-interval" value="{$event_recurring_interval}" min="1" step="1" class="textbox">
											<div class="smalltext">
													Anzahl der Tage zwischen zwei Ereignisstarts.
													Die Dauer ergibt sich aus Beginn und Ende.
											</div>
									</td>
							</tr>
							{$event_official_option}
						</table>
						<br>
						<input type="submit" class="button" value="Speichern">
					</form>
					{$footer}
					<script>
						document.addEventListener("DOMContentLoaded", function () {
								const recurring = document.querySelector(\'select[name="recurring"]\');
								const row = document.getElementById("st-recurring-interval-row");
								const input = document.getElementById("st-recurring-interval");

								if (!recurring || !row || !input) {
										return;
								}

								function updateIntervalField() {
										const enabled = recurring.value === "6";

										row.hidden = !enabled;
										row.style.display = enabled ? "" : "none";
										input.disabled = !enabled;
										input.required = enabled;
								}

								recurring.addEventListener("change", updateIntervalField);
								updateIntervalField();
						});
						</script>
					</body>
					</html>'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);


	$templates[] = array(
		"title" => 'scenetracker_plots_empty',
		"template" => $db->escape_string('
		<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder" >
			<tr>
				<td class="trow1" align="center">
					Es wurden keine Plots gefunden.
				</td>
			</tr>
		</table>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);


	$templates[] = array(
		"title" => 'scenetracker_plots_bit',
		"template" => $db->escape_string('
					<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder scenetracker-plot">
				<tr>
					<td class="thead">
						<strong>
							<a href="{$plot_link}">{$plot_name}</a>
						</strong>
						<span class="smalltext">{$plot_category}</span>
					</td>
				</tr>
				<tr>
					<td class="trow1">
						<div class="scenetracker-plot-meta">
							<strong>Zeitraum:</strong>
							{$plot_date}
						</div>
						<div class="scenetracker-plot-description">
							{$plot_summary}
						</div>
					</td>
				</tr>
			</table>

			<br />
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);


	$templates[] = array(
		"title" => 'scenetracker_plots_tab',
		"template" => $db->escape_string('
		<a href="{$category_link}" class="scenetracker-plots-tab  {$category_active}">{$category_name}</a>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_plots',
		"template" => $db->escape_string('
						<html>
				<head>
					<title>{$mybb->settings[\'bbname\']} - Plots</title>
					{$headerinclude}
				</head>
				<body>

				{$header}

				<table width="100%" cellspacing="0" cellpadding="5" border="0" align="center">
					<tr>
						<td class="navigation">
							<a href="{$mybb->settings[\'bburl\']}/index.php">{$mybb->settings[\'bbname\']}</a>
							&rsaquo;
							<strong>Plots</strong>
						</td>
					</tr>
				</table>
				<br />
				<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
					<tr>
						<td class="thead">
							<strong>Plots</strong>
						</td>
					</tr>

					<tr>
						<td class="trow1">
							<div class="scenetracker-plots-tabs">
								{$scenetracker_plots_tabs}
							</div>
						</td>
					</tr>
				</table>
				<br />
				<div class="scenetracker-plots">
					{$scenetracker_plots_list}
					{$scenetracker_plots_empty}
				</div>

				{$footer}

				</body>
				</html>
		'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_counter_character',
		"template" => $db->escape_string('({$counter_chara_open}/{$counter_chara_all})'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_counter_all',
		"template" => $db->escape_string('({$cnts[\'open\']}/{$cnts[\'all\']})'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	// $templates[] = array(
	// 	"title" => 'scenetracker_calender_birthday',
	// 	"template" => $db->escape_string('<a onclick="$(\'#bday{$day}\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \'undefined\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">[Geburtstage]</a>
	//   <div class="modal" id="bday{$day}" style="display: none; padding: 10px; margin: auto; text-align: center;">{$birthday_popup}</div>'),
	// 	"sid" => "-2",
	// 	"version" => "",
	// 	"dateline" => TIME_NOW
	// );

	// $templates[] = array(
	// 	"title" => 'scenetracker_calender_birthday_popup',
	// 	"template" => $db->escape_string('<div class="st_calendar bl-space bl-box--soft">
	//         <div class="st_calendar__sceneitem birthday icon">{$birthdayuser}</div>
	//        </div>'),
	// 	"sid" => "-2",
	// 	"version" => "",
	// 	"dateline" => TIME_NOW
	// );

	$templates[] = array(
		"title" => 'scenetracker_ucp_reminder',
		"template" => $db->escape_string('
    <div class="sceneucp__sceneitem scene_reminder_settings">
    <a onclick="$(\'#reminderopoup{$id}\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \'undefined\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;"><i class="fa-solid fa-alarm-clock"></i>[reminder ist {$index_reminder}]</a>
      <div class="modal addrela" id="reminderopoup{$id}" style="display: none; padding: 10px; margin: auto; text-align: center;">
        <form method="post" class="bl-space bl-box--soft" action="usercp.php?action=scenetracker">
          {$hidden}
          <input type="hidden" value="{$data[\'id\']}" name="getid">
          <div>
            <label class="bl-heading3" for="reminder{$id}">Reminder Settings</label><br>
            <select name="reminder" id="reminder{$id}">
              <option value="1" {$rem_sel_on}>an</option>
              <option value="0" {$rem_sel_off}>aus</option>
            </select><br>
            nach 
            <input type="number" name="reminder_days" value="{$days_reminder}" id="remdays{$id}">
            Tage(n)
          </div>
          <input type="submit" name="remindersettings" />
        </form>
      </div>
      </div>'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
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
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_forumdisplay_user',
		"template" => '<span class="scenetracker_forumdisplay scenetracker_user">{$user} {$delete}</span>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_trigger',
		"template" => $db->escape_string('<div class="scenetracker__sceneitem scene_trigger icon bl-btn bl-btn--info">Inhaltswarnung: {$scenes[\'scenetracker_trigger\']}</div>'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_search_results',
		"template" => $db->escape_string('<div class="sceneinfo-container">
        <div class="scenetracker_date"><i class="fas fa-calendar"></i>{$scene_date}</div>
        <div class="scenetracker_place"><i class="fas fa-map-marker-alt"></i>{$thread[\'scenetracker_place\']}</div>
        {$scenetrigger}
        <div class="scenetracker_user"><i class="fas fa-users"></i>
          {$user}
        </div>
      </div>'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_index_bit_chara',
		"template" => '<div class="scenetracker_index character_box">
        <div class="scenetracker_index character_item name "><h1>{$charaname} {$cnt_chara}</h1> </div>
        <div class="scenetracker_index character container item">
          {$scenetracker_index_bit_scene}
        </div>
        </div>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
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
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_index_main',
		"template" => '<div class="scenetracker_index wrapper_container"><strong>Szenenverwaltung {$counter}</strong>
        {$scenetracker_index_bit_chara}
        </div>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_index_reminder',
		"template" => '<div class="scenetracker_reminder box"><div class="scenetracker_reminder_wrapper"><span class="senetracker_reminder text">Du lässt deinen Postpartner in folgenden Szenen warten:</span>
        <div class="scenetracker_reminder container">
        {$scenetracker_index_reminder_bit}
        </div>
        <span class="senetracker_reminder text"><a href="index.php?action=reminder_all">[ignore all]</a></span>
        </div></div>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_index_reminder_bit',
		"template" => '<div class="scenetracker_reminder item">
        {$userarr[\\\'username\\\']} - <a href="showthread.php?tid={$scene[\\\'tid\\\']}&action=lastpost">{$scene[\\\'subject\\\']}</a> 
        ({$lastpostdays} Tage) - <a href="index.php?action=reminder&sceneid={$sceneid}">[ignore and hide]</a> 
        </div>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_newreply',
		"template" => '<tr>
          <td class="trow2" width="20%" colspan="2" align="center">
            <input type="checkbox" name="scenetracker_add" id="scenetracker_add" value="add" checked /> <label for="scenetracker_add">Charakter zu Teilnehmern hinzufügen</label>
          </td>
        </tr>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_newthread_plot',
		"template" => '<tr>
          <td class="trow2" width="20%"><strong>Plotauswahl:</strong></td>
          <td class="trow2">
            <div class="con">
              <div class="con-item">
                {$plot_select}
              </div>
              <div class="con-item">
							Gehört diese Szene zu einem Plot?
              </div>
            </div>
          </td>
          </tr>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
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
					{$scenetracker_plot}
          <tr>
          <td class="trow2" width="20%"><strong>Inhaltswarnung:</strong></td>
          <td class="trow2">
            <div class="con">
              <div class="con-item">
                <input type="text" id="scenetracker_trigger" name="scenetracker_trigger" size="40" value="{$scenetracker_trigger}" />
              </div>
              <div class="con-item">
                Gibt es eine Inhaltswarnung für die Szene? Wenn ja dann mit aussagekräftigem Begriff(en) füllen.
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
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_profil',
		"template" => '<div class="scenetracker container scenetracker_profil">
        {$scenetracker_profil_bit}
        </div>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_profil_active',
		"template" => '	<div class="scenetracker container active">
        {$scenetracker_profil_bit_active}
        </div>	',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
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
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_profil_bit_mY',
		"template" => '<span class="scentracker month">{$scenedatetitle}</span>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_profil_closed',
		"template" => '<div class="scenetracker container closed">
        {$scenetracker_profil_bit_closed}
        </div>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_showthread',
		"template" => '<div class="scenetracker scenebit scenetracker_showthread">
            <div class="scenetracker__sceneitem scene_date icon"><i class="fas fa-calendar"></i> {$scene_date}</div>
            <div class="scenetracker__sceneitem scene_place icon "><i class="fas fa-map-marker-alt"></i> {$sceneplace}</div>
            <div class="scenetracker__sceneitem scene_status icon"><i class="fas fa-play"></i> {$scenestatus}</div>
            {$scenetrigger}
						{$scene_plot}
            <div class="scenetracker__sceneitem scene_users icon"><i class="fas fa-users"></i>{$scenetracker_showthread_user}</div> 
            {$scenetracker_showthread_export}
            {$edit}
          </div>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_showthread_export',
		"template" => '<div class="scenetracker__sceneitem scene_export">
      <form  method="post">
        {$exp_sel} <input type="submit" name="export_scene" value="export" id="export" />
      </form>
    </div>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_showthread_user',
		"template" => '<span class="scenetracker_user">{$user} {$delete}</span>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_ucp_bit_chara',
		"template" => '<div class="scene_ucp chara_item {$divclass}">
          <h3>{$charaname}</h3>
          <div class="scene_ucp chara_item__scenes-con">
            {$scenetracker_ucp_bit_scene}
          </div>
        </div>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_ucp_bit_scene',
		"template" => '<div class ="sceneucp__scenebox scene_ucp chara_item__scene">
            <div class="sceneucp__sceneitem scene_title icon"><i class="fas fa-folder-open"></i> {$scene} {$statusclass}</div>
            <div class="sceneucp__sceneitem scene_status icon"><i class="fas fa-play"></i> scene {$close}
            </div>
            <div class="sceneucp__sceneitem scene_profil icon"><i class="fas fa-circle-user"></i> scene {$hide}</div>
            <div class="sceneucp__sceneitem scene_alert icon {$alertclass}"><i class="fas fa-bullhorn"></i>
              <span class="sceneucp__scenealerts">{$alerttype} {$alerttype_alert} {$certain} {$always}</span>
            </div>
            {$scenereminder}
            <div class="sceneucp__sceneitem scene_date icon"><i class="fas fa-calendar"></i> {$scenedate}</div>
            <div class="sceneucp__sceneitem scene_users icon "><i class="fas fa-users"></i>{$users}</div>
            <div class="sceneucp__sceneitem scene_place icon"><i class="fas fa-map-marker-alt"></i> {$sceneplace}</div>
            <div class="sceneucp__sceneitem scene_last icon ">last: {$lastposterlink} ({$lastpostdate})</div>
          </div>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
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
            {$scenetracker_ucp_filterscenes}
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
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
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
                  <table border="0" class="tborder">
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
          </html>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);


	$templates[] = array(
		"title" => 'scenetracker_calendar',
		"template" => '<div class="calendar-container">{$scenetracker_calendar}</div>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_ucp_filterscenes',
		"template" => $db->escape_string('<div class="scene_ucp scenefilteroptions">
          <h2>Filteroptions</h2>

          <form action="usercp.php?action=scenetracker" method="post">
            <div class="scene_ucp scenefilteroptions filter ">

              <div class="scenefilteroptions__items scenefilteroptions__items--filter">
                <label for="charakter">Szenen anzeigen von: </label>{$selectchara}
                <input type="hidden" value="{$thisuser}" name="uid" id="uid"/>
              </div>	
              <div class="scenefilteroptions__items scenefilteroptions__items--filter">
                <label for="status">Status der Szene:</label>
                <select name="status" id="status">
                  <option value="both" {$sel_s[\'both\']}>beides</option>
                  <option value="open" {$sel_s[\'open\']} >offen</option>
                  <option value="closed" {$sel_s[\'closed\']}>geschlossen</option>
                </select>
              </div>
              <div class="scenefilteroptions__items scenefilteroptions__items--filter">
                <label for="move">Du bist dran: </label>
                <select name="move" id="move">
                  <option value="beides" {$sel_m[\'beides\']}>beides</option>
                  <option value="ja" {$sel_m[\'ja\']}>ja</option>
                  <option value="nein" {$sel_m[\'nein\']}>nein</option>

                </select>
              </div>
              {$scenetracker_ucp_filterscenes_username}
              <div class="scenefilteroptions__items button">
                <input class="button" type="submit" value="newest (all characters)" name="newest"/>
			          <input class="button" type="submit" value="oldest (all characters)" name="oldest"/>
                <input type="submit" name="scenefilter" value="Szenen filtern" id="scenefilter" />
              </div>
            </div>
          </form>'),
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_ucp_filterscenes_username',
		"template" => '<div class="scenefilteroptions__items scenefilteroptions__items--filter">
                <label for="move">Szenen mit: </label>
                <div class="filterinput">
                  <input type="text" name="player" id="player" />
                </div>
              </div>
            
          <link rel="stylesheet" href="{$mybb->asset_url}/jscripts/select2/select2.css?ver=1807">
          <script type="text/javascript" src="{$mybb->asset_url}/jscripts/select2/select2.min.js?ver=1806"></script>
          <script type="text/javascript">
            <!--
              if(use_xmlhttprequest == "1")
            {
              MyBB.select2();
              $("#player").select2({
                placeholder: "Filter: Spielername",
                minimumInputLength: 2,
                multiple: false,
                allowClear: true,
                ajax: { // instead of writing the function to execute the request we use Select2\\\'s convenient helper
                  url: "xmlhttp.php?action=scenetracker_get_player",
                  dataType: \\\'json\\\',
                  data: function (term, page) {
                    return {
                      query: term, // search term
                      fieldid: "player",
                    };
                  },
                  results: function (data, page) { // parse the results into the format expected by Select2.
                    // since we are using custom formatting functions we do not need to alter remote JSON data
                    return {results: data};
                  }
                },
                initSelection: function(element, callback) {
                  var value = $(element).val();
                  if (value !== "") {
                    callback({
                      id: value,
                      text: value
                    });
                  }
                },
                // Allow the user entered text to be selected as well
                createSearchChoice:function(term, data) {
                  if ( $(data).filter( function() {
                    return this.text.localeCompare(term)===0;
                  }).length===0) {
                    return {id:term, text:term};
                  }
                },
              });
  
              $(\\\'[for=player]\\\').on(\\\'click\\\', function(){
                $("#player").select2(\\\'open\\\');
                return false;
              });
            }
            // -->
          </script>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_showthread_trigger',
		"template" => '<div class="scenetracker__sceneitem scenethread scene_trigger"><span class="scene_trigger__title">{$lang->scenetracker_triggeredit}</span> {$thread[\\\'scenetracker_trigger\\\']}</div>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_showthread_plot',
		"template" => '<div class="scenetracker__sceneitem scenethread scene_plot"><span class="scene_plot__title">Plot:</span> {$plotname}</div>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_showthread_edit',
		"template" => '<div class="scenetracker__sceneitem scene_edit icon bl-btn bl-btn--scenetracker">
      <a onclick="$(\\\'#sceneinfos{$tid}\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">{$lang->scenetracker_editinfos}</a>
          <div class="modal editscname" id="sceneinfos{$tid}" style="display: none; padding: 10px; margin: auto; text-align: center;">
             <form action="" id="formeditscene" method="post" >
              <input type="hidden" value="{$tid}" name="id" id="id"/>
                <center><input id="teilnehmer" placeholder="{$lang->scenetracker_teilnehmer}" type="text" value="" size="40"  name="teilnehmer" autocomplete="off" style="display: block;" /></center>
                <div id="suggest" style="display:none; z-index:10;"></div>
                <input type="date" id="scenetracker_date" name="scenetracker_date" value="{$scenetracker_date}" /> 
                <input type="{$time_input_type}" id="scenetracker_time" name="{$time_input_name}" value="{$scenetracker_time}" />
                  <input type="text" name="scenetrigger" id="scenetrigger" placeholder="{$lang->scenetracker_trigger}" value="{$scenetriggerinput}" />
                  <input type="text" name="sceneplace" id="sceneplace" placeholder="{$lang->scenetracker_place}" value="{$sceneplace}" />
                  
            </form><button name="edit_sceneinfos" id="edit_sceneinfos">{$lang->scenetracker_btnsubmit}</button>
            <script src="./jscripts/scenetracker.js"></script>
            <script type="text/javascript" src="./jscripts/suggest.js"></script>

        </div>

      </div>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_ucp_options_reminder',
		"template" => '<div class="scenefilteroptions__items scenefilteroptions__items--alerts">
          <form action="usercp.php?action=scenetracker" method="post">
          <fieldset><legend >{$lang->scenetracker_reminderopt}</legend>
          <input type="radio" name="reminder" id="reminder_yes" value="1" {$yes_rem}> 
          <label for="reminder_yes">Ja</label>
          <input type="radio" name="reminder" id="reminder_no" value="0" {$no_rem}> 
          <label for="reminder_no">Nein</label><br />
          <input type="submit" name="opt_reminder" value="{$lang->scenetracker_btnsubmit}" id="reminder_button" />
          </fieldset>
        </form>
        </div>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_ucp_options_calendarform',
		"template" => '
      <form action="usercp.php?action=scenetracker" method="post" class="scenetracker_cal_setting">
        {$scenetracker_calendarview_all}
        {$scenetracker_calendarview_ownall}
        {$scenetracker_calendarview_mini}
        {$scenetracker_calendarview}
        <input type="submit" name="calendar_settings" value="{$lang->scenetracker_btnsubmit}" id="calsettings_button" />
      </form>
      ',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_ucp_options_calendar_all',
		"template" => '<fieldset class="scenefilteroptions__items scenefilteroptions__items--alerts">
			      <legend>Kalender Settings für alle verbundene Charaktere oder nur diesen?</legend>
            <input type="radio" name="calendar_setforalls" id="calendar_setforalls_all" value="1" {$setforalls_all}>
            <label for="calendar_setforalls_all">alle</label><br />
            <input type="radio" name="calendar_setforalls" id="calendar_setforalls_this" value="0" {$setforalls_this}>
            <label for="calendar_setforalls_this">diesen</label><br />
            </fieldset>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_ucp_options_minicalendar',
		"template" => '<fieldset class="scenefilteroptions__items scenefilteroptions__items--alerts">
			    <legend>Mini Kalender: Welche Szenen sollen angezeigt werden? </legend>
            <input type="radio" name="mini_view" id="mini_view_all" value="2" {$mini_view_all}> 
            <label for="mini_view_all">Von allen Charakteren des Forums.</label><br />
            <input type="radio" name="mini_view" id="mini_view_all_own" value="1" {$mini_view_all_own}> 
            <label for="mini_view_all_own">Von deinen Charakteren.</label><br />
			      <input type="radio" name="mini_view" id="mini_view_all_this" value="0" {$mini_view_all_this}>
            <label for="mini_view_all_this">Nur von diesem Charakter</label><br />
          </fieldset>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_ucp_options_calendar',
		"template" => '<fieldset class="scenefilteroptions__items scenefilteroptions__items--alerts">
			  <legend>Großer Kalender: Welche Szenen sollen angezeigt werden? </legend>
        <input type="radio" name="big_view" id="big_view_all" value="2" {$big_view_all}> 
        <label for="big_view_all">Von allen Charakteren des Forums.</label><br />
        <input type="radio" name="big_view" id="big_view_all_own" value="1" {$big_view_all_own}> 
        <label for="big_view_all_own">Von deinen Charakteren.</label><br />
			  <input type="radio" name="big_view" id="big_view_all_this" value="0" {$big_view_all_this}>
        <label for="big_view_all_this">Nur von diesem Charakter</label><br />
        </fieldset>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_popup',
		"template" => '<a onclick="$(\\\'#certain{$id}\\\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \\\'undefined\\\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;"><i class="fas fa-cogs"></i>[...]</a>
						<div class="modal addrela" id="certain{$id}" style="display: none; padding: 10px; margin: auto; text-align: center;">
							<form method="post" action="usercp.php?action=scenetracker">
								<input type="hidden" value="{$data[\\\'id\\\']}" id="getid" name="getid">
								{$hidden}
								{$scenetracker_popup_select_options_index}
								{$scenetracker_popup_select_options_alert}
								<div>
									<span>reminder settings für die Szene kannst du im User CP ändern </span>
								</div>
								<input type="submit" name="certainuser" />

							</form>
							<link rel="stylesheet" href="{$mybb->settings[\\\'bburl\\\']}/jscripts/select2/select2.css">
							<script type="text/javascript" src="{$mybb->settings[\\\'bburl\\\']}/jscripts/select2/select2.min.js?ver=1804"></script>
						</div>',
		"sid" => "-2",
		"version" => "2",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_popup_select_options',
		"template" => '<fieldset><legend>Setting für {$type}</legend>
				<div class="st-alertsetting-box" style="display: flex; flex-direction: column; text-align: left;">
					<input type="hidden" value="{$type}" name="{$type}_type">
					<label>
						<input type="radio" name="{$type}_mode{$id}" value="0" {$sel_array[\\\'another\\\']}>
						<span>another user posted</span>
					</label>
					<label>
						<input type="radio" name="{$type}_mode{$id}" value="-3" {$sel_array[\\\'fieldorder\\\']}>
						<span>nach eingetragener Reihenfolge</span>
					</label>
					<label>
						<input type="radio" name="{$type}_mode{$id}" value="-2" {$sel_array[\\\'always\\\']}>
						<span>always</span>
					</label>
					<label>
						<input type="radio" name="{$type}_mode{$id}" value="-1" {$sel_array[\\\'never\\\']}>
						<span>never</span>
					</label>
					<label>
						<input type="radio" name="{$type}_mode{$id}" value="character" {$sel_array[\\\'character\\\']}>
						<span>bestimmter Charakter</span>
					</label>
					<div id="{$type}_select_wrap{$id}" style="display:none;">
						<input type="text" id="{$type}_select{$id}" name="{$type}_select{$id}" value="{$sel_array[\\\'charactername\\\']}">
					</div>
				</div>
				<script type="text/javascript">
					var radios = $(\\\'input[name="{$type}_mode{$id}"]\\\');

					<!--
						$("#{$type}_select{$id}").select2({
						placeholder: "Nach Charakter suchen",
						minimumInputLength: 2,
						multiple: false,
						allowClear: true,
						ajax: { 
							url: "{$mybb->settings[\\\'bburl\\\']}/xmlhttp.php?action=get_users",
							dataType: \\\'json\\\',
							data: function (term, page) {
								return {
									query: term, // search term
								};
							},
							results: function (data, page) { // parse the results into the format expected by Select2.
								// since we are using custom formatting functions we do not need to alter remote JSON data
								return {results: data};
							}
						},
						initSelection: function(element, callback) {
							var query = $(element).val();
							if (query !== "") {
								$.ajax("{$mybb->settings[\\\'bburl\\\']}/xmlhttp.php?action=get_users&getone=1", {
									data: {
										query: query
									},
									dataType: "json"
								}).done(function(data) { callback(data); });
							}
						},
					});

					$(\\\'[for={$type}_select{$id}]\\\').on(\\\'click\\\', function(){
						$("#{$type}_select{$id}").select2(\\\'open\\\');
						return false;
					});
					// -->
					radios.on(\\\'change\\\', function () {
						var selectedMode = $(this).val();
						if (selectedMode === \\\'character\\\') {
							$("#{$type}_select_wrap{$id}").show();
						} else {
							$("#{$type}_select_wrap{$id}").hide();
						}
					});
					
					preselect = $(\\\'input[name="index_mode{$id}"]:checked\\\').val();
					if (preselect === \\\'character\\\') {
						$(\\\'#index_select_wrap{$id}\\\').show();
					} else {
						$(\\\'#index_select_wrap{$id}\\\').hide();
					}
				</script>
			</fieldset>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_showthread_export',
		"template" => '<div class="scenetracker__sceneitem scene_export">
    <form  method="post">
      {$exp_sel} <input type="submit" name="export_scene" value="export" id="export" />
      </form>
    </div>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_displaystatus_shown',
		"template" => '{$lang->scenetracker_displaystatus_shown} <a href="usercp.php?action=scenetracker&showsceneprofil=0&getsid={$id}"><i class="fas fa-toggle-on"></i><span class="cta cta-hide">[hide]</span></a>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_displaystatus_hidden',
		"template" => '{$lang->scenetracker_displaystatus_hidden} <a href="usercp.php?action=scenetracker&showsceneprofil=1&getsid=©{$id}"><i class="fas fa-toggle-off"></i><span class="cta cta-show">[show]</span></a>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_sceneisclosed',
		"template" => '{$lang->scenetracker_sceneisclosed} <a href="usercp.php?action=scenetracker&closed=0&getsid={$id}&gettid={$tid}&getuid={$uid}"><i class="fas fa-unlock"></i><span class="cta cta-open">[öffnen]</span></a>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_sceneisopen',
		"template" => '{$lang->scenetracker_sceneisopen} <a href="usercp.php?action=scenetracker&closed=1&getsid={$id}&gettid={$tid}&getuid={$uid}"><i class="fas fa-lock"></i><span class="cta cta-close">[schließen]</span></a>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	$templates[] = array(
		"title" => 'scenetracker_scenestatus_solved_mod',
		"template" => '<a href="member.php?action=profile&uid={$userprofil}&closed=0&gettid={$tid}"><i class="fas fa-check-circle"></i><span class="cta cta-open">[reopen]</span></a>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_scenestatus_solved',
		"template" => '<i class="fas fa-check-circle"></i><span class="cta cta-solve">[erledigt]</span>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_scenestatus_unsolved_mod',
		"template" => '<a href="member.php?action=profile&uid={$userprofil}&closed=1&gettid={$tid}"><i class=\"fas fa-times-circle"></i><span class="cta cta-close">[close]</span></a>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_scenestatus_unsolved',
		"template" => '<i class=\"fas fa-times-circle"></i><span class="cta cta-unsolved">[unerledigt]</span>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);
	$templates[] = array(
		"title" => 'scenetracker_scenestatus_hide',
		"template" => '<a href="member.php?action=profile&uid={$userprofil}&show=0&getsid={$sid}"><i class="fas fa-eye-slash"></i><span class="cta cta-hide">[verstecken]</a></span>',
		"sid" => "-2",
		"version" => "",
		"dateline" => TIME_NOW
	);

	return $templates;
}

/**
 * Hier werden Templates gespeichert, die im Laufe der Entwicklung aktualisiert wurden
 * @return array - template daten die geupdatet werden müssen
 * templatename: name des templates mit dem was passieren soll
 * change_string: nach welchem string soll im alten template gesucht werden
 * action: Was soll passieren - add: fügt hinzu, replace ersetzt (change)string, overwrite ersetzt gesamtes template
 * action_strin: Der string der eingefügt/mit dem ersetzt/mit dem überschrieben werden soll
 */
function scenetracker_updated_templates()
{
	global $db;

	//data array initialisieren 
	$update_template = array();

	$update_template[] = array(
		"templatename" => 'scenetracker_ucp_main',
		"change_string" => '<form action="usercp.php?action=scenetracker" method="post">
      <div class="scene_ucp scenefilteroptions">
        <h2>Filteroptions</h2>
        <div class="scenefilteroptions__items">
          <label for="charakter">Szenen anzeigen von: </label>{$selectchara}
          <input type="hidden" value="{$thisuser}" name="uid" id="uid"/>
        </div>	
        <div class="scenefilteroptions__items">
          <label for="status">Status der Szene:  </label>
          <select name="status" id="status">
            <option value="both" {$sel_s[\'both\']}>beides</option>
              <option value="open" {$sel_s[\'open\']} >offen</option>
            <option value="closed" {$sel_s[\'closed\']}>geschlossen</option>
          </select>
        </div>
        <div class="scenefilteroptions__items">
            <label for="move">Du bist dran: </label>
            <select name="move" id="move">
            <option value="beides" {$sel_m[\'beides\']}>beides</option>
              <option value="ja" {$sel_m[\'ja\']}>ja</option>
            <option value="nein" {$sel_m[\'nein\']}>nein</option>
            
          </select>
        </div>
        <div class="scenefilteroptions__items button">
          <input type="submit" name="scenefilter" value="Szenen filtern" id="scenefilter" />
        </div>
      </div>
        </form>',
		"action" => 'replace',
		"action_string" => '{$scenetracker_ucp_filterscenes}'
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_index_reminder_bit',
		"change_string" => '({$lastpostdays} Tage)',
		"action" => 'add',
		"action_string" => ' - <a href="index.php?action=reminder&sceneid={$sceneid}">[ignore and hide]</a>'
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_showthread',
		"change_string" => '{$scenetrigger}',
		"action" => 'add',
		"action_string" => '{$scene_plot}'
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_ucp_filterscenes',
		"change_string" => '<input type="submit" name="scenefilter" value="Szenen filtern" id="scenefilter" />',
		"action" => 'add',
		"simple_check_string" => 'name="oldest"',
		"action_string" => '<input class="button" type="submit" value="newest (all characters)" name="newest"/>
			<input class="button" type="submit" value="oldest (all characters)" name="oldest"/>'
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_index_reminder',
		"change_string" => '<a href="index.php?action=reminder">[ignore all]</a>',
		"action" => 'replace',
		"action_string" => '<a href="index.php?action=reminder_all">[anzeige deaktivieren]</a>'
	);
	$update_template[] = array(
		"templatename" => 'scenetracker_index_reminder',
		"change_string" => '{$scenes',
		"action" => 'replace',
		"action_string" => '{$scene'
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_ucp_bit_scene',
		"change_string" => '({$lastpostdate})</div>',
		"action" => 'add',
		"action_string" => '{$scenereminder}'
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_ucp_bit_chara',
		"change_string" => 'scene_ucp chara_item',
		"action" => 'add',
		"action_string" => '{$divclass}'
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_popup',
		"change_string" => 'jscripts/select2/select2.css',
		"action" => 'overwrite',
		"action_string" => '<a onclick="$(\'#certain{$id}\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \'undefined\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;"><i class="fas fa-cogs"></i>[...]</a>
						<div class="modal addrela" id="certain{$id}" style="display: none; padding: 10px; margin: auto; text-align: center;">
							<form method="post" action="usercp.php?action=scenetracker">
								<input type="hidden" value="{$data[\'id\']}" id="getid" name="getid">
								{$hidden}
								{$scenetracker_popup_select_options_index}
								{$scenetracker_popup_select_options_alert}
								<div>
									<span>reminder settings für die Szene kannst du im User CP ändern </span>
								</div>
								<input type="submit" name="certainuser" />

							</form>
							<link rel="stylesheet" href="{$mybb->settings[\'bburl\']}/jscripts/select2/select2.css">
							<script type="text/javascript" src="{$mybb->settings[\'bburl\']}/jscripts/select2/select2.min.js?ver=1804"></script>
				</div>'
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_newthread',
		"change_string" => '<tr><td class="trow2" width="20%"><strong>Inhaltswarnung:</strong></td>',
		"action" => 'add_before',
		"action_string" => '{$scenetracker_plot}'
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_showthread',
		"change_string" => '{$edit}',
		"action" => 'add',
		"action_string" => '{$scenetracker_showthread_export}'
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_popup_select_options',
		"change_string" => 'fieldorder',
		"action" => 'overwrite',
		"action_string" => '<fieldset><legend>Setting für {$type}</legend>
				<div class="st-alertsetting-box" style="display: flex; flex-direction: column; text-align: left;">
					<input type="hidden" value="{$type}" name="{$type}_type">
					<label>
						<input type="radio" name="{$type}_mode{$id}" value="0" {$sel_array[\'another\']}>
						<span>another user posted</span>
					</label>
					<label>
						<input type="radio" name="{$type}_mode{$id}" value="-3" {$sel_array[\'fieldorder\']}>
						<span>nach eingetragener Reihenfolge</span>
					</label>
					<label>
						<input type="radio" name="{$type}_mode{$id}" value="-2" {$sel_array[\'always\']}>
						<span>always</span>
					</label>
					<label>
						<input type="radio" name="{$type}_mode{$id}" value="-1" {$sel_array[\'never\']}>
						<span>never</span>
					</label>
					<label>
						<input type="radio" name="{$type}_mode{$id}" value="character" {$sel_array[\'character\']}>
						<span>bestimmter Charakter</span>
					</label>
					<div id="{$type}_select_wrap{$id}" style="display:none;">
						<input type="text" id="{$type}_select{$id}" name="{$type}_select{$id}" value="{$sel_array[\'charactername\']}">
					</div>
				</div>
				<script type="text/javascript">
					var radios = $(\'input[name="{$type}_mode{$id}"]\');

					<!--
						$("#{$type}_select{$id}").select2({
						placeholder: "Nach Charakter suchen",
						minimumInputLength: 2,
						multiple: false,
						allowClear: true,
						ajax: { 
							url: "{$mybb->settings[\'bburl\']}/xmlhttp.php?action=get_users",
							dataType: \'json\',
							data: function (term, page) {
								return {
									query: term, // search term
								};
							},
							results: function (data, page) { // parse the results into the format expected by Select2.
								// since we are using custom formatting functions we do not need to alter remote JSON data
								return {results: data};
							}
						},
						initSelection: function(element, callback) {
							var query = $(element).val();
							if (query !== "") {
								$.ajax("{$mybb->settings[\'bburl\']}/xmlhttp.php?action=get_users&getone=1", {
									data: {
										query: query
									},
									dataType: "json"
								}).done(function(data) { callback(data); });
							}
						},
					});

					$(\'[for={$type}_select{$id}]\').on(\'click\', function(){
						$("#{$type}_select{$id}").select2(\'open\');
						return false;
					});
					// -->
					radios.on(\'change\', function () {
						var selectedMode = $(this).val();
						if (selectedMode === \'character\') {
							$("#{$type}_select_wrap{$id}").show();
						} else {
							$("#{$type}_select_wrap{$id}").hide();
						}
					});
					
					preselect = $(\'input[name="index_mode{$id}"]:checked\').val();
					if (preselect === \'character\') {
						$(\'#index_select_wrap{$id}\').show();
					} else {
						$(\'#index_select_wrap{$id}\').hide();
					}
				</script>
			</fieldset>'
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_calendar_bit',
		"action" => 'delete_template',
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_calendar_day',
		"action" => 'delete_template',
	);
	$update_template[] = array(
		"templatename" => 'scenetracker_calendar_day_pop',
		"action" => 'delete_template',
	);
	$update_template[] = array(
		"templatename" => 'scenetracker_calendar_weekrow',
		"action" => 'delete_template',
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_calender_birthday',
		"action" => 'delete_template',
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_calender_birthday_bit',
		"action" => 'delete_template',
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_calender_birthday_popup',
		"action" => 'delete_template',
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_calender_day',
		"action" => 'delete_template',
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_calender_event_bit',
		"action" => 'delete_template',
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_calender_plot_bit',
		"action" => 'delete_template',
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_calender_popbit',
		"action" => 'delete_template',
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_calender_scene_bit',
		"action" => 'delete_template',
	);

	$update_template[] = array(
		"templatename" => 'scenetracker_calender_sceneinfos',
		"action" => 'delete_template',
	);

	$update_template[] = array(
		"templatename" => '',
		"action" => 'delete_template',
	);

	return $update_template;
}

/**
 * Update Check
 * @return boolean false wenn Plugin nicht aktuell ist
 * überprüft ob das Plugin auf der aktuellen Version ist
 */
function scenetracker_is_updated()
{
	global $db, $mybb;
	$needupdate = 0;
	require_once MYBB_ROOT . "inc/plugins/risuena_updates/risuena_updatefile.php";

	$text =  '<div class="border_wrapper" style="margin-bottom:10px;max-height: 200px; overflow: auto; background: #efefef; border-radius:6px; border: 1px solid #ccc;"><div class="title">Szenentracker updates:</div><div class="inner_wrapper" style="padding:10px;">';
	// if (!$db->field_exists("scenetracker_date", "threads")) {
	// 	$text .= "<br>In der Threadtabelle muss das Feld scenetracker_date  hinzugefügt werden <br>";
	// 	$needupdate = 1;
	// }

	$fields = scenetracker_added_dbfields();
	$tables = scenetracker_db_schema();

	//Checken ob die vom Szenentracker hinzugefügten Tabellen, dem aktuellen Schema entsprechen
	foreach ($tables as $table => $table_fields) {
		foreach ($table_fields['fields'] as $field => $definition) {
			$fields[] = array(
				"tablename" => $table,
				"fieldname" => $field,
				"definition" => $definition,
			);
		}
	}
	//die hinzugefügten Felder überprüfen
	$field_check = risuenaupdatefile_check_fields($fields);
	if ($field_check[0] == true) {
		$text .= $field_check[1];
		$needupdate = 1;
	}

	$setting_array = scenetracker_settingarray();
	$gid = $db->fetch_field($db->simple_select("settinggroups", "gid", "name = 'scenetracker'"), "gid");

	foreach ($setting_array as $name => $setting) {
		$setting['name'] = $name;
		$setting['gid'] = $gid;
		$check2 = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "settings` WHERE name = '{$name}'");
		if ($db->num_rows($check2) > 0) {
			while ($setting_old = $db->fetch_array($check2)) {
				if (
					$setting_old['title'] != $setting['title'] ||
					stripslashes($setting_old['description']) != stripslashes($setting['description']) ||
					$setting_old['optionscode'] != $setting['optionscode'] ||
					$setting_old['disporder'] != $setting['disporder']
				) {
					$text .= "<b>Setting</b>: {$name} muss aktualisiert werden.<br>";
					$needupdate = 1;
				}
			}
		} else {
			$text .= "<b>Setting:</b> {$name} muss hinzugefügt werden.<br>";
			$needupdate = 1;
		}
	}

	//Testen ob im CSS etwas fehlt
	$update_data_all = scenetracker_stylesheet_update();
	//alle Themes bekommen
	$theme_query = $db->simple_select("themestylesheets", "*", "name='scenetracker.css'");
	while ($theme = $db->fetch_array($theme_query)) {
		foreach ($update_data_all as $update_data) {
			$update_stylesheet = $update_data['stylesheet'];
			$update_string = $update_data['update_string'];
			if (!empty($update_string)) {
				$test_ifin = $db->write_query("SELECT stylesheet FROM " . TABLE_PREFIX . "themestylesheets WHERE tid = '{$theme['tid']}' AND name = 'scenetracker.css' AND stylesheet LIKE '%" . $update_string . "%' ");
				if ($db->num_rows($test_ifin) == 0) {
					if (!empty($update_string)) {
						//checken ob updatestring in css vorhanden ist - dann muss nichts getan werden
						$test_ifin = $db->write_query("SELECT stylesheet FROM " . TABLE_PREFIX . "themestylesheets WHERE tid = '{$theme['tid']}' AND name = 'scenetracker.css' AND stylesheet LIKE '%" . $update_string . "%' ");
						//string war nicht vorhanden
						if ($db->num_rows($test_ifin) == 0) {
							$text .= "<b>Theme</b> {$theme['tid']} muss aktualisiert werden ({$update_data['update_string']}) <br>";
							$needupdate = 1;
						}
					}
				}
			}
		}
	}

	//Testen ob eins der Templates aktualisiert werden muss
	//Wir wollen erst einmal die templates, die eventuellverändert werden müssen
	$update_template_all = scenetracker_updated_templates();

	//testen ob ein template hinzugefügt werden muss
	//nur template hinzufügen, wenn es noch nicht existiert
	$templates = scenetracker_templates();
	foreach ($templates as $template) {
		if (risuenaupdatefile_check_templates($template['title'])) {
			$text .= "<b>Template (hinzufügen)</b> {$template['title']}<br>";
			$needupdate = 1;
		}
	}

	//alle themes durchgehen
	foreach ($update_template_all as $update_template) {
		//entsprechendes Tamplate holen
		$old_template_query = $db->simple_select("templates", "tid, template, sid", "title = '" . $update_template['templatename'] . "'");
		while ($old_template = $db->fetch_array($old_template_query)) {
			//pattern bilden
			if ($update_template['action'] == "replace") {
				$pattern = risuenaupdatefile_createRegexPattern($update_template['change_string']);
				$check = preg_match($pattern, $old_template['template']);
				$checktext_a = htmlentities($update_template['change_string']);
				$checktext_b = "ersetzen mit";
			} elseif ($update_template['action'] == "add") {
				//bei add wird etwas zum template hinzugefügt, wir müssen also testen ob das schon geschehen ist
				//change string 
				$pattern = risuenaupdatefile_createRegexPattern($update_template['action_string']);
				$check = !preg_match($pattern, $old_template['template']);
				$checktext_a = htmlentities($update_template['change_string']);
				$checktext_b = "dahinter einfügen";
			} elseif ($update_template['action'] == "add_before") {
				//bei add wird etwas zum template hinzugefügt, wir müssen also testen ob das schon geschehen ist
				//change string 
				$pattern = risuenaupdatefile_createRegexPattern($update_template['action_string']);
				$check = !preg_match($pattern, $old_template['template']);
				$checktext_a = htmlentities($update_template['change_string']);
				$checktext_b = "dahinter einfügen";
			} elseif ($update_template['action'] == "overwrite") {
				//checken ob das bei change string angegebene vorhanden ist - wenn ja wurde das template schon überschrieben
				$checktext_a = "Template: " . $update_template['templatename'];
				$checktext_b = "Inhalt komplett ersetzen mit";
				$pattern = risuenaupdatefile_createRegexPattern($update_template['change_string']);
				$check = !preg_match($pattern, $old_template['template']);
			}
			//testen ob der zu ersetzende string vorhanden ist
			//wenn ja muss das template aktualisiert werden.
			if ($check) {
				$templateset = $db->fetch_field($db->simple_select("templatesets", "title", "sid = '{$old_template['sid']}'"), "title");
				$text .= "
          <b>Template aktualisieren</b> {$update_template['templatename']} im Set '{$templateset}(SID: {$old_template['sid']}') muss aktualisiert werden. 
          <div style=\"max-height: 100px; overflow:auto;\">" . $checktext_a . "</div>
          <b>{$checktext_b}</b> 
          <div style=\"max-height: 100px; overflow:auto;\">" . htmlentities($update_template['action_string']) . "</div><br>";
				$needupdate = 1;
			}
		}
	}
	$text .= "</div></div>";
	if ($needupdate == 1) {
		echo $text;
		return false;
	}
	return true;
}

/**
 * Test function
 * Debugging / Testing for Risuena. 
 * Kann einfach ignoriert werden ;) 
 */
$plugins->add_hook("misc_start", "scenetracker_misc_test");
function scenetracker_misc_test()
{
	global $mybb, $db, $templates, $header, $footer, $theme, $headerinclude, $scenes;

	if (!($mybb->get_input('action') == "scenetest")) return;
	// scenetracker_updated_templates();
	scenetracker_calendar_build_day_array(9, 2023);
	echo "<br> ----------- <br>";
}
