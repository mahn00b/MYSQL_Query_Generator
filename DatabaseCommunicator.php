<?php

/**
 * Created by PhpStorm.
 * User: Mahmoud
 * Date: 7/23/2017
 * Time: 8:50 PM
 */
class DatabaseCommunicator
{

	//constants:

	//database operations:
	const INSERT = 0;
	const DELETE = 1;
	const UPDATE = 2;
	const SEARCH = 3;

	//error numbers:
	const FIELD_NOT_EXIST_ERROR = 100;
	const INVALID_TYPE_OPERATION = 101;
	const MYSQL_ERROR = 102;
	const CANNOT_RETRIEVE_FIELDS = 103;
	const NOT_ENOUGH_FIELDS_ERROR = 104;
	const ZERO_CONDITION_DANGER = 105;
	const NULL_QUERY_TYPE = 106;
	const INVALID_ARGS = 107;
	const NO_SET_CONDITIONS = 108;
	const NO_SET_SELECTIONS = 109;
	const NO_UPDATED_FIELDS = 110;
	const UNKNOWN_ERROR = 111;

	//private variables:

	private $database_address = "127.0.0.1";
	private $admin_username = "username";
	private $admin_password = "password";
	private $database_name = "database";
	private $database_connection = null;

	private $attempted_queries = array();
	private $retrieved_results = array();

	private $conditional_array = array();
	private $column_array = array();
	private $selected_values = array();
	private $updated_values = array();
	private $values_to_insert = array();
	private $current_query_type = null;
	private $last_error_number = null;
	private $current_query = "";
	private $last_result = "";
	private $current_table = "";
	private $last_error = "";

	//CONSTRUCTOR(S):

	/*
	 *
	 * DatabaseCommunicator
	 * @param $add - address of database
	 * @param $user - username of the accessing admin
	 * @param $pass- password of the accessing admin
	 * @param $name - name of the database
	 * */
	function __construct($add, $user, $pass, $name)
	{

		$this->database_address = $add;
		$this->admin_username = $user;
		$this->admin_password = $pass;
		$this->database_name = $name;
		$this->database_connection = mysqli_connect($add, $user, $pass, $name);

	}



	//DESTRUCTOR:
	/*
	 * destruct() - cleans up and closes database connection
	 * -
	 * */

	function __destruct()
	{
		//close the database connection after destructed
		mysqli_close($this->database_connection);
	}




	//HELPER FUNCTIONS:

	/*
	 * retryConnection() - if connection to MySQL fails, user may retry that connection
	 * @return true if connnection has been made false if not
	 * */
	function retryConnection()
	{

		$this->database_connection = mysqli_connect($this->database_address,
			$this->admin_username,
			$this->admin_password,
			$this->database_name);


		return $this->isConnected();
	}


	/*
	 * constructNewQuery() - will start building a new query based on a selected type, indicated by
	 * the classes constants, SEARCH, DELETE, UPDATE, INSERT. Query type will be changed and conditionals,
	 * values, and column names will be reset. If either value is null then function will return false and keep
	 * previous query values.
	 * @param $type - Type indicated by said integer constants
	 * @param $table - name of the table to query
	 * @return - True if new query was successfully constructed, false if not
	 * */
	function constructNewQuery($type, $table)
	{

		if (!$this->isConnected()) return false;//if database is not connected return false



			$this->resetCurrentQuery();
			$this->current_table = $table;
			$this->current_query_type = $type;



		$queryFields = "DESCRIBE " . $table;

		echo "The query we're sending: " . $queryFields . "\n";

		//connect to the database to get all the field names for the table
		if (($results = $this->database_connection->query($queryFields)) != FALSE) {

			echo "we have successfully described the table \n";

			//push each field into the column array
			foreach ($results as $row)
				array_push($this->column_array, $row['Field']);

			return true;

			//if we can't get the fields of the table, then certain functions won't operate properly
			//reset the query, and return
		} else
			return $this -> resolveError($this::CANNOT_RETRIEVE_FIELDS);


	}


	/*
	 * addCondition() - must take at least one arg to add a condition. Conditions are passed as Strings.
	 * Conditions added together in the same call will be ANDED together in the query. Subsequent calls to
	 * this function will append functions with an OR.
	 * @param one or more conditions. Conditions should be passed as Strings created in the mysql convention, for
	 * example "ID = 8", "age > 3"
	 * @return True if condition successfully added to array, False if not added
	 * */
	function addCondition()
	{
		$numArgs = func_num_args();
		if (!$this->isConnected() || $numArgs == 0) return false;//if database is not connected or no args return false

		$newCondition = "";//start building a new condition

		$i = 0;

		for (; $i < $numArgs - 1; $i++)//combine all args, if only one will not even run
			$newCondition .= func_get_arg($i) . " AND ";

		$newCondition .= func_get_arg($numArgs - 1); //last arg will never be ANDED

		array_push($this->conditional_array, $newCondition);

		return true;

	}

	/*
	 * selectValue() - this will help specify which values to change on UPDATE or add on INSERT
	 * @param $colName - the name of the column to select in a search query
	 * @return true if name of field exists, return false if no field with that name, or query is the
	 * wrong type.
	 * */
	function selectValue($colName)
	{

		if (!$this->isConnected()) //if database is not connected return false
		 return false; //NOTE: only searching can select fields

		if($this->current_query_type != $this::SEARCH)
			return $this -> resolveError($this::INVALID_TYPE_OPERATION);

		if (in_array($colName, $this->column_array))
			array_push($this->selected_values, $colName);
		else
			return $this -> resolveError($this::FIELD_NOT_EXIST_ERROR);

		return true;
	}

	/*
	 * insertValue() - choose a field to assign a value for an INSERT query
	 * @param @colName - name of column to edit for inserted row
	 * @param @val - value of column
	 * @return Boolean true if column exist, false if not
	 * */
	function insertValue($colName, $val)
	{
		if (!$this->isConnected()) //if database is not connected return false
			//NOTE: must be using an INSERT query.
		 return false;

		if($this->current_query_type != $this::INSERT )
			return $this -> resolveError($this::INVALID_TYPE_OPERATION);

		//if column is not in array of columns
		if(!in_array($colName, $this->column_array))
		    return	$this -> resolveError($this::FIELD_NOT_EXIST_ERROR);

		//mysql needs quotes for string values
		if(is_string($val)) $val = $this -> padQuotes($val);


		//we can use Column name as a key for easier query building
		$this -> values_to_insert[$colName] = $val;
		return true;
	}

	/*
	 *updateValue() - will update a column in a table to corresponding values based on any conditions set. Only used
	 * with UPDATE query
	 * @param $colName - the name of the column to update.
	 * @param $val - value to update.
	 * @return True if added to updates, False if column doesn't exist.
	 *
	 * */
	 function updateValue($colName, $val)
	{
		if (!$this->isConnected()) //if database is not connected return false
			  return false;




		//NOTE: must be using an UPDATE query.
		if( $this->current_query_type != $this::UPDATE)
			return $this -> resolveError($this::INVALID_TYPE_OPERATION);


		//if column is not in array of columns

		if(!in_array($colName,$this->column_array))
			return $this->resolveError($this::FIELD_NOT_EXIST_ERROR);


		//using join we can add an "=" and push update
		$this ->updated_values[$colName] = $val;
		return true;
	}


	/*
	 * buildQuery() - combines all the information gathered on the query and builds a large query string
	 * @return String query constructed;
	*/
	 function rebuildQuery()
	{
		if (!$this->isConnected()) return null;//if database is not connected return false

		switch ($this->current_query_type) {
			case $this::SEARCH :

				//SYNTAX: SELECT [selected_values] FROM [current_table]
				$this->current_query = "SELECT ";

				//if user has selected specific values they want to be returned, should call this -> addSelectedValues()
				if (sizeOf($this->selected_values) > 0)
					$this->current_query .= join(",", $this->selected_values) . " ";
				else
					$this->current_query .= "* ";//if selected_array is empty then we take all values

				//add table name
				$this->current_query .= "FROM " . $this->current_table;

				break;
			case $this::DELETE :

				//NOTE: The DELETE query needs a WHERE clause
				$this->current_query = "DELETE FROM " . $this->current_table;

				break;
			case $this::UPDATE :
				//NOTE: The UPDATE Query syntax : UPDATE [tablename] SET COL1=val1,col2=val2,..coln=valn
				$this->current_query = "UPDATE " . $this->current_table . " SET ";

				if (sizeOf($this->updated_values) > 0)
					foreach(array_keys($this->updated_values) as $key)
						$this -> current_query .= $key . "=" .
								( is_string($this->updated_values[$key]) ? //if value is string
									'"' . $this->updated_values[$key] . '"': //then concat quotes for value
									$this->updated_values[$key]) . ',';//if not just concat the value


				//IDEA: PAD THE QUOTES IN UpdateValue(0 to avoid all these complexities

				//remove last comma, definitely should think of a better way.
				$this -> current_query = substr_replace($this -> current_query, "", -1);

				break;
			case $this::INSERT :
				//NOTE: INSERT SYNTAX: INSERT INTO [tablename](col1,col2,...coln) VALUES(val1,val2,...,valn)
				$this->current_query = "INSERT INTO " . $this->current_table
					. "(" . (join(",", array_keys($this->values_to_insert))) . ") "
					. "VALUES(" . (join(",", array_values($this->values_to_insert))) . ") ";
				break;
			default :
				return false;
				break;
		}

		/*
		 * Since all queries can attach conditionals we can do that outside
		 * the switch statement:
		 * */
		if (sizeOf($this->conditional_array) > 0){
			$this->current_query .= " WHERE ";

			 foreach($this ->conditional_array as $index=>$condition)
			 	$this-> current_query .= $condition . ( $index < sizeof($this->conditional_array)-1 ? " OR " : "");

		}




		return $this->current_query;//return final query
	}


	/*
	 * connectQuery() - Connect query will rebuild the query and validate it for the
	 * query type.
	 * @param $strict = true - When this value is true it makes validation for dangerous
	 * queries, false will remove those limits
	 * @return Boolean true if query is made
	 * */

	 function connectQuery($strict = true) {

		if (!$this->isConnected()) return false;//if database is not connected return false

		$this -> rebuildQuery();//rebuild query with latest parameters

		if(!($this -> validateCurrentQuery($strict))) return false;

		$this -> last_result = ($this -> database_connection -> query($this -> current_query));

		//check for an error in the connection
		if($this -> last_result == FALSE)
			$this -> last_result = [];

		//push the attempted query and resulting array
		array_push($this -> attempted_queries, $this -> current_query);
		array_push($this -> retrieved_results, $this -> last_result);


		//return false if false, but true if anything else
		return ($this -> last_result == FALSE ?  $this->resolveError($this::MYSQL_ERROR):true);

	}




	/*
	 * validateCurrentQuery() - is a function that ensures correct parameters have
	 * been selected. Strict validation will not allow DELETE or UPDATE to post a query
	 * without conditions first. Otherwise we can risk losing data.
	 * @param $strict = true - set strictness of query to limit the submission of dangerous queries
	 * @return Boolean true if query is valid, false if parameters not made
	 * */
	private function validateCurrentQuery($strict = true){


		if (!$this->isConnected()) return false;//if database is not connected return false


		switch($this -> current_query_type){
			case $this::INSERT :
				//if values haven't been assigned to insert then inValid query
				if(sizeof($this-> values_to_insert) == 0)
					return $this ->resolveError($this::NOT_ENOUGH_FIELDS_ERROR);
				break;
			case $this::DELETE :

				//we don't allow for user to delete without conditions, during a
				// or we risk losing entire tables of data
				if($strict && sizeof($this -> conditional_array) == 0)
					return $this -> resolveError($this::ZERO_CONDITION_DANGER);

				break;
			case $this::UPDATE :
				//cannot update rows with no updated values
				if(sizeof($this -> updated_values) == 0)
					return $this->resolveError($this::NOT_ENOUGH_FIELDS_ERROR);

				//prevent the sending of a query that could update the entire table
				if($strict && (sizeof($this -> conditional_array) == 0))
					return $this -> resolveError($this::ZERO_CONDITION_DANGER);

				break;

			case null :
				//if query type is null then query hasn't been constructed yet
				$this->resetCurrentQuery();
				return $this -> resolveError($this::NULL_QUERY_TYPE);
				break;
			}

			return true;

	}


	/*
	 * resetCurrentQuery() - will store the previously attempted query, and reset members
	 * for a new query
	 * @return Boolean true if successfully reset, false if database not connected or current
	 * query is strictly an empty string
	 * */
	 function resetCurrentQuery() {

		if((!$this ->isConnected()))
			return false;//return false if database is not connected or no new queries created yet

		if( $this -> current_query_type == null)
			return $this->resolveError($this::NULL_QUERY_TYPE);

		//reset all query info
		//$this->current_query_type = $this::SEARCH; //Search and select queries are default
		$this->current_query = "";
		$this->current_table = "";
		$this->column_array = array();
		$this->selected_values = array();
		$this->conditional_array = array();

		return true;
	}



	//GETTERS AND SETTERS:

	/*
	 * getConditions() - returns an array of any added conditions in String format
	 * @return - String []
	 * */
	function getCondition(){

		return $this ->conditional_array;
	}


	function getSelectedFields() {
		return $this -> selected_values;
	}

	function getUpdatedFields() {
		return $this -> updated_values;
	}

	function getInsertedValues() {
		return $this -> values_to_insert;
	}

	function getLastResult(){
		return $this-> last_result;
	}


	/*
	 * removeCondition() - removes a condition(s) based on 0-based indices passed into the array, if none,
	 *  the first index in the array
	 * @param $index - int 0-based index to delete one of the conditions
	 * @param [OPTIONAL] if user wishes to remove one or more
	 * @return null if nothing was deleted, or an array of 1 or more deleted conditions
	 * */
	function removeCondition($index = 0) {

		if(!$this->isConnected()  //if no connection was established
			//if there are no created conditions
			)//if # of args greater than # of conditions
				return false;



		if(func_num_args() > sizeof($this -> conditional_array))
			return $this ->resolveError($this::INVALID_ARGS);


		if(sizeof($this -> conditional_array) == 0)
			return $this -> resolveError($this::NO_SET_CONDITIONS);

		$deleted = array(); //array to store deleted conditions

		foreach(func_get_args() as $arg) {
			if ($this->conditional_array[$arg] != null) {

				//push condition into delete[] before deletion
				array_push($deleted, $this->conditional_array[$arg]);

				unset($this->conditional_array[$arg]);//deletes single index without re-enumerating the array

			}

		}

		array_values($this -> conditional_array);//re-enumerates indices after all is deleted

		return (sizeof($deleted) > 0 ?  $deleted : null);

	}


	/*
 * removeSelectedField() - removes a selected field(s) for a search
 *  based on 0-based indices passed into the function, if no index passed, the first index in
	 * the array will be removed.
 * @param $index - int 0-based index to delete one of the conditions
 * @param [OPTIONAL] if user wishes to remove one or more
 * @return null if nothing was deleted, or an array of 1 or more deleted conditions
 * */

	function removeSelectedField($index){

		if(!$this->isConnected())  //if no connection was established
			 //if there are no selected fields
			//if # of args greater than # of selected
			return null;

		if( func_num_args() > sizeof($this -> selected_values))
			return $this ->resolveError($this::INVALID_ARGS);

		if( sizeof($this -> selected_values) == 0)
			$this -> resolveError($this::NO_SET_SELECTIONS);


		$deleted = array(); //array to store deleted selections

		foreach(func_get_args() as $arg) {
			if ($this->selected_values[$arg] != null) {

				//push condition into delete[] before deletion
				array_push($deleted, $this->selected_values[$arg]);

				unset($this->selected_values[$arg]);//deletes single index without re-enumerating the array

			}

		}


		array_values($this -> selected_values); //re-enumerates indices after all is deleted

		return (sizeof($deleted) > 0 ?  $deleted : null);

	}



	function removeUpdatedField($index = 0){
		if(!$this->isConnected())  //if no connection was established
			return null;

		//if # of args greater than # of selected
		if( func_num_args() > sizeof($this -> updated_values))
			return $this ->resolveError($this::INVALID_ARGS);

		//if there are no updated fields
		if( sizeof($this -> updated_values) == 0)
			$this -> resolveError($this::NO_UPDATED_FIELDS);


		$deleted = array(); //array to store deleted selections

		foreach(func_get_args() as $arg) {
			if ($this->updated_values[$arg] != null) {

				//push condition into delete[] before deletion
				array_push($deleted, $this->updated_values[$arg]);

				unset($this->updated_values[$arg]);//deletes single index without re-enumerating the array

			}

		}


		array_values($this -> updated_values); //re-enumerates indices after all is deleted

		return (sizeof($deleted) > 0 ?  $deleted : null);

	}

	/*
	 * removeInsertedField() - removes a value to be inserted by key, or index.
	 * @param $key - this is the key of the inserted field you wish to remove.
	 * @return - removed field or fields
	 * */
	function removeInsertedField($key){
		if(!$this -> isConnected())
			return false;

		if(!array_key_exists($key, $this -> values_to_insert))
			return $this -> resolveError($this::FIELD_NOT_EXIST_ERROR);


		unset($this -> values_to_insert[$key]);

		array_values($this -> values_to_insert); //re-enumerate the array

		return $key;
	}


	function editInsertedField($key, $newVal){
		if(!$this -> isConnected())
			return false;

		if(!array_key_exists($key, $this -> values_to_insert))
			return $this -> resolveError($this::FIELD_NOT_EXIST_ERROR);

		$this -> values_to_insert[$key] = $newVal;

		return $key;
	}

	/*
	 *isConnected - will return whether the connection was successfully established
	 *@return - Boolean true if connected, false otherwise.
	 * */
	 function isConnected()
	 {
		 $connection_error = ($this->database_connection == FALSE);

		 if ($connection_error) return $this->resolveError($this::MYSQL_ERROR);

		 return true;
	 }



	/*
	 * resolveError() - handles error messages for calling functions nd handles specific errors depending on specific errors.
	 * @errNo - accepts an error number from function, and sets the error as appropriate, if
	 * the error does not exist as a class constant, then the error will be unknown
	 * @return FALSE - to resolve returns for calling functions
	 *
	 * */

	private function resolveError($errNo){

	 	switch($errNo){
		    case $this::FIELD_NOT_EXIST_ERROR:
		    	$this->last_error_number = $this::FIELD_NOT_EXIST_ERROR;
		    	$this->last_error = "FIELD_NOT_EXIST_ERROR 100: field passed doesn't exist for currently, selected table or query operation";
		    	break;
		    case $this::INVALID_TYPE_OPERATION:
		    	$this ->last_error_number = $this::INVALID_TYPE_OPERATION;
		    	$this -> last_error  = "INVALID_TYPE_OPERATION 101: Operation cannot be preformed for current query type";
		    	break;
		    case $this::MYSQL_ERROR :
			    $this ->last_error_number = mysqli_errno($this -> database_connection);
			    $this -> last_error  = "MYSQL_ERROR " . $this ->last_error_number . ": " . mysqli_error($this->database_connection);
			    break;
		    case $this::CANNOT_RETRIEVE_FIELDS:
			    $this ->last_error_number = $this::CANNOT_RETRIEVE_FIELDS;
			    $this -> last_error  = "CANNOT_RETRIEVE_FIELDS 103: Operation cannot be preformed for current query type";
			    break;
		    case $this::NOT_ENOUGH_FIELDS_ERROR:
			    $this ->last_error_number = $this::NOT_ENOUGH_FIELDS_ERROR;
			    $this -> last_error  = "NOT_ENOUGH_FIELDS_ERROR 104: The selected query does not have enough field to be valid";
			    break;
		    case $this::ZERO_CONDITION_DANGER:
			    $this ->last_error_number = $this::ZERO_CONDITION_DANGER;
			    $this -> last_error  = "ZERO_CONDITION_ERROR 105: No conditions for the selected type of query can make undesirable changes to the entire table";
			    break;
		    case $this::NULL_QUERY_TYPE:
			    $this ->last_error_number = $this::NULL_QUERY_TYPE;
			    $this -> last_error  = "NULL_QUERY_TYPE 106: No query type, start to construct a query to set a type";
			    break;
		    case $this::INVALID_ARGS:
			    $this ->last_error_number = $this::INVALID_ARGS;
			    $this -> last_error  = "INVALID_ARGS 107: Arguments are invalid for this function, see docs.";
			    break;
		    case $this::NO_SET_CONDITIONS:
			    $this ->last_error_number = $this::NO_SET_CONDITIONS;
			    $this -> last_error  = "NO_SET_CONDITIONS 108: No conditions for the query have been set";
			    break;
		    case $this::NO_SET_SELECTIONS:
			    $this ->last_error_number = $this::NO_SET_SELECTIONS;
			    $this -> last_error  = "NO_SET_CONDITIONS 109: No fields have been selected";
			    break;
		    case $this::NO_UPDATED_FIELDS:
			    $this ->last_error_number = $this::NO_SET_CONDITIONS;
			    $this -> last_error  = "NO_UPDATED_FIELDS 110: No fields have been updated.";
			    break;
		    default:
		    	$this -> last_error_number = $this::UNKNOWN_ERROR;
		    	$this -> last_error = "UKNOWN_ERROR 111";
	    }

	    return false;//this will always return false for calling functions to resolve their return statements

	}


	/*
	 * getError() - returns the specific mysql error in connected or query
	 * @return - the last error or "" empty string indicating no error
	 * */
	 function getError()
	{
		return $this -> last_error;
	}

	/*
	 * getCurrentTableFields() - returns the fields for the currently selected table
	 * @return Array of associated field names, or empty if no query has started to be
	 * constructed.
	 * */
	function getCurrentTableFields(){

	 	return $this->column_array;
	}

	/*
	 * setTable() - sets the table for the query being currently constructed, will
	 * reset currentTableFields, and reset the query. Will return false if database is
	 * not connected or it could not retrieve the new field names.
	 * */
	function setTable($tableName) {
		if(!($this -> isConnected())) return false;


		if(($result = $this ->database_connection -> query("DESCRIBE " . $tableName)) != false){

			$this-> resetCurrentQuery();

			$this -> current_table = $tableName;

			foreach($result as $row)
				array_push($this-> column_array, $row['Field']);


			return true;

		}else
			return false;

	}


	/*
	 * getCurrentQuery() - returns the latest constructed query
	 * @return FALSE if connected has not been made, "" if no query constructed, or the
	 * last rebuilt query.
	 *
	 */
	function getCurrentQuery() {
		if(!$this -> isConnected()) return false;

		return $this -> current_query;
	}


	private function padQuotes($string){
		return ("\"" . $string . "\"");

	}


}

?>