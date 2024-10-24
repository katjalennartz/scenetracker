<?php
if (!defined("IN_MYBB")) {
  die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// error_reporting ( -1 );
// ini_set ( 'display_errors', true );


function hidescenes_info()
{
  return array(
    "name"    => "Versteckte Szenen",
    "description"  => "Ein Plugin mit dem man Szenen verstecken kann.",
    "website"  => "",
    "author"  => "risuena",
    "authorsite"  => "https://github.com/katjalennartz",
    "version"  => "1.0",
    "compatibility" => "18*"
  );
}

function hidescenes_install()
{
  global $db, $cache, $mybb;

  hidescenes_uninstall();

  // Admin Einstellungen
  $setting_group = array(
    'name' => 'hidescenes',
    'title' => 'Versteckte Szenen',
    'description' => 'Allgemeine Einstellungen für versteckte Szenen',
    'disporder' => 1, // The order your setting group will display
    'isdefault' => 0
  );

  $gid = $db->insert_query("settinggroups", $setting_group);

  hidescenes_addsettings();
  rebuild_settings();
  hidescenes_addtemplates();

  if (!$db->field_exists("hidescene_readable", "threads")) {
    $db->add_column("threads", "hidescene_readable", "int(1) NOT NULL DEFAULT '1'");
    // ->0: komplett verstecken
    // ->1: Szenentital/szeneninfos werden gezeigt
    // ->2: User darf entscheiden
  }

  if (!$db->field_exists("hidescene_type", "threads")) {
    $db->add_column("threads", "hidescene_type", "int(1) NOT NULL DEFAULT '1'");
    //0 -> komplett vestecken
    //1 -> Szenentital/szeneninfos werden gezeigt
  }
}

function hidescenes_activate()
{
  global $db, $mybb;
  //Variablen einfügen
  include MYBB_ROOT . "/inc/adminfunctions_templates.php";
  //forumdisplay
  find_replace_templatesets("forumdisplay_thread", "#" . preg_quote('<tr class="inline_row">') . "#i", '<tr class="inline_row"{$hiderow}>');
  find_replace_templatesets("forumdisplay_thread", "#" . preg_quote('<a href="{$thread[\'lastpostlink\']}">{$lang->lastpost}</a>: {$lastposterlink}</span>') . "#i", '<a href="{$thread[\'lastpostlink\']}">{$hidewrap_start}{$lang->lastpost}{$hidewrap_end}</a>: {$lastposterlink}</span>');

  find_replace_templatesets("search_results_threads", "#" . preg_quote('{$footer}') . "#i", '{$hidescenes_forumdisplay_js}{$footer}');
  
  find_replace_templatesets("forumdisplay", "#" . preg_quote('{$footer}') . "#i", '{$hidescenes_forumdisplay_js}{$footer}');

  //suche
  find_replace_templatesets("search_results_threads_thread", "#" . preg_quote('<tr class="inline_row">') . "#i", '<tr class="inline_row"{$hiderow}>');
  find_replace_templatesets("search_results_threads_thread", "#" . preg_quote('<a href="{$thread[\'lastpostlink\']}">{$lang->lastpost}</a>: {$lastposterlink}</span>') . "#i", '<a href="{$thread[\'lastpostlink\']}">{$hidewrap_start}{$lang->lastpost}{$hidewrap_end}</a>: {$lastposterlink}</span>');

  //inputs newthread/edit
  find_replace_templatesets("newthread", "#" . preg_quote('{$posticons}') . "#i", '{$hidescenes_newthread}{$posticons}');
  find_replace_templatesets("editpost", "#" . preg_quote('{$posticons}') . "#i", '{$hidescenes_newthread}{$posticons}');

  //member profile
  find_replace_templatesets("member_profile", "#" . preg_quote('{$footer}') . "#i", '{$hidescene_js}{$footer}');

  //adding a unique class name to tracker items in ipt 2.0 - we need it for our Javascript!
  if ($db->num_rows($db->simple_select("templates", "title", "title = 'member_profile_inplaytracker_bit'")) > 0) {
    find_replace_templatesets("member_profile_inplaytracker_bit", "#" . preg_quote('trow1') . "#i", 'trow1 ipt-jule');
  }
}

function hidescenes_is_installed()
{
  global $mybb;
  if (isset($mybb->settings['hidescenes_tracker'])) {
    return true;
  }
  return false;
}

function hidescenes_uninstall()
{
  global $db, $cache;

  //Einstellungen löschen
  $db->delete_query('settings', "name LIKE 'hidescenes%'");
  $db->delete_query('settinggroups', "name = 'hidescenes'");

  rebuild_settings();

  //datenbankfeld löschen
  if ($db->field_exists("hidescene_readable", "threads")) {
    $db->drop_column("threads", "hidescene_readable");
  }
  //datenbankfeld löschen
  if ($db->field_exists("hidescene_type", "threads")) {
    $db->drop_column("threads", "hidescene_type");
  }

  //templates löschen
  $db->delete_query("templates", "title LIKE 'hidescenes%'");
  $db->delete_query("templategroups", "prefix = 'hidescenes'");
}

function hidescenes_deactivate()
{
  global $db, $mybb;
  //Variablen löschen
  include MYBB_ROOT . "/inc/adminfunctions_templates.php";
  find_replace_templatesets("forumdisplay_thread", "#" . preg_quote('{$hiderow}') . "#i", '');
  find_replace_templatesets("forumdisplay_thread", "#" . preg_quote('{$hidewrap_start}') . "#i", '');
  find_replace_templatesets("forumdisplay_thread", "#" . preg_quote('{$hidewrap_end}') . "#i", '');
  find_replace_templatesets("forumdisplay", "#" . preg_quote('{$hidescenes_forumdisplay_js}') . "#i", '');
  find_replace_templatesets("search_results_threads", "#" . preg_quote('{$hidescenes_forumdisplay_js}') . "#i", '');
  
  find_replace_templatesets("search_results_threads_thread", "#" . preg_quote('{$hiderow}') . "#i", '');
  find_replace_templatesets("search_results_threads_thread", "#" . preg_quote('{$hidewrap_start}') . "#i", '');
  find_replace_templatesets("search_results_threads_thread", "#" . preg_quote('{$hidewrap_end}') . "#i", '');

  find_replace_templatesets("newthread", "#" . preg_quote('{$hidescenes_newthread}') . "#i", '');
  find_replace_templatesets("editpost", "#" . preg_quote('{$hidescenes_newthread}') . "#i", '');

  find_replace_templatesets("member_profile", "#" . preg_quote('{$hidescene_js}') . "#i", '');

  //adding a unique class name to tracker items in ipt 2.0 - we need it for our Javascript!
  if ($db->num_rows($db->simple_select("templates", "title", "title = 'member_profile_inplaytracker_bit'")) > 0) {
    find_replace_templatesets("member_profile_inplaytracker_bit", "#" . preg_quote(' ipt-jule') . "#i", '');
  }
}

function hidescenes_addsettings($type = 'install')
{
  global $db;
  $setting_array = array(
    'hidescenes_tracker' => array(
      'title' => 'Szenentracker',
      'description' => 'Welcher Szenentracker wird verwendet?',
      'optionscode' => "select\n0=Risuenas Szenentracker\n1=Jules IPT 2.0\n2=Jules IPT 3.0",
      'value' => '0', // Default
      'disporder' => 1
    ),
    'hidescenes_group' => array(
      'title' => 'Sichbarkeit',
      'description' => 'Gibt es eine Gruppe, die Szenen immer sehen darf, auch wenn sie kein Teilnehmer ist? (z.B. Admins)',
      'optionscode' => 'groupselect',
      'value' => '0', // Default
      'disporder' => 2
    ),
    'hidescenes_ingame' => array(
      'title' => 'Ingamebereich',
      'description' => 'Welche Foren sind dein Ingamebereich/Archiv? Elternforen reichen. ',
      'optionscode' => 'forumselect',
      'value' => '1', // Default
      'disporder' => 3
    ),
    'hidescenes_type' => array(
      'title' => 'Wie genau verstecken?',
      'description' => 'Entweder setzt ihr hier die Einstellung, die für das ganze Forum gelten soll, oder ihr lasst eure User entscheiden.',
      'optionscode' => "select\n0=komplett verstecken\n1=Titel, wiw etc. anzeigen, nicht die Szene selbst\n2=user entscheiden",
      'value' => '1', // Default
      'disporder' => 4
    ),
  );

  $gid = $db->fetch_field($db->write_query("SELECT gid FROM `" . TABLE_PREFIX . "settinggroups` WHERE name like 'hidescenes' LIMIT 1;"), "gid");

  if ($type == 'install') {
    foreach ($setting_array as $name => $setting) {
      $setting['name'] = $name;
      $setting['gid'] = $gid;
      $db->insert_query('settings', $setting);
    }
  }
  rebuild_settings();
}

function hidescenes_addtemplates($type = 'install')
{
  global $db;
  if ($type == 'install') {
    $templategrouparray = array(
      'prefix' => 'hidescenes',
      'title'  => $db->escape_string('Versteckte Szenen'),
      'isdefault' => 1
    );
    $db->insert_query("templategroups", $templategrouparray);
  }

  $template[] = array(
    "title" => 'hidescenes_newthread_default',
    "template" => '<tr>
    <td class="trow2" width="20%" >
    <strong>Szene verstecken?</strong>
    </td>
    <td class="trow2">
      <input type="checkbox" name="hidescene" id="hidescenes_default" value="0" {$checked_default}/> Verstecken
    </td>
  </tr>
    ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[] = array(
    "title" => 'hidescenes_newthread_user',
    "template" => '<tr>
    <td class="trow2" width="20%"  >
    <strong>Szene verstecken?</strong>
    </td>
    <td class="trow2">
    <select name="hidescene" id="hidescene_user">
      <option value="-1" {$user_select_no}>Nicht verstecken</option>
      <option value="0" {$user_select_all}>Komplett verstecken</option>
      <option value="1" {$user_select_infos}>Nur Szeneninfos zeigen</option>
    </select>
    <br>

    </td>
  </tr>
    ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  $template[] = array(
    "title" => 'hidescenes_forumdisplay_js',
    "template" => '<script type="text/javascript">
	$(\\\'a\\\').has(\\\'span.hidescene\\\').each(function() {
		// Holen wir den Inhalt des span-Elements
		var spanContent = $(this).find(\\\'span.hidescene\\\').html();

		// Ersetzen wir den Link mit dem span-Inhalt
		$(this).replaceWith(\\\'<span class="hidescene">\\\' + spanContent + \\\'</span>\\\');
	});

	var spanContent = $(this).find(\\\'span.hidescene\\\').html();

	// Ersetzen wir den Link mit dem span-Inhalt
	$(this).replaceWith(\\\'<span class="hidescene">\\\' + spanContent + \\\'</span>\\\');
</script>
    ',
    "sid" => "-2",
    "version" => "1.0",
    "dateline" => TIME_NOW
  );

  foreach ($template as $row) {
    $check = $db->num_rows($db->simple_select("templates", "title", "title LIKE '{$row['title']}'"));
    if ($check == 0) {
      $db->insert_query("templates", $row);
    }
  }
}

/**
 * Neuen Thread erstellen - Inputs hinzufügen
 */
$plugins->add_hook("newthread_start", "hidescenes_newthread");
function hidescenes_newthread()
{
  global $db, $mybb, $templates, $fid, $hidescene_type, $hidescene_readable, $thread, $post_errors, $hidescenes_newthread;
  $hidescene_readable = $hidescene_type = "";
  if (hidescenes_testParentFid($fid)) {
    if ($mybb->get_input('previewpost') || $post_errors) {
      $hidescene_readable = $mybb->get_input('hidescene');
    }
    //default einstellungen admin
    if ($mybb->settings['hidescenes_type'] != 2) {
      eval("\$hidescenes_newthread = \"" . $templates->get("hidescenes_newthread_default") . "\";");
    } else {
      //User darf wählen
      eval("\$hidescenes_newthread = \"" . $templates->get("hidescenes_newthread_user") . "\";");
    }
  }
}

/**
 * Thread speichern
 **/
$plugins->add_hook("newthread_do_newthread_end", "hidescenes_do_newthread");
function hidescenes_do_newthread()
{
  global $db, $mybb, $tid, $thread, $templates, $fid, $pid, $visible;

  if (hidescenes_testParentFid($fid)) {

    //default einstellungen admin
    if ($mybb->settings['hidescenes_type'] != 2) {
      $save = array(
        "hidescene_readable" => $mybb->get_input('hidescene'),
      );
      $db->update_query("threads", $save, "tid='{$tid}'");
    } else {
      //User darf wählen
      if ($mybb->get_input('hidescene') == -1) {
        $save = array(
          "hidescene_readable" => 1,
        );
      } else {
        $save = array(
          "hidescene_readable" => 0,
          "hidescene_type" => intval($mybb->get_input('hidescene')),
        );
      }
      $db->update_query("threads", $save, "tid='{$tid}'");
    }
  }
}

/**
 * Thread editieren - ansicht
 **/
$plugins->add_hook("editpost_end", "hidescenes_editpost", 40);
function hidescenes_editpost()
{
  global $thread, $templates, $db, $lang, $mybb, $templates, $fid, $post_errors, $hidescenes_newthread;
  if (hidescenes_testParentFid($fid) && $thread['firstpost'] == $mybb->get_input('pid')) {
    if ($mybb->get_input('previewpost') || $post_errors) {
      $hidescene_readable = $mybb->get_input('hidescene');
    } else {

      //default einstellungen admin
      if ($mybb->settings['hidescenes_type'] != 2) {
        if ($thread['hidescene_readable'] == 1) {
          $checked_default = "";
        } else {
          $checked_default = "checked";
        }
      } else {
        if ($thread['hidescene_readable'] == 1) {
          $user_select_no = "selected";
          $user_select_all = "";
          $user_select_infos = "";
        } else {
          if ($thread['hidescene_type'] == 0) {
            $user_select_all = "selected";
            $user_select_infos = "";
            $user_select_no = "";
          } elseif ($thread['hidescene_type'] == 1) {
            $user_select_infos = "selected";
            $user_select_all = "";
            $user_select_no = "";
          }
        }
      }
    }

    if ($mybb->settings['hidescenes_type'] != 2) {
      eval("\$hidescenes_newthread = \"" . $templates->get("hidescenes_newthread_default") . "\";");
    } else {
      //User darf wählen
      eval("\$hidescenes_newthread = \"" . $templates->get("hidescenes_newthread_user") . "\";");
    }
  }
}

/**
 * Thread editieren - speichern
 **/
$plugins->add_hook("editpost_do_editpost_end", "hidescenes_do_editpost");
function hidescenes_do_editpost()
{
  global $db, $mybb, $tid, $pid, $thread, $fid, $post;
  if (hidescenes_testParentFid($fid)) {
    if ($thread['firstpost'] == $pid) {
      //default einstellungen admin
      if ($mybb->settings['hidescenes_type'] != 2) {
        $save = array(
          "hidescene_readable" => $mybb->get_input('hidescene'),
        );
        $db->update_query("threads", $save, "tid='{$tid}'");
      } else {
        //User darf wählen
        if ($mybb->get_input('hidescene') == -1) {
          $save = array(
            "hidescene_readable" => 1,
          );
        } else {
          $save = array(
            "hidescene_readable" => 0,
            "hidescene_type" => intval($mybb->get_input('hidescene')),
          );
        }
        $db->update_query("threads", $save, "tid='{$tid}'");
      }
    }
  }
}

/**
 * Forumdisplay - Anzeige von verstecken Threads bzw. verstecken
 **/
$plugins->add_hook("forumdisplay_thread_end", "hidescenes_forumdisplay_thread_end", 10);
function hidescenes_forumdisplay_thread_end()
{
  global $thread, $lang, $threads, $mybb, $templates, $db, $fid, $scenetrackerforumdisplay, $lastposterlink, $hidewrap_start, $hidewrap_end, $hiderow;
  // var_dump($thread);
  $lang->load("hidescenes");
  $hidewrap_start = "";
  $hidewrap_end = "";
  $hiderow = "";
  $fid = $mybb->get_input("fid", MyBB::INPUT_INT);
  $hidetype = $mybb->settings['hidescenes_type'];

  // Anzeigen, dass die Szene für einen versteckt ist
  if ($thread['hidescene_readable'] == 0 && hidescenes_testParentFid($fid)) {
    $hideclass = "hidescenes_own";
    //Wir wollen immer die Info, dass die Szene versteckt ist
    $thread['subject'] .= $lang->hidescenes_ishidden;
    //Teilnehmer dürfen immer sehen
    if (!hidescenes_allowed_to_see($thread)) {
      //komplett verstecken
      if ($hidetype == 0 || ($hidetype == 2 && $thread['hidescene_type'] == 0)) {
        //nur szeneninfos zeigen - zum einen verstecken wir die ganze reihe mit display none, 
        // damit aber keiner pfuschen kann und in den HTML Code schaut, überschreiben wir auch die Variablen.

        $hiderow = " style=\"display:none;\" ";
        $thread['threadlink'] = "#";
        $thread['lastpostlink'] = "#";
        $lastposterlink = "";
        $thread['multipage'] = "";
        $hidewrap_start = "<span class=\"nolink\" style=\"display:none;\">";
        $hidewrap_end = "</span>";
        $thread['subject'] = "<span class=\"nolink\" onclick=\"return false;\"></span>";
        //empty ipt 3.0 and 2.0 sceneinfos ?
        $thread['profilelink'] = "";
        //empty szenentracker risuena 
        $scenetrackerforumdisplay = "";
      }
      //nur szeneninos anzeigen
      if ($hidetype == 1 || ($hidetype == 2 && $thread['hidescene_type'] == 1)) {
        //nur szeneninfos zeigen
        //wir wollen die linkadresse auch verstecken
        $thread['threadlink'] = "#";
        $thread['lastpostlink'] = "#";
        $thread['multipage'] = "";
        //damit man nicht auf den Link klicken kann
        $hidewrap_start = "<span class=\"hidescene\" onclick=\"return false;\">";
        $hidewrap_end = "</span>";
        //link soll nicht mehr klickbar sein
        $thread['subject'] = "<span class=\"hidescene\" onclick=\"return false;\">" . $thread['subject'] . " </span>";
      }
    }
  }
}

$plugins->add_hook("search_results_end", "hidescenes_forumdisplay_end");
$plugins->add_hook("forumdisplay_end", "hidescenes_forumdisplay_end");
function hidescenes_forumdisplay_end()
{
  global $templates, $hidescenes_forumdisplay_js;
  eval("\$hidescenes_forumdisplay_js = \"" . $templates->get("hidescenes_forumdisplay_js") . "\";");
}
/**
 * Showthread - Gesamten Thread verstecken
 **/
$plugins->add_hook("showthread_start", "hidescenes_showthread");
function hidescenes_showthread()
{
  global $db, $mybb, $tid, $thread, $fid, $lang;
  $lang->load('hidescenes');
  //den ganzen Kram brauchen wir nur, wenn die Szene versteckt sein soll
  if ($thread['hidescene_readable']  == 0) {
    //im Ingame und kein Teilnehmer. 
    if (hidescenes_testParentFid($fid) && !hidescenes_allowed_to_see($thread)) {
      error($lang->hidescenes_notallowed);
    }
  }
}


/**
 * Suchergebnisse verstecken
 **/
$plugins->add_hook("search_results_thread", "hidescenes_search_results_thread");
function hidescenes_search_results_thread()
{
  global $thread, $lang, $threads, $mybb, $thread_link, $inline_edit_tid, $gotounread, $lastposterlink, $sceneinfos, $hiderow, $hidewrap_start, $hidewrap_end;

  $lang->load("hidescenes");

  $hidewrap_start = "";
  $hidewrap_end = "";
  $hiderow = "";

  $fid = $thread['fid'];
  $hidetype = $mybb->settings['hidescenes_type'];
  // Anzeigen, dass die Szene für einen versteckt ist
  if ($thread['hidescene_readable'] == 0 && hidescenes_testParentFid($fid)) {

    //Wir wollen immer die Info, dass die Szene versteckt ist
    $thread['subject'] .= $lang->hidescenes_ishidden;
    //Teilnehmer dürfen immer sehen
    if (!hidescenes_allowed_to_see($thread)) {
      //komplett verstecken
      if ($hidetype == 0 || ($hidetype == 2 && $thread['hidescene_type'] == 0)) {
        //gar nicht zeiten- zum einen verstecken wir die ganze reihe mit display none, 
        // damit aber keiner pfuschen kann und in den HTML Code schaut, überschreiben wir auch die Variablen.
        $hiderow = " style=\"display:none;\" ";
        $thread_link = "#";
        $gotounread = "";
        $thread['multipage'] = "";
        $thread['lastpostlink'] = "#";
        $lastposterlink = "";
        $inline_edit_tid = "";
        $thread['replies'] = "";
        $thread['tid'] = "";
        $hidewrap_start = "<span style=\"display:none;\">";
        $hidewrap_end = "</span>";
        $thread['subject'] = "";
        //empty ipt 3.0 and 2.0 sceneinfos ?
        $thread['profilelink'] = "";
        //empty szenentracker risuena 
        $sceneinfos = "";
      }
      //nur szeneninos anzeigen

      if ($hidetype == 1 || ($hidetype == 2 && $thread['hidescene_type'] == 1)) {
        //nur szeneninfos zeigen
        //wir wollen die linkadresse auch verstecken
        $thread['tid'] = "";
        $thread_link = "#";
        $inline_edit_tid = "";
        $thread['lastpostlink'] = "#";
        $thread['multipage'] = "";

        //damit man nicht auf den Link klicken kann
        $hidewrap_start = "<span class=\"hidescene\" onclick=\"return false;\">";
        $hidewrap_end = "</span>";
        //link soll nicht mehr klickbar sein
        $thread['subject'] = "<span class=\"hidescene\" onclick=\"return false;\">" . $thread['subject'] . " </span>";
      }
    }
  }
}

/**
 * Wer ist online verstecken
 **/
$plugins->add_hook("build_friendly_wol_location_end", "hidescenes_online_location");
function hidescenes_online_location($plugin_array)
{
  global $mybb, $theme, $lang;
  $lang->load("hidescenes");
  if ($plugin_array['user_activity']['activity'] == "showthread") {
    $thread = get_thread($plugin_array['user_activity']['tid']);
    if (!hidescenes_allowed_to_see($thread)) {
      $plugin_array['location_name'] = $lang->hidescenes_wiw_location;
    }
  }
  return $plugin_array;
}

/**
 * Memberprofile Szenen verstecken
 **/
$plugins->add_hook("member_profile_end", "hidescenes_member_profile_end");
function hidescenes_member_profile_end()
{
  global $hidescene_js, $mybb, $db, $memprofile, $lang;
  $lang->load("hidescenes");

  $hidetype = $mybb->settings['hidescenes_type'];

  //risuenas tracker
  if ($mybb->settings['hidescenes_tracker'] == 0) {
    $get_scenes = $db->simple_select("threads", "*", "scenetracker_user like '%" . $memprofile['username'] . "%' AND hidescene_readable = 0");
  } elseif ($mybb->settings['hidescenes_tracker'] == 1) {
    $get_scenes = $db->simple_select("threads", "*", "partners like '%" . $memprofile['username'] . "%' AND hidescene_readable = 0");
  } elseif ($mybb->settings['hidescenes_tracker'] == 2) {
    $get_scenes = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "threads` t LEFT JOIN " . TABLE_PREFIX . "ipt_scenes_partners p ON p.tid = t.tid WHERE p.uid = " . $memprofile['uid'] . " and hidescene_readable = 0;");
  }

  if ($mybb->settings['hidescenes_tracker'] == 0) {
    $js_selector = ".scenetracker.scenebit";
  } elseif ($mybb->settings['hidescenes_tracker'] == 1) {
    $js_selector = ".ipt-jule";
  } elseif ($mybb->settings['hidescenes_tracker'] == 2) {
    $js_selector = ".ipbit";
  }

  $hidescene_js = "<script type=\"text/javascript\">
  $(document).ready(function () {";

  while ($thread = $db->fetch_array($get_scenes)) {
    //Info, dass die Szene versteckt ist
    $hidescene_js .= "
      //info, dass szene versteckt ist (auch für die, die lesen dürfen)
      var sceneItems = $('" . $js_selector . "');
      // Nur die Div Boxen filtern, die einen Link mit der URL 'tid=X' enthalten
      var filteredSceneItems = sceneItems.filter(function() {
        // Überprüfe, ob der Link innerhalb dieser Div-Box die URL 'tid=X' enthält
        return $(this).find('a[href*=\"?tid=" . $thread['tid'] . "\"]').length > 0;
      });
      // über die Div Boxen gehen
      filteredSceneItems.each(function() {
        // Info, dass Szene versteck ist hinzufügen
        $(this).find('a[href*=\"?tid=" . $thread['tid'] . "\"]').after('<span class=\"hideinfo\">" . $lang->hidescenes_ishidden . "</span>');
      }); 

      ";

    if (!hidescenes_allowed_to_see($thread)) {
      if ($hidetype == 0 || ($hidetype == 2 && $thread['hidescene_type'] == 0)) {
        // scenetracker.scenebit ->  löschen
        $hidescene_js .= "
        //Szene komplett löschen
          var sceneItems = $('" . $js_selector . "');
          // Nur die Div Boxen filtern, die einen Link mit der URL 'tid=X' enthalten
          var filteredSceneItems = sceneItems.filter(function() {
              // Überprüfe, ob der Link innerhalb dieser Div-Box die URL 'tid=X' enthält
              return $(this).find('a[href*=\"?tid=" . $thread['tid'] . "\"]').length > 0;
          });
          // über die Div Boxen gehen
          filteredSceneItems.each(function() {
              // Eintrag löschen
              $(this).remove();
          });


          ";
      }
      //Szeneninfos angezeigt lassen  - aber link nicht mehr anklickbar
      if ($hidetype == 1 || ($hidetype == 2 && $thread['hidescene_type'] == 1)) {
        $hidescene_js .= "
        //Szeneninfos angezeigt lassen  - aber link nicht mehr anklickbar
          var sceneItems = $('" . $js_selector . "');
          // Nur die Div Boxen filtern, die einen Link mit der URL 'tid=X' enthalten
          var filteredSceneItems = sceneItems.filter(function() {
              // Überprüfe, ob der Link innerhalb dieser Div-Box die URL 'tid=X' enthält
              return $(this).find('a[href*=\"?tid=" . $thread['tid'] . "\"]').length > 0;
          });
          // über die Div Boxen gehen
          filteredSceneItems.each(function() {
          // console.log($(this).find('a').html());
          var spanContent = $(this).find('a[href*=\"?tid=\"]').html();
          $(this).find('a[href*=\"?tid=\"]').replaceWith('<span class=\"hidescene\">' + spanContent + '</span>');
            });
        ";
      }
    }
  }
  $hidescene_js .= "}); </script>";
}

/**
 * Check if an fid belong to ingame/archiv
 * @param $fid to check
 * @return boolean true/false
 * */
function hidescenes_testParentFid($fid)
{
  global $db, $mybb;
  // scenetracker_exludedfids
  //die parents des forums holen in dem wir sind.
  $parents = $db->fetch_field($db->write_query("SELECT CONCAT(',',parentlist,',') as parents FROM " . TABLE_PREFIX . "forums WHERE fid = $fid"), "parents");
  $ingame = $mybb->settings['hidescenes_ingame'];
  if ($ingame == '-1') return true; //alle foren aktiviert
  $ingameexplode = explode(",", $ingame);
  //array durchgehen und testen ob gewolltes forum in der parentlist ist.
  foreach ($ingameexplode as $ingamefid) {
    //jetzt holen wir uns die parentliste des aktuellen forums und testen, ob die parentid enthalten ist. wenn ja, dann sind wir richtig
    if (strpos($parents, "," . $ingamefid . ",") !== false) {
      return true;
    }
  }
  return false;
}

/**
 * get all attached account to a given user
 * @param $user which is online
 * @return array with uids(keys) and usernames(values)
 * */
function hidescenes_get_accounts($thisuser)
{
  global $mybb, $db;
  $user = get_user($thisuser);
  $charas = array();
  if ($db->field_exists("as_uid", "users")) {
    $as_uid = $user['as_uid'];
    if ($as_uid == 0) {
      $get_all_users = $db->query("SELECT uid,username FROM " . TABLE_PREFIX . "users WHERE (as_uid = $thisuser) OR (uid = $thisuser) ORDER BY username");
    } else if ($as_uid != 0) {
      //id des users holen an den alle angehangen sind 
      $get_all_users = $db->query("SELECT uid,username FROM " . TABLE_PREFIX . "users WHERE (as_uid = $as_uid) OR (uid = $thisuser) OR (uid = $as_uid) ORDER BY username");
    }
    while ($users = $db->fetch_array($get_all_users)) {
      $uid = $users['uid'];
      $charas[$uid] = $users['username'];
    }
  } else {
    $uid = $mybb->user['uid'];
    $charas[$uid] = $mybb->user['username'];
  }
  return $charas;
}

/**
 * returns if a user is participant of scene
 * @param string $thisuser which is online, flag if main charakter or not
 * @param array $array with thread infos
 * @return boolean true or false
 * */
function hidescenes_allowed_to_see($thread)
{
  global $mybb, $db;
  $uidstring = ",";
  $tid = $thread['tid'];
  $thisuser = $mybb->user['uid'];
  if ($mybb->user['uid'] == 0) return false;

  if ($db->table_exists('ipt_scenes_partners') && $mybb->settings['hidescenes_tracker'] == "2") {
    $uidquery = $db->simple_select("ipt_scenes_partners", "uid", "tid = '{$tid}'");
    while ($uids = $db->fetch_array($uidquery)) {
      $uidstring .= $uids['uid'] . ",";
    }
  } //Jules IPT 2.0 
  else if ($db->field_exists('partners', 'threads') && $mybb->settings['hidescenes_tracker'] == "1") {
    $names = $thread['partners'];
    $names_array = explode(",", $names);
    foreach ($names_array as $name) {
      $user = get_user_by_username($name);
      $uidstring .= $user['uid'] . ",";
    }
  } //Risuenas Szenentracker
  else if ($db->table_exists('scenetracker') && $mybb->settings['hidescenes_tracker'] == "0") {
    $userArray = scenetracker_getUids($thread['scenetracker_user']);
    foreach ($userArray as $uid => $username) {
      if ($uid != $username) {
        $uidstring .= $uid . ",";
      }
    }
  };

  //alle charas des users durchegehen
  $chars = hidescenes_get_accounts($thisuser);
  foreach ($chars as $uid => $username) {
    //wir brauchen die kommas um sicher zugehen dass wir nicht die uid 2 z.B. in uid 23 treffen, deswegen suchen wir nach ,2, nicht nur nach 2
    $uid_teststr = "," . $uid . ",";
    $isin_uidstring = stripos($uidstring, $uid_teststr);
    if ($isin_uidstring !== false) {
      return true;
    }
  }

  //welche gruppen dürfen immer sehen? 
  $allowedgroups = $mybb->settings['hidescenes_group'];
  if (is_member($allowedgroups, $thisuser) || $allowedgroups == '-1') {
    return true;
  }

  return false;
}
