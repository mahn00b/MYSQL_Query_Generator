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

echo "<h2>we in here</h2>";


$communicator = new DatabaseCommunicator($server,$dbuser,$dbpass,$dbname);

if($communicator -> isConnected()){

//	echo "We got connected <br>"; NOTE: WORKS

	if($communicator -> constructNewQuery($communicator::INSERT, "Customer")){

			//echo "we started building da new query <br>"; NOTE: WORKS


		echo "This is the column array: <br>";

		/*
		foreach($communicator -> getCurrentTableFields() as $field)
			echo $field . "<br>";
		*/

		if($communicator -> insertValue("firstname" , "Haytham") == false)
			;




	}else
		echo "the new query we built didn't work \n" . $communicator -> getError();




}else
	echo "there was some kind of error connecting \n" . $communicator -> getError();


?>