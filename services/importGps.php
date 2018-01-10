<?php
// ---------------------------------------------------------------------------------------------
// PHP script loading gpx files into table track points. When no track exists, a simple track
// record is created. Before loading the gpx track points all previous track points are deleted. 
// When a gpx for an existing track is imported, the track time is updated.
//
// This service is called in two different manner: 
// A) for the request 'temp' the parameters are // passed as dataForm object. This is due to the 
//    fact that the file to be uploaded has to be transferred to this service. 
// B) for the other requests a JSON is passed
//
// Parameters:
// sessionid: id of user session; used to ensure multi-user capabilities
// filename: name of file to be uploaded (one at a time); file is expected at import/gpx or import/kml
// filetype: type of file to be imported (gpx or kml)

// Created: 13.12.2017 - Daniel Leutwyler
// ---------------------------------------------------------------------------------------------

// -----------------------------------
// Set variables and parameters
include("./config.inc.php");                                        // include config file
date_default_timezone_set('Europe/Zurich');                         // must be set when using time functions

$debugLevel = 3;                                                    // 0 = off, 6 = all
$loopSize = 5000;                                                   // Number of trkPts inserted in one go

// Open file for import log
$importGpxLog = dirname(__FILE__) . "\..\log\importGpx.log";        // Assign file location
$logFile = @fopen($importGpxLog,"a");                               // open log file handler 
fputs($logFile, "\r\n============================================================\r\n");    
fputs($logFile, "importGpx.php started: " . date("Ymd-H:i:s", time()) . "\r\n");    

// Evaluate request type
if ( isset($_REQUEST["request"]) && $_REQUEST["request"] != '' )    // if call to this service was done with dataForm (temp)
{
    $request = $_REQUEST["request"];                                // temp = temporary creation; save = final storage; cancel = cancel operation / delete track & track points
    fputs($logFile, "Line 38: Request (_REQUEST): $request\r\n");    
} else {
    // variables passed on by client (as formData object)
    $receivedData = json_decode ( file_get_contents('php://input'), true );
    $request = $receivedData["request"];                            // temp = temporary creation; save = final storage; cancel = cancel operation / delete track & track points
    fputs($logFile, "Line 43: Request (JSON): $request\r\n");    
}

if ($request == "temp") {

    // ---------------------------------------------------------------------------------
    // request type is "temp" meaning that track records are created on temporary basis
    // ---------------------------------------------------------------------------------
  
    // Read posted parameters
    $sessionid = $_REQUEST["sessionid"];                                // ID of current user session - required to make site multiuser capable
    $filename = basename($_FILES['filename']['name']);                  // file name of chosen gps file
    $filetype = $_REQUEST["filetype"];                                  // Type of upload file (gpx or kml)
    $loginname = $_REQUEST["loginname"];                                // Login name

    fputs($logFile, "Line 58: Parameters: \r\n");    
    fputs($logFile, "Line 59: sessionid:$sessionid | filename:$filename | filetype:$filetype | loginname:$loginname\r\n");    
    
    // check if file extension is kml or gpx
    $filetype = pathinfo($filename);
    $filetype = $filetype['extension'];
    fputs($logFile, "Line 64; filetype = $filetype \r\n");    

    if ( $filetype == "gpx" || $filetype == "kml" ) {
        // define directory and copy file 
        $uploaddir = '../import/gpx/uploads/' . $sessionid . '/';       // Session id used to create unique directory
        $uploadfile = $uploaddir . $filename;
        if ( $debugLevel > 2) fputs($logFile, "Line 54 - uploaddir: $uploaddir\r\n");  
        if ( $debugLevel > 2) fputs($logFile, "Line 55 - uploadfile: $uploadfile\r\n");  
            
        if (!is_dir ( $uploaddir )) {                                   // Create directory with name = session id
            mkdir($uploaddir, 0777);
        }

        if (move_uploaded_file($_FILES['filename']['tmp_name'], $uploadfile)) {         // move uploaded file to target dir
            if ( $debugLevel > 2) fputs($logFile, "Line 62 - file " . $_FILES['filename']['name'] . " successfully uploaded to: $uploaddir\r\n");    
        } else {
            fputs($logFile, "Line 64 - error uploading file " . $_FILES['filename']['name'] . " to: $uploaddir\r\n"); 
        }  

        // -----------------------------------------
        // Main process for gpx files
        // -----------------------------------------

        if ( $filetype == "gpx") {
            // Call function to insert track data
            $trackobj = array();                                                // array storing track data in array
            $returnArray = insertTrack($conn,$filename,$uploadfile,$loginname);
            $trackid = $returnArray[0];                                   // return id of newly created track
            $trackobj = $returnArray[1];                                  // track object with all know track data derived from file

            fputs($logFile, "Line 80 - trackid: $trackid\r\n");
            foreach ($trackobj as $dbField => $value) {
                fputs($logFile, "Line 82 - $key: $value\r\n");
            }
            
            //$returnArray = array();                                       // Clear return array

            // insert track points found in file in table tmp_trackpoints with given track id
            $returnArray = insertTrackPoints($conn,$trackid,$uploadfile);  // Insert new track points; returns temp ID for track
            $trackid = $returnArray[0];                                   // return id of newly created track
            $coordArray = $returnArray[1];                                // array string with coordinates
        
            $coordString = "";                                            // clear var coordString
        // join array $coordArray into a string
            foreach ( $coordArray as $coordLine) {                        // Create string containing the coordinates
                $coordString = $coordString . $coordLine; 
            };

            // create JSON object with known gpx data
            $trackobj['trkCoordinates'] = $coordString;                   // add field coordinates to track object
            $trackobj['status'] = 'OK';
            $trackobj['errmessage'] = '';

            // calculate distance based on gpx data

            // calculate time based on gpx data

            // calcuate meters up and down based on gpx data
            
            // return JSON object to client
            echo json_encode($trackobj);                                  // echo track object to client

            // remove imported file & close connections
            if ( file_exists) unlink ($uploadfile);                       // remove file if existing
            rmdir($uploaddir, 0777);                                      // remove upload directory          

            $conn->close();                                               // Close DB connection

        } else if ($filetype == "kml") {
            fputs($logFile, "Filetype $filetype not supported. Please import as gpx file.\r\n");    
        } else {
            fputs($logFile, "Filetype $filetype not supported. Please import as gpx file.\r\n");    
        }
    } else {
        fputs($logFile, "Line 67: extension is kml or gpx: $filetype \r\n");    
        $trackobj['status'] = 'ERR';
        $trackobj['errmessage'] = 'Wrong file extension';
        echo json_encode($trackobj);                                  // echo track object to client
        exit;        
        fputs($logFile, "extension is kml or gpx: $filetype \r\n");    
    }
} else if ( $request == "save") {

    // ---------------------------------------------------------------------------------
    // request type is "save" meaning that track records are updated and finalised
    // ---------------------------------------------------------------------------------

    $trackobj = array();                                                // array storing track data in array
    $sessionid = $receivedData["sessionid"];                                // ID of current user session - required to make site multiuser capable
    $request = $receivedData["request"];                                    // temp = temporary creation; save = final storage; cancel = cancel operation / delete track & track points
    $filetype = $receivedData["filetype"];                                  // Type of upload file (gpx or kml)
    $trackobj = $receivedData["trackobj"];                                // Array of track data 

    if ( $debugLevel > 2) fputs($logFile, "Line 49 - sessionid: $sessionid\r\n");  
    if ( $debugLevel > 2) fputs($logFile, "Line 50 - request: $request\r\n");  
    //if ( $debugLevel > 2) fputs($logFile, "Line 51 - filename: $filename\r\n");  
    if ( $debugLevel > 2) fputs($logFile, "Line 52 - filetype: $filetype\r\n");   

    $sql = "UPDATE `tourdb2`.`tbl_tracks` SET ";                    // Insert Source file name, gps start time and toReview flag
    $sql .= "`trkLoginName`='$loginname',";

    foreach ($trackobj as $dbField => $content) {
        $sql .= "`$dbField`='$content',";
    }
    $sql = substr($sql,0,strlen($sql)-1);
    $sql .= " WHERE `tbl_tracks`.`trkId` = " . $trackobj["trkId"]; 
    
    fputs($GLOBALS['logFile'], "Line 164 - sql: $sql\r\n");
    
    if ($conn->query($sql) === TRUE)                                // run sql against DB
    {
        if ($GLOBALS['debugLevel']>3) fputs($GLOBALS['logFile'], "Line 163 - New track inserted successfully\r\n");
    } else {
        if ($GLOBALS['debugLevel']>0) fputs($GLOBALS['logFile'], "Line 165 - Error inserting trkPt: $conn->error\r\n");
        return -1;
    } 
    
    echo "Track saved";

} else if ( $request == "cancel") {

        // ---------------------------------------------------------------------------------
    // request type is "save" meaning that track records are updated and finalised
    // ---------------------------------------------------------------------------------

    $trackobj = array();                                                // array storing track data in array
    $sessionid = $receivedData["sessionid"];                                // ID of current user session - required to make site multiuser capable
    $request = $receivedData["request"];                                    // temp = temporary creation; save = final storage; cancel = cancel operation / delete track & track points
    $filetype = $receivedData["filetype"];                                  // Type of upload file (gpx or kml)
    $trackobj = $receivedData["trackobj"];                                // Array of track data 

    if ( $debugLevel > 2) fputs($logFile, "Line 49 - sessionid: $sessionid\r\n");  
    if ( $debugLevel > 2) fputs($logFile, "Line 50 - request: $request\r\n");  
    if ( $debugLevel > 2) fputs($logFile, "Line 52 - filetype: $filetype\r\n");   

    $sql = "DELETE FROM `tourdb2`.`tbl_tracks` ";                    // Insert Source file name, gps start time and toReview flag
    $sql .= "WHERE `tbl_tracks`.`trkId` = " . $trackobj["trkId"]; 
    
    fputs($GLOBALS['logFile'], "Line 186 - sql: $sql\r\n");
    
    if ($conn->query($sql) === TRUE)                                // run sql against DB
    {
        if ($GLOBALS['debugLevel']>3) fputs($GLOBALS['logFile'], "Line 163 - New track inserted successfully\r\n");
    } else {
        if ($GLOBALS['debugLevel']>0) fputs($GLOBALS['logFile'], "Line 165 - Error inserting trkPt: $conn->error\r\n");
        return -1;
    } 
    
    echo "Track cancelled";

} else {

}

function insertTrack($conn,$filename,$uploadfile,$loginname)
{
    if ($GLOBALS['debugLevel']>4) fputs($GLOBALS['logFile'], "Line 199 - Function insertTrack entered\r\n");

    $gpx = simplexml_load_file($uploadfile);                        // Load XML structure
    $newTrackTime = $gpx->metadata->time;                           // Assign track time from gpx file to variable
    $GpsStartTime = strftime("%Y.%m.%d %H:%M:%S", strtotime($newTrackTime));    // convert track time 
    $DateBegin = strftime("%Y.%m.%d", strtotime($newTrackTime));    // convert track time 
    $DateFinish = strftime("%Y.%m.%d", strtotime($newTrackTime));   // convert track time 
    $trackName = $gpx->trk->name;                                   // Track name
            
    $sql = "INSERT INTO `tourdb2`.`tbl_tracks`";                    // Insert Source file name, gps start time and toReview flag
    $sql .= " (`trkSourceFileName`, `trkRoute`, `trkTrackName`, `trkGPSStartTime`, ";
    $sql .= " `trkDateBegin`, `trkDateFinish`, `trkLoginName`) VALUES "; 

    // trkSourceFileName
    $sql .= "('" . $filename . "', ";                               // create value bracket statement
    $sql .= "'test', ";
    $sql .= "'" . $trackName . "', ";
    $sql .= "'" . $GpsStartTime . "', ";
    $sql .= "'" . $DateBegin . "', ";
    $sql .= "'" . $DateFinish . "', ";
    $sql .= "'" . $loginname . "') ";

    fputs($GLOBALS['logFile'], "Line 143 - sql: $sql\r\n");

    if ($conn->query($sql) === TRUE)                                // run sql against DB
    {
        if ($GLOBALS['debugLevel']>3) fputs($GLOBALS['logFile'], "Line 163 - New track inserted successfully\r\n");
    } else {
        if ($GLOBALS['debugLevel']>0) fputs($GLOBALS['logFile'], "Line 165 - Error inserting trkPt: $conn->error\r\n");
        return -1;
    } 

    $sql = "SELECT max(`trkId`) FROM `tourdb2`.`tbl_tracks` ";      // Search for trkId of record just created

    if ($stmt = mysqli_prepare($conn, $sql)) 
    {
        mysqli_stmt_execute($stmt);                                 // execute select statement
        mysqli_stmt_bind_result($stmt, $trackid);                   // bind result variables

        while (mysqli_stmt_fetch($stmt)) {                          // Fetch result of sql statement (one result expeced)
            if ($GLOBALS['debugLevel']>4) fputs($GLOBALS['logFile'], "Line 177 - sql: $sql\r\n");
            
            // create JSON object with known gpx data
            $trackobj = array (
                "trkId"=>$trackid,
                "trkSourceFileName"=>"$filename",
                "trkTrackName"=>"$trackName",
                "trkDateBegin"=>"$DateBegin",
                "trkDateFinish"=>"$DateFinish",
                "trkGPSStartTime"=>"$GpsStartTime",
                "trkDistance"=>"",
                "trkTimeOverall"=>"",
                "trkMeterUp"=>"",
                "trkMeterDown"=>"",
            );
        }
        return array($trackid,$trackobj);                           // return tmp trackId, track name and coordinate array in array
        mysqli_stmt_close($stmt);                                   // Close statement
    } else {
        if ($GLOBALS['debugLevel']>0) fputs($GLOBALS['logFile'], "Line 195 - Error selecting max(trkId): $conn->error\r\n");
        if ($GLOBALS['debugLevel']>4) fputs($GLOBALS['logFile'], "Line 196 - sql: $stmt\r\n");
        return -1;
    } 
}

// ----------------------------------------------------------
// Insert track points into table
// ----------------------------------------------------------
function insertTrackPoints($conn,$trackid,$filename) 
{
    if ($GLOBALS['debugLevel']>2) fputs($GLOBALS['logFile'], "Line 207 - Function insertTrackPoints entered\r\n");
    
    $tptNumber = 1;                                                 // Set counter for tptNumber to 1
    $loopCumul = $GLOBALS['loopSize'];                              // loopCumul is the sum of loop sizes processed
    $gpx = simplexml_load_file($filename);                          // Load XML structure
    $trackName = $gpx->trk->name;  
    $coordArray = array();                                          // initialize array to store coordinates in kml style

    $totalTrkPts = count($gpx->trk->trkseg->trkpt);                 // total number of track points in file
    $loop = 0;                                                      // set current loop to 0 (only required for debug purposes)

    $sqlBase = "INSERT INTO `tourdb2`.`tmp_trackPoints`";           // create first part of insert statement 
    $sqlBase .= " (`tptNumber`, `tptTrackFID`, `tptLat`, `tptLon`, ";
    $sqlBase .= "  `tptEle`, `tptTime`) VALUES "; 
    
    $firstRec = 1;                                                  // flag first record as all other records need to be treated slightly different 

    foreach ($gpx->trk->trkseg->trkpt as $trkpt)                    // loop through each trkpt XML element in the gpx file
    {                  
        if ($firstRec == 1)                                         // if record is not first, a comma is written
            {
                $sql = $sqlBase;                                    // Add first part of sql to variable $sql
                $firstRec = 0;
        } else
        {
            $sql .= ",";
        }
        
        $sql .= "('" . $tptNumber . "', ";                          // write tptNumber - a continuous counter for the track points
        $sql .= "'" . $trackid . "', ";                             // tptTrackFID - reference to the track         
        $sql .= "'" . $trkpt["lat"] . "', ";                        // tptLat - latitude value 
        $sql .= "'" . $trkpt["lon"] . "', ";                        // tptLon - longitude value
        $sql .= "'" . $trkpt->ele . "', ";                          // tptEle - elevation of track point
        $sql .= "'" . strftime("%Y.%m.%d %H:%M:%S", strtotime($trkpt->time)) . "')";     // tptTime - time of track point
        
        $coordString = $trkpt["lon"] . ',' . $trkpt["lat"] . ',' . $trkpt->ele . ' ';

        array_push( $coordArray, $coordString );                    // write Lon, Lat and Ele into coordArray array

        if($tptNumber == $loopCumul || $tptNumber == $totalTrkPts)  // If current loop size or last track is reached
        {        
            $loop++;
            if ($GLOBALS['debugLevel']>2) fputs($GLOBALS['logFile'], "Line 249 - loop: $loop\r\n");
            
            if ($conn->query($sql) === TRUE) {                      // execute query
                if ($GLOBALS['debugLevel']>6) fputs($GLOBALS['logFile'],"Line 252 - Sql: " . $sqldebug . "\r\n"); 
                if ($GLOBALS['debugLevel']>1) fputs($GLOBALS['logFile'],"Line 253 - New track points inserted successfully\r\n");
                $loopCumul = $loopCumul + $GLOBALS['loopSize'];     // Raise current loop size by overall loop size
                $firstRec = 1;                                      // Next record will be 'first'
                
            } else {
                if ($GLOBALS['debugLevel']>2) fputs($GLOBALS['logFile'],"Line 258 - Sql: " . $sql); 
                if ($GLOBALS['debugLevel']>1) fputs($GLOBALS['logFile'],"Line 259 - Error inserting trkPt! Error Message: $conn->error\r\n");
                return -1;
            }
        }       
        $tptNumber++;                                               // increase track point counter by 1
    }
    return array($trackid,$coordArray);                             // return tmp trackId, track name and coordinate array in array
}
?>