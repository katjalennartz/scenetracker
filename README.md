# Szenentracker
Readme ist in bearbeitung :)     
Hier findet ihr einen weiteren Szenentracker für RPGs. Damit könnt ihr direkt beim Threaderstellen Teilnehemr, Ort und Datum, sowie auch eine Triggerwarnung eingeben. Die Szenen werden im Profil automatisch nach Datum sortiert angezeigt und können im Benutzer CP verwaltet werden. Es gibt verschiedene Benachrichtigungseinstellungen. 

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

**Mini Kalendaer**  
Einbindung im Footer oder im Header über
{$scenetracker_calendar} 
    
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
 
**Beiträge erstellen/bearbeiten**.    
Es kann ein Datum und Zeit, Ort und bei Bedarf eine Triggerwarnung angegeben werden.    
Teilnehmer können eingetragen werden, Benutzernamen werden automatisch vervollständig, es kann aber auch eine Info wie 'und weitere' hinzugefügt werden.    
Charaktere, die auf den Beitrag antworten aber noch nicht als Teilnehmer eingetragen sind, können mit einem Häkchen bestimmen ob sie hinzugefügt werden wollen oder nicht. (Per Default auf hinzufügen).      

**Index Anzeige**.
Auf dem Index über dem Footer, werden die Szenen des Spielers angezeigt, je nach Einstellung im User CP.     
Auch hier können direkt die wichtigsten Einstellungen vorgenommen werden      

**Anzeige der Szenen im Profil:**.  
Sortiert nach Szenendatum. 
Szenen können auf Wunsch ausgeblendet werden.



**Demo**  

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_answer.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_index.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_newscene.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_profil.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_thread.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/tracker_ucp.png?raw=true" style="width:450px">

<img src="https://github.com/katjalennartz/scenetracker/blob/main/treacker_threadedit.png?raw=true" style="width:450px">

