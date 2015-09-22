<!DOCTYPE html>
<html>
<header>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="http://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.css">
<script src="http://code.jquery.com/jquery-1.11.3.min.js"></script>
<script src="http://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js"></script>
<title>TVM</title>
</header>

<script type="text/javascript"> //////////////////////////////////////////////

function ajaxDeleteRecording(rSID){
    // document.getElementById('actionStatus').innerHTML = "ajaxDelete " + rSID;
    var rXML;
    rXML = new XMLHttpRequest();
    rXML.onreadystatechange=function(){
        if (rXML.readyState==4 && rXML.status==200){
            // document.getElementById('actionStatus').innerHTML = "Recording deleted";
            var rtxt = this.responseText;
            // only grab payload... {{{mypayload}}}
            var rSID = rtxt.slice((rtxt.indexOf("{{{") + 3),(rtxt.indexOf("}}}")));
            // document.getElementById('ajaxResponse').innerHTML = "rSID: "+rSID;
            // document.getElementById('TD'+rSID).innerHTML = "D";
            // alert("Deleted: "+rSID);          
        }
    }
    rXML.open("GET","tvm-deleteRecording.php?rSID="+rSID+"&rdm="+Math.random(),true);
        // Math.random() parameter prevents cached response to GET
    rXML.send(null);
    // document.getElementById('actionStatus').innerHTML = "ajax delete recording request sent...";
} // ajaxDeleteRecording()

function deleteMessage(rSID){
    // alert("deleteMessage: " + rSID);
    $('#AUD'+rSID)[0].pause();
    ajaxDeleteRecording(rSID);
    $('#RPOP'+rSID).popup('close');                            
    $('#recList #TD'+rSID).remove().listview('refresh');
} // deleteMessage()

function testFunc(rSID){
    alert("testFunc:" + rSID);
} // testFunc()
</script>

<?php ////////////////////////////////////////////////////////////////////////

define('__ROOT__', dirname(dirname(__FILE__)));

require_once(__ROOT__.'/includes/myincludes.php');
    // include file that defines
            // $accountSid = 'ACrrrrrrrrzzzzzzzzyyyyyyyyxxxxxxxx';
            // $authToken = '12345678123456781234567812345678';
            // $twilioAccountRecordingUrl = "https://api.twilio.com/2010-04-01/Accounts/".$accountSid."/Recordings/";

require_once('tvm-urlfile.php');
    // some file io utility functions

require_once(__ROOT__.'/services/Twilio.php');
    // standard twilio php services

$server_root = $root = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$downloadDirectoryUrl= $server_root . "/downloads/";
$downloadDirectoryPath=__ROOT__."/downloads/";

$client = new Services_Twilio($accountSid, $authToken);

$dbhost     = 'localhost';
$dbname     = 'cbook';
$collection = 'gcontacts'; // google contacts collection
$phoneColl  = 'pcoll';     // phone number collection

// $mg = new Mongo(("mongodb://$dbhost"));
$mg = new MongoClient();
$db = $mg->$dbname;
$crecs = $db->$collection; // contact records
$pcoll = $db->$phoneColl;  // collction of phone numbers indexed to contact records

function listCalls($clientVar){
    echo "<p>listCalls()...</p>";
    foreach ($clientVar->account->calls as $call) {
        echo "Date: {$call->date_created} ";
        echo "From: {$call->from} ";
        echo "To: {$call->to} ";
        echo "Duration: {$call->duration} ";
        // echo "Status: {$call->status} ";
        echo "Sid: {$call->sid} ";
        echo "<br>";
    } // for
} // listCalls()

function formattedPhoneNum($cNo){
    // found nothing, so just return the calling number
    $areaCode = substr($cNo, 0, 3);
    $nextThree = substr($cNo, 3, 3);
    $lastFour = substr($cNo, 6, 4);
    $pNumber = '('.$areaCode.') '.$nextThree.'-'.$lastFour;
    return $pNumber;
} // formattedPhoneNum()

function stdPhoneNum($oldNum){
    // echo "Old#: ".$oldNum."\n";
    $nn = preg_replace("/[^0-9]/", "", $oldNum);
    if (strlen($nn)<10){
        // if it's less then 10, it's garbage, so clear it.
        $nn = ""; 
    }
    elseif (strlen($nn)>10){
        $nn = substr($nn,-10,10);
    }
    // echo "New#: ".$nn."\n";
    return $nn;
} // stdPhoneNum()

function strTranslateToRealContactID($cNo,$cRec){
    $rstr = "";
    if (array_key_exists('First Name', $cRec)){
        $rstr = $cRec['First Name'];
    }

    if (array_key_exists('Last Name', $cRec)){
        if (strlen($rstr) > 0){
            $rstr .= " ";
        }
        $rstr .= $cRec['Last Name'];
    }   

    // found first and/or last name
    if (strlen($rstr) > 0) {
        return $rstr;
    }

    // No first or last name, so try to return company name
    if (array_key_exists('Company', $cRec)){
        return $cRec['Company'];
    }

    return formattedPhoneNum($cNo);
    
} // strRealContactName()

function getRealIDFromRecording($twClient,$rec,$pcol,$ccol){
    $call = $twClient->account->calls->get($rec->call_sid);
    $callingNo = stdPhoneNum($call->from); // strip off the "+1" prefix
    // error_log('$callingNo: '.$callingNo);

    $sparm = ['phoneNumber' => $callingNo]; // search parameters
    $prec = $pcol->findOne($sparm);
    if ($prec !== NULL){
        // return "CALLER: ".$callingNo;
        // echo "Found phone number... looking up matching contact.\n";
        // echo "GUID: ".$prec['GUID']."\n";
        // var_dump($prec);
        $sparm = ['GUID' => $prec['GUID']]; // search parameters
        $crec = $ccol->findOne($sparm);
        if ($crec !==NULL){
            // echo "Found matching contact record.\n";
            return strTranslateToRealContactID($callingNo, $crec);
        }
    }
    return formattedPhoneNum($callingNo); // default is to simply return the calling number
} // getRealIDFromRecSID()

function listRecordings($cr, $pc, $clientVar, $dPath, $dUrl, $tUrl){
    // echo "<p>listRecordings()...</p>";
    echo "\n"; // html source formatting aid
    foreach ($clientVar->account->recordings as $recording) {
        // $recording->sid -> duration ->date_created ->call_sid ->uri
        $recordingSID = $recording->sid;
        $localPath = $dPath.$recordingSID.".mp3";
        $rUrl = $tUrl.$recordingSID.".mp3";
        $tableDataID = "TD".$recordingSID;
        $recordingPopID = "RPOP".$recordingSID;
        $audioElementID = "AUD".$recordingSID;
        $iconID = "ICO".$recordingSID;
        if (file_exists($localPath)) {
            // we already downloaded this recording
            // echo "<td id='".$tableDataID."'>+</td>";
        } else {
            // missing so try to download it
            if (storeUrlToFilesystem($rUrl,$localPath)){
                // echo "<td>o</td>";
            }
            else {
                // echo "<td>-</td>";
            }
        }

        echo "<li id='".$tableDataID."'>";
            // Hook the popup icon id
            echo "<script>";
                echo "$( document ).on( 'pagecreate', function() {";
                    // on recording pop
                    echo "$( '#".$recordingPopID."' ).on({";
                        echo "popupbeforeposition: function() {";
                            // echo "var ae = document.getElementById('".$audioElementID."');";
                            echo "var ae = $('#".$audioElementID."')[0];"; // equiv to above
                            echo "ae.currentTime=0;";
                            echo "ae.controls = true;";
                            echo "ae.play();";
                            // echo "alert('popupbeforeposition:".$recordingPopID."');";
                        echo "},";
                        echo "popupafterclose: function() {";
                            // echo "document.getElementById('".$audioElementID."').pause();";
                            echo "$('#".$audioElementID."')[0].pause();";
                            // echo "alert('popupafterclose:".$recordingPopID."');";
                        echo "}";
                    echo "});";
                    // If we wanted to specifically hook the recording delete button vs. use JQuery Mobile onclick attribute
/*
                    echo "$('#DEL".$recordingSID."').bind('tap',function(event){";
                        echo "if(event.handled !== true){"; // to eliminate JQuery "bounce" querk
                            // echo "alert('tapout');";
                            echo "event.handled = true;";
                            echo "$('#".$audioElementID."')[0].pause();";
                            // echo "ajaxDeleteRecording('".$recordingSID."');";                            
                            echo "$('#recList').remove('".$tableDataID."').listview('refresh');";
                            echo "$('#".$recordingPopID."').popup('close');";
                        echo "}";
                    echo "});";
*/   
                echo "});";
            echo "</script>"; 

            // Create Play Message popup
            echo "<div data-role='popup' id='". $recordingPopID . "' class='ui-content'>";
                 // Create the popup content
                echo "<a href='#' data-rel='back' class='ui-btn ui-corner-all ui-shadow ui-btn ui-icon-delete ui-btn-icon-notext ui-btn-right'>Close</a>";                           
                // Create the play recording popup
                echo "<audio id='".$audioElementID."'>";
                    echo "<source src='".$dUrl.$recordingSID.".mp3'>";
                echo "</audio>";
                echo "<p>Playing " . $audioElementID . "</p>";    
                // echo "<a href='#' id='DEL".$recordingSID."' class='ui-btn ui-icon-delete ui-btn-icon-left'>Delete Message</a>";
                echo "<a href='#' onclick=\"deleteMessage('".$recordingSID."')\" class='ui-btn ui-icon-delete ui-btn-icon-left'>Delete Message</a>";
            echo "</div>";

            // Create Message Info slider

            // .... add message info slider stuff here

            // Actual list element
            echo "<a href='#". $recordingPopID . "' data-rel='popup'>" . getRealIDFromRecording($clientVar,$recording, $pc, $cr) . "<span class='ui-li-count'>" . $recording->duration . "s</span></a>";
            echo "<a href='#" . $recordingPopID . "' id='".$iconID."' data-rel='popup' data-icon='bullets'>Info</a>";
        echo "</li>";

        echo "\n"; // html source formatting aid

        // $myDate = date_create($recording->date_created);
        //echo "<td>".date_format($myDate,"Y/m/d H:i:s ")."</td>";
    } // for
    echo "\n"; // html source formatting aid
} // listRecordings()

function testContent(){
    alert("testContent()");
}

?> <!-- ////////////////////////////////////////////////////////////////// -->

<body>

<!-- -------------------- PAGE - HOME ----------------------- -->
<div data-role="page" id="home">
    <div data-role="header" data-position="fixed">
        <a href="#info" class="ui-btn ui-corner-all ui-shadow ui-icon-plus ui-btn-icon-left">Info</a>
        <h1>TVM v0.1</h1>
        <a href="#" class="ui-btn ui-corner-all ui-shadow ui-icon-gear ui-btn-icon-left">Settings</a>
    </div> <!-- header -->

    <div data-role="main" class="ui-content">
        <p>Home page...</p>
    </div> <!-- main -->

    <div data-role="footer" data-position="fixed" style="text-align:center;">
        <div data-role="controlgroup" data-type="horizontal">
            <a href="#calls" class="ui-btn ui-corner-all ui-shadow ui-icon-bullets ui-btn-icon-left">Calls</a>    
            <a href="#recordings" class="ui-btn ui-corner-all ui-shadow ui-icon-bullets ui-btn-icon-left">Recordings</a>
        </div> <!-- controlgroup -->
    </div> <!-- footer -->
</div> <!-- page -->

<!-- -------------------- PAGE - INFO ----------------------- -->
<div data-role="page" id="info">
    <div data-role="header" data-position="fixed">
            <a href="#" class="ui-btn ui-corner-all ui-shadow ui-icon-plus ui-btn-icon-left ui-btn-active ui-state-persist">Info</a>
        <h1>TVM v0.1</h1>
        <a href="#" class="ui-btn ui-corner-all ui-shadow ui-icon-gear ui-btn-icon-left" alt="Settings">Settings</a>
    </div> <!-- header -->

    <div data-role="main" class="ui-content">
        <p>Information page...</p>
    </div> <!-- main -->

    <div data-role="footer" data-position="fixed" style="text-align:center;">
        <div data-role="controlgroup" data-type="horizontal">
            <a href="#calls" class="ui-btn ui-corner-all ui-shadow ui-icon-bullets ui-btn-icon-left">Calls</a>    
            <a href="#recordings" class="ui-btn ui-corner-all ui-shadow ui-icon-bullets ui-btn-icon-left">Recordings</a>
        </div> <!-- controlgroup -->
    </div> <!-- footer -->
</div> <!-- page -->

<!-- -------------------- PAGE - CALLS ----------------------- -->
<div data-role="page" id="calls">
    <div data-role="header" data-position="fixed">
        <a href="#info" class="ui-btn ui-corner-all ui-shadow ui-icon-plus ui-btn-icon-left">Info</a>
        <h1>TVM v0.1</h1>
        <a href="#" class="ui-btn ui-corner-all ui-shadow ui-icon-gear ui-btn-icon-left">Settings</a>
    </div> <!-- header -->

    <div data-role="main" class="ui-content">
        <ol data-role="listview">
            <li><a href="#">Call</a></li>
            <li><a href="#">Call</a></li>
            <li><a href="#">Call</a></li>
        </ol>
    </div> <!-- main -->

    <div data-role="footer" data-position="fixed" style="text-align:center;">
        <div data-role="controlgroup" data-type="horizontal">
            <a href="#" class="ui-btn ui-corner-all ui-shadow ui-icon-bullets ui-btn-icon-left ui-btn-active ui-state-persist">Calls</a>    
            <a href="#recordings" class="ui-btn ui-corner-all ui-shadow ui-icon-bullets ui-btn-icon-left">Recordings</a>
        </div> <!-- controlgroup -->
    </div> <!-- footer -->
</div> <!-- page -->

<!-- -------------------- PAGE - RECORDINGS ----------------------- -->
<div data-role="page" id="recordings">
    <div data-role="panel" id="adelePage"> 
        <h2>Adele Pitt</h2>
        <p>Phone number: 555-555-7483</p>
        <p>Address: 121 N. Lisa Street
        <br>Chicago, Illinois 13294 USA</p>
        <p>Email: pittmail@nomail.com</p>
        <a href="#recordings" data-rel="close" class="ui-btn ui-btn-inline ui-shadow ui-corner-all ui-btn-b ui-icon-delete ui-btn-icon-left">Close</a>
    </div>


    <div data-role="header" data-position="fixed">
        <a href="#info" class="ui-btn ui-corner-all ui-shadow ui-icon-plus ui-btn-icon-left">Info</a>
        <h1>TVM v0.1</h1>
        <a href="#" class="ui-btn ui-corner-all ui-shadow ui-icon-gear ui-btn-icon-left">Settings</a>
    </div> <!-- header -->

    <div data-role="main" class="ui-content">
        <h3>Recordings</h3>
        <form class="ui-filterable">
            <input id="recordingFilter" data-type="search" placeholder="Search for names..">
        </form>
        <ol id="recList" data-role="listview" data-filter="true" data-input="#recordingFilter" data-inset="true">
            <?php listRecordings($crecs, $pcoll, $client, $downloadDirectoryPath, $downloadDirectoryUrl, $twilioAccountRecordingUrl); ?>
        </ol>
    </div> <!-- main -->

    <div data-role="footer" data-position="fixed" style="text-align:center;">
        <div data-role="controlgroup" data-type="horizontal">
            <a href="#calls" class="ui-btn ui-corner-all ui-shadow ui-icon-bullets ui-btn-icon-left">Calls</a>    
            <a href="#" class="ui-btn ui-corner-all ui-shadow ui-icon-bullets ui-btn-icon-left ui-btn-active ui-state-persist">Recordings</a>
        </div> <!-- controlgroup -->
    </div> <!-- footer -->
</div> <!-- page -->


</body>
</html>
