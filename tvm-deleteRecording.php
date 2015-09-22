<?php

/*

Deletes any cached local copy of the a recording and the cooresponding Twilio online version.

Intended to be called via AJAX from the main tvm.php module.

USAGE: htp://www.myserver.com/tvm-deleteRecording.php?rSID=RE9e3b370d02a9a95207cd2863f859952a
RETURNS: {{{rSID}}} for success, else {{{ERROR}}}
... where rSID is the Twilio recording SID

NOTE: Eventually the goal would be to convert this so that it returns a JSON result.

*/

define('__ROOT__', dirname(dirname(__FILE__)));
define('__DOWNLOADDIR__', __ROOT__."/downloads/");

require_once(__ROOT__.'/includes/myincludes.php');
    // include file that defines
            // $accountSid = 'ACrrrrrrrrzzzzzzzzyyyyyyyyxxxxxxxx';
            // $authToken = '12345678123456781234567812345678';
            // $twilioAccountRecordingUrl = "https://api.twilio.com/2010-04-01/Accounts/".$accountSid."/Recordings/";
require_once(__ROOT__.'/services/Twilio.php');
            // standard twilio php interface package

$client = new Services_Twilio($accountSid, $authToken);

function deleteLocalRecording($rID){
    $rPath = __DOWNLOADDIR__ . $rID . '.mp3';
    if (file_exists($rPath)){
        unlink($rPath);
        return true;
    }
    return false;
} // deleteLocalRecording()

function deleteTwilioRecording($tc, $rID){
    $tc->account->recordings->delete($rID);
        // note that this doesn't delete any transcription that might be associated with the recording
} // deleteTwilioRecording()


function deleteTwilioTranscription($tc, $tID){
    $$tc->account->transcriptions->delete($tID);
} // deleteTwilioTranscription()

$rSID = $_GET["rSID"];

try {
    if (deleteLocalRecording($rSID)){
        deleteTwilioRecording($client,$rSID);
        echo "{{{" . $rSID . "}}}"; 
    }
    else {
        echo "{{{ERROR}}}";
    }
} // try
catch (Services_Twilio_RestException $e){
    echo "{{{ERROR}}}";
} // catch

?>