<?php

echo "findContact v0.1...";

function strRealContactID($cNo,$cRec){
	$rstr = "";
	if (array_key_exists('First Name', $cRec)){
		echo 'Key [First Name] exists...'."\n";
		$rstr = $cRec['First Name'];
	}
	if (array_key_exists('Last Name', $cRec)){
		echo 'Key [Last Name] exists...'."\n";
		if (strlen($rstr) > 0){
		 	$rstr .= " ";
		}
		$rstr .= $cRec['Last Name'];
	}	

	if (strlen($rstr) > 0) {
		return $rstr;
	}
	else {
		return $cNo;
	}
} // strRealContactName()

$dbhost 	= 'localhost';
$dbname 	= 'cbook';
$collection	= 'gcontacts'; // google contacts collection
$phoneColl  = 'pcoll';     // phone number collection


// $mg = new Mongo(("mongodb://$dbhost"));
try {
	echo "\nTry to open the database...\n";
	$mg = new MongoClient();
	$db = $mg->$dbname;	
}
catch ( MongoConnectionException $e ) {
	echo "Couldn't connect to mongodb...";
    exit();
}

echo "Database opened...";

$crecs = $db->$collection; // contact records
$pcoll = $db->$phoneColl;  // collction of phone numbers indexed to contact records

echo "Collections assigned...";

$phoneNumber = "3042647000";
$sparm = ['phoneNumber' => $phoneNumber];
$prec = $pcoll->findOne($sparm);

console.log("Completed search...");


if ($prec !== NULL){
	echo "Found phone number... looking up matching contact...";
	echo "GUID: ".$prec['GUID']."...";
	// var_dump($prec);
	$sparm = ['GUID' => $prec['GUID']];
	$crec = $crecs->findOne($sparm);
	if ($crec !==NULL){
		echo "Found matching contact record...";
		echo strRealContactID($argv[1], $crec)."...";
	}
	else {
		echo "No matching contact record found...";
	}
}
else {
	echo "No matching phone number found...";
}

?>