<?php
/**
 * Created by PhpStorm.
 * User: Mahmoud
 * Date: 7/26/2017
 * Time: 6:38 PM
 */


require('./DatabaseCommunicator.php');



$server = "127.0.0.1"; //address of mysql database
$dbuser = "user"; //mysql username
$dbpass = "password";//mysql pass
$dbname = "database";//name of the database







//debugging funcitons:


function printMessage($message)
{
	echo "<h2>" . $message . "</h2><br>";
}
function printArray($arr, $hasKeys = false){
	if($hasKeys)
		$keys = array_keys($arr);
	for($i = 0; $i < sizeof($arr); $i++){
		if($hasKeys)
			printMessage($keys[$i] . " : " . $arr[$keys[$i]]);
		else
			printMessage($i . " : " . $arr[$i]);
	}
}



?>