<?php   

######################################################################
#/------------------------------------------------------------------\#
#|           ____               _____   __    __                    |#
#|           |__   |      /\      |    |  |  |  |  \ /              |#
#|           |     |     /--\     |    |-{   |  |   X               |#
#|           |     |__  /    \    |    |__|  |__|  / \              |#
#|                                                                  |#
#\------------------------------------------------------------------/#
######################################################################


### flat_box.php              ###

### Copyright:                ###
### Thomas Schmieder          ###
### Dennis Riehle             ###

### Stand 13.07.2005 21:39:52 ###
### Version 0.2.3             ###


######################################################################
#/------------------------------------------------------------------\#
#|                            Changelog                             |#
#\------------------------------------------------------------------/#
######################################################################

/*

- Die Parameter $_userdata und $_rights für flat_rec_select werden bis jetzt
  noch nicht gebraucht, weshalb ich sie optional gemacht habe.
- In flat_file_backup noch eine Prüfung eingebaut, ob der Komprimierungsgrad
  auch 1 bis 9 ist - ist er größer, wird nicht komprimiert
- Noch mal flat_file_backup - der zweite Paramter mit dem Komprimierungsgrad
  wurde erst in 4.2 eingeführt, deshalb noch eine Abfrage eingebaut, ob die
  verwendete PHP Version >= 4.2 ist.

*/

######################################################################
#/------------------------------------------------------------------\#
#|     Definition der benötigten Funktionen mit Erklärung           |#
#\------------------------------------------------------------------/#
######################################################################

/*

Dauerfunktionen:
================
- get_error_description               => Liefert die Beschreibungen zu einem
									     Fehler, je nach definierten Konstanten,
										 werden diese ausgegeben oder nicht.

- get_microtime                       => Diese Funktion liefert einen Timestamp
										 zurück, der die aktuelle Zeit in Sekunden
										 mit 4 Nachkommastellen angibt.
										 Was in PHP5 durch microtime(true); erledigt
										 werden kann, kann in PHP4 nur mit einem
										 Workaround erzielt werden.

- get_time_s                          => Liefert die aktuelle Zeit zurück, 
                                         in einem speziallen Timestamp:
										 JahrMonatTagStundeMinuteSekunde.Millisekunden

- strip                               => Rekursive Entfernung der 
                                         Maskierungs-Backslashes in $data, sofern
										 magic_quotes aktiviert sind

- flat_open_lock				      => Öffnet $filename und nutzt als 
                                         Sperrmethode $lockmode


Einmalige Funktionen:
=====================
- flat_file_create			          => Erstellt die Datei $filepath

- flat_file_alter                     => Ändert die Dateien, wenn z.B. ein 
                                         Feld dazukommt


Grundfunktionen (BIOS):
=======================
- flat_rec_insert                     => Schreibt einen neuen Datensatz in 
                                         die angegebene Datei

- flat_rec_select                     => holt eine Recordliste aus 
                                         einem File

- flat_rec_update                     => Erneuert Datensätze in $filepath 
                                         oder fügt sie hinzu, wenn die ID noch
                                         nicht vorhanden war.

- flat_rec_delete					  => Löscht die Datensätze in $filename


Anzeigefunktionen:
==================
- flat_rec_get_listdata               => Besorgt alle notwendigen Daten für
                                         die HTML Ausgabe mit Blätterfunktion

- flat_rec_make_list                  => Erzeugt die HTML Ausgabe für die 
                                         Anzeige mit Blätterfunktion

- flat_rec_make_detail                => Erzeugt die HTML Ausgabe für 
                                         einen angeforderten Datenksatz

- flat_rec_search                     => Sucht je nach Auswahl im Archiv oder 
                                         aktuell.dat nach String


Backup- und Archivierungsfunktionen:
====================================
- flat_rec_copy                       => Kopiert eine bestimmte Menge an 
                                         Datensätzen, wird zur Archivierung genutzt

- flat_file_backup                    => Erstellt ein Backup der
                                         Datei $filepath am Ort $backuppath, 
										 zusätzlich kann ein Komprimierungscode 
										 angegeben werden.

*/


#--------------------------------------------------------------------
# includes und Konstanten
#--------------------------------------------------------------------
define ('FLAT_MAXFILESIZE', 1000000);
define ('PRINT_NOTICES',    true   );
define ('PRINT_WARNINGS',   true   );
define ('PRINT_FAILES',     true   );


#--------------------------------------------------------------------
# Dauerfunktionen
#--------------------------------------------------------------------
function get_error_description($errnr, $returndescr = false)
{
  //Notiz-Meldungen, 0 und 100+
  $_notices = array(
  		 0 => "No errors, function was successfully carried out."
	);
  //Warn-Meldungen, 1 bis 49
  $_warnings = array(
  		 2 => "Low-Level Error: Flat Box wasn't able to find the selected file.",
   		 3 => "Low-Level Error: Selected file already exists.",
   		 4 => "Low-Level Error: There are no data records, selected file is empty.",
   		 5 => "Low-Level Error: Authorisation Error on low-level section.",
   		 8 => "Low-Level Error: Flat Box wasn't able to write in the file.",
		10 => "Low-Level Error: Maximum allowable filesize is transgressed.",
		11 => "Data Error: File format doesn't match.",
		12 => "Data Error: Still erroneous data records in ['denied'].",
		13 => "Data Error: There are still some data records that couldn't be worked up.",
		14 => "Data Error: There are no data records, but the file format is OK."
	);
  //Fehler-Meldungen in der Userverwaltung, 50 bis 99
  $_failes = array(
  		50 => "Authorisation Error, no further information avaliable."
	);
  
  //Wenn keine Error Nummer übergeben wurde, abbrechen
  if(!is_numeric($errnr)) return false;
  //Prüfen, ob es sich um eine existierende Notice-Meldung handelt
  elseif($errnnr == 0 OR $errnr >= 100 AND isset($_notices[$errnr]))
  {
  	$message = $_notices[$errnr];
	//Wenn entsprechend definiert, Meldung ausgeben
	if(defined('PRINT_NOTICES') AND PRINT_NOTICES)
	{
	  echo "<b>Notice:</b> " . $message . "<br /><br />\n";
	}
  }
  //Prüfen, ob es sich um eine existierende Warning-Meldung handelt
  elseif($errnr >= 1 AND $errnr <= 49 AND isset($_warnings[$errnr]))
  {
  	$message = $_warnings[$errnr];
	//Wenn entsprechend definiert, Meldung ausgeben
	if(defined('PRINT_WARNINGS') AND PRINT_WARNINGS)
	{
	  echo "<b>Warning:</b> " . $message . "<br /><br />\n";
	}
  }
  //Prüfen, ob es sich um eine existierende Fehler-Meldung handelt
  elseif($errnr >= 50 AND $errnr <= 99 AND isset($_failes[$errnr]))
  {
  	$message = $_failes[$errnr];
	//Wenn entsprechend definiert, Meldung ausgeben
	if(defined('PRINT_WARNINGS') AND PRINT_WARNINGS)
	{
	  echo "<b>Warning:</b> " . $message . "<br /><br />\n";
	}
  }
  //Ansonsten ist der Errorcode unbekannt
  else
  {
  	$message = "An unknown error occured, please refer to Thomas Schmieder or "
			 . "Dennis Riehle to get more information about error " . $errnr;
	echo "<b>Fatal Error:</b> " . $message . "<br /><br />\n";
  }
  if($returndescr)
  {
  	return $message;
  }
  else
  {
  	return $errnr;
  }
}

#--------------------------------------------------------------------
function get_microtime()
{
  //Wir eine PHP Version kleiner 5 verwendet?
  if(version_compare(phpversion(), "5", "<"))
  {
	//Dann müssen wir uns die Zahl selber zusammenstellen
	list($usec, $sec) = explode(" ",microtime());
	$microtime = (float)$usec + (float)$sec;
  }
  else
  {
    //PHP5 kennt einen opt. Parameter für microtime(),
	//der das bereits alles erledigt
	$microtime = microtime(true);
  }
  return $microtime;
}

#--------------------------------------------------------------------
function get_time_s()
{
  list($usec, $sec) = explode(" ",microtime());
  $time_s = date("YmdHis").substr($usec,1,7);
  
  return $time_s;
}

#--------------------------------------------------------------------
function strip($data)        
{
  if (!get_magic_quotes_gpc())
  {
    return $data;
  }
  
  if (is_array($data))
  {
    foreach($data as $key => $val)
    {
      $data[$key] = strip($val); 
    }
  }
  else
  {
    $data = stripslashes($data);
  }  
  
  return $data;
}


#--------------------------------------------------------------------
function flat_open_lock($filepath,$lockmode)
{  
  # Lockdatei öffnen oder anlegen
  for ($x=0;$x<5;$x++)
  {
    if($lh = @fopen($filepath,"a+")) break;
    usleep(8000);  ## 8ms warten bis zum nächsten Versuch
  }

  if (!$lh) return false;  
  
  # Lockversuch
  for ($x=0;$x<5;$x++)
  {
    if (@flock($lh,$lockmode + LOCK_NB)) return $lh;
    usleep(8000);  ## 8ms warten bis zum nächsten Versuch
  }
  
  fclose($lh);
  return false;
}


#--------------------------------------------------------------------
# Einmalige Funktionen
#--------------------------------------------------------------------
function flat_file_create($filepath)      
{
 clearstatcache();                     ## Statusbuffer rücksetzen
 
 if(file_exists($filepath)) return get_error_description(3);  ## Datei existiert schon 
 
 $fp = flat_open_lock($filepath,LOCK_EX);
 
 if($fp)
 {                                     ## wenn File > 0 war es schon da 
   if (filesize($filepath) > 0)        ## man muss das hier nochmal bzw 
                                       ## eigentlich erst hier testen,
   {                                   ## weil zwischen Handle-Beschaffung 
                                       ## und Lock Zeit vergeht
     fclose($fp); 
     return get_error_description(3);  ## Datei existiert schon
   }
   $time_s = get_time_s();
   $time_u = time();
   
   $_file         = array();
   $_file['meta'] = array();
   $_file['data'] = array();
   
   $_file['meta']['created']    = $time_u;      ## erstellt am
   $_file['meta']['lastupdate'] = $time_s;      ## letztes Write   
   $_file['meta']['lastid']     = 0;
   $_file['meta']['amount']     = 0;
   
   $_file_packed = serialize($_file);
   fseek($fp,0);
   fwrite($fp,$_file_packed,strlen($_file_packed));
   fclose($fp);
   
   return get_error_description(0);    ##Code für kein Fehler
 }
 else return get_error_description(5); ##Code für Datei nicht geöffnet 
}

#--------------------------------------------------------------------
function flat_file_alter()
{
  
  
}


#--------------------------------------------------------------------
# Grundfunktionen (BIOS)
#--------------------------------------------------------------------
function flat_rec_insert($filepath,&$_recdata)
{
  //status-Buffer rücksetzen
  clearstatcache();
  
  //Datei öffnen und locken, bei Fehler abrechen
  $fp = flat_open_lock($filepath,LOCK_EX);
  if (!$fp) return get_error_description(5); ## Datei konnte nicht gesperrt werden
  
  // maximale Dateigröße prüfen
  $filesize = filesize($filepath);
  if ($filesize > FLAT_MAXFILESIZE)
  {
    fclose($fp);
    return get_error_description(10);  ## max. zulässige Dateigröße überschritten
  }
       
  //Datei entpacken
  fseek($fp,0,SEEK_SET);
  $_file_packed = fread($fp,$filesize);
  $_file = unserialize($_file_packed);
     
  
  //Zeitstempel erzeugen und merken
  $time_s = get_time_s();
  $time_u = time();

  //$_file anlegen, falls nicht vorhanden
  if(empty($_file))
  {
    $_file = array();
    $_file['meta'] = array();
    $_file['data'] = array();

    $_file['meta']['created'] = $time_u;
    $_file['meta']['lastupdate'] = $time_s;       
    $_file['meta']['lastid'] = 0;
    $_file['meta']['amount'] = 0;
  }
  else
  {
    //Dateiformat überprüfen
    if (!isset($_file['meta']['created']) or
        !isset($_file['meta']['lastupdate']) or 
        !isset($_file['meta']['lastid']) or
        !isset($_file['meta']['amount']) or 
        !is_array($_file['data']))
    {
      fclose($fp);
      return get_error_description(11);  ## Dateiformat passt nicht
    }

    if (!empty($_recdata['denied']) or !empty($_recdata['meta'])) 
    {
      fclose($fp);                       ## noch fehlerhafte Datensätze im Array
      return get_error_description(12);  ## oder noch Ergebnisdaten vorhanden 
    }
    $_recdata['meta']   = array();       ## es könnten ja noch leere Strings sein
    $_recdata['denied'] = array();
  }
     
  $_recdata['meta']['rec_inserted'] = 0; ## Anzahl der fehlerfrei updated Records
  $_recdata['meta']['rec_denied']  = 0;  ## Anzahl der abgelehnten Records

  foreach($_recdata['data'] as $key => $_record)
  {
    if(!empty($_record))
    { 
      $_recdata['meta']['rec_inserted'] ++; ## fehlerfrei eingefügten DS zählen
      $_file['meta']['lastid'] ++;    
      $_file['meta']['amount'] ++;            
    
      $new_key = $_file['meta']['lastid'];
    
      $_file['data'][$new_key] = $_record;   ## Daten übertragen
      $_file['data'][$new_key]['lastupdate'] = $time_s; ## Aktualisierungsdatum eintragen 
      $_file['data'][$new_key]['created']   = $time_u;  ## Erstelldatum eintragen
      unset($_recdata['data'][$key]);        ## Record aus Auftragsliste löschen
    
      $_recdata['rec_inserted'][$key]['new_id'] = $new_key; ## Erteilter Schlüssel 
    } 
    else
    {
      $_recdata['denied'][$key] = $_record;  ## abgelehnter Datensatz 
      $_recdata['meta']['rec_denied'] ++;    ## "abgelehnt" zählen
    }
  }

  // wenn Datensätze eingefügt wurden
  if ($_recdata['meta']['rec_inserted'] > 0)
  {
    // letzte Veränderung eintragen 
    $_file['meta']['lastupdate'] = $time_s;

    //Datei verpacken
    $_file_packed = serialize($_file);
       
    //und abspeichern
    fseek($fp,0);
    ftruncate($fp,0);
    $writeok = @fwrite($fp,$_file_packed,strlen($_file_packed));
  }   

  @fclose($fp);
 
  //Rückgabewert der Funktion
  if (!$writeok)
  {
    return get_error_description(8);     # Fehler beim Schreiben der Daten;
  }  

  if (count($_recdata['denied'])==0)
  {
    return get_error_description(0);       # kein Fehler aufgetreten, alle Sätze verarbeitet.
  }
  else
  {
    return get_error_description(13);      # nicht alle Sätze konnten verarbeitet werden
  }
}

#--------------------------------------------------------------------
function flat_rec_select($filepath,&$_recdata,$_userdata = false,$_rights = false)
{
  //status-Buffer rücksetzen
  clearstatcache();
  
  //Datei öffnen und locken, bei Fehler abrechen
  $fp = flat_open_lock($filepath,LOCK_SH);  
  
  if (!$fp) return get_error_description(5);           ## Datei konnte nicht gesperrt werden
  
  // maximale Dateigröße prüfen
  $filesize = filesize($filepath);
  if ($filesize > FLAT_MAXFILESIZE + 5000)
  {
    fclose($fp);
    return get_error_description(10);  ## max. zulässige Dateigröße überschritten
  }
       
  //Datei entpacken
  fseek($fp,0,SEEK_SET);
  $_file_packed = fread($fp,$filesize);
  fclose($fp);       
  $_file = unserialize($_file_packed);
  
  //abbrechen, falls $_file leer ist
  if(empty($_file) or ($filesize == 0))
  {
    return get_error_description(4);
  }
  else
  {
    //Dateiformat überprüfen
    if (!isset($_file['meta']['created']) or
        !isset($_file['meta']['lastupdate']) or 
        !isset($_file['meta']['lastid']) or
        !isset($_file['meta']['amount']) or 
        !is_array($_file['data']))
    {
      return get_error_description(11);      ## Dateiformat passt nicht
    }
  
     
    $_recdata = array();
    $_recdata['meta'] = $_file['meta'];      ## Metadaten übertragen 
    $_recdata['meta']['rec_denied']    = 0;  ## Anzahl der verbotenen Records
    $_recdata['meta']['rec_selected']  = 0;  ## Anzahl der gelieferten Records


    foreach($_file['data'] as $key => $_record)
    {
      if(isset($_record['rights']) and (false))          ## Einschränkende rechte vorhanden
      {
        $_recdata['meta']['rec_denied'] ++;              ## mangelnde Userrechte
      }
      else
      {
        $_recdata['meta']['rec_selected'] ++;            ## erlaubter Datensatz 
        $_recdata['data'][$key] = $_record;              ## Daten übertragen
      }
    }
    ksort($_recdata['data']);                            ## nach IDs sortieren
  }
  
  //Rückgabewert der Funktion
  if ($_recdata['meta']['rec_denied'] == 0)              ## alle Sätze erlaubt
  {
    return get_error_description(0);       # kein Fehler aufgetreten, alle Sätze verarbeitet.
  }
  else
  {
    return get_error_description(13);      # nicht alle Sätze konnten verarbeitet werden
  }
}

#--------------------------------------------------------------------
function flat_rec_update($filepath,&$_recdata)
{
  //status-Buffer rücksetzen
  clearstatcache();
  
  //Datei öffnen und locken, bei Fehler abrechen
  $fp = flat_open_lock($filepath,LOCK_EX);
  if (!$fp) return get_error_description(5); ## Datei konnte nicht gesperrt werden
  
  // maximale Dateigröße prüfen
  $filesize = filesize($filepath);
  if ($filesize > FLAT_MAXFILESIZE)
  {
    fclose($fp);
    return get_error_description(10);  ## max. zulässige Dateigröße überschritten
  }
  
  //Datei entpacken
  fseek($fp,0,SEEK_SET);
  $_file_packed = fread($fp,$filesize);
  $_file = unserialize($_file_packed);
     
  
  //Zeitstempel erzeugen und merken
  $time_s = get_time_s();
  $time_u = time();

  //$_file anlegen, falls nicht vorhanden
  if(empty($_file))
  {
    $_file = array();
    $_file['meta'] = array();
    $_file['data'] = array();

    $_file['meta']['created'] = $time_u;
    $_file['meta']['lastupdate'] = $time_s;       
    $_file['meta']['lastid'] = 0;
    $_file['meta']['amount'] = 0;
  }
  else
  {
    //Dateiformat überprüfen
    if (!isset($_file['meta']['created']) or
        !isset($_file['meta']['lastupdate']) or 
        !isset($_file['meta']['lastid']) or
        !isset($_file['meta']['amount']) or 
        !is_array($_file['data']))
    {
      fclose($fp);
      return get_error_description(11);           ## Dateiformat passt nicht
    }

    if (!empty($_recdata['denied']) or !empty($_recdata['meta'])) 
    {
      fclose($fp);                       ## noch fehlerhafte Datensätze im Array
      return get_error_description(12);                         ## oder noch Ergebnisdaten vorhanden 
    }
    $_recdata['meta']   = array();       ## es könnten ja noch leere Strings sein
    $_recdata['denied'] = array();
  }
     
  $_recdata['meta']['rec_updated'] = 0;  ## Anzahl der fehlerfrei updated Records
  $_recdata['meta']['rec_denied']  = 0;  ## Anzahl der abgelehnten Records

  foreach($_recdata['data'] as $key => $_record)
  {
    if(isset($_file['data'][$key]))      ## Datensatz ist vorhanden
    {
      if ($_file['data'][$key]['lastupdate'] == $_record['lastupdate'])
      {
        $_recdata['meta']['rec_updated'] ++;           ## fehlerfrei updated zählen
        $_file['data'][$key] = $_record;               ## Daten übertragen
        $_file['data'][$key]['lastupdate'] = $time_s;   ## Aktualisierungsdatum eintragen 
        unset($_recdata['data'][$key]);                ## Record aus Auftragsliste löschen
      }
      else
      {
        $_recdata['denied'][$key] = $_file['data'][$key]; ## Daten aus dem File holen
        $_recdata['meta']['rec_denied'] ++;               ## "abgelehnt" zählen
      }
    }
    else  ## Datensatz aus der Auftragsliste ist noch nicht im File
    {
      if ($key > $_file['meta']['lastid'])             ## wenn die ID aus der Auftragsliste
      {                                                ## größer als die größte ID im File
        $_file['meta']['lastid'] = $key;               ## ist, lastID erhöhen
      }
      
      if (trim($_record['lastupdate']) == "")           ## record hat keinen Meinstamp
      {
        $_record['lastupdate'] = $time_s;               ## Aktualisierungsdatum eintragen 
      }
      
      $_file['data'][$key] = $_record;                 ## Satz hinzufügen
      $_file['meta']['amount'] ++;                     ## datensatz im File zählen
      $_recdata['meta']['rec_updated'] ++;             ## erfolgreichen Auftrag zählen
      unset($_recdata['data'][$key]);                  ## Record aus Auftragsliste löschen      
    }
  }

  //wenn Datensätze verändert oder eingefügt wurden
  if ($_recdata['meta']['rec_updated'] >0)
  {
    // letzte Veränderung eintragen 
    $_file['meta']['lastupdate'] = $time_s;

    //Datei verpacken
    $_file_packed = serialize($_file);
     
    //und abspeichern
    fseek($fp,0);
    ftruncate($fp,0);
    $writeok = @fwrite($fp,$_file_packed,strlen($_file_packed));
  }  
    
  @fclose($fp);
     
  //Rückgabewert der Funktion

  if (!$writeok) return get_error_description(8); # Fehler beim Schreiben;

  if (count($_recdata['denied'])==0)
  {
    return get_error_description(0);       # kein Fehler aufgetreten, alle Sätze verarbeitet.
  }
  else
  {
    return get_error_description(13);      # nicht alle Sätze konnten verarbeitet werden
  }
}


#--------------------------------------------------------------------
function flat_rec_delete($filepath,&$_recdata)
{
  //status-Buffer rücksetzen
  clearstatcache();
  
  //Datei öffnen und locken, bei Fehler abrechen
  $fp = flat_open_lock($filepath,LOCK_EX);
  if (!$fp) return get_error_description(5); ## Datei konnte nicht gesperrt werden
  
  // maximale Dateigröße prüfen
  $filesize = filesize($filepath);
  
  // maximale Dateigröße zuzüglich Sicherheitszuschlag für LESEN
  if ($filesize > FLAT_MAXFILESIZE + 5000)
  {                             
    fclose($fp);
    return get_error_description(10);  ## max. zulässige Dateigröße überschritten
  }
    
  //Datei entpacken
  fseek($fp,0,SEEK_SET);
  $_file_packed = fread($fp,$filesize);
  $_file = unserialize($_file_packed);
  
  //Zeitstempel erzeugen.
  $time_s = get_time_s();

  //Prüfen, ob $_file Daten enthält
  if(empty($_file))
  {
    return get_error_description(4);              ## Keine Daten vorhanden
  }
  else
  {
    //Dateiformat überprüfen
    if (!isset($_file['meta']['created']) or
        !isset($_file['meta']['lastupdate']) or 
        !isset($_file['meta']['lastid']) or
        !isset($_file['meta']['amount']) or 
        !is_array($_file['data']))
    {
      fclose($fp);
      return get_error_description(11);           ## Dateiformat passt nicht
    }

    if (!empty($_recdata['denied']) or !empty($_recdata['meta'])) 
    {
      fclose($fp);
      return get_error_description(12);           ## noch fehlerhafte Datensätze im Array
    }                      ## oder noch falsche Ergbnisdaten
    $_recdata['meta']   = array();
    $_recdata['denied'] = array();
    
  }
  
  $_recdata['meta']['rec_deleted'] = 0;  ## Anzahl der fehlerfrei gelöschten Records
  $_recdata['meta']['rec_denied']  = 0;  ## Anzahl der abgelehnten Records

  foreach($_recdata['data'] as $key => $_record)
  {
    if(isset($_file['data'][$key]))      ## Datensatz ist vorhanden
    {
      if ($_file['data'][$key]['lastupdate'] == $_record['lastupdate'])
      {
        $_recdata['meta']['rec_deleted'] ++;  ## fehlerfrei updated zählen
        unset($_file['data'][$key]);          ## Daten löschen
        unset($_recdata['data'][$key]);       ## Record aus Auftragsliste löschen
        $_file['meta']['amount']--;           ## Satzanzahl verringern
      }
      else
      {
        $_recdata['denied'][$key] = $_file['data'][$key]; ## Daten aus dem File holen
        $_recdata['meta']['rec_denied'] ++;               ## "abgelehnt" zählen
      }
    }
    else        ## Datensatz aus der Auftragsliste ist nicht im File
    {
      unset($_recdata['data'][$key]);       ## Record aus Auftragsliste löschen    
    }
  }

  // Wenn datensätze gelöscht wurden
  if ($_recdata['meta']['rec_deleted'] >0)
  {
    // letzte Veränderung eintragen 
    $_file['meta']['lastupdate'] = $time_s;

    //Datei verpacken
    $_file_packed = serialize($_file);
     
    //und abspeichern
    fseek($fp,0);
    ftruncate($fp,0);
    $writeok = @fwrite($fp,$_file_packed,strlen($_file_packed));
  } 
 
  @fclose($fp);
     
  //Rückgabewert der Funktion

  if (!$writeok) return get_error_description(8);   ## Fehler beim Schreiben;

  if (count($_recdata['denied'])==0)
  {
    return get_error_description(0);       # kein Fehler aufgetreten, alle Sätze gelöscht wurden.
  }
  else
  {
    return get_error_description(13);      # nicht alle Sätze konnten verarbeitet werden
  }
}


#--------------------------------------------------------------------
# Anzeigefunktionen
#--------------------------------------------------------------------
function flat_rec_get_listdata($filepath,&$_showdata,&$nextID,&$lastID,$startID=false,$show=5,
                              $_userdata=false,$_rights=false)
{
  //status-Buffer rücksetzen
  clearstatcache();
  
  //alle Daten einlesen
  $ok = flat_rec_select($filepath,&$_recdata,$userdata=false,$_right=false);
  
  //und bei Fehler abbrechen
  if($ok != 0) return $ok;
  
  if(!$startID) $startID = $_recdata['meta']['lastid'];
  
  //Der Zähler für bereits rüberkopierte Datensätze auf 0 setzen
  $count = 0;
  
  for($ID = $startID; $ID >= 0; $ID--)
  {
    //Wenn der Datensatz vorhanden ist
    if(isset($_recdata['data'][$ID]))
    {
      //und noch Datensätze bis zum Soll fehlen
      if($count < $show)
      {
        //Dann kopiere diesen Datensatz rüber
        $_showdata['data'][$ID] = $_recdata['data'][$ID];
        $count++;
      }
      //und das Soll errreicht ist
      else
      {
        //Diese ID als nächste ID festhalten
        $nextID = $ID;
        //Und abbrechen
        break;
      }
    }
    //Wenn der Datensatz nicht vorhanden ist,
    //einfach weitermachen
  }
  //Falls keine Datensätze gefunden wurden
  if(empty($nextID)) $nextID = false;
  
  //LastID ermitteln:
  //Den Zähler wieder auf null setzen
  $count = 0;
  for($ID = $startID; $ID <= $_recdata['meta']['lastid']; $ID++)
  {
    //Wenn der Datensatz vorhanden ist
    if(isset($_recdata['data'][$ID]))
    {
      if($count < $show)
      {
        $count++;
      }
      else
      {
        $lastID = $ID;
        break;
       }
    }
    //Wenn der Datensatz nicht vorhanden ist,
    //einfach weitermachen
  }
  //Wenn die lastID noch nicht herrausgefunden wurde
  if(empty($lastID))
  {
    //Auf false setzen wenn gleich der es keine vorherigen Einträge
    //mehr gibt
    if($startID == $_recdata['meta']['lastid']) $lastID = false;
    //Oder ansonsten auf die höchste ID setzen
    else $lastID = $_recdata['meta']['lastid'];
  }
  
  //Rückgabewert feststellen
  if(empty($_showdata)) return get_error_description(4); //Keine Daten vorhanden
  else                  return get_error_description(0); //Alles OK ;-)
}

#--------------------------------------------------------------------
function flat_rec_make_list($filepath,$startID=false,$show=5,$_userdata=false,$_rights=false)
{
  //status-Buffer rücksetzen
  clearstatcache();
  
  $ok = flat_rec_get_listdata($filepath,$_showdata,$nextID,$lastID,$startID,$show,
                                                         $_userdata=false,$_rights=false);
  
  //Bei Fehler abbrechen
  if($ok != 0) return $ok;
  
  echo "<h2>Übersicht alles ausgewählten Records</h2>\n\n";
  foreach($_showdata['data'] as $id => $_record)
  {
    echo "<div class=\"beitrag\">\n";
    echo "Eintrags ID: $id<br>\n";
	echo "<a href=\"".$_SERVER['PHP_SELF']."?detailID=".$id."&amp;backID=".$startID."\">Details</a><br>\n";
    echo "</div>\n\n";
  }
  
  if($lastID != false) 
  {
    echo "<a href=\"".$_SERVER['PHP_SELF']."?startID=".$lastID."\">Vorherige Einträge</a><br>\n";
  }
  if($nextID != false)
  {
    echo "<a href=\"".$_SERVER['PHP_SELF']."?startID=".$nextID."\">Weitere Einträge</a><br>\n";
  }
}

#--------------------------------------------------------------------
function flat_rec_make_detail($filepath,$dataID,$backStartID = false)
{
  //status-Buffer rücksetzen
  clearstatcache();
  
  //Einen Datensatz holen
  $ok = flat_rec_get_listdata($filepath,$_showdata,$nextID,$lastID,$dataID,$show = 1,
                                                         $_userdata=false,$_rights=false);
  
  //Bei Fehler abbrechen
  if($ok != 0) return $ok;
  
  //Es ist maximal ein Record im Array $_showdata['data']
  foreach($_showdata['data'] as $id => $_record)
  {
    echo "<h3>Detailansicht eines Records</h3>\n";
	echo "<div class=\"beitrag\">\n";
    echo "	<table cellpadding=2 cellspacing=2>\n";
    foreach($_showdata['data'][$id] as $key => $value)
    {
      echo "	<tr>\n";
      echo "		<td>".$key.":</td>\n";
      echo "		<td>".$value."</td>\n";
      echo "	</tr>\n";
    }
    echo "	</table>\n";
    echo "</div>\n\n";
  }
  
  //Wenn eine Zurück ID angegeben wurde, diese ausgeben
  if($backStartID)
  {
    echo "<a href=\"".$_SERVER['PHP_SELF']."?startID=".$lastID."\">Zurück</a><br>\n";
  }
}

#--------------------------------------------------------------------
function flat_rec_search()
{
  
  
}


#--------------------------------------------------------------------
# Backup- und Archivierungsfunktionen
#--------------------------------------------------------------------
function flat_rec_copy()
{
  
  
}

#--------------------------------------------------------------------
function flat_file_backup($filepath,$backuppath,$compress=0)
{
  //status-Buffer rücksetzen
  clearstatcache();
  
  //Datei öffnen und locken, bei Fehler abrechen
  $fp = flat_open_lock($filepath,LOCK_EX);
  if (!$fp) return get_error_description(5); ## Datei konnte nicht gesperrt werden
  
  $filesize = filesize($filepath);

  // maximal zulässige Filegröße + Sicherheit zum Öffnen
  if ($filesize > FLAT_MAXFILESIZE+5000) 
  {                             
    fclose($fp);
    return get_error_description(10);  ## max. zulässige Dateigröße überschritten
  }
  
  //Datei einlesen
  fseek($fp,0,SEEK_SET);
  $_file_packed = fread($fp,$filesize);
  
  fclose($fp);

  if($backuppath===false)
  {
    $backuppath = $filepath . date(".Ymd");
  }
  
  // wahlweise Komprimierung durchführen
  $compress = intval($compress);
  if ($compress > 0 AND $compress < 10)
  {
    $backuppath .= '.cpr';
    //Haben wir eine PHP Version >= 4.2? Denn der Komprimierungsgrad als
	//zweiter Parameter für gzencode() wurde erst in PHP 4.2 eingeführt!
	if(version_compare(phpversion(), "4.2", ">="))
	{
		$_file_packed = gzencode($_file_packed,$compress);  ## gZIP erstellen
	}
	else
	{
		$_file_packed = gzencode($_file_packed);            ## gZIP erstellen
	}
  }
  else
  {
    $backuppath .= '.abk';
  }
    
    
  $fp = flat_open_lock($backuppath,LOCK_EX);
  if (!$fp) return get_error_description(5); ## Datei konnte nicht gesperrt werden
  
  // prüfen, ob Ausgabefile schon Daten enthielt.
  $filesize = filesize($backuppath);
  if ($filesize > 0) 
  {
    fclose($fp);
    return get_error_description(3);  ## File existiert schon
  }

  fwrite($fp,$_file_packed,strlen($_file_packed));
  fclose($fp);
  
  return get_error_description(0);  ## Kein Fehler aufgetreten
}

#--------------------------------------------------------------------

### EOF ###