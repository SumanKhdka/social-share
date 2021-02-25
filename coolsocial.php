<?PHP
	/* 	Cool Social Share Buttons with Share Count Script for PHP
		The fastest share buttons with share count
		Created By Jeff Baker on December 16, 2016
		Copyright (C) 2016 Jeff Baker
		www.seabreezecomputers.com/social/
		Version 1.0 - 12/31/2016 - Version 1.1a - 2/17/2018
		
		Here is how it works:
		1. PHP gets the cached share count from a mysql database and gives it to the Javascript below
		2. PHP does NOT at first get the share count from the different social networks every $get_count_minutes
			because it may be having to check all of the social networks and that would slow that one user every
			$get_count_minutes down. And we don't even want that one user to have to wait for the page to load.
		3. So next, Javascript displays the cached share count and adds the $share_url to the share buttons href
		4. Then Javascript sends an Ajax request to the PHP script for each share button, one at a time 
		5. PHP gets the Ajax request and checks the mysql database to see if $get_count_minutes has passed. If it has
			then PHP gets the count for the social network and sends it back to Javascript where it is displayed.
		6. If $get_count_onclick is true then Javascript also adds a click event to each button so that if a user
			clicks on a share button then Ajax requests that PHP get the share count for that social network.
	*/
	
/* Edit the variables below */

	$share_url = $_SERVER['HTTP_REFERER']; // The url to share will be detected by the webpage address
	//$share_url = "http://www.yourdomain.com"; // Or uncomment this line to specify URL to like
	$share_text = ""; // Used on Twitter and Pinterest as description. Blank out to use current document.title
	$share_image = ""; // Used on Pinterest. Blank out to use the webpage's meta og: or itemprop tags
	
	$get_count_onclick = true; // Get count whenever a user clicks a social button
	$get_count_minutes = 720; // 0 = Don't get count every n minutes; (720 = Get count every 12 hours)
	
	$use_twitter_newsharecounts = false; // If true then register your website at newsharecounts.com

	$db_username="your_mysql_username"; 
	$db_pw="your_mysql_password";
	$server="localhost"; // Usually keep as "localhost"
	$database="your_mysql_database";
	
/* DO NOT EDIT ANYTHING BELOW THIS LINE */
	
	@include_once('realsettings.php');
	require('db.php'); // to use db_query etc..
	
	// Connect to mysql database
	$db_link = db_connect($server, $db_username, $db_pw, $database);
	
	// Keep track of ip so we don't count tweet 'share' twice
	$ip = inet_pton(db_escape_string($db_link, strip_tags(substr($_SERVER['REMOTE_ADDR'],0,100)))); 
	
	$share_url = db_escape_string($db_link, substr($share_url,0,255)); // Don't strip_tags from urls
	
	if (isset($_POST['network'])) {
		check_network($_POST['network']);
		exit;	
	}
		
	function check_network($network) {
		global $db_link, $share_url, $ip, $get_count_minutes; // Version 1.1a - forgot to have $get_count_minutes here
		$last_count = 0; // 1/17/2018 - Version 1.1 - Was $count = "N/A" // Version 1.1a - Changed $count to $last_count
		$get_new_count = false; $network_count = 0; // Version 1.1a - Added $network_count = 0
		
		// Check database for Network counts
		$network = db_escape_string($db_link, substr($network,0,255)); // Don't strip_tags from urls
		
		$query = "SELECT *, TIMESTAMPDIFF(MINUTE, date_updated, NOW()) AS minutes
				FROM social_count WHERE url='$share_url' AND network='$network' LIMIT 1";
		$result = db_query($db_link, $query);
		if (db_num_rows($result)) {
			$row = db_fetch_assoc($result);
			$last_count = $row["count"];
			if ($row['minutes'] > $get_count_minutes || isset($_POST['click'])) // Version 1.1a - Removed  "&& $row['minutes'] > 5" at end
				$get_new_count = true;		
		}
		else // No rows for this network, first time getting count
			$get_new_count = true;
			
		//if (isset($_POST['click']) && ($row['ip'] != $ip || $last_count == 0)) // Version 1.1a - Moved to line 89
		//	$count++; // Add 1 to count if user clicked share and not same ip as last share
		
		if ($get_new_count) {
			if (function_exists("get_".$network."_count")) {
				$network_count = call_user_func("get_".$network."_count", $share_url); // 12/17/2018 - Version 1.1 was $count =
				
				if ($network_count > $last_count || !db_num_rows($result)) // Version 1.1a - Only update count in db if it is bigger than last_count
					update_count($share_url, $network, $network_count);
			}
		}
		$count = ($network_count > $last_count) ? $network_count : $last_count; // 12/17/2018 - Version 1.1	
		if (isset($_POST['click']) && $last_count == $count) $count++; // Add 1 to share count if a user clicked the share button
		if (is_numeric($count))
			$count = add_letter($count);
		echo $network."=".$count; // echo to ajax
	}
	
	function add_letter($count) {
		/* This function adds a letter G M or K to count if it is big */
		if ($count >= 1000000000) {
	        return round($count/ 1000000000, 1).'G';
	    }
	    else if ($count >= 1000000) {
	        return round($count/ 1000000, 1).'M';
	    }
	    else if ($count >= 1000) {
	        return round($count/ 1000, 1).'K';
	    }
	    else
	    	return $count;
	}		
	
	function update_count($share_url, $network, $count) {
		
		// Update database with new share count
		global $ip, $db_link; 
		if (!isset($_POST['click'])) $ip = inet_pton("0.0.0.0"); // Version 1.1a - If updating without a user click then don't save ip
		$network = db_escape_string($db_link, substr($network,0,255)); // Don't strip_tags from urls
		$count = db_escape_string($db_link, substr($count,0,20)); // Don't strip_tags from urls
		
		$query = "UPDATE social_count SET count = '$count', ip = '$ip', date_updated=NOW()
				WHERE network = '$network' AND url = '$share_url' LIMIT 1";
		$result = db_query($db_link, $query);
		if (db_affected_rows($db_link) == 0)
		{
			$query = "INSERT INTO social_count (url, network, count, ip, date_updated)
				VALUES ('$share_url', '$network', '$count', '$ip', NOW())";
			$result = db_query($db_link, $query);
		}			
	}
	
	function get_google_count($share_url) {
		global $ip, $db_link; // 1/17/2018 - Version 1.1
		// 1/17/2018 - Version 1.1 - Google+ not longer returns share count. https://plus.google.com/110610523830483756510/posts/Z1FfzduveUo
		/*$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, "https://clients6.google.com/rpc");
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, '[{"method":"pos.plusones.get","id":"p","params":{"nolog":true,"id":"' . $share_url . '","source":"widget","userId":"@viewer","groupId":"@self"},"jsonrpc":"2.0","key":"p","apiVersion":"v1"}]');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
		$curl_results = curl_exec ($curl);
		curl_close ($curl);
		$json = json_decode($curl_results, true);
		$count = intval( $json[0]['result']['metadata']['globalCounts']['count'] );
		*/
		// 1/17/2018 - Version 1.1 - Google+ no longer returns share count. So create your own count db like twitter
	
			// Create our own share tracking for Google+
			$count = 0;
			$query = "SELECT * FROM social_count WHERE url='$share_url' AND network='google' LIMIT 1";
			$result = db_query($db_link, $query);
			if (db_num_rows($result)) {
				$row = db_fetch_assoc($result);
				$count = $row['count']; 
				if (isset($_POST['click']) && ($row['ip'] != $ip || $count == 0))
					$count++; // Add 1 to count if user clicked share and not same ip as last share
			}
		return $count;
	}
	
	function get_facebook_count($share_url) {
	    $data = @file_get_contents('http://graph.facebook.com/?id='.urlencode($share_url));
	    if ($data === false) { // Version 1.1b - Added curl as alternative if file_get_contents fails
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, "http://graph.facebook.com/?id=".urlencode($share_url));
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			$data = curl_exec ($curl);
			curl_close ($curl);
		}
	    $obj = json_decode($data, true);
	    $count = $obj['share']['share_count'];
		if ($count == null) $count = 0;
	    return $count;
	}
	
	function get_linkedin_count($share_url) {
		global $ip, $db_link; // 2/19/2018 - Version 1.1b
		// 2/7/2018 - LinkedIn no long tracks share count - https://developer.linkedin.com/blog/posts/2018/deprecating-the-inshare-counter
		/*$data = @file_get_contents("http://www.linkedin.com/countserv/count/share?url=".urlencode($share_url)."&format=json"); 
		$json = json_decode($data, true); 
		$count = intval($json['count']);*/
		// Version 1.1b - Create our own share tracking for LinkedIn
			$count = 0;
			$query = "SELECT * FROM social_count WHERE url='$share_url' AND network='linkedin' LIMIT 1";
			$result = db_query($db_link, $query);
			if (db_num_rows($result)) {
				$row = db_fetch_assoc($result);
				$count = $row['count']; 
				if (isset($_POST['click']) && ($row['ip'] != $ip || $count == 0))
					$count++; // Add 1 to count if user clicked share and not same ip as last share
			}
		return $count;
	}
	
	function get_pinterest_count($share_url){
	    $data = @file_get_contents( "http://api.pinterest.com/v1/urls/count.json?callback=receiveCount&url=".urlencode($share_url));
	    if ($data === false) { // Version 1.1b - Added curl as alternative if file_get_contents fails
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, "http://api.pinterest.com/v1/urls/count.json?callback=receiveCount&url=".urlencode($share_url));
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			$data = curl_exec ($curl);
			curl_close ($curl);
		}
		$data = substr($data, 13, -1);
	    $json = json_decode($data, true);
	    $count = $json['count'];
	    return $count;
	}
	
	function get_twitter_count($share_url)
	{
		global $ip, $db_link, $use_twitter_newsharecounts;
		
		if ($use_twitter_newsharecounts) {
			/* Sign up your website at: newsharecounts.com */
			$data = @file_get_contents("http://public.newsharecounts.com/count.json?url=".urlencode($share_url)); 
			$json = json_decode($data, true); 
			$count = intval($json['count']);
			return $count;
		}
		else {
			// Create our own share tracking for twitter
			$count = 0;
			$query = "SELECT * FROM social_count WHERE url='$share_url' AND network='twitter' LIMIT 1";
			$result = db_query($db_link, $query);
			if (db_num_rows($result)) {
				$row = db_fetch_assoc($result);
				$count = $row['count']; 
				if (isset($_POST['click']) && ($row['ip'] != $ip || $count == 0))
					$count++; // Add 1 to count if user clicked share and not same ip as last share
			}
			return $count;
		}
	}
	
	
	//create social_count table
	db_query($db_link, "CREATE TABLE IF NOT EXISTS social_count
	( id INT UNSIGNED NOT NULL AUTO_INCREMENT, 
	PRIMARY KEY(id), 
	url VARCHAR(255),
	network VARCHAR(50),
	INDEX (url, network),
	count BIGINT UNSIGNED,	
	ip VARBINARY(16),
	date_updated DATETIME)");
	
	$social_count_array = array(); // Create array to hold all share counts for all social networks
	
	// Check database for Network share counts
	$query = "SELECT *, TIMESTAMPDIFF(MINUTE, date_updated, NOW()) AS minutes
			FROM social_count WHERE url='$share_url'";
	$result = db_query($db_link, $query);
	if (db_num_rows($result)) {
		while ($row = db_fetch_assoc($result)) {
			$social_count_array[$row['network']] = $row['count'];
		}
	}
	else // No database, first time ran. So we need to get the count for all social networks? No we will get it with ajax
	{
		//$count = get_google_count($share_url);
			
	}
	
?>
//<script type="text/javascript">
var cool_ajax_req = false; // Used with ajax
var cool_social_test_mode = false;

var share_url = "<?PHP echo rawurlencode($share_url);?>";
var share_text = "<?PHP echo rawurlencode($share_text);?>"; // used on Twitter and Pinterest as description
var share_image = "<?PHP echo rawurlencode($share_image);?>"; // used on Pinterest
var get_count_onclick = <?PHP echo $get_count_onclick;?>;
var get_count_minutes = <?PHP echo $get_count_minutes;?>;

if (share_text == "") share_text = document.title;

// Put PHP $social_count_array into JS social_count_array
var social_count_array = { 
<?PHP 
	foreach ($social_count_array as $key => $value)
		echo "'$key' : '$value', ";
?>
};

function display_social_count()
{
	/* This function displays the cached social count for each share button
		which php loaded from mysql database. Or if called from ajax then
		it will update the most recent social network count
	*/
	
	var elems = document.getElementsByTagName('*');
	for (var j=0; j<elems.length; j++) {
		if (String(elems[j].className).match("cool_social_count")) {
			for (var key in social_count_array) {
				if (social_count_array.hasOwnProperty(key)) {
					var network_re = new RegExp(key, 'gi');	
					if (elems[j].parentElement.getAttribute("data-network").match(network_re))
						elems[j].innerHTML = social_count_array[key];
				}
			}
		}		
	}	
}

function social_div_init() {
	/* This function will take all elements with className "cool_social_div"
		and initialize each button with the settings from the php script
		1. Add share_url to all anchor tags	
		2. Add onclick to share buttons if we are getting the count on click
		3. Send each social network name to ajax one at a time if we are getting count every n minnutes
	*/
	
	var elems = document.getElementsByTagName('*');
	for (var j=0; j<elems.length; j++) {
		if (String(elems[j].className).match("cool_social_div")) {
			var a_tags = elems[j].getElementsByTagName("a");
			for (var i = 0; i < a_tags.length; i++) { 
				var network = a_tags[i].getAttribute("data-network");
				// Add URL to anchor tags in share buttons
				if (a_tags[i].href.match(/twitter/))
					a_tags[i].href += share_url+"&text="+share_text;
				else if (a_tags[i].href.match(/pinterest/))
					a_tags[i].href += share_url+"&media="+share_image+"&description="+share_text;
				else
					a_tags[i].href += share_url;
				
				// Add onclick to share buttons
				if (get_count_onclick)
					(function () {
						var network_name = a_tags[i].getAttribute("data-network");
						if (document.addEventListener) // Chrome, Safari, FF, IE 9+
							a_tags[i].addEventListener('click',function(event) { cool_send_ajax("network="+network_name+"&click=1"); },false);
						else // IE < 9
							a_tags[i].attachEvent('onclick',function(event) { cool_send_ajax("network="+network_name+"&click=1"); });
					}()); // immediate invocation
				
				// Send each button's network to php one at a time
				if (get_count_minutes)
					cool_send_ajax("network="+network);	
					
			}
		}
	}
}


function cool_send_ajax(params) 
{
	if (cool_ajax_req) // Send ajax request later if another one is in progress
	{
		setTimeout(function()
		{
			cool_send_ajax(params); 
			return;
		} , 100);
		return;
    }
    
	// branch for native XMLHttpRequest object
    if(window.XMLHttpRequest && !(window.ActiveXObject)) 
	{
    	try 
		{
			cool_ajax_req = new XMLHttpRequest();
        } 
		catch(e) 
		{
			cool_ajax_req = false;
        }
    // branch for IE/Windows ActiveX version
    } 
	else if(window.ActiveXObject) 
	{
       	try 
		{
        	cool_ajax_req = new ActiveXObject("Msxml2.XMLHTTP");
      	} 
		catch(e) 
		{
        	try 
			{
          		cool_ajax_req = new ActiveXObject("Microsoft.XMLHTTP");
        	} 
			catch(e) 
			{
          		cool_ajax_req = false;
        	}
		}
    }
	if(cool_ajax_req) 
	{
		//document.getElementById('test').innerHTML = params;
		cool_ajax_req.onreadystatechange = cool_get_ajax;
		cool_ajax_req.open("POST", "<?PHP echo $_SERVER["SCRIPT_NAME"];?>", true);
		//Send the proper header information along with the request
		cool_ajax_req.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
		if (cool_social_test_mode) console.log(params);
		cool_ajax_req.send(params);
	}
} // end function loadXMLDoc


function cool_get_ajax() 
{
    // only if req shows "loaded"
    if (cool_ajax_req.readyState == 4) 
	{
        // only if "OK"
        if (cool_ajax_req.status == 200) 
		{
            if (cool_ajax_req.responseText)
			{
				if (cool_social_test_mode) console.log(cool_ajax_req.responseText);	
				var variables = cool_ajax_req.responseText.split("="); // Ex: google=50
				social_count_array[variables[0]] = variables[1];	
				display_social_count();
			}
		} 
		else 
		{
            console.log("There was a problem retrieving the ajax data:\n" +
                cool_ajax_req.status);
        }
        cool_ajax_req = false;
    }
} // end function processReqChange()

display_social_count();
social_div_init();
//</script>
