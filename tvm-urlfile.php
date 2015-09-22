<?php

function allowUrlFopen(){
	if (ini_get('allow_url_fopen')) {
		return true;
	} 
	else {
		// not currently allowed, but try and fix that
		ini_set('allow_url_fopen', true);
		if (ini_get('allow_url_fopen')) {
			return true;
		} 
		else {
			return false;
		}
	}
} // function allowUrlFopen()

function storeUrlToFilesystem($url, $localFile) {
	try {
		$pRemote=fopen($url, 'r');
		if ($pRemote){
			$pLocal=fopen($localFile, 'w');
			if ($pLocal){
				while(!feof($pRemote)){
    				fwrite($pLocal, fread($pRemote, 8192));
				}
				fclose($pLocal);
				return true;
			}
			fclose($pRemote);
		}
	} catch (Exception $e) {
    	// echo "<p>".'Caught exception: '.$e->getMessage()."</p>";
		return false;
	} // catch()
	return false;
} // storeUrlToFilesystem()

?>