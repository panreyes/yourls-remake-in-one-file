<?php
/*
	Basic remake of YOURLS, but procedural and just one file!
	© Pablo A. Navarro (@panreyes) 2021
	
	License: MIT. https://github.com/YOURLS/YOURLS/blob/master/LICENSE
	
	INSTALLATION:
	- Configure MySQL parameters
	- Read API usage's first line :)
	
	API Usage (GET parameters):
	+ Installation: ?action=install&secret={SECRET}
	+ Add/Update: ?action=add&secret={SECRET}&keyword={KEYWORD}&url={URL}&title={TITLE}
	+ Delete: ?action=delete&secret={SECRET}&keyword={KEYWORD}
	+ Dump list (CSV): ?action=list&secret={SECRET}

*/

	// MySQL parameters:
	$mysql_host="localhost";
	$mysql_database="";
	$mysql_user="";
	$mysql_password="";
	$secret="";
	
	// API to add new redirections
	if(isset($_GET['secret']) and isset($_GET['action'])){
		if($_GET['secret']==$secret){
			switch($_GET['action']){
				case "install":
					install();
					break;
				case "add":
					add_redirection();
					break;
				case "delete":
					delete_redirection();
					break;
				case "list":
					list_redirections();
					break;
				default: 
					die("?"); 
					break;
			}
		}
		die("?");
	} elseif(!empty($_SERVER['REQUEST_URI'])){
		do_redirect();
	} else {
		die("Oh, hi Mark!");
	}
	
function do_redirect(){	
	// We parse and check that the keyword is made of only alphanumeric characters, underscores and hyphens
	$keyword=clean_keyword($_SERVER['REQUEST_URI']);
	if(!check_keyword($keyword)){
		fail();
	}
	
	// Query URL and redirect
	$result = do_query('SELECT url FROM yourls_url WHERE keyword = "'.$keyword.'"');
	$data = mysqli_fetch_assoc($result);
	if (!empty($data['url'])) {
		
		if(strpos($data['url'],"http://")===false and strpos($data['url'],"https://")===false){
			$data['url']="https://".$data['url'];
		}
		
		// Update click counter
		do_query('UPDATE yourls_url SET clicks = (@cur_value := clicks) + 1 WHERE keyword = "'.$keyword.'"');

		// Redirecting!
		header("Location: ".$data['url']);
		exit;
	} else {
		fail();
	}
}

function connect_mysql(){
	global $link,$mysql_host,$mysql_user,$mysql_password,$mysql_database;
	
	// Connect MySQL
	$link = mysqli_connect($mysql_host,$mysql_user,$mysql_password,$mysql_database);

	if (!$link) {
		fail();
	}	
}

function fail(){
	//header("Location: https://www.google.es");
	die("Invalid redirection.");
}

function clean_keyword($keyword){
	$keyword=ltrim($keyword,"/");
	$keyword=rtrim($keyword,"/");
	if(strlen($keyword)>100){
		die("Error: Keyword too long.");
	}
	
	return $keyword;
}

function check_keyword($keyword){
	$keyword=rtrim($keyword,"/");
	$keyword=str_replace("_","",$keyword);
	$keyword=str_replace("-","",$keyword);
	if(!ctype_alnum($keyword)){
		return false;
	}
	return true;
}

function add_redirection(){
	if(!isset($_GET['keyword']) or !isset($_GET['url']) or !isset($_GET['title'])){
		die("Error: Missing parameters.");
	}
	
	//Sanitize keyword parameter (no need to sanitize MySQL, ctype_alnum will already filter anything strange)
	$keyword=clean_keyword($_GET['keyword']);
	if(!check_keyword($keyword)){
		die("Error: Invalid keyword.");
	}
	
	// Sanitize URL parameter
	$url=sanitize_sql($_GET['url']);
	if(strpos($url,"http://")===false and strpos($url,"https://")===false){
		$url="https://".$url;
	}
	if(!filter_var($url, FILTER_VALIDATE_URL)){
		die("Error: Invalid URL.");
	}
	
	//Sanitize title
	$title=sanitize_sql($_GET['title']);
	
	// Insert or update redirection
	$query='INSERT INTO yourls_url(keyword,url,title,ip) 
		VALUES("'.$keyword.'","'.$url.'","'.$title.'","'.$_SERVER['REMOTE_ADDR'].'") 
		ON DUPLICATE KEY UPDATE url = "'.$url.'", title = "'.$title.'"';
	$result=do_query($query);
	if(!$result){
		die("SQL Error in Query: ".$query);
	} else {
		die("Success! Share this URL: https://".$_SERVER['SERVER_NAME']."/".$keyword);
	}
}

function delete_redirection(){
	if(!isset($_GET['keyword'])){
		die("Error: Missing parameters.");
	}
	
	// Sanitize keyword parameter (no need to sanitize MySQL, ctype_alnum will already filter anything strange)
	$keyword=clean_keyword($_GET['keyword']);
	if(!check_keyword($keyword)){
		die("Error: Invalid keyword.");
	}
	
	// Insert or update redirection
	$query='DELETE FROM yourls_url WHERE keyword = "'.$keyword.'"';
	$result=do_query($query);
	if(!$result){
		die("SQL Error in Query: ".$query);
	} else {
		die("Success! (If it existed) Redirection deleted: ".$keyword);
	}
}

function list_redirections(){
	$query='SELECT keyword,url,title,timestamp,ip,clicks FROM yourls_url ORDER BY keyword ASC';
	$result=do_query($query);
	if(!$result){
		die("SQL Error in Query: ".$query);
	} else {
		echo '<table border="1"><tr><th>keyword<th>url<th>title<th>timestamp<th>ip<th>clicks';
		while($redirection=mysqli_fetch_assoc($result)){
			echo '<tr><td>'.implode('<td>',$redirection);
		}
		echo '</table>';
		exit;
	}
}

function sanitize_sql($text){
	global $link;
	
	if(!$link){
		connect_mysql();
	}
	return mysqli_real_escape_string($link,$text);
}

function do_query($query){
	global $link;
	
	if(!$link){
		connect_mysql();
	}
	return mysqli_query($link,$query);
}

function install(){
	
	// Creating .htaccess
	$htaccess = '# BEGIN YOURLS
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^.*$ /index.php [L]
</IfModule>
# END YOURLS';
	file_put_contents(".htaccess",$htaccess);
	
	// Checking if the table already exists
	$result=do_query('SELECT COUNT(keyword) FROM yourls_url');
	if($result){
		die("Error: Already installed!");
	}

	// Creating table
	$query='CREATE TABLE yourls_url (
  keyword varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT "",
  url text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  title text COLLATE utf8mb4_unicode_ci,
  timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip varchar(41) COLLATE utf8mb4_unicode_ci NOT NULL,
  clicks int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
	$result=do_query($query);
	if(!$result){
		die("Could not install: Error creating table.");
	}

	// Altering table
	$query='ALTER TABLE yourls_url
  ADD PRIMARY KEY (keyword),
  ADD UNIQUE KEY keyword (keyword),
  ADD KEY ip (ip),
  ADD KEY timestamp (timestamp);';
	do_query($query);
	
	if(!$result){
		die("Could not install: Error altering table.");
	}

	die("Congrats! Installation succesful!");
}
?>