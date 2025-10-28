# Szenentracker
## Updates: 		

Last: Okto 2025  
Version: 1.0.13

### Must Have
- RPG Modul von Lara
- Der Ordner risuenas_updatefile mit der Datei risuena_updatefile.php
- Accountswitcher
- Für Export als PDF: https://github.com/dompdf/dompdf/releases **Installationshinweis beachten**
- Für Export als Word: https://github.com/PHPOffice/PHPWord **Installationshinweis beachten**

### Todo nach Update: 
- Dateien neu hochladen
- Reiter RPG-Erweiterungen -> Plugins aktualisieren
- **achtung** Templateänderungen, nachprüfen ob sie automatisch mit dem Update übernommen werden

### Installationshinweise Bibliotheken (PDF und Word) 
#### PDF
- auf https://github.com/dompdf/dompdf/releases gehen
- die neuste Version suchen, hier den ersten Zip Ordner nehmen (der mit dem Viereck als Symbol!) Nur der enthält alle Dateien die ihr braucht.
- falls noch nicht vorhanden im Hauptverzeichnis des Forums einen Ordner 'lib' erstellen
- dort den gesamten Ordner (nach dem entpacken) 'dompfd' reinladen

#### Word
- auf https://github.com/PHPOffice/PHPWord gehen und das Rep runterladen
- entpacken und den Ordner src/PHPWord kopieren (ihr braucht nur diesen Ordner)
- alls noch nicht vorhanden im Hauptverzeichnis des Forums einen Ordner 'lib' erstellen
- in lib einen Ordner 'PhpOffice' erstellen
- in PhpOffice den Ordner PHPWord einfügen

### Changelog: 
#### 1.0.12 -> 1.0.13
- bugfixes
- entfernen internen Updatefunktionen
- Einfügen von risuenas_updatefile/risuena_updatefile.php (enthält die updatefunktionen)
- {$plotoutput} hinzufegügt zu template calendar_weekrow_thismonth

#### 1.0.11 -> 1.0.12
- Export als PDF oder Word, wenn gewollt (Im ACP einstellbar)
- bugfixes
  
#### 1.0.10 -> 1.0.11
- Auslagern der Options fürs UCP aus der PHP in Templates (einfacher anzupassen)
- Aufspaltung Alerts und Index.
- Hinzufügen der Auswahl für Index und Alerts 'always' (vorher nur 'ein anderer user hat gepostet oder nie oder ein bestimmter)  
- Einstellung des Szenenreminders pro Szene

#### 1.0.9 -> 1.0.10
- Integration RPG Modul von Lara
- Optimierung Update Prozess
- Einfügen Filtern nach Usernamen in UCP
- Counter für Szenen Global verfügbar über {$counter} (keine manuelle änderungen in der php mehr nötig)
- Auslagern von Trigger in eigenes Template -> bessere (individuelle) Anpassung
- Auslagern von Edit in eigenes Template -> bessere (individuelle) Anpassung
- Auslagern Infos in Suche in eigenes Template
   
#### 1.0.8 -> 1.0.9  
- Optimierungen für Minikalender. Einstellung ob der Kalender über dem Ingame angezeigt werden soll
- -> wenn ja dann {$forum['minicalender']} in forumbit_depth1_cat hinzufügen
 
#### 1.0.7 -> 1.0.8  
- Optimierungen für Minikalender. Hinzufügen der Klasse 'fullmoon' entfernt, weil fehlerhaft. Alternativ hier ganz unten ein Javascript schnippsel der eingefügt werden kann bei Bedarf.
- scenetracker_calendar wieder hinzugefügt, um besser einen wrapper für den Kalender zu haben. Kann benutzt werden muss nicht
- -> wenn ja dann $scenetracker_calendar mit $scenetracker_calendar_wrapper im footer,tpl ersetzen.

#### 1.0.6 -> 1.0.7  
- bugfix: Minikalender - Korrektur bei Geburtstagen mit Standardfeld
- bugfixes: php8
- Extra: hinzufügen von Funktion um Szenentracker auf dem Index einklappbar zu machen. Template änderungen dafür weiter unten
  
#### 1.0.5 -> 1.0.6    
Korrektur Anzeige Events (jetzt aber hoffentlich wirklich :D )     
- scenetracker_calendar_bit ```</div>{$kal_day}```ersetzen mit ```{$kal_day}</div>```
- Neue Templates: scenetracker_calendar_day_pop, scenetracker_calender_popbit, scenetracker_calender_plot_bit, scenetracker_calender_birthday_bit, scenetracker_calender_scene_bit, scenetracker_calender_event_bit, scenetracker_calendar_day, scenetracker_calendar_weekrow
- Änderung Language Datei
- änderungen css: ```.day.st_mini_scene.lastmonth {
    opacity: 0.1;
}``` hinzufügen

#### 1.0.4 -> 1.0.5    
- savescenes.php und getusernames.php können gelöscht werden
##### Bugfix:
- Korrektur Anzeige von Events - Verschiebung des Tags, sowie anzeige einmaliges Ergebnis.

#### 1.0.3 -> 1.0.4    

##### Bugfix:
- Korrektur Anzeige von Events die nur einen Tag dauern.

##### Improvements:
- Abfangen wenn der Accountswitcher nicht installiert ist
- automatisches einfügen der Minikalender Variabel in den Footer

#### 1.0.2 -> 1.0.3    

##### Bugfix
- Korrektur Javascript in scenetracker.js / anpassung auf neues Feld / Anpassung AJAX Request
- Verschieben der ausgelagerten php Datein in Plugin datei  

##### New Feature:   
- Auflistung aller Szenen aller User nach Datum unter /misc.php?action=scenelist
  
##### Neues Template:
- scenetracker_misc_allscenes  
   
## Wichtige Infos: 

**Die Updates müssen natürlich nur durchgeführt werden, wenn der Tracker vorher schon installiert war. Wenn nicht, reicht es das Plugin ganz normal zu installieren** 		

```diff
- Sowie: HOW TO: Minicalender überm Inplay anzeigen.
```

Hier findet ihr einen weiteren Szenentracker für RPGs. Damit könnt ihr direkt beim Threaderstellen Teilnehmer, Ort und Datum, sowie auch eine Triggerwarnung eingeben. Die Szenen werden im Profil automatisch nach Datum sortiert angezeigt und können im Benutzer CP verwaltet werden. Es gibt verschiedene Benachrichtigungseinstellungen. 

**Wichtiges TODO für die Darstellung im mybb Kalender:**.      
Damit Szenen und Geburtstage direkt im Kalender von MyBB angezeigt werden können, ist diese Änderung **zwingend** nötig.  
empfohlen wird hier die Bearbeitung über Patches :) Ihr findet eine xml datei für den direkten import in patches, ansonsten hier aber auch die Änderungen, um sie manuell durchführen zu können:


calendar.php 
suchen nach  
```
eval("\$day_bits .= \"".$templates->get("calendar_weekrow_thismonth")."\";");
```
darüber einfügen 
```
 $plugins->run_hooks("calendar_weekview_day");
```


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

### **Anzeige der Szenen im Forumdisplay :**  
Szeneninfos im Forumdisplay anzeigen:
forumdisplay_thread öffnen   
{$scenetrackerforumdisplay} einfügen   


## **Demo**  

<img src="https://github.com/katjalennartz/scenetracker/blob/main/screens/tracker_answer.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/screens/tracker_index.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/screens/tracker_newscene.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/screens/tracker_profil.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/screens/tracker_thread.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/screens/tracker_ucp.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/screens/treacker_threadedit.png?raw=true" style="width:450px">


## **HOW TO: Minicalender über dem Ingame nur vor Version 1.0.9 nötig**
suchen nach:
```
$plugins->add_hook('global_intermediate', 'scenetracker_minicalendar');
```

**ersetzen mit**:
```
$plugins->add_hook("build_forumbits_forum", "scenetracker_minicalendar");
```

und
```
function scenetracker_minicalendar()
```
***ersetzen mit***
```
function scenetracker_minicalendar(&$forum)
```

dann unter 
```$enddate_ingame = $mybb->settings['scenetracker_ingametime_tagend'];```

 (X ersetzen mit fid) 

```
$forum['minicalender'] = "";
if ($forum['fid'] == "X") {  
```
einfügen


und dann am ende der funktion
```
eval("\$scenetracker_calendar .= \"" . $templates->get("scenetracker_calendar_bit") . "\";");
```

**ersetzen mit**
```
$forum['minicalender'] .= eval($templates->render('scenetracker_calendar_bit'));
}
```


Die Ausgabe erfolgt dann über $forum['minicalender'] im forumbit_depth1_cat


***Vor Version 1.0.6***  
suchen nach:
```
$plugins->add_hook('global_intermediate', 'scenetracker_minicalendar');
```

**ersetzen mit**:
```
$plugins->add_hook("build_forumbits_forum", "scenetracker_minicalendar");
```


dann unter 
```$enddate_ingame = $mybb->settings['scenetracker_ingametime_tagend'];```
 (X ersetzen mit fid) 

```
$forum['minicalender'] = "";
if ($forum['fid'] == "X") {  
```

und dann ans ende der funktion
```
    eval("\$scenetracker_calendar .= \"" . $templates->get("scenetracker_calendar") ."\";"); 
```

**ersetzen mit**
```
    $forum['minicalender'] .= eval($templates->render('scenetracker_calendar'));
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
 

Die Ausgabe erfolgt dann über ```{$forum['minicalender']} ```



## **HOW TO: Javascript für extra Klasse im Minikalender je nach Eventname**

Einmal ganz ans ende des footer.tpl:  


```
  <script>
        $(document).ready(function(){
            var searchText = "Fullmoon";
            $(".day.event").each(function(){
                if ($(this).text().includes(searchText)) {
                    $(this).addClass("fullmoon");
                }
            });
        });
    </script>
```

Nennt ihr euer Event jetzt 'Fullmoon' wird die klasse 'fullmoon' hinzugefügt. Ihr könnt das script auch kopieren, anpassen und mehrfach verwenden.  
``` var searchText = "Fullmoon"; ```
hier den Eventnamen eintragen
``` $(this).addClass("fullmoon"); ```
hier die Klasse die hinzugefügt werden soll

## **HOW TO: Szenentracker auf dem Index einklappbar machen**

scenetracker_index_main template inhalt ersetzen mit
```
<table border="0" class="tborder scenetrackerindex">
<thead>
<tr>
<td class="thead{$expthead}">
<div class="expcolimage"><img src="{$theme['imgdir']}/collapse{$collapsedimg['szenenindex']}.png" id="szenenindex_img" class="expander" alt="{$expaltext}" title="{$expaltext}" /></div>

<div><strong>Szenen {$counter}</strong></div>
</td>
</tr>
</thead>

<tbody style="{$collapsed['szenenindex_e']}" id="szenenindex_e">
<tr>
<td align="center" style="white-space: nowrap"> 
	<div class="scenetracker_index wrapper_container">
    {$scenetracker_index_bit_chara}
  </div></td></tr>
</tbody>
</table>
<br /> ```
