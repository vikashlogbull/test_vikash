<?php

class SiteListing {
 	
	/**
	 * Connecting with postgres database
	 * RETURNS: Database Object
	*/	
	protected function postgresDBConnection(){
		
		$host        = "host=54.174.204.196";
		$port        = "port=5432";
		$dbname      = "dbname=evolpaasmgr";
		$credentials = "user=postgres password=lbit123";
		return pg_connect( "$host $port $dbname $credentials"  );
	}
	
	public function ListSitesAndSubsites($serverid,$companyid,$environmentid,$siteid) {

		// Making DB connection
		$db = $this->postgresDBConnection();
		if (!$db) {
			$flag = "failed";
			$returnmsg = http_response_code(401);
			$msgdesc="Connection issue with Master DB";
			return json_encode(array("Status"=>$flag,"MsgCode"=>$returnmsg ,"msgdescription"=>$msgdesc ));
		}
		else {
			// Fetching drupal folder path
			if($siteid != ""){
				$siteDirectorySQL =<<<EOF
				SELECT x.drupalfolderpath from epm_server AS s INNER JOIN epm_xref_site_environment AS x ON s.serverid = x.serverid WHERE s.backendcompanyid = $companyid AND x.siteid=$siteid AND x.environmentid=$environmentid AND x.serverid=$serverid AND lower(s.serverstatus) = 'active';
EOF;
			} 
			else {
				$siteDirectorySQL =<<<EOF
				SELECT drupalfolderpath from epm_xref_site_environment WHERE serverid=$serverid AND environmentid=$environmentid;
EOF;
			}
			
			$siteDirectoryResult = pg_query($db, $siteDirectorySQL);
			$siteDirectoryData	= pg_fetch_all($siteDirectoryResult);
	
			$sites = array();
				
			foreach($siteDirectoryData as $siteDirectory){
				
				$sites_directory 	= trim($siteDirectory["drupalfolderpath"]);
				
				if (is_dir($sites_directory) && file_exists($sites_directory."/sites/sites.php")){
						
					if($siteid == "")
						$site = $this->getMultipleSiteDetails($sites_directory,$serverid,$companyid,$environmentid);
					else
						$site = $this->getSingleSiteDetails($sites_directory,$serverid,$companyid,$environmentid,$siteid);
					
					if(isset($site) && is_array($site) && sizeof($site)>0)
						$sites[] = $site;
				}
			}
			
			/*
			$serverDrupalFolderPath = "/var/www/html";
			if($serverDrupalFolderPath == "" || $serverDrupalFolderPath == "/")
				$serverDrupalFolderPath 	= "/";
			else 
				$serverDrupalFolderPath 	= (substr($serverDrupalFolderPath, -1) == "/") ? $serverDrupalFolderPath : $serverDrupalFolderPath."/";
			
			if (is_dir($serverDrupalFolderPath)){
				if ($dh = opendir($serverDrupalFolderPath)){
					while (($file = readdir($dh)) !== false){						
						if(is_dir($serverDrupalFolderPath.$file) && file_exists($serverDrupalFolderPath.$file."/sites/sites.php")){
							$siteDirectorySQL =<<<EOF
							SELECT * from epm_xref_site_environment WHERE drupalfolderpath='$serverDrupalFolderPath.$file';
EOF;
							$siteDirectoryResult = pg_query($db, $siteDirectorySQL);
							if(pg_num_rows($siteDirectoryResult) == 0){
								
							}			
						}
					}
				}
			}
			*/
			
			echo json_encode($sites); 
		}	
	}
	
	protected function getMultipleSiteDetails($sites_directory,$serverid,$companyid,$environmentid){
		
		 // Making DB connection
		$db = $this->postgresDBConnection();
		
		$file = end(explode("/",$sites_directory));
		$fileArray = explode("_",$file);

		// reading sites.php file
		if($inner_file = file( $sites_directory."/sites/sites.php" )){
			
			$subSites = $subSiteFolders = array();
			foreach( $inner_file as $line ) {
				if ( $line[0] === "$") {
					
					$tempDNS1 = strstr($line, '=', true);
					preg_match_all('/\'(.*)\'/', $tempDNS1, $matches1);
					$dns_name = trim($matches1[0][0], "'");
					
					$tempSitepath1 = strstr($line, '=');
					preg_match_all('/\'(.*)\'/', $tempSitepath1, $matches2);
					$site_path = trim($matches2[0][0], "'");
					
					// if the subsite exists, It will be added into the subsite array
					if($site_path == "all"){
						$master_site_domain = trim($dns_name);
					}
					else if(is_dir($sites_directory."/sites/".$site_path)){						
					
						$subsite_directory = $sites_directory."/sites/";
						$siteSettingsFilePath	= $sites_directory."/sites/default/settings.php";
						$subSiteSettingsFilePath = $subsite_directory.trim($site_path)."/sites/default/settings.php";
						$usingOwnDB = $this->isSubsiteUsesOwnDB($siteSettingsFilePath,$subSiteSettingsFilePath);
					
						$subSites[] = array("subSiteID"=>1,"subSiteName"=>trim($dns_name),"subSiteURL"=>trim($site_path),"adminSiteURL"=>trim($site_path)."/user","usingOwnDB"=>$usingOwnDB);
						$subSiteFolders[] = trim($site_path);
					}
				}
			}
			
			// scane site directory to get the subsites missing in sites.php
			$subsite_directory = $sites_directory."/sites/";
			$notASubsite = array(".","..","all","default");	
			if (is_dir($subsite_directory)){
				if ($dh2 = opendir($subsite_directory)){
					while (($subSiteFolder = readdir($dh2)) !== false){						
						if(!in_array($subSiteFolder,$notASubsite) && is_dir($subsite_directory.$subSiteFolder) && file_exists($subsite_directory.$subSiteFolder."/sites/sites.php") && !in_array($subSiteFolder,$subSiteFolders)){
							
							$siteSettingsFilePath	= $sites_directory."/sites/default/settings.php";
							$subSiteSettingsFilePath = $subsite_directory.$subSiteFolder."/sites/default/settings.php";
							$usingOwnDB = $this->isSubsiteUsesOwnDB($siteSettingsFilePath,$subSiteSettingsFilePath);
							$subSites[] = array("subSiteID"=>1,"subSiteName"=>trim($subSiteFolder),"subSiteURL"=>trim($subSiteFolder),"adminSiteURL"=>$subSiteFolder."/user","usingOwnDB"=>$usingOwnDB);
						}
					}
				}
			}	
				
			$siteSQL =<<<EOF
			SELECT * from epm_sites WHERE sitename = '$file';
EOF;
			$siteResult = pg_query($db, $siteSQL);
			
			if(sizeof($fileArray)<4 && pg_num_rows($siteResult)==0){
				$newSiteID = $this->addSiteInDB($file,$master_site_domain,$subSites,$sites_directory,$serverid,$companyid,$environmentid);
			}
			else {
				$newSiteID = end($fileArray);
			}

			if(sizeof($subSites)>0){
				foreach($subSites as $key=>$val){
					$subsiteURL = $val["subSiteURL"];
					$subSiteSQL =<<<EOF
						SELECT 
							s.subsiteid,
							s.subsitename,
							x.subsite_status	
						FROM 
							epm_sub_site AS s 
						INNER JOIN 
							epm_xref_subsite_environment as x 
						ON
							x.subsite_id = s.subsiteid
						WHERE 
							s.siteid = $newSiteID AND x.subsite_domain_name='$subsiteURL';
EOF;

					$subSiteResult 					= pg_query($db, $subSiteSQL);
					$subSiteData 					= pg_fetch_assoc($subSiteResult);
					$subSites[$key]["subSiteID"] 	= $subSiteData["subsiteid"];
					$subSites[$key]["subSiteName"] 	= trim($subSiteData["subsitename"]); 

					$subSiteStatus = "Errors";
					if(trim($subSiteData["subsite_status"]) == "Completed")
						$subSiteStatus = "Deployed";
					
					if($environmentid == 1){
						$subSites[$key]["DevSubSiteURL"] 		= $val["subSiteURL"];
						$subSites[$key]["DevAdminSubSiteURL"] 	= $val["adminSiteURL"];
						$subSites[$key]["DevSubSiteUsingOwnDB"] = $val["usingOwnDB"];
						$subSites[$key]["DevSubSiteStatus"] 	= $subSiteStatus;
						$subSites[$key]["Dev"] 					= 1;						
					}
					else if($environmentid == 2){
						$subSites[$key]["TestSubSiteURL"] 		= $val["subSiteURL"];
						$subSites[$key]["TestAdminSubSiteURL"] 	= $val["adminSiteURL"];
						$subSites[$key]["TestSubSiteUsingOwnDB"] = $val["usingOwnDB"];
						$subSites[$key]["TestSubSiteStatus"] 	= $subSiteStatus;
						$subSites[$key]["Test"] 				= 1;
					}
					else if($environmentid == 3){
						$subSites[$key]["LiveSubSiteURL"] 		= $val["subSiteURL"];
						$subSites[$key]["LiveAdminSubSiteURL"] 	= $val["adminSiteURL"];
						$subSites[$key]["LiveSubSiteUsingOwnDB"] = $val["usingOwnDB"];
						$subSites[$key]["LiveSubSiteStatus"] 	= $subSiteStatus;
						$subSites[$key]["Live"] 				= 1;
					}
					
					unset($subSites[$key]["subSiteURL"]);
					unset($subSites[$key]["adminSiteURL"]);					
					unset($subSites[$key]["usingOwnDB"]);					
				}
			}
			
			$siteSQL =<<<EOF
				SELECT s.sitename,x.site_status FROM epm_sites AS s INNER JOIN epm_xref_site_environment AS x ON s.siteid = x.siteid WHERE s.siteid=$newSiteID;
EOF;
			$siteResult 	= pg_query($db, $siteSQL);
			$siteData 		= pg_fetch_assoc($siteResult);
			$siteName 		= $siteData["sitename"];
			$siteStatus 	= "Errors";	
			if(trim($siteData["site_status"]) == "Completed"){
				$siteStatus = "Deployed";	
			}
			
			$site["siteID"] 		=	$newSiteID; 
			$site["siteName"] 		=	trim($siteName); 
			$site["subSite"] 		=	$subSites; 
			
			if($environmentid == 1){
				$site["DevSiteURL"] 		=	trim($master_site_domain); 
				$site["DevAdminSiteURL"] 	=	trim($master_site_domain)."/user"; 
				$site["DevSiteStatus"] 		=	$siteStatus; 
				$site["Dev"] 				=	1;
			}
			elseif($environmentid == 2){
				$site["TestSiteURL"] 		=	trim($master_site_domain); 
				$site["TestAdminSiteURL"] 	=	trim($master_site_domain)."/user"; 
				$site["TestSiteStatus"] 	=	$siteStatus; 
				$site["Test"] 				=	1;
			}
			elseif($environmentid == 3){
				$site["LiveSiteURL"] 		=	trim($master_site_domain); 
				$site["LiveAdminSiteURL"] 	=	trim($master_site_domain)."/user"; 
				$site["LiveSiteStatus"] 	=	$siteStatus; 
				$site["Live"] 				=	1;
			}
			return $site;	
		}
	}
	
	protected function getSingleSiteDetails($sites_directory,$serverid,$companyid,$environmentid,$siteid){
		
		 // Making DB connection
		$db = $this->postgresDBConnection();
		
		$file = end(explode("/",$sites_directory));
		$fileArray = explode("_",$file);

		if(sizeof($fileArray)>=4 && end($fileArray) == $siteid){

			if($inner_file = file( $sites_directory."/sites/sites.php" )){
				
				$subSites = $subSiteFolders = array();
				if(sizeof($inner_file)>0){
					foreach( $inner_file as $line ) {
						if ( $line[0] === "$") {
							
							$tempDNS1 = strstr($line, '=', true);
							preg_match_all('/\'(.*)\'/', $tempDNS1, $matches1);
							$dns_name = trim($matches1[0][0], "'");
							
							$tempSitepath1 = strstr($line, '=');
							preg_match_all('/\'(.*)\'/', $tempSitepath1, $matches2);
							$site_path = trim($matches2[0][0], "'");
							
							// if the subsite exists, It will be added into the subsite array
							if($site_path != "all" && is_dir($sites_directory."/sites/".$site_path)){
								
								$subsite_directory = $sites_directory."/sites/";
								$siteSettingsFilePath	= $sites_directory."/sites/default/settings.php";
								$subSiteSettingsFilePath = $subsite_directory.$site_path."/sites/default/settings.php";
								$usingOwnDB = $this->isSubsiteUsesOwnDB($siteSettingsFilePath,$subSiteSettingsFilePath);
								
								$subSites[] = array("subSiteID"=>1,"subSiteName"=>$dns_name,"subSiteURL"=>$site_path,"adminSiteURL"=>$site_path."/user","usingOwnDB"=>$usingOwnDB);
								$subSiteFolders[] = trim($site_path);
							}
						}
					}
				}

				// scane site directory to get the subsites missing in sites.php
				$subsite_directory = $sites_directory."/sites/";
				$notASubsite = array(".","..","all","default");	
				if (is_dir($subsite_directory)){
					if ($dh2 = opendir($subsite_directory)){
						while (($subSiteFolder = readdir($dh2)) !== false){						
							if(!in_array($subSiteFolder,$notASubsite) && is_dir($subsite_directory.$subSiteFolder) && file_exists($subsite_directory.$subSiteFolder."/sites/sites.php") && !in_array($subSiteFolder,$subSiteFolders)){								
								$siteSettingsFilePath	= $sites_directory."/sites/default/settings.php";
								$subSiteSettingsFilePath = $subsite_directory.$subSiteFolder."/sites/default/settings.php";
								$usingOwnDB = $this->isSubsiteUsesOwnDB($siteSettingsFilePath,$subSiteSettingsFilePath);							
								$subSites[] = array("subSiteID"=>1,"subSiteName"=>trim($subSiteFolder),"subSiteURL"=>trim($subSiteFolder),"adminSiteURL"=>$subSiteFolder."/user","usingOwnDB"=>$usingOwnDB);
							}
						}
					}
				}	
				
				if(sizeof($subSites)>0){
					foreach($subSites as $key=>$val){
						$subsitename = $val["subSiteName"];
						$subsiteURL = $val["subSiteURL"];
						$subSiteSQL =<<<EOF
							SELECT 
								s.subsiteid, 
								s.subsitename, 
								x.subsite_status 
							FROM 
								epm_sub_site AS s 
							INNER JOIN 
								epm_xref_subsite_environment as x 
							ON
								x.subsite_id = s.subsiteid
							WHERE 
								s.siteid = $siteid AND x.subsite_domain_name='$subsiteURL';
EOF;
	 
						$subSiteResult 					= pg_query($db, $subSiteSQL);
						$subSiteData 					= pg_fetch_assoc($subSiteResult);
						$subSites[$key]["subSiteID"] 	= trim($subSiteData["subsiteid"]); 
						$subSites[$key]["subSiteName"] 	= trim($subSiteData["subsitename"]); 
						
						$subSiteStatus = "Errors";
						if(trim($subSiteData["subsite_status"]) == "Completed")
							$subSiteStatus = "Deployed";
						
						if($environmentid == 1){
							$subSites[$key]["DevSubSiteURL"] 		= $val["subSiteURL"];
							$subSites[$key]["DevAdminSubSiteURL"] 	= $val["adminSiteURL"];
							$subSites[$key]["DevSubSiteUsingOwnDB"] = $val["usingOwnDB"];
							$subSites[$key]["DevSubSiteStatus"] 	= $subSiteStatus;
							$subSites[$key]["Dev"] 					= 1;
						}
						else if($environmentid == 2){
							$subSites[$key]["TestSubSiteURL"] 		= $val["subSiteURL"];
							$subSites[$key]["TestAdminSubSiteURL"] 	= $val["adminSiteURL"];
							$subSites[$key]["TestSubSiteUsingOwnDB"] = $val["usingOwnDB"];
							$subSites[$key]["TestSubSiteStatus"] 	= $subSiteStatus;
							$subSites[$key]["Test"] 				= 1;
						}
						else if($environmentid == 3){
							$subSites[$key]["LiveSubSiteURL"] 		= $val["subSiteURL"];
							$subSites[$key]["LiveAdminSubSiteURL"] 	= $val["adminSiteURL"];
							$subSites[$key]["LiveSubSiteUsingOwnDB"] = $val["usingOwnDB"];
							$subSites[$key]["LiveSubSiteStatus"] 	= $subSiteStatus;
							$subSites[$key]["Live"] 				= 1;
						}
						
						unset($subSites[$key]["subSiteURL"]);
						unset($subSites[$key]["adminSiteURL"]);					
						unset($subSites[$key]["usingOwnDB"]);					
					}
				}
				
				$siteSQL =<<<EOF
					SELECT s.siteid,s.sitename,x.site_status,x.sitedomainname FROM epm_sites AS s INNER JOIN epm_xref_site_environment AS x ON s.siteid = x.siteid WHERE s.siteid = $siteid;
EOF;
				$siteResult 			= pg_query($db, $siteSQL);
				$siteData 				= pg_fetch_assoc($siteResult);
				
				$site["siteID"] 		= trim($siteData["siteid"]); 
				$site["siteName"] 		= trim($siteData["sitename"]); 

				$siteStatus 	= "Errors";	
				if(trim($siteData["site_status"]) == "Completed"){
					$siteStatus = "Deployed";	
				}
				
				if($environmentid == 1){
					$site["DevSiteURL"] 		=	trim($siteData["sitedomainname"]); 
					$site["DevAdminSiteURL"] 	=	trim($siteData["sitedomainname"])."/user"; 
					$site["DevSiteStatus"] 		=	$siteStatus; 
					$site["Dev"] 				=	1;
				}
				elseif($environmentid == 2){
					$site["TestSiteURL"] 		=	trim($siteData["sitedomainname"]); 
					$site["TestAdminSiteURL"] 	=	trim($siteData["sitedomainname"])."/user"; 
					$site["TestSiteStatus"] 	=	$siteStatus; 
					$site["Test"] 				=	1;
				}
				elseif($environmentid == 3){
					$site["LiveSiteURL"] 		=	trim($siteData["sitedomainname"]); 
					$site["LiveAdminSiteURL"] 	=	trim($siteData["sitedomainname"])."/user"; 
					$site["LiveSiteStatus"] 	=	$siteStatus; 
					$site["Live"] 				=	1;
				}				
				$site["subSite"]=$subSites;				
				return $site;
			}
		}
	}

	protected function addSiteInDB($file,$master_site_domain,$subSites,$sites_directory,$serverid,$companyid,$environmentid){
		
		 // Making DB connection
		$db = $this->postgresDBConnection();
		
		$addSiteSQL =<<<EOF
		INSERT INTO epm_sites (companyid,sitename,sitestatus) VALUES ($companyid,'$file','Completed') RETURNING siteid;
EOF;
		$addSiteResult 	= pg_query($db, $addSiteSQL);
		$addSiteData 	= pg_fetch_assoc($addSiteResult);
		$newSiteID 		= $addSiteData["siteid"];
				
		$addSiteEnvironmentSQL =<<<EOF
		INSERT INTO epm_xref_site_environment (siteid,environmentid,drupalfolderpath,serverid,sitedomainname,site_status) VALUES ($newSiteID,$environmentid,'$sites_directory',$serverid,'$master_site_domain','Completed');
EOF;

		$addSiteEnvironmentResult 	= pg_query($db, $addSiteEnvironmentSQL);

		if(sizeof($subSites)>0){
			foreach($subSites as $key=>$val){
				
				$subsitename = $val["subSiteName"];
				$subsiteurl = $val["subSiteURL"];			
				$sub_site_directory = $sites_directory."_".$companyid."_".$environmentid."_".$newSiteID."/sites/".$val["subSiteURL"];
				$addSubSiteSQL =<<<EOF
					INSERT INTO epm_sub_site 
						(environmentid,siteid,subsitename) 
					VALUES 
						($environmentid,'$newSiteID','$subsitename') 
					RETURNING subsiteid;
EOF;
				$addSubSiteResult 					= pg_query($db, $addSubSiteSQL);
				$addSubSiteData 					= pg_fetch_assoc($addSubSiteResult);				
				$subSiteID							= $addSubSiteData["subsiteid"];
				$addSubSiteEnvironmentSQL =<<<EOF
					INSERT INTO epm_xref_subsite_environment 
						(subsite_id,environment_id,subsite_path,serverid,subsite_domain_name,subsite_status,database_id,git_branch_id) 
					VALUES 
						($subSiteID,'$environmentid','$sub_site_directory',$serverid,'$subsiteurl','Completed',0,0);
EOF;

				$addSubSiteEnvironmentResult	= pg_query($db, $addSubSiteEnvironmentSQL);
			}
		}
		
		// Renaming the site folder, example_folder to example_folder_1_1_23
		rename($sites_directory,$sites_directory."_".$companyid."_".$environmentid."_".$newSiteID);
		return $newSiteID;
	}	
	
	protected function isSubsiteUsesOwnDB($siteSettingsFilePath,$subSiteSettingsFilePath){
		
		// Getting Site DB details
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
								$sitedb[trim(trim($DBElement[0]),"'")] = trim(trim($DBElement[1]),"'");
							}
						}
					}
				}
			}
		}
		
		// Getting Sub Site DB details
		if(file_exists($subSiteSettingsFilePath)){
			if($file = file($subSiteSettingsFilePath)){									
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
								$subsitedb[trim(trim($DBElement[0]),"'")] = trim(trim($DBElement[1]),"'");
							}
						}
					}
				}
			}
		}
		
		$sameDB = "NO";
		if(isset($sitedb) && isset($subsitedb)){
			$sameDB = "YES";
			foreach($sitedb as $key=>$val){
				if(!isset($subsitedb[$key]) || $subsitedb[$key] != $val){
					$sameDB = "NO";
				}
			}
		}
		return $sameDB;
	}
}

$serverid 		= (isset($_GET["serverid"])) 		? $_GET["serverid"] 		: "";
$companyid 		= (isset($_GET["companyid"])) 		? $_GET["companyid"] 		: "";
$environmentid	= (isset($_GET["environmentid"])) 	? $_GET["environmentid"] 	: "";	
$siteid 		= (isset($_GET["siteid"])) 			? $_GET["siteid"] 			: "";

if($serverid == "" || $companyid == "" || $environmentid == ""){
	return json_encode(array());
} 
else {
	$subsiteobj = new SiteListing();
	echo $subsiteobj->ListSitesAndSubsites($serverid,$companyid,$environmentid,$siteid);
}