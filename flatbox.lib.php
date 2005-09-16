<?php

######################################################################
#/------------------------------------------------------------------\#
#|            ____               _____   __    __                   |#
#|            |__   |      /\      |    |  |  |  |  \ /             |#
#|            |     |     /--\     |    |-{   |  |   X              |#
#|            |     |__  /    \    |    |__|  |__|  / \             |#
#|                                                                  |#
#\------------------------------------------------------------------/#
######################################################################


### FlatBox - Verwalten von Datensätzen in FlatFiles (Textdateien) ###

### Autoren:                                                       ###
### ===============                                                ###
### Thomas Schmieder  <tschmider@bitworks.de>                      ###
### Dennis Riehle     <selfhtml@riehle-web.com>                    ###

### Stand:    03.09.2005 20:53:13                                  ###
### Version:  0.3.0                                                ###
### Stable?   Beta                                                 ###


######################################################################
#/------------------------------------------------------------------\#
#|                              Lizenz                              |#
#\------------------------------------------------------------------/#
######################################################################

/*

*/


######################################################################
#/------------------------------------------------------------------\#
#|                            Changelog                             |#
#\------------------------------------------------------------------/#
######################################################################

/*
- Funktion flat_rec_filter eingeführt, mit der sich aus der Rückgabe von
  flat_rec_select bequem ein einzelner Datensatz oder ein Datensatzbereich
  herrausfiltern lässt.
- Mal an die Funktion flat_file_alter herangemacht, es ist jetzt möglich,
  über alle Datensätze in einem Flatfile hinweg ein Feld zu löschen oder
  ein Feld mit einem Default Wert anzulegen, optional dabei auch bereits
  existierende zu überschreiben
- Fehler in flat_rec_select korrigiert - da gabs bis jetzt immer noch eine
  Fehlermeldung, wenn man einen File öffnen wollte, der _leer_ war, also
  keine leeren Arrays drin, sondern eine Datei mit der Länge 0, fread be-
  schwert sich nämlich, wenn es als Filesize 0 übergeben bekommt
- flat_rec_insert, da gabs ne Notice Meldung wegen einem nicht angelegten
  Array Index, außerdem musste auch hier noch wie bei flat_rec_select eine
  Kontrolle eingebaut werden, ob der File leer ist
- Errorcode 9 eingeführt: In die Datei können keine Datensätze eingetragen
  werden, weil der Filetype nicht flatfile ist.
  Gedenke ich für die Archivierung zu nutzen - wird meta['filetype'] auf
  etwas anders als flatfile gesetzt, ist ein Eintragen mit flat_rec_insert
  nicht mehr möglich, ein Archiv-File wäre somit geschützt, habe flat_rec_
  insert und flat_file_create diesbezüglich angepasst.
  Ist meta['filetype'] nicht vorhanden, so ist das auch OK, aus Gründen der
  Abwärtskompilität
- gleiches Problem wie bei flat_rec_select und bei flat_rec_insert existierte
  auch noch bei flat_rec_update und flat_rec_delete - ist jetzt gefixed
- flat_rec_search geschrieben, die Funktion kann bis jetzt wahlweise in
  den Schlüsseln oder in den Werten der Datensätze suchen und das wahlweise
  mit einem einfachen Vergleich oder mit einem regulären Ausdruck. Alle 
  Datensätze in denen nichts gefunden wurde, werden entfernt
- bei flat_rec_update wäre es bis jetzt noch möglich gewesen, [created] zu
  überschreiben, ist aber nicht sinnvoll, geht jetzt nicht mehr
- flat_rec_update hat bis jetzt Datensatze die noch nicht im Flatfile waren
  neu eingefügt, abgesehen davon, dass da noch einiges nicht so ganz korrekt
  war, ist das doch auch Schwachsinn - dazu soll der Programmierer doch bitte
  flat_rec_insert benutzen, wir sind doch nicht dazu verpflichtet, den "Arsch
  des faulen Programmierers zu retten" ;-)
- bei flat_rec_get_listdata, flat_rec_make_list und flat_rec_make_detail den
  ersten Parameter Filepath durch $_recdata ersetzt - das bläht den Quellcode
  nur unnötig auf, wenn jede Funktion selber wieder noch andere Funktionen 
  aufruft. Da ist es doch besser, wenn man einfach sich von flat_rec_select
  $_recdata geben lässt und dass dann selber weiterreicht, so ersparen wir 
  uns auch das Durchschleifen von $_userdata und $_rights
- in flat_rec_make_detail kleinen Bug gefixet - da wurde auf eine nicht
  existierende Variable zugegriffen, kleiner Schreibfehler
- in flat_rec_make_detail war eine sinnlose foreach, dass ließ sich alles 
  viel einacher lösen, weil man ja $dataID zur Verfügung hat - jetzt kann 
  problemlos das komplette $_recdata übergeben werden.
- Für unsere drei Konstanten fürs Error Reporting habe ich jetzt noch eine
  Abfrage eingebaut, sodass diese nur dann definiert werden, sofern sie 
  nicht schon definiert sind. Das hat den Vorteil, dass der Anweder die 
  Konstanten  selber in seinem Script schon vor dem Includen der FlatBox 
  setzten kann
- Das BIOS (alle 4 Hauptfunktionen) mal durchgegangen und teilweise noch 
  etwas  bereinigt, vereinfacht, Struktur optimiert, vereinheitlicht und 
  weiter kommentiert
- Die Beschreibungen der Funktionen unter dem Changelog hier aufs Nötigste
  reduziert und einen Hinweis auf die Doku hinzugefügt
- bei flat_rec_get_listdata hätte es noch ein kleines Problem gegeben, wenn
  last bzw. nextID 0 gewesen wäre (sollte zwar nicht vorkommen) - ließ 
  sich durch eine Typenprüfung lösen
- von flat_rec_get_listdata nach flat_rec_make_list werden die Informationen
  nextID, lastID und starID jetzt auch über meta in $_showdata weitergegeben
- in flat_rec_make_list und flat_rec_make_detail werden HTML Elementen
  (CSS) Klassen vergeben - da wir eigentlich überall engliche Begriffe 
  verwenden, hab ich die Klassennamen mal auf entry und detailentry geändert
*/


######################################################################
#/------------------------------------------------------------------\#
#|     Definition der benötigten Funktionen mit Erklärung           |#
#\------------------------------------------------------------------/#
######################################################################

/*
Hinweis: Eine ausführliche Beschreibung der Funktionen, sowie eine 
         Auflistung derer Parameter finden Sie in der Dokumentation zur
         FlatBox.

Dauerfunktionen:
================
- get_error_description               => Ist für die Ausgabe von Fehlermeldungen
                                         verantwortlicht.
- get_microtime                       => Besorgt einen Timestamp der aktuellen
                                         Uhrzeit in Millisekunden
- get_time_s                          => Liefert einen Spezial Timestamp in
                                         Millisekunden zurück
- strip                               => Rekursive Entfernung der 
                                         Maskierungs-Backslashes, sofern
                                         magic_quotes aktiviert sind
- flat_open_lock				      => Öffnet und sperrt Dateien, existiert
                                         die Datei nicht, wird sie angelegt

Einmalige Funktionen:
=====================
- flat_file_create			          => Legt einen FlatFile an und füllt ihn
                                         mit den Meta Daten
- flat_file_alter                     => Ändert die Dateien, wenn z.B. ein 
                                         Feld dazukommt

Grundfunktionen (BIOS):
=======================
- flat_rec_insert                     => Schreibt einen neuen Datensatz in 
                                         eine Datei, bzw. legt eine Datei davor
                                         auch an
- flat_rec_select                     => holt eine Liste aller Datensätze
                                         aus einem File
- flat_rec_update                     => Aktualisiert Datensätze in einer Datei
                                         nach lastupdate Vergleich
- flat_rec_delete					  => Löscht Datensätze in einer Datei

Anzeigefunktionen:
==================
- flat_rec_get_listdata               => Besorgt alle notwendigen Daten für
                                         die HTML Ausgabe mit Blätterfunktion
- flat_rec_make_list                  => Erzeugt die HTML Ausgabe für die 
                                         Anzeige mit Blätterfunktion
- flat_rec_make_detail                => Erzeugt die HTML Ausgabe für 
                                         einen angeforderten Datenksatz
- flat_rec_search                     => Sucht im übergebenen Array der Datensätze
                                         nach bestimmtem Text, auch reguläre
                                         Ausdrücke können angewandt werden
- flat_rec_filter					  => Filtert Datensätze anhand ihrer ID
                                         aus den Informationen von flat_rec_select
                                         herraus

Backup- und Archivierungsfunktionen:
====================================
- flat_rec_copy                       => Kopiert eine bestimmte Menge an 
                                         Datensätzen, wird zur Archivierung genutzt
                                         -- nocht nicht geschrieben! --
- flat_file_backup                    => Erstellt ein Backup von FlatFiles,
                                         zusätzlich kann ein Komprimierungsgrad
                                         angegeben werden.
*/


#--------------------------------------------------------------------
# includes und Konstanten
#--------------------------------------------------------------------

//Konstante für die maximale Größe eines Flatfiles
define ('FLAT_MAXFILESIZE', 1000000);

//Drei Konstanten für das Error Reporting
//nur dann definieren, sofern diese nicht schon vorher durch den
//Anwender definiert wurden
if(!defined('PRINT_NOTICES'))
{
  define('PRINT_NOTICES', true);
}
if(!defined('PRINT_WARNINGS'))
{
  define('PRINT_WARNINGS', true);
}
if(!defined('PRINT_FAILES'))
{
  define('PRINT_FAILES', true);
}


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
		 9 => "Low-Level Error: Inserting into this file is not possible becaus filetype is not flatfile.",
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
  elseif($errnr == 0 OR $errnr >= 100 AND isset($_notices[$errnr]))
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
function flat_open_lock($filepath, $lockmode)
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
   $_file['meta']['filetype']   = "flatfile";
   
   $_file_packed = serialize($_file);
   fseek($fp,0);
   fwrite($fp,$_file_packed,strlen($_file_packed));
   fclose($fp);
   
   return get_error_description(0);    ##Code für kein Fehler
 }
 else return get_error_description(5); ##Code für Datei nicht geöffnet 
}

#--------------------------------------------------------------------
function flat_file_alter($filepath, $action, $fieldname, $value = "", $overwrite = false)
{
  //status-Buffer rücksetzen
  clearstatcache();
  
  //Datei öffnen und locken, bei Fehler abrechen
  $fp = flat_open_lock($filepath,LOCK_EX);  
  
  if (!$fp) return get_error_description(5); ## Datei konnte nicht gesperrt werden
  
  //Zeitstempel erzeugen und merken
  $time_s = get_time_s();
  $time_u = time();
  
  //Dateigröße feststellen
  $filesize = filesize($filepath);
  //maximale Dateigröße prüfen
  if($filesize > FLAT_MAXFILESIZE + 5000)
  {
    fclose($fp);
    return get_error_description(10);  ## max. zulässige Dateigröße überschritten
  }
  //ist in der Datei überhaupt etwas drin?
  elseif($filesize == 0)
  {
  	fclose($fp);
	return get_error_description(4);   ## File ist leer
  }
  
  //Datei entpacken
  fseek($fp,0,SEEK_SET);
  $_file_packed = fread($fp,$filesize);
  $_file = unserialize($_file_packed);
  
  //abbrechen, falls $_file leer ist
  if(empty($_file))
  {
    return get_error_description(4);
  }
  //Dateiformat prüfen
  elseif(!isset($_file['meta']['created']) or
        !isset($_file['meta']['lastupdate']) or 
        !isset($_file['meta']['lastid']) or
        !isset($_file['meta']['amount']) or 
        !is_array($_file['data']))
  {
    return get_error_description(11);      ## Dateiformat passt nicht
  }
  //Alle Datensätze bearbeiten
  foreach($_file['data'] as $key => $_record)
  {
	if($action == "remove" and isset($_record[$fieldname]))
	{
	  unset($_file['data'][$key][$fieldname]);
	  $_file['data'][$key]['lastupdate'] = $time_s;
	}
	elseif($action == "add")
	{
	  if(!isset($_record[$fieldname]) or $overwrite)
	  {
	    $_file['data'][$key][$fieldname] = $value;
		$_file['data'][$key]['lastupdate'] = $time_s;
	  }
	}
  }
  
  //letzte Veränderung eintragen 
  $_file['meta']['lastupdate'] = $time_s;
  
  //Datei verpacken
  $_file_packed = serialize($_file);
     
  //und abspeichern
  fseek($fp,0);
  ftruncate($fp,0);
  $writeok = @fwrite($fp,$_file_packed,strlen($_file_packed));
  
  @fclose($fp);
     
  //Rückgabewert der Funktion
  if (!$writeok) return get_error_description(8); # Fehler beim Schreiben;
  
  return get_error_description(0);       ## Kein Fehler aufgetreten
}


#--------------------------------------------------------------------
# Grundfunktionen (BIOS)
#--------------------------------------------------------------------
function flat_rec_insert($filepath, &$_recdata)
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
  
  //Wenn Daten vorhanden, diese entpacken
  if($filesize > 0)
  {
    fseek($fp,0,SEEK_SET);
    $_file_packed = fread($fp,$filesize);
    $_file = unserialize($_file_packed);
  }
  else
  {
    $_file = array();
  }
  
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
	$_file['meta']['filetype'] = "flatfile";
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

	if (isset($_file['meta']['filetype']) AND     ## isset, wegen Abwärtskompatiblität
	    $_file['meta']['filetype'] != "flatfile")
	{
	  return get_error_description(9);   ## Ist kein Flatfile, möglicherweise ein
	                                     ## Archiv File
    }
	elseif (!isset($_file['meta']['filetype']))   ## existierte keine Angabe flatfile
	{
	  $_file['meta']['filetype'] = "flatfile";    ## so wird diese angelegt
	}
	
    if (!empty($_recdata['denied']) or !empty($_recdata['meta'])) 
    {
      fclose($fp);                       ## noch fehlerhafte Datensätze im Array
      return get_error_description(12);  ## oder noch Ergebnisdaten vorhanden 
    }
  }
  
  $_recdata['meta']                 = array(); ## es könnten ja noch leere Strings sein
  $_recdata['meta']['rec_inserted'] = 0;       ## Anzahl der fehlerfrei updated Records
  $_recdata['meta']['rec_denied']   = 0;       ## Anzahl der abgelehnten Records
  $_recdata['denied']               = array(); ## Array f. abgelehnte Datensätze anlegen
  $_recdata['rec_inserted']         = array(); ## Array f. eingefügte Datensätze anlegen

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
      $_file['data'][$new_key]['created']    = $time_u;  ## Erstelldatum eintragen
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
    return get_error_description(0);     # kein Fehler aufgetreten, alle Sätze verarbeitet.
  }
  else
  {
    return get_error_description(13);      # nicht alle Sätze konnten verarbeitet werden
  }
}

#--------------------------------------------------------------------
function flat_rec_select($filepath, &$_recdata, $_userdata = false, $_rights = false)
{
  //status-Buffer rücksetzen
  clearstatcache();
  
  //Datei öffnen und locken, bei Fehler abrechen
  $fp = flat_open_lock($filepath,LOCK_SH);  
  
  if (!$fp) return get_error_description(5); ## Datei konnte nicht gesperrt werden
  
  //Dateigröße feststellen
  $filesize = filesize($filepath);
  //maximale Dateigröße prüfen
  if($filesize > FLAT_MAXFILESIZE + 5000)
  {
    fclose($fp);
    return get_error_description(10);  ## max. zulässige Dateigröße überschritten
  }
  //ist in der Datei überhaupt etwas drin?
  elseif($filesize == 0)
  {
  	fclose($fp);
	return get_error_description(4);   ## File ist leer
	                                   ## müssen wir hier schon abbrechen, weil sonst 
									   ## fread und unserialize Fehler melden
  }
  //Datei entpacken
  else
  {
    fseek($fp,0,SEEK_SET);
    $_file_packed = fread($fp,$filesize);
    fclose($fp);       
    $_file = unserialize($_file_packed);
  }
  
  //abbrechen, falls nur ein leeres Array im File sein sollte
  if(empty($_file))
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
function flat_rec_update($filepath, &$_recdata)
{
  //status-Buffer rücksetzen
  clearstatcache();
  
  //Datei öffnen und locken, bei Fehler abrechen
  $fp = flat_open_lock($filepath,LOCK_EX);
  if (!$fp) return get_error_description(5); ## Datei konnte nicht gesperrt werden
  
  //Dateigröße feststellen
  $filesize = filesize($filepath);
  //maximale Dateigröße prüfen
  if($filesize > FLAT_MAXFILESIZE + 5000)
  {
    fclose($fp);
    return get_error_description(10);  ## max. zulässige Dateigröße überschritten
  }
  //ist in der Datei überhaupt etwas drin?
  elseif($filesize == 0)
  {
  	fclose($fp);
	return get_error_description(4);   ## File ist leer
  }
  //Daten entpacken
  else
  {
    fseek($fp,0,SEEK_SET);
    $_file_packed = fread($fp,$filesize);
    $_file = unserialize($_file_packed);
  }
  
  //Zeitstempel erzeugen und merken
  $time_s = get_time_s();
  $time_u = time();

  //abbrechen, falls nur ein leeres Array im File sein sollte
  if(empty($_file))
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
      fclose($fp);
      return get_error_description(11);           ## Dateiformat passt nicht
    }

    if (!empty($_recdata['denied']) or !empty($_recdata['meta'])) 
    {
      fclose($fp);                       ## noch fehlerhafte Datensätze im Array
      return get_error_description(12);  ## oder noch Ergebnisdaten vorhanden 
    }
  }
  
  $_recdata['denied'] = array();
  $_recdata['meta']['rec_updated'] = 0;  ## Anzahl der fehlerfrei updated Records
  $_recdata['meta']['rec_denied']  = 0;  ## Anzahl der abgelehnten Records

  foreach($_recdata['data'] as $key => $_record)
  {
    if(isset($_file['data'][$key]))      ## Datensatz ist vorhanden
    {
      if ($_file['data'][$key]['lastupdate'] == $_record['lastupdate'])
      {
        //Ein Manipulieren von [created] soll nicht möglich sein
		unset($_recdata['data']['created']);
		//Datensatz einfügen
		$_recdata['meta']['rec_updated'] ++;           ## fehlerfrei updated zählen
        $_file['data'][$key] = $_record;               ## Daten übertragen
        $_file['data'][$key]['lastupdate'] = $time_s;  ## Aktualisierungsdatum eintragen 
        unset($_recdata['data'][$key]);                ## Record aus Auftragsliste löschen
      }
      else
      {
        $_recdata['denied'][$key] = $_file['data'][$key]; ## Daten aus dem File holen
        $_recdata['meta']['rec_denied'] ++;               ## "abgelehnt" zählen
      }
    }
    else  ## Datensatz aus der Auftragsliste ist nicht im File
    {
      $_recdata['meta']['rec_denied'] ++;    ## "abgelehnt" hochzählen
      $_recdata['denied'][$key] = $_record;  ## Datensatz in [denied] kopieren
    }
  }

  //wenn Datensätze verändert wurden
  if ($_recdata['meta']['rec_updated'] > 0)
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

  if (count($_recdata['denied']) == 0)
  {
    return get_error_description(0);    ## kein Fehler aufgetreten, alle Sätze verarbeitet.
  }
  else
  {
    return get_error_description(13);   ## nicht alle Sätze konnten verarbeitet werden
  }
}

#--------------------------------------------------------------------
function flat_rec_delete($filepath, &$_recdata)
{
  //status-Buffer rücksetzen
  clearstatcache();
  
  //Datei öffnen und locken, bei Fehler abrechen
  $fp = flat_open_lock($filepath,LOCK_EX);
  if (!$fp) return get_error_description(5); ## Datei konnte nicht gesperrt werden
  
  //Dateigröße feststellen
  $filesize = filesize($filepath);
  //maximale Dateigröße prüfen
  if($filesize > FLAT_MAXFILESIZE + 5000)
  {
    fclose($fp);
    return get_error_description(10);  ## max. zulässige Dateigröße überschritten
  }
  //ist in der Datei überhaupt etwas drin?
  elseif($filesize == 0)
  {
  	fclose($fp);
	return get_error_description(4);   ## File ist leer
  }
  //Daten entpacken
  else
  {
    fseek($fp,0,SEEK_SET);
    $_file_packed = fread($fp,$filesize);
    $_file = unserialize($_file_packed);
  }
  
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
    
    
  }
  
  $_recdata['meta']   = array();
  $_recdata['denied'] = array();
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
function flat_rec_get_listdata(&$_recdata, &$_showdata, $startID = false, $show = 5)
{
  //status-Buffer rücksetzen
  clearstatcache();
  
  if(!$startID) $startID = $_recdata['meta']['lastid'];
  
  //Der Zähler für bereits rüberkopierte Datensätze auf 0 setzen
  $count = 0;
  
  //Default Wert für nächste ID festlegen
  $nextID = false;
  
  //Next ID ermitteln
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
  
  //Den Zähler wieder auf null setzen
  $count = 0;
  
  //Default für lastID setzen
  $lastID = false;
  
  //LastID ermitteln:
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
  if($lastID === false AND $startID != $_recdata['meta']['lastid'])
  {
    //lastID auf die höchste ID setzen
    $lastID = $_recdata['meta']['lastid'];
  }
  
  //NextID, LastID und StartID in meta schreiben
  $_showdata['meta']['nextID']  = $nextID;
  $_showdata['meta']['lastID']  = $lastID;
  $_showdata['meta']['startID'] = $startID;
  
  //Rückgabewert feststellen
  if(empty($_showdata)) return get_error_description(4); //Keine Daten vorhanden
  else                  return get_error_description(0); //Alles OK ;-)
}

#--------------------------------------------------------------------
function flat_rec_make_list(&$_showdata)
{
  //status-Buffer rücksetzen
  clearstatcache();
  
  echo "<h2>Übersicht aller ausgewählten Records</h2>\n\n";
  foreach($_showdata['data'] as $id => $_record)
  {
    echo "<div class=\"entry\">\n";
    echo "Eintrags ID: $id<br>\n";
	echo "<a class=\"detailslink\" href=\"" . $_SERVER['PHP_SELF'] . "?detailID=" . $id . "&amp;backID=";
	echo intval($_showdata['meta']['startID']) . "\">Details ansehen</a><br>\n";
    echo "</div>\n\n";
  }
  
  if($_showdata['meta']['lastID'] !== false) 
  {
    echo "<a id=\"beforelink\" href=\"" . $_SERVER['PHP_SELF'] . "?startID=" . $_showdata['meta']['lastID'];
	echo "\">Vorherige Einträge</a>\n";
  }
  if($_showdata['meta']['nextID'] !== false)
  {
    echo "<a id=\"nextlink\" href=\"" . $_SERVER['PHP_SELF'] . "?startID=" . $_showdata['meta']['nextID'];
	echo "\">Weitere Einträge</a>\n";
  }
}

#--------------------------------------------------------------------
function flat_rec_make_detail(&$_recdata, $dataID, $backStartID = false)
{
  //status-Buffer rücksetzen
  clearstatcache();
  
  echo "<h3>Detailansicht eines Records</h3>\n";
  echo "<div class=\"detailentry\">\n";
  echo "<table cellpadding=2 cellspacing=2>\n";
  foreach($_recdata['data'][$dataID] as $key => $value)
  {
    echo "<tr>\n";
    echo "<td>" . $key . ":</td>\n";
    echo "<td>" . $value . "</td>\n";
    echo "</tr>\n";
  }
  echo "</table>\n";
  echo "</div>\n\n";
  
  //Wenn eine Zurück ID angegeben wurde, diese ausgeben
  if($backStartID)
  {
    echo "<a id=\"backlink\" href=\"" . $_SERVER['PHP_SELF'] . "?startID=";
	echo $backStartID . "\">Zurück</a>\n";
  }
}

#--------------------------------------------------------------------
function flat_rec_search(&$_recdata, $term, $searchin = "value", $searchtype = "simple")
{
  //Dateiformat überprüfen
  if (!is_array($_recdata) or
      !is_array($_recdata['data']))
  {
    return get_error_description(11);    ## Dateiformat passt nicht
  }
  //Überhaupt Datensätze vorhanden?
  if (empty($_recdata))
  {
    return get_error_description(14);    ## Format ok, aber keine Datensätze
  }
  //Soll in den Schlüsseln gesucht werden?
  if($searchin == "key")
  {
    //Alle Datensätze durchgehen
	foreach($_recdata['data'] as $id => $_record)
	{
	  //Schalter, ob etwas gefunden wurde
	  $found = false;
	  //Alle Datensätze durchgehen
	  foreach($_record as $key => $value)
	  {
	    //Aufwendige Suche?
		if($searchtype == "preg")
		{
		  //Wenn was gefunden, Schalter auf true setzen
		  if(preg_match($term, $key)) $found = true;
		}
		//Sonst einfache Suche
		else
	    {
		  //Wenn was gefunden, Schalter auf true setzen
	      if($key == $term) $found = true;
	    }
	  }
	  //Wenn nichts gefunden wurde, Datensatz entfernen
	  if($found == false)
	  {
	    unset($_recdata['data'][$id]);
	  }
	}
  }
  //Ansonsten suchen wir in den Werten
  else //$searchin == "value"
  {
    //Alle Datensätze durchgehen
    foreach($_recdata['data'] as $id => $_record)
	{
	  //Schalter, ob etwas gefunden wurde
	  $found = false;
	  //Alle Datensätze durchgehen
	  foreach($_record as $key => $value)
	  {
	    //Handelt es sich um ein Unter-Array?
		//Das können wir bis jetzt noch nicht durchsuchen
		if(is_array($value)) continue;
		//Aufwendige Suche?
		if($searchtype == "preg")
		{
		  //Wenn was gefunden, Schalter auf true setzen
		  if(preg_match($term, $value)) $found = true;
		}
		else
	    {
		  //Wenn was gefunden, Schalter auf true setzen
	      if($value == $term) $found = true;
	    }
	  }
	  //Wenn nichts gefunden wurde, Datensatz entfernen
	  if($found == false)
	  {
	    unset($_recdata['data'][$id]);
	  }
	}
  }
  return get_error_description(0);  ## Kein Fehler aufgetreten
}

#--------------------------------------------------------------------
function flat_rec_filter(&$_recdata, $startID, $stopID = false)
{
  //Dateiformat überprüfen
  if (!is_array($_recdata) or
      !is_array($_recdata['data']))
  {
    return get_error_description(11);    ## Dateiformat passt nicht
  }
  //Überhaupt Datensätze vorhanden?
  if (empty($_recdata))
  {
    return get_error_description(14);    ## Format ok, aber keine Datensätze
  }
  //Wenn nur ein Datensatz herrausgefiltert werden soll
  if($stopID === false)
  {
    foreach($_recdata as $key => $_record)
	{
	  //Alle nicht passenden Datensätze rauslöschen
	  if($key != $startID)
	  {
	    unset($_recdata[$key]);
	  }
	}
  }
  //Wenn ein Bereich an Datensätzen herrausgefiltert werden soll
  else
  {
    foreach($_recdata as $key => $_record)
	{
	  //Alle nicht in diesem Bereich liegenden Datensätze löschen
	  if($key < $startID OR $key > $stopID)
	  {
	    unset($_recdata[$key]);
	  }
	}
  }
  return get_error_description(0);    ## Keine Fehler aufgetreten
}


#--------------------------------------------------------------------
# Backup- und Archivierungsfunktionen
#--------------------------------------------------------------------
function flat_rec_copy()
{
  
  
}

#--------------------------------------------------------------------
function flat_file_backup($filepath, $backuppath, $compress = 0)
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


######################################################################
#/------------------------------------------------------------------\#
#|                           End of File                            |#
#\------------------------------------------------------------------/#
######################################################################

?>