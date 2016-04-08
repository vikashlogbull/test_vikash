<?php
class SiteDBDetails {
 	
	/**
	 * Connecting with postgres database
	 * RETURNS: Database Ob.ject
	*/	
	protected function postgresDBConnection(){
		
		$host        = "host=54.174.204.196";
		$port        = "port=5432";
		$dbname      = "dbname=evolpaasmgr";
		$credentials = "user=postgres password=lbit123";
		return pg_connect( "$host $port $dbname $credentials"  );
	}
	
	public function GetSiteDBDetails($environmentid,$siteid,$subsiteid) {

		$db = $this->postgresDBConnection();		
		
		if($siteid != ""){
			$connectionInfoSQL =<<<EOF
				SELECT 
					x.db_username_secondary,
					x.db_password_secondary,
					x.drupalfolderpath,
					x.username,
					x.password,
					x.drup_dbname,
					x.dbconnection_string,
					sr.externalip,
					ssh.username as ssh_username,
					d.database_name,
					d.external_hostname,
					d.dbport,
					g.git_ssh,
					g.git_username,
					g.git_password,
					g.repo_name,
					g.repo_type
				FROM 
					epm_xref_site_environment AS x  
				INNER JOIN 
					epm_database AS d  
				ON 
					d.id = x.database_id
				INNER JOIN 
					epm_server AS sr  
				ON 
					sr.serverid = x.serverid
				INNER JOIN 
					epm_sftp_ssh_accessdetails AS ssh  
				ON 
					ssh.serverid = sr.serverid
				INNER JOIN 
					epm_git_branch AS gb 
				ON 
					x.git_branch_id = gb.id 
				INNER JOIN 
					epm_git AS g 
				ON 
					gb.git_id = g.git_id 
				WHERE
					x.siteid = $siteid AND
					x.environmentid = $environmentid AND
					d.isprimary = TRUE
EOF;

			$connectionInfoResult 	= pg_query($db, $connectionInfoSQL);
			if(pg_num_rows($connectionInfoResult)==0){
				$connectionInfoSQL =<<<EOF
					SELECT 
						x.db_username_secondary,
						x.db_password_secondary,
						x.drupalfolderpath,
						x.username,
						x.password,
						x.drup_dbname,
						x.dbconnection_string,
						sr.externalip,
						ssh.username as ssh_username,
						d.database_name,
						d.external_hostname,
						d.dbport,
						g.git_ssh,
						g.git_username,
						g.git_password,
						g.repo_name,
						g.repo_type
					FROM 
						epm_xref_site_environment AS x  
					LEFT JOIN 
						epm_database AS d  
					ON 
						d.primary_db_id = x.database_id
					LEFT JOIN 
						epm_server AS sr  
					ON 
						sr.serverid = x.serverid
					LEFT JOIN 
						epm_sftp_ssh_accessdetails AS ssh  
					ON 
						ssh.serverid = sr.serverid
					LEFT JOIN 
						epm_git_branch AS gb 
					ON 
						x.git_branch_id = gb.id 
					LEFT JOIN 
						epm_git AS g 
					ON 
						gb.git_id = g.git_id 
					WHERE 
						x.siteid = $siteid AND
						x.environmentid = $environmentid
EOF;
			
			}
		}
		else {
			$connectionInfoSQL =<<<EOF
				SELECT 
					x.db_username_secondary,
					x.db_password_secondary,
					x.drupalfolderpath,
					x.username,
					x.password,
					x.drup_dbname,
					x.dbconnection_string,
					sr.externalip,
					ssh.username as ssh_username,
					d.database_name,
					d.external_hostname,
					d.dbport,
					g.git_ssh,
					g.git_username,
					g.git_password,
					g.repo_name,
					g.repo_type
				FROM 
					epm_xref_subsite_environment AS x  
				INNER JOIN 
					epm_database AS d  
				ON 
					d.id = x.database_id
				INNER JOIN 
					epm_server AS sr  
				ON 
					sr.serverid = x.serverid
				INNER JOIN 
					epm_sftp_ssh_accessdetails AS ssh  
				ON 
					ssh.serverid = sr.serverid
				INNER JOIN 
					epm_git_branch AS gb 
				ON 
					x.git_branch_id = gb.id 
				INNER JOIN 
					epm_git AS g 
				ON 
					gb.git_id = g.git_id 
				WHERE
					x.subsiteid = $subsiteid AND
					x.environmentid = $environmentid AND
					d.isprimary = TRUE
EOF;

			$connectionInfoResult 	= pg_query($db, $connectionInfoSQL);
			if(pg_num_rows($connectionInfoResult)==0){
				$connectionInfoSQL =<<<EOF
					SELECT 
						x.db_username_secondary,
						x.db_password_secondary,
						x.drupalfolderpath,
						x.username,
						x.password,
						x.drup_dbname,
						x.dbconnection_string,
						sr.externalip,
						ssh.username as ssh_username,
						d.database_name,
						d.external_hostname,
						d.dbport,
						g.git_ssh,
						g.git_username,
						g.git_password,
						g.repo_name,
						g.repo_type
					FROM 
						epm_xref_subsite_environment AS x  
					LEFT JOIN 
						epm_database AS d  
					ON 
						d.primary_db_id = x.database_id
					LEFT JOIN 
						epm_server AS sr  
					ON 
						sr.serverid = x.serverid
					LEFT JOIN 
						epm_sftp_ssh_accessdetails AS ssh  
					ON 
						ssh.serverid = sr.serverid
					LEFT JOIN 
						epm_git_branch AS gb 
					ON 
						x.git_branch_id = gb.id 
					LEFT JOIN 
						epm_git AS g 
					ON 
						gb.git_id = g.git_id 
					WHERE 
						x.subsiteid = $subsiteid AND
						x.environmentid = $environmentid
EOF;
			
			}
		}

		$connectionInfoResult 	= pg_query($db, $connectionInfoSQL);

		$return = array();

		if(pg_num_rows($connectionInfoResult)>0){
			
			$connectionInfoData = pg_fetch_assoc($connectionInfoResult);

			$return["sftp_command_line"] 		= "ssh ".trim($connectionInfoData["ssh_username"])."@".trim($connectionInfoData["externalip"]);
			
			/*Fetching GIT Details -- Start*/	
			
			/*trim($connectionInfoData["hook_is_set"]) 
			trim($connectionInfoData["repo_visibility"])
			trim($connectionInfoData["repo_type"])*/
			
			
			if(trim($connectionInfoData["repo_type"]) == "private"){
				$return["git_ssh_clone_url"] 	= "git clone ".trim($connectionInfoData["git_ssh"]);
				$return["git_username"] 		= trim($connectionInfoData["git_username"]);
				$return["git_password"] 		= trim($connectionInfoData["git_password"]);
				$return["git_repo_name"] 		= trim($connectionInfoData["repo_name"]);
			}
			elseif(trim($connectionInfoData["repo_type"]) == "PrivateDontShow"){
				$return["git_ssh_clone_url"] 	= "git clone ".trim($connectionInfoData["git_ssh"]);
			}
			elseif(trim($connectionInfoData["repo_type"]) == "ClientHookEstablished"){
				$return["git_ssh_clone_url"] 	= "git clone ".trim($connectionInfoData["git_ssh"]);		
			}
			elseif(trim($connectionInfoData["repo_type"]) == "ClientNoHook"){
				$return["git_ssh_clone_url"] 	= "";
			}
			/*Fetching GIT Details -- End*/

			
			/*Fetching DB Details -- Start*/			
			$drupalfolderpath 	= trim($connectionInfoData["drupalfolderpath"]);			
			if($drupalfolderpath == "" || $drupalfolderpath == "/")
				$sites_directory 	= "/";
			else 
				$sites_directory 	= (substr($drupalfolderpath, -1) == "/") ? $drupalfolderpath : $drupalfolderpath."/";
			
			$siteSettingsFilePath	= trim($sites_directory)."/sites/default/settings.php";
			$siteSettingsFilePath	= str_replace(" ","",str_replace(array("//","\\"),"/",$siteSettingsFilePath));

			$DBDetails = array();
			if(file_exists($siteSettingsFilePath)){
				if($file = file($siteSettingsFilePath)){									
					foreach( $file as $line ) {
						if($line[0] === "$"){											
							$DBTitle = strstr($line, '=', true);		
							$match = "$"."databases['default']['default']";											
							if(trim($DBTitle) == $match){
								$DBString = substr(strstr($line, '='), 1, -1);
								$DBArray = explode("(",$DBString);
								$DBArray = explode(")",$DBArray[1]);
								$DBArray = explode(",",$DBArray[0]);
								
								foreach($DBArray as $val){
									$DBElement = explode("=>",$val);
									$DBDetails[trim(trim($DBElement[0]),"'")] = trim(trim($DBElement[1]),"'");
								}
							}
						}
					}
				}
			}
			
			if(isset($DBDetails["database"])) 
				$return["database_name"]		= trim($DBDetails["database"]);
			
			$return["database_username"]		= trim($connectionInfoData["db_username_secondary"]);
			$return["database_password"]		= trim($connectionInfoData["db_password_secondary"]);
			$return["database_host"]			= trim($connectionInfoData["external_hostname"]);
			$return["database_port"]			= trim($connectionInfoData["dbport"]);
			$return["database_command_line"] 	= "mysql -u ".trim($connectionInfoData["db_username_secondary"])." -p ".trim($connectionInfoData["db_password_secondary"]);
			/*Fetching DB Details -- End*/

			echo json_encode($return);
		}
	}
}

$environmentid	= (isset($_GET["environmentid"])) 	? $_GET["environmentid"] 	: "";	
$siteid 		= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";
$subsiteid 		= (isset($_GET["subsiteid"])) 		? $_GET["subsiteid"] 		: "";

if($environmentid == "" || ($siteid == "" && $subsiteid == "")){
	return json_encode(array());
} 
else {
	$siteDBDetails = new SiteDBDetails();
	echo $siteDBDetails->GetSiteDBDetails($environmentid,$siteid,$subsiteid);
}