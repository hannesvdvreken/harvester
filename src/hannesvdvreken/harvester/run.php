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

	$stop_model = new model\Stop();

	/* testing */
	$stops = $stop_model->get_all();
	echo json_encode($stops);
	
	$date = date('Ymd', strtotime('+2 days'));

	$curl = new \Curl();
	$logger = new model\Logger();
	$cache = Cache::getInstance(['system'=>'MemCache']);
	
	foreach ($stops as $stop)
	{
		/* prepare */
		$request_uri = "stop/".$stop['sid']."/$date";
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

		if ($stop != end($stops)) {
			sleep(config\Config::$stops_interval);
		}
	}

	exit;

	sleep( 300 ); // 5 minutes
	$uncompleted = [];

	/* check for all jobs to be finished */
	foreach ($stops as $stop)
	{
		$request_uri = "stop/".$stop['sid']."/$date";
		$remote = Utils::get_scraper($request_uri);
		$json = $curl->simple_get($remote.$request_uri);
		$result = json_decode($json);

		if (isset($result['error'])) {
			$uncompleted[] = $request_uri;
		} else {
			foreach ($result as $trips)
			{
				$tid = $trip['tid'];
				$request_uri = "trip/$tid/$date";
				$remote = Utils::get_scraper( $request_uri );
				$json = $curl->simple_get($remote.$request_uri);
				$result = json_decode($json);
				if (isset($result->error)) {
					$uncompleted[] = $request_uri;
				}
			}
		}
	}

	$log_entry = ['uncompleted'=>$uncompleted, 'origin'=>'run.php'];
	$logger->log($log_entry);