<html>
<head>
    <title>PHP test</title>
</head>
<body>
<?php
/**
 * Created by PhpStorm.
 * User: Mahmoud
 * Date: 8/6/2017
 * Time: 1:08 AM
 */

require('./DatabaseCommunicator.php');


$server = "127.0.0.1"; //address of mysql database
$dbuser = "dbone"; //mysql username
$dbpass = "Drjing123";//mysql pass
$dbname = "themancavellc";//namte of the database


$communicator = new DatabaseCommunicator($server, $dbuser, $dbpass, $dbname);

if ($communicator->isConnected()) {

//	echo "We got connected <br>"; NOTE: WORKS

	if ($communicator->constructNewQuery($communicator::DELETE, "Customer")) {

		$communicator->addCondition("ID=8");

		printMessage("Attempted query:");
		printMessage($communicator -> rebuildQuery());

		$communicator->connectQuery(true);
        $results = $communicator->getLastResult();
		if (sizeof($results) == 0)
			printMessage($communicator->getError());
		else
			foreach ($results as $row)
				printMessage(join(",", $row));


	} else
		printMessage("the new query we built didn't work \n" . $communicator->getError());


} else
	printMessage("there was some kind of error connecting \n" . $communicator->getError());


function printMessage($message)
{

	echo "<h2>" . $message . "</h2><br>";

}


?>

</body>
</html>