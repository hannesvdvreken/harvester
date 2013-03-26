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

	$date = date('Ymd',time());
	$trip_model = new model\Trip();
	$trips = $trip_model->get_running();
	

	foreach ($trips as &$trip) {
		$jobs[] = "trip/".$trip['_id']."/$date/nocache";
	}

	$curl = new \Curl();
	$logger = new model\Logger();
	$cache = Cache::getInstance(['system'=>'MemCache']);
	
	$i = 0;
	foreach ($jobs as $request_uri)
	{
		/* prepare */
		$remote = Utils::get_scraper($request_uri);

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

		if ($request_uri != end($jobs) && $i % 10 == 0 ) {
			sleep(config\Config::$trips_interval);
		}
		$i++;
	}

	exit;