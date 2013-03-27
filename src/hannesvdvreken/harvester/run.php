<?php
	/**
	 * Copyright (C) 2013 by iRail vzw/asbl
	 *
	 * @author Hannes Van De Vreken (hannes aŧ irail.be) 
	 * @license AGPLv3
	 */

	namespace hannesvdvreken\harvester;

	require_once '../../../vendor/autoload.php';

	use tdt\cache\Cache;

	header('Content-type: application/json');

	/* get parameters */
	array_shift($argv); // remove script name from argv list
	if (count($argv) < 1){ echo "usage: php run.php Ymd-date [(trip|stop) id]\n"; exit;}
	$date = array_shift($argv);

	$jobs = [];

	if (count($argv) > 0) {
		list( $type, $id ) = $argv;
		$jobs[] = "$type/$id/$date";
	} else {
		$stop_model = new model\Stop();
		$stops = $stop_model->get_all();
		
		foreach ($stops as $stop) {
			if (!isset($stop['sid'])) { continue;}
			$jobs[] = "stop/".$stop['sid']."/$date";
		}
	}

	$curl = new \Curl();
	$logger = new model\Logger();
	$cache = Cache::getInstance(['system'=>'MemCache']);
	
	foreach ($jobs as $request_uri)
	{
		/* prepare */
		$remote = Utils::get_scraper($request_uri);
		echo date('c',time()) . "$remote$request_uri\n";

		/* sign request */
		$params = Utils::sign_request($request_uri);

		/* make request */
		$log_entry = ['request'=>$remote.$request_uri, 'method'=>'POST', 'origin'=>'run.php', 'post_params' => $params];
		$logger->log($log_entry);
		$json = $curl->simple_post($remote.$request_uri, $params, [CURLOPT_SSL_VERIFYPEER=>false]);
		$result = json_decode($json);

		if (!is_null($curl->error_code))
		{
			$log_entry = ['origin'=>'run.php', 'log'=>'post failed', 'error_string'=>$curl->error_string, 'error_code'=> $curl->error_code, 'request'=>$remote.$request_uri, 'method'=>'POST', 'post_params' => $params];
			$logger->log($log_entry);
			exit;
		}

		$cache->set('awaiting/'.$request_uri, ['time'=>date('c'), 'signature'=>$params['signature'], 'nonce'=>$params['nonce']], 60*60);

		if ($request_uri != end($jobs)) {
			sleep(config\Config::$stops_interval);
		}
	}

	exit;