<?PHP

	
	$GLOBALS["server_has_mysqli"] = function_exists('mysqli_connect');
	
	function reviver($row)
	{
		// Mysql wants to return numbers as strings. Ex:  "3"
	    // So the next three lines of code converts number strings into numbers. Ex: "3" into 3
		
		if (is_array($row))
		foreach ($row as $key => $value)
		{
	    	if (is_numeric($row[$key]))
	    		$row[$key] = floatval($row[$key]);
	    }
	    return $row;
	} // end function reviver($row)
	
	function db_connect($server, $db_username, $db_pw, $database)
	{
		if ($GLOBALS["server_has_mysqli"])
		{
			// connect to mysql using mysqli procedural interface
		/*	$db_link = mysqli_connect($server, $db_username, $db_pw, $database);
			if (mysqli_connect_errno($db_link)) {
		    	echo "Failed to connect to MySQL: " . mysqli_connect_error($db_link);
			}
		*/
			// connect to mysql using mysqli object-oriented interface
			$db_link = new mysqli($server, $db_username, $db_pw, $database);
			if ($db_link->connect_errno) {
			    echo "Failed to connect to MySQL: " . $db_link->connect_error;
			}
		}
		else
		{
			// connect to mysql the old procedural way
			$db_link = mysql_connect($server, $db_username, $db_pw)
				or die("Couldn't connect to MySQL".mysql_error());
			// connect to database
			mysql_select_db($database , $db_link)
				or die("Select DB Error: ".mysql_error());	
		}
		return $db_link;
	} // end function db_connect()
	
	function db_escape_string($db_link, $string)
	{	
		if ($GLOBALS["server_has_mysqli"])
		{
			//$string = mysqli_real_escape_string($db_link, $string); // // mysqli procedural way
			$string = $db_link->real_escape_string($string); // mysqli Object-oriented way
		}
		else
		{
			$string = mysql_real_escape_string($string, $db_link); // mysql procedural way
		}
		return $string;
	} // end function db_escape_string($string);
	
	function db_query($db_link, $query)
	{	
		if ($GLOBALS["server_has_mysqli"])
		{
			//$result = mysqli_query($db_link, $query) or die($query." : ".mysqli_error($db_link)); // New MySQLi procedural way
			$result = $db_link->query($query) or die($query." : ".$db_link->error);  // New MySQLi object oriented way
		}
		else
		{
			// Note in old mydql $db_link is optional
			$result = mysql_query($query, $db_link) or die($query." : ".mysql_error($db_link)); // Old MySQL procedural way
		}
		return $result;	
	} // end function db_query()
	
	function db_num_rows($result)
	{
		if ($GLOBALS["server_has_mysqli"])
		{
			//$row_count = mysqli_num_rows($result); // New MySQLi procedural way
			$row_count = $result->num_rows;	// New MySQLi object oriented way
		}	
		else
		{
			$row_count = mysql_num_rows($result); // Old MySQL procedural way
		}
		return $row_count;
	} // end function db_num_rows($result)
	
	function db_fetch_array($result)
	{
		if ($GLOBALS["server_has_mysqli"])
		{
			//$row = mysqli_fetch_array($result, MYSQLI_BOTH); // New MySQLi procedural way
			$row = $result->fetch_array(MYSQLI_BOTH); // New MySQLi object oriented way
		}
		else
		{
			$row = mysql_fetch_array($result, MYSQL_BOTH); // Old MySQL procedural way
		}
		$row = reviver($row);
		return $row;
	} // end function db_fetch_array($result)
	
	function db_fetch_assoc($result)
	{
		if ($GLOBALS["server_has_mysqli"])
		{
			//$row = mysqli_fetch_assoc($result); // New MySQLi procedural way
			$row = $result->fetch_assoc(); // New MySQLi object oriented way
		}
		else
		{
			$row = mysql_fetch_assoc($result); // Old MySQL procedural way
		}
		$row = reviver($row);
		return $row;
	} // end function db_fetch_array($result)
	
	function db_insert_id($db_link)
	{
		if ($GLOBALS["server_has_mysqli"])
		{
			//$id = mysqli_insert_id($db_link); // New MySQLi procedural way
			$id = $db_link->insert_id; // New MySQLi object oriented way
		}
		else
		{
			$id = mysql_insert_id($db_link); // Old MySQL procedural way
		}
		return $id;
	
	}
	
	function db_affected_rows($db_link)
	{
		if ($GLOBALS["server_has_mysqli"])
		{
			//$num = mysqli_insert_id($db_link); // New MySQLi procedural way
			$num = $db_link->affected_rows; // New MySQLi object oriented way
		}
		else
		{
			$num = mysql_affected_rows($db_link); // Old MySQL procedural way
		}
		return $num;
	
	}
	
?>
