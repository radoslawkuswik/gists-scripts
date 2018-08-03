<?php

/**
 * Configuration
 */

define("SETTINGS", [
  "jenkins-url" => "", // eg. https://jenkins.radoslawkuswik.pl
  "job-name" => "", // eg. my-jenkins-job-deploy
  "username" => "", // eg. rkuswik
  "token" => "", // eg. 551a1b09d930a695cc03d2ba82ecd140
  "date-string" => "Y-m-d H:i:s",
  "date-timezone" => "Europe/Warsaw"
]);

date_default_timezone_set(SETTINGS["date-timezone"]);

/**
 * Check provided arguments
 */

$CMD_ARGUMENT = isset($argv[1]) ? strtolower($argv[1]) : '';

if ($CMD_ARGUMENT == '') {
	exit("\nPlease provide Jenkins operation type: [CHECK|START]\n\n");
}

if (!in_array($CMD_ARGUMENT, ['check', 'start'])) {
	exit("\nPlease provide valid Jenkins operation type: [CHECK|START]\n\n");
}

/**
 * Program
 */

$curl = createCurlInstance();
$lastBuildInfo = getLastBuildInfo($curl);
readAndShowLastBuildInfo($lastBuildInfo);

if ($CMD_ARGUMENT == 'start' && $lastBuildInfo['body']['result'] == 'SUCCESS') {
	startNewBuild($curl);
	waitTillEndOfBuild($curl);
} elseif ($CMD_ARGUMENT == 'start') {
	exit("Last build gone wrong or isn't finished.\n\nPlease check Jenkins on ".SETTINGS["jenkins-url"]." for more information!\n");
	curl_close($curl);
}

curl_close($curl);

/**
 * Functions
 */

function createCurlInstance() {
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($curl, CURLOPT_USERPWD, SETTINGS["username"].':'.SETTINGS["token"]);
	curl_setopt($curl, CURLOPT_POST, 1);
	return $curl;
}

function getLastBuildInfo($_curl) {
	curl_setopt($_curl, CURLOPT_URL, SETTINGS["jenkins-url"].'/job/'.SETTINGS["job-name"].'/lastBuild/api/json');
	$result = curl_exec($_curl);
	$JSON = json_decode($result, true);

	return [
		'body' => $JSON,
		'http_code' => curl_getinfo($_curl, CURLINFO_HTTP_CODE),
	];
}

function readAndShowLastBuildInfo($_result) {
	echo "\n";
	if ($_result['http_code'] === 200) {
		echo "Last job name: ".$_result['body']['fullDisplayName']."\n";
		echo "Started by:    ".$_result['body']['actions'][0]['causes'][0]['userName']."\n";
		echo "Timestamp:     ".date(SETTINGS['date-string'], ($_result['body']['timestamp'] / 1000))."\n";
		echo "Result:        ".($_result['body']['building'] == 1 ? 'BUILDING...' : $_result['body']['result'])."\n";
	} else if ($_result['http_code'] === 404) {
		echo "Job '".SETTINGS['job-name']."' not found...\n";
	} else {
		echo "Got HTTP code ".$_result['http_code']."...\n";
		echo "Something gone wrong!\n\nPlease check Jenkins on ".SETTINGS["jenkins-url"]." for more information!\n"; 
	}
	echo "\n";
}

function startNewBuild($_curl) {
	echo "Trying to start new build... ";
	curl_setopt($_curl, CURLOPT_URL, SETTINGS["jenkins-url"].'/job/'.SETTINGS["job-name"].'/build');
	curl_exec($_curl);
	$HTTP_CODE = curl_getinfo($_curl, CURLINFO_HTTP_CODE);
	if ($HTTP_CODE == 201) {
		echo "Done!";
	} else {
		exit("Failed! HTTP Code: ".$HTTP_CODE);
	}
}

function waitTillEndOfBuild($_curl) {
	echo "\nBuilding: ";

	$lastBuildInfo = getLastBuildInfo($_curl);
	while($lastBuildInfo['body']['building'] == 1 && $lastBuildInfo['body']['result'] == '') {
		echo "#";
		$lastBuildInfo = getLastBuildInfo($_curl);
		sleep(5);
	}
		
	if ($lastBuildInfo['body']['result'] == 'SUCCESS') {
		echo " Done!\n";
	} else {
		echo "Something gone wrong!\n\nPlease check Jenkins on ".SETTINGS["jenkins-url"]." for more information!\n"; 
	}
	echo "\n";
}
