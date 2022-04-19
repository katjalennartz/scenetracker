# Szenentracker

Diesen Szenentracker habe ich vor allem für meine Boards gebaut, kann aber gerne benutzt werden ;) Allerdings werde ich hier keinen großen Support bieten, oder Wunschfunktionen einbauen ^^  
Der Tracker kann was er kann und wenn ihr ihn mögt nehmt ihr gerne, wenn nicht. Auch okay.  
Bugs/Fehler könnt ihr mir gerne bei Discord (auch wenn ihr allgemeine Fragen habt) schreiben oder hier als Issue melden. Im SG gibt es hierzu keinen Supportthread.   

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

