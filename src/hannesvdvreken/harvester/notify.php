<?php
	/**
	 * Copyright (C) 2013 by iRail vzw/asbl
	 *
	 * @author Hannes Van De Vreken (hannes aŧ irail.be) 
	 * @license AGPLv3
	 */

	namespace hannesvdvreken\harvester;
	use tdt\input\Input;

	header('Content-type: application/json');

	/* booting */
	require_once '../../../vendor/autoload.php';
	$logger = new model\Logger();

	/* log call */
	$log_entry = ['origin'=>'notify.php', 'log'=>'called', 'post'=>$_POST];
	$logger->log($log_entry);

	/* check validity */
	if (!isset($_POST['nonce']) || !isset($_POST['request_uri'])) {
		$log_entry = ['origin'=>'notify.php', 'log'=>'not a valid request', 'post'=>$_POST];
		$logger->log($log_entry);
		exit;
	}

	$nonce       = $_POST['nonce'];
	$request_uri = $_POST['request_uri'];

	/* init cache and curl */
	use tdt\cache\Cache;
	$curl = new \Curl();
	$trip_model = new model\Trip();
	$cache = Cache::getInstance(['system'=>'MemCache']);

	/* check the nonce */
	$c = $cache->get('awaiting/'.$request_uri);
	$cache->delete('awaiting/'.$request_uri); //anyway

	if ($c
		&& isset(config\Config::$enable_authentication)
		&& config\Config::$enable_authentication
		&& isset($c['params'])
		&& isset($c['params']['nonce'])
		&& $c['params']['nonce'] != $nonce)
	{
		$log_entry = ['origin'=>'notify.php', 'log'=>'not a valid nonce used', 'post'=>$_POST];
		$logger->log($log_entry);
		exit;
	}

	list( $type, $id, $date ) = explode('/', $request_uri);

	/* get stop data from remote */
	$remote = Utils::get_scraper( $request_uri );
	
	$json = $curl->simple_get($remote.$request_uri, [], [CURLOPT_SSL_VERIFYPEER=>false]);
	$result = json_decode($json);
	
	if (!is_null($curl->error_code))
	{
		$log_entry = ['origin'=>'notify.php', 'log'=>'get failed', 'request'=>$remote.$request_uri, 'error_string'=>$curl->error_string, 'error_code'=> $curl->error_code, 'method'=>'GET'];
		$logger->log($log_entry);
		exit;
	}

	if ($type == 'stop')
	{
		/* all the trips */
		foreach ( $result as $trip )
		{
			/* don't repull */
			if ($trip_model->exists($trip->tid, $date, $trip->agency)) {
				$trip_model->save([$trip]);
				continue;
			}

			/* save and get full data */
			$trip_model->save([$trip]);

			/* prepare */
			$tid = $trip->tid;
			$platform = isset($trip->platform) ? $trip->platform : NULL;
			$request_uri = "trip/$tid/$date";
			$remote = Utils::get_scraper( $request_uri );

			/* don't repull */
			$awaiting = $cache->get('awaiting/'.$request_uri);
			if ($awaiting) {
				continue;
			}

			/* sign request */
			$params = Utils::sign_request($request_uri);

			/* go */
			$r = $curl->simple_post($remote.$request_uri, $params, [CURLOPT_SSL_VERIFYPEER=>false]);
			$log_entry = ['origin'=>'notify.php', 'request'=>$remote.$request_uri, 'method'=>'POST', 'post_params'=>$params];
			$logger->log($log_entry);

			if (!is_null($curl->error_code))
			{
				$log_entry = ['origin'=>'notify.php', 'log'=>'post failed', 'request'=>$remote.$request_uri, 'method'=>'POST', 'post_params'=>$params, 'error_string'=>$curl->error_string, 'error_code'=> $curl->error_code];
				$logger->log($log_entry);
			} else {
				$cache->set('awaiting/'.$request_uri, ['params'=>$params], 60*60);
			}
			
			if ($trip != end($result)) {
				/* don't kill the scrapers */
				sleep(config\Config::$trips_interval);
			}
		}
	} else {
		/* saving */
		$trip_model->save($result);
	}