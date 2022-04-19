# Szenentracker
Readme ist in bearbeitung :)     
Hier findet ihr einen weiteren Szenentracker für RPGs. Damit könnt ihr direkt beim Threaderstellen Teilnehemr, Ort und Datum, sowie auch eine Triggerwarnung eingeben. Die Szenen werden im Profil automatisch nach Datum sortiert angezeigt und können im Benutzer CP verwaltet werden. Es gibt verschiedene Benachrichtigungseinstellungen. 

**Benutzer CP**. (/usercp.php?action=scenetracker)  
Hier findet der User 3 Einstellungen.  
 
* *Szenenübersicht auf der Indexseite?*.  
Der User kann einstellen ob ihm die Szenen, in denen er mit posten dran ist auf dem Index angezeigt werden. 
* *Szenen aller Charaktere auf dem Index anzeigen?*  
Der User kann einstellen ob ihm die Szenen, aller Charaktere (accountswitcher) oder nur des Charakters angezeigt werden soll, mit dem er online ist. 
* *Szenenerinnerung nach x Tage(n)?*  
Wenn im ACP eingestellt, wird dem User einer Erinnerung auf dem Index angezeigt, wenn er in einer Szene zu lange nicht gepostet hat.
* *Anzeige / Sortierung der Szenen*  
Der User kann die Szenen ganz verschieden Filtern. Nach Charakteren, Status und ob er dran ist oder nicht (und in jeglicher Kombination). 
* *Verwaltung der Szenen*  
Der User kann verschiedene Einstellungen vornehmen. Zum Beispiel ob die Szene im Profil angezeigt werden soll, ob und wann er benachrichtigt werden soll oder sie öffnen/schließen.
 
  
  
**Was kann der Tracker:** 
Szeneninfos im Thread hinzufügen (Zeit, Ort, Triggerwarnung, Teilnehmer)    
Alerts bei neuer Antwort (Einstellungsmöglichkeit: Nie, bei Antwort eines bestimmten Users, immer) 
Anzeige der Szenen in einem Minikalener 
Anzeigen der Szenen im Mybb Kalender (Achtung dazu weiter unten Anleitung zu beachten!)  
Alle Szenenteilnehmer können Szenen schließen/öffnen.   
Alle Szenenteilnehmer können die Szeneninfos bearbeiten.   
  
**Anzeige der Szenen im Profil:**.  
Sortiert nach Szenendatum. 
Szenen können auf Wunsch ausgeblendet werden.
  
**Verwaltung/Übersicht der Szenen** im UCP.  
User können einstellen:  
Szenenübersicht auf der Indexseite?  
Szenen aller Charaktere auf dem Index anzeigen?  
Szenen Erinnerung?  
Außerdem gibt es im UCP viele Filtermöglichkeiten.  
(Szenen pro Charakter anzeigen, Szenen anzeigen wo man dran ist / nicht dran ist /geschlossen... etc. :D )   


**Wichtig für Darstellung im mybb Kalender:**. 
suchen nach  
```` 
eval("\$day_bits .= \"".$templates->get("calendar_weekrow_thismonth")."\";");
````
darüber einfügen 
````
 $plugins->run_hooks("calendar_weekview_day");
````

**Demo**  

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_answer.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_index.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_newscene.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_profil.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_thread.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_ucp.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/treacker_threadedit.png?raw=true" style="width:450px">

