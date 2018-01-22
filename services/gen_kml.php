<?php
// ---------------------------------------------------------------------------------------------
// This script generates kml files (and potentially also gpx)
// for tracks and segments
// It should be by all functions expecting a kml and gpx output
//
// Created: 30.12.2017 - Daniel Leutwyler
// ---------------------------------------------------------------------------------------------
//
// Tasks
// *

// -----------------------------------
// Set variables and parameters    
date_default_timezone_set('Europe/Zurich');
include("config.inc.php");                                                  // Include config file

// Set debug level
$debugLevel = 3;                                                            // 0 = off, 1 = min, 3 = a lot, 5 = all 
$countTracks = 0;                                                           // Internal counter for tracks processed

// Open log file
if ($debugLevel >= 1){
    $logFileLoc = dirname(__FILE__) . "\..\log\gen_kml.log";                // Assign file location
    $logFile = @fopen($logFileLoc,"a");     
    fputs($logFile, "=================================================================\r\n");
    fputs($logFile, date("Ymd-H:i:s", time()) . "-Line 24: gen_kml.php opened \r\n"); 
};

// Array for the styling of the lines in the kml file
$styleArray = array(
    array("Wanderung","FF01EDFF",3,"FF01EDFF",5),                           // gelb
    array("Winterwandern","#ff852eff",3,"#ff852eff",5),                     // orange
    array("Alpintour","FF00C0FF",3,"FF00C0FF",5),                           // rot
    array("Hochtour","FF0000FF",3,"FF0000FF",5),                            // schwarz
    
    array("Sportklettern","FFD9D9D9",3,"FFD9D9D9",5),                       // hell grau
    array("Mehrseilklettern","FFA6A6A6",3,"FFA6A6A6",5),                    // mittel grau
    array("Alpinklettern","FF808080",3,"FF808080",5),                       // dunkel grau
        
    array("Velotour","#FF01FF86",3,"#FF01FF86",5),                          // grün 
    
    array("Schneeschuhwanderung","#FFFFCC33",3,"#FFFFCC33",5),              // hell blau
    array("Skitour","#FFC07000",3,"#FFC07000",5),                           // dunkel blau
    
    array("Others","#FFCC66FF",3,"#FFCC66FF",5)                             // rosa
);

// variables passed on by client (as JSON object)
$receivedData = json_decode ( file_get_contents('php://input'), true );
$sessionid = $receivedData["sessionid"];                                    
$sqlWhereTracks = $receivedData["sqlWhereTracks"];                          // where statement to select tracks to be displayed
$genTrackKml = $receivedData["genTrackKml"];                                // true when tracks kml file should be generated
$sqlWhereSegments = $receivedData["sqlWhereSegments"];                      // where statement to select tracks to be displayed
$genSegKml = $receivedData["genSegKml"];                                    // true when tracks kml file should be generated

if ($debugLevel >= 2){
    fputs($logFile, 'Line 56: sessionid: ' . $sessionid . "\r\n");
    fputs($logFile, 'Line 57: sqlWhereTracks: ' . $sqlWhereTracks . "\r\n");
    fputs($logFile, 'Line 58: sqlWhereSegments: ' . $sqlWhereSegments . "\r\n");
    fputs($logFile, 'Line 57: genTrackKml: ' . $genTrackKml . "\r\n");
    fputs($logFile, 'Line 58: genSegKml: ' . $genSegKml . "\r\n");
};

// create upload dir / file name
$kml_dir = '../tmp/kml_disp/' . $sessionid . '/';                           // Session id used to create unique directory
if (!is_dir ( $kml_dir )) {                                                 // Create directory with name = session id
    mkdir($kml_dir, 0777);
}

// ==================================================================
// If flag is set to generate tracks KML
//
if ( $genTrackKml ) {

    // open file to store track kml file
    $trackKmlFileURL = $kml_dir . 'tracks.kml';
    $trackOutFile = fopen($trackKmlFileURL, "w");                           // Open kml output file

    // Write headern and style section of KML
    $kml[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $kml[] = '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">';
    $kml[] = '  <Document>';
    $kml[] = '    <name>tourdb - tracks</name>';

    // Create kml stylemaps
    $i=0;
    for ($i; $i<11; $i++) {                                                 // 10 is the number of existing subtypes in array (lines)
        $kml = createStyles($styleArray[$i], $kml);    
    }

    // Write main section - intro
    $kml[] = '    <Folder>';
    $kml[] = '      <name>tourdb exported tracks</name>';
    $kml[] = '        <visibility>0</visibility>';
    $kml[] = '        <open>1</open>';

    // Select tracks meeting given WHERE clause
    $sql = "SELECT trkId, trkTrackName, trkRoute, trkParticipants, trkSubType, trkCoordinates ";
    $sql .= "FROM tbl_tracks ";
    $sql .= $sqlWhereTracks;

    $records = mysqli_query($conn, $sql);

    if ($debugLevel >= 3){
        fputs($logFile, 'Line 76: sql: ' . $sql . "\r\n");
    };

    // Loop through each selected track and write main track data
    while($singleRecord = mysqli_fetch_assoc($records))
    { 
        $countTracks++;                                                     // Counter for the number of tracks produced
        $kml[] = '        <Placemark id="linepolygon_' . sprintf("%'05d", $singleRecord["trkId"]) . '">';
        $kml[] = '          <name>' . $singleRecord["trkTrackName"] . '</name>';
        $kml[] = '          <visibility>1</visibility>';
        $kml[] = '          <description>' . $singleRecord["trkId"] . ' - ' . $singleRecord["trkRoute"] . ' (mit ' .  $singleRecord["trkParticipants"] . ')</description>';

        // set default stylmap and then loop through style maps
        $styleMapDefault = '          <styleUrl>#stylemap_Others</styleUrl>';       // Set styleUrl to Others in case nothing in found
        $i=0;
        for ($i; $i<11; $i++) {                                             // 10 is the number of existing subtypes in array (lines)
            if ($styleArray[$i][0] == $singleRecord["trkSubType"])
            {
                $styleMapDefault = '          <styleUrl>#stylemap_' . $singleRecord["trkSubType"] . '</styleUrl>';
                break;
            }
        }
        $kml[] = $styleMapDefault;
        
        $kml[] = '          <ExtendedData>';
        $kml[] = '            <Data name="type">';
        $kml[] = '              <value>linepolygon</value>';
        $kml[] = '            </Data>';
        $kml[] = '          </ExtendedData>';
        $kml[] = '          <LineString>';
        $kml[] = '            <coordinates>' . $singleRecord["trkCoordinates"];
        $kml[] = '            </coordinates>';
        $kml[] = '          </LineString>';
        $kml[] = '        </Placemark>';   
    };

    // Write KML trailer
    $kml[] = '    </Folder>';
    $kml[] = '  </Document>';
    $kml[] = '</kml>';

    // Merge kml array into one variable
    $kmlOutput = join("\r\n", $kml);

    // Write kml into file
    fputs($trackOutFile, "$kmlOutput");                                     // Write kml to file
}
fputs($logFile, "$countTracks Tracks processed\r\n");
$countTracks = 0; 

// ==================================================================
// If flag is set to generate segments KML
//
if ( $genSegKml ) {
    
    // open file to store segment kml file
    $segKmlFileURL = $kml_dir . 'segments.kml';
    $segOutFile = fopen($segKmlFileURL, "w");                               // Open kml output file
    
    // re-initialise kml variable
    $kml = [];

    // Write headern and style section of KML
    $kml[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $kml[] = '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">';
    $kml[] = '  <Document>';
    $kml[] = '    <name>tourdb - segments</name>';

    // Create kml stylemaps
    $i=0;
    for ($i; $i<11; $i++) {                                                 // 10 is the number of existing subtypes in array (lines)
        $kml = createStyles($styleArray[$i], $kml);    
    }

    // Write main section - intro
    $kml[] = '    <Folder>';
    $kml[] = '      <name>tourdb exported segments</name>';
    $kml[] = '        <visibility>0</visibility>';
    $kml[] = '        <open>1</open>';

    // Select tracks meeting given WHERE clause
    $sql =  "SELECT vw_segments.Id, vw_segments.segName, vw_segments.sourceFID, ";
    $sql .= "vw_segments.sourceRef, vw_segments.grade, vw_segments.coordinates, ";
    $sql .= "TIME_FORMAT(tStartTarget, '%h:%i') AS timeUp FROM vw_segments ";
    $sql .= "INNER JOIN tbl_waypoints ON vw_segments.segTargetLocFID = tbl_waypoints.waypID ";
    $sql .= $sqlWhereSegments;

    // execute sql query and store results in variable $records
    $records = mysqli_query($conn, $sql);
    
    if ($debugLevel >= 3){
        fputs($logFile, 'Line 182: sql: ' . $sql . "\r\n");
    };

    // Loop through each selected track and write main track data
    while($singleRecord = mysqli_fetch_assoc($records))
    { 
        $countTracks++;                                                     // Counter for the number of tracks produced
        $kml[] = '        <Placemark id="linepolygon_' . sprintf("%'05d", $singleRecord["Id"]) . '">';
        $kml[] = '          <name>' . $singleRecord["segName"] . '</name>';
        $kml[] = '          <visibility>1</visibility>';
        $kml[] = '      <description>' . $singleRecord["sourceFID"] . '-' . $singleRecord["sourceRef"] . ' ' .
                $singleRecord["segName"] . ' (' . $singleRecord["grade"] . '/' . $singleRecord["timeUp"] . 
                ')</description>';
        $kml[] = '          <styleUrl>#stylemap_Others</styleUrl>';         // Set styleUrl to Others in case nothing in found
        $kml[] = '          <ExtendedData>';
        $kml[] = '            <Data name="type">';
        $kml[] = '              <value>linepolygon</value>';
        $kml[] = '            </Data>';
        $kml[] = '          </ExtendedData>';
        $kml[] = '          <LineString>';
        $kml[] = '            <coordinates>' . $singleRecord["coordinates"];
        $kml[] = '            </coordinates>';
        $kml[] = '          </LineString>';
        $kml[] = '        </Placemark>';   
    };

    // Write KML trailer
    $kml[] = '    </Folder>';
    $kml[] = '  </Document>';
    $kml[] = '</kml>';

    // Merge kml array into one variable
    $kmlOutput = join("\r\n", $kml);

    // write kml output to file
    fputs($segOutFile, "$kmlOutput");                                       // Write kml to file

}

// Create return object
$returnObject['status'] = 'OK';                                             // add status field (OK) to trackobj
$returnObject['errmessage'] = '';                                           // add empty error message to trackobj
echo json_encode($returnObject);                                            // echo JSON object to client

fputs($logFile, "gen_kml.php finished: " . date("Ymd-H:i:s", time()) . "\r\n");    
fputs($logFile, "$countTracks Segments processed\r\n");

// Close all files and connections
if ( $debugLevel >= 1 ) fclose($logFile);                                   // close log file
mysql_close($conn);                                                         // close SQL connection 
fclose($trackOutFile);                                                      // close kml file
fclose($segOutFile);

exit;

// function to create styles in KML file
function createStyles ($styleArray,$kml) {
    
    // Generates style map and style for each subtype                       // variable aus $styleArray lesen und einsetzen
    $styleMapId = "stylemap_" . $styleArray[0];
    $styleUrlNorm = "style_" . $styleArray[0] . "_norm";
    $styleUrlHl = "style_" . $styleArray[0] . "_hl";

    // StyleMap tourdb
    $kml[] = '    <StyleMap id="' . $styleMapId . '">';

    // StyleMap
    $kml[] = '      <Pair>';
    $kml[] = '        <key>normal</key>';
    $kml[] = '        <styleUrl>#' . $styleUrlNorm . '</styleUrl>';
    $kml[] = '      </Pair>';
    $kml[] = '      <Pair>';
    $kml[] = '        <key>highlight</key>';
    $kml[] = '        <styleUrl>#' . $styleUrlHl . '</styleUrl>';
    $kml[] = '      </Pair>';
    $kml[] = '    </StyleMap>';

    // Style
    $kml[] = '    <Style id="' . $styleUrlNorm . '">';
    $kml[] = '      <LineStyle>';
    $kml[] = '        <color>' . $styleArray[1] . '</color>';
    $kml[] = '        <width>' . $styleArray[2] . '</width>';
    $kml[] = '      </LineStyle>';
    $kml[] = '      <PolyStyle>';
    $kml[] = '        <color>' . $styleArray[1] . '</color>';
    $kml[] = '        <width>' . $styleArray[2]. '</width>';
    $kml[] = '      </PolyStyle>';
    $kml[] = '    </Style>';
    $kml[] = '    <Style id="' . $styleUrlHl . '">';
    $kml[] = '      <LineStyle>';
    $kml[] = '        <color>' . $styleArray[3] . '</color>';
    $kml[] = '        <width>' . $styleArray[4] . '</width>';
    $kml[] = '      </LineStyle>';
    $kml[] = '      <PolyStyle>';
    $kml[] = '        <color>' . $styleArray[3] . '</color>';
    $kml[] = '        <width>' . $styleArray[4] . '</width>';
    $kml[] = '      </PolyStyle>';
    $kml[] = '    </Style>';

    return $kml;
}
?>

