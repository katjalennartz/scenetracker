# Szenentracker
## Updates: 		
Last: 28. Mai 2024
Version: 1.0.3		
		
### Todo nach Update: 
- Dateien neu hochladen
- update_scenetracker.php ins hauptverzeichnis laden und aufrufen. Anschließend aufrufen und dann wieder löschen.
- savescenes.php und getusernames.php können gelöscht werden

### Changelog: 
1.0.2 -> 1.0.3    

***Bugfix:***
- Korrektur Javascript in scenetracker.js / anpassung auf neues Feld / Anpassung AJAX Request
- Verschieben der ausgelagerten php Datein in Plugin datei  

***New Feature:***   
- Auflistung aller Szenen aller User nach Datum unter /misc.php?action=scenelist
  
***Neues Template:***
- scenetracker_misc_allscenes  
   
## Wichtige Infos: 

**Die Updates müssen natürlich nur durchgeführt werden, wenn der Tracker vorher schon installiert war. Wenn nicht, reicht es das Plugin ganz normal zu installieren** 		

```diff
- Ganz unten im Text: HOW TO: Verbinden vom Szenentracker mit aheartforspinach's Archivierungsplugin - sollte schon automatisch drin sein. 
- Sowie: HOW TO: Minicalender überm Inplay anzeigen.
```

Hier findet ihr einen weiteren Szenentracker für RPGs. Damit könnt ihr direkt beim Threaderstellen Teilnehmer, Ort und Datum, sowie auch eine Triggerwarnung eingeben. Die Szenen werden im Profil automatisch nach Datum sortiert angezeigt und können im Benutzer CP verwaltet werden. Es gibt verschiedene Benachrichtigungseinstellungen. 

**Wichtiges TODO für die Darstellung im mybb Kalender:**.      
Damit Szenen und Geburtstage direkt im Kalender von MyBB angezeigt werden können, ist diese Änderung **zwingend** nötig.  
empfohlen wird hier die Bearbeitung über Patches :) Ihr findet eine xml datei für den direkten import in patches, ansonsten hier aber auch die Änderungen, um sie manuell durchführen zu können:


calendar.php 
suchen nach  
```` 
eval("\$day_bits .= \"".$templates->get("calendar_weekrow_thismonth")."\";");
````
darüber einfügen 
````
 $plugins->run_hooks("calendar_weekview_day");
````


## **Allgemeine Beschreibung**   
Der Tracker funktioniert automatisch und ermöglicht es so, den Usern eine wunderbaren Überblick über all ihre Szenen zu haben. Beim Erstellen der Szene werden Datum, Zeit(feste Zeit oder freier String) und Ort eingetragen. Außerdem kann hier auch direkt eine Triggerwarnung eingestellt werden, welche die Szene mit einem Ausrufezeichen schon im Forumdisplay und auch in der Profilübersicht kennzeichnet.   
Teilnehmer können beim erstellen der Szene hinzugefügt werden, die Usernamen werden automatisch vervollständigt, so dass keine Tippfehler entstehen können. Anders als bei der Autovervollständigung von Mybb können jedoch auch zusätzliche Infos hinzugefügt werden, also zum Beispiel: 'John Smith, Jane Doe, und alle die möchten'
Wird eine Szene erstellt, erhalten die eingetragenen Teilnehmer einen Alert, auch wenn sie im Nachhinein hinzugefügt wurden.     
Antwortet auf die Szene ein Charakter der noch nicht eingetragen ist, kann er sich automatisch hinzufügen. Desweiteren können **alle** Teilnehmer die Szeneninformationen auch im Nachhinein bearbeiten.   
Szenen haben den Status offen oder geschlossen, welcher von den Teilnehmern gesetzt werden kann und mit dem Thread erledigt/unerledigt Plugin verbunden werden kann.   
Die Übersicht der Szenen findet sich im User CP, hier können die Nutzer ihre Szenen ansehen, filtern und verwalten.   
Im Profil wird automatisch eine Szenenübersicht nach Monaten und Jahren sortiert. Der Charakter kann Szenen aber auch ausblenden, sowie bei Wunsch im UCP dann doch wieder einblenden.    
Außerdem können User bestimmen, wann sie über eine Antwort informiert werden wollen. Immer (wenn ein anderer Charakter gepostet hat), wenn ein bestimmer Charakter gepostet hat, oder nie.  

### **Mini Kalendar**  
Einbindung im Footer oder im Header über
{$scenetracker_calendar} 

### **Admin CP**.
Mögliche (und nötige) Einstellungen im ACP:  
* Ob der Accountswitcher benutzt wird oder nicht
* Ob das Thema erledigt/unerledigt benutzt wird. (wenn ja aktiviert, wird beim schließen das Thema auch als erledigt markiert)
* Indexanzeige - Werden die Szenen auf dem Index angezeigt?
* Ob MyAlerts benutzt werden soll um den Benutzer über neue Szenen/Antworten zu informieren.
* Ob eine Private Nachricht verschickt werden soll um den Benutzer über neue Szenen/Antworten zu informieren.
* Ingame - Die ID des Ingames
* ID des Archivs.
* Geburtsfeld für Kalender. Standard, Profilfeld ( **Wichtig** (Format dd.mm.YYYY) ) oder Feld des 'Steckbrief im UCP Plugin'
* Geburtstagsfeld ID? Angabe der Profilfelds-ID oder der Bezeichnung für das Feld im 'Steckbrief im UCP Plugin'
* Erinnerung: Soll und wenn ja, nach wie vielen Tagen soll der User eine Erinnerung angezeigt bekommen?
* Ingame Zeitaum. Format: 2024-04, 2024-06, 2024-07
* Ingame Zeitraum 1. Tag: Datum des 1. Tags
* Ingame Zeitraum letzter Tag: Datum des letzter Tags
* ausgeschlossene Foren
* Kalender Szenen Ansicht - Alle Szenen: Dürfen Mitglieder auswählen das die Szenen von allen Charakteren angezeigt werden?
* Kalender Szenen Ansicht - Alle eigenen Szenen: Dürfen Mitglieder auswählen das die Szenen von allen eigenen (verbundenen) Charakteren angezeigt werden?
* Kalender Szenen Ansicht - Szenen des Charaktes: Dürfen Mitglieder auswählen das die Szenen nur von dem Charakter angezeigt werden, mit dem man online ist?
* Angabe Tageszeit: Soll das Datum für eine Szene mit fester Zeit (Datum + Zeit z.B. 24.02.01 - 11:00) oder mit offener Zeit, als Textfenster (z.B. Mittags) angegeben werden?

### **Benutzer CP**. (/usercp.php?action=scenetracker)  
Hier findet der User folgende Einstellungen.  
 
* *Szenenübersicht auf der Indexseite?*.  
Der User kann einstellen ob ihm die Szenen, in denen er mit posten dran ist auf dem Index angezeigt werden. 
* *Szenen aller Charaktere auf dem Index anzeigen?*  
Der User kann einstellen ob ihm die Szenen, aller Charaktere (accountswitcher) oder nur des Charakters angezeigt werden soll, mit dem er online ist. 
* *Szenenerinnerung nach x Tage(n)?*  
Wenn im ACP eingestellt, wird dem User einer Erinnerung auf dem Index angezeigt, wenn er in einer Szene zu lange nicht gepostet hat.
* *Settings für alle verbundene Charaktere oder nur diesen?* Sollen vorgenommene Einstellungen für den Kalender für alle Charaktere oder nur dem eingeloggten vorgenommen werden?
* *Mini Kalender: Welche Szenen sollen angezeigt werden?* (alle, alle des users, nur dieser)
* Großer Kalender: Welche Szenen sollen angezeigt werden?  (alle, alle des users, nur dieser)
* *Anzeige / Sortierung der Szenen*  
Der User kann die Szenen ganz verschieden Filtern. Nach Charakteren, Status und ob er dran ist oder nicht (und in jeglicher Kombination). 
* *Verwaltung der Szenen*  
Der User kann verschiedene Einstellungen vornehmen. Zum Beispiel ob die Szene im Profil angezeigt werden soll, ob und wann er **benachrichtigt** werden soll oder sie öffnen/schließen.
 
### **Beiträge erstellen/bearbeiten**.    
Es kann ein Datum und Zeit, Ort und bei Bedarf eine Triggerwarnung angegeben werden.    
Teilnehmer können eingetragen werden, Benutzernamen werden automatisch vervollständig, es kann aber auch eine Info wie 'und weitere' hinzugefügt werden.    
Charaktere, die auf den Beitrag antworten aber noch nicht als Teilnehmer eingetragen sind, können mit einem Häkchen bestimmen ob sie hinzugefügt werden wollen oder nicht. (Per Default auf hinzufügen).      

### **Index Anzeige**.
Auf dem Index über dem Footer, werden die Szenen des Spielers angezeigt, je nach Einstellung im User CP.     
Auch hier können direkt die wichtigsten Einstellungen vorgenommen werden      

### **Anzeige der Szenen im Profil:**.  
Sortiert nach Szenendatum. 
Szenen können auf Wunsch ausgeblendet werden.

### **Anzeige der Szenen im Forumdisplay :**.  
Szeneninfos im Forumdisplay anzeigen:
forumdisplay_thread öffnen   
{$scenetrackerforumdisplay} einfügen   


## **Demo**  

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_answer.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_index.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_newscene.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_profil.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_thread.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_ucp.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/treacker_threadedit.png?raw=true" style="width:450px">



## **HOW TO: Verbinden vom Szenentracker mit aheartforspinach's Archivierungsplugin**  
**Nur in älteren Versionen des Plugins nötig** 
öffne: inc/plugins/scenetracker.php  
suche nach:
```
  } elseif ($close == 0) {
    if (scenetracker_change_allowed($teilnehmer)) {
```
füge **darüber** ein:
```    
	if ($db->field_exists('archiving_inplay', 'forums')) {
   $fid = $db->fetch_field($db->simple_select("threads", "fid", "tid = {$tid}"), "fid");
   redirect("misc.php?action=archiving&fid={$fid}&tid={$tid}");
 }
```
  
öffne inc/plugins/archiving.php
suche nach:
```
$ipdate = $db->fetch_field($db->simple_select('ipt_scenes', 'date', 'tid = ' . $tid), 'date');
```
 **ersetze mit** :
```    
		// $ipdate = $db->fetch_field($db->simple_select('ipt_scenes', 'date', 'tid = ' . $tid), 'date');
		$ipdate = $db->fetch_field($db->simple_select('threads', 'scenetracker_date', 'tid = ' . $tid), 'scenetracker_date');
		$ipdate = strtotime($ipdate);
```
  

suche nach:
```
$query = $db->simple_select('ipt_scenes_partners', 'uid', 'tid = '. $thread['tid']);
```
 **ersetze mit** :
```    
// $query = $db->simple_select('ipt_scenes_partners', 'uid', 'tid = '. $thread['tid']);
	$query = $db->fetch_field($db->simple_select('threads', 'scenetracker_user', 'tid = ' . $thread['tid']), "scenetracker_user");
	
```

  

suche nach:
```
	$partners = [];
	while($row = $db->fetch_array($query)) {
		$partners[] = $row['uid'];
	}
```
 **ersetze mit** :
```    
		$partners_name = explode(",", $query);
		foreach($partners_name as $name) {
			$partneris = get_user_by_username($name);
			$partners[] = $partneris['uid'];
		}
```


## **HOW TO: Minicalender über dem Ingame**


suchen nach:
```
$plugins->add_hook('global_intermediate', 'scenetracker_minicalendar');
```

**ersetzen mit**:
```
$plugins->add_hook("build_forumbits_forum", "scenetracker_minicalendar");
```


dann unter 
```   global $db, $mybb, $templates, $scenetracker_calendar;```

 (X ersetzen mit fid) 

```
if ($forum['fid'] == "X") {  
```

und dann ans ende der funktion
```
    eval("\$scenetracker_calendar .= \"" . $templates->get("scenetracker_calendar") ."\";"); 
```

**ersetzen mit**
```
    $forum['minicalender'] = eval($templates->render('scenetracker_calendar'));
  }
```


und
```
function scenetracker_minicalendar()
```
ersetzen mit
```
function scenetracker_minicalendar(&$forum)
```
 

Die Ausgabe erfolgt dann über $forum['minicalender']




