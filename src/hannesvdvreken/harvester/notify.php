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
	$cache = Cache::getInstance(['system'=>'MemCache']);

	/* check the nonce */
	$c = $cache->get('awaiting/'.$request_uri);
	if ( $c && isset($c['nonce']) && $c['nonce'] != $nonce)
	{
		$log_entry = ['origin'=>'notify.php', 'log'=>'not a valid nonce used', 'post'=>$_POST];
		$logger->log($log_entry);
		exit;
	}
	$cache->delete('awaiting/'.$request_uri);

	list( $type, $id, $date ) = explode('/', $request_uri);

	/* get stop data from remote */
	$remote = Utils::get_scraper( $request_uri );
	
	$json = $curl->simple_get($remote.$request_uri, [CURLOPT_SSL_VERIFYPEER=>false]);
	$result = json_decode($json);

	echo json_encode(compact('result','json','remote','c','request_uri','nonce')); exit;
	
	if (isset($result->error)){
		$log_entry = ['origin'=>'notify.php', 'request'=>$remote.$request_uri, 'result'=>$result, 'method'=>'GET'];
		$logger->log($log_entry);
		exit;
	}

	if ($type == 'stop')
	{
		/* all the trips */
		foreach ( $result as $trip )
		{
			/* prepare */
			$tid = $trip->tid;
			$request_uri = "trip/$tid/$date";
			$remote = Utils::get_scraper( $request_uri );

			/* sign request */
			$params = Utils::sign_request($request_uri);

			/* go */
			$r = $curl->simple_post($remote.$request_uri, $params, [CURLOPT_SSL_VERIFYPEER=>false]);
			$log_entry = ['origin'=>'notify.php', 'request'=>$remote.$request_uri, 'method'=>'POST', 'post_params'=>$params];
			$logger->log($log_entry);

			if (isset($r->error) && $r->error != 1) {
				$log_entry = ['origin'=>'notify.php', 'request'=>$remote.$request_uri, 'method'=>'POST', 'post_params'=>$params, 'result'=>$r];
				$logger->log($log_entry);
			} else {
				$cache->set('awaiting/'.$request_uri, ['time'=>date('c'), 'signature'=>$params['signature'], 'nonce'=>$params['nonce']], 60*60);
			}
			
			/* don't kill them */
			if ($trip != end($result)) {
				sleep(config\Config::$trips_interval);
			}
		}
	} else {

		/* transforming */
		$t = [];
		$t['headsign'] = end($result)->headsign;
		$t['tid'] = end($result)->tid;
		$t['date'] = end($result)->date;
		$t['agency'] = end($result)->agency;
		$t['type'] = end($result)->type;

		$t['stops'] = $result;

		/* saving */
		$trip = new model\Trip();
		$trip->save($t);

		/* do the tdt\input part
		$transformclass = ucfirst($type) . 'Transform';

		$input_config['extract']['source'] = Utils::get_scraper( $request_uri ) . $request_uri ;
		$input_config['extract']['type'] = 'JSON';
		$input_config['transform'] = ["hannesvdvreken\\harvester\\transform\\$transformclass"];
		$input_config['map']['type'] = 'RDF';
		$input_config['map']['mapfile'] = config\Config::$mapfile[$type];
		$input_config['load']['type'] = 'RDF';
		//$input_config['graph'] = 'http://stations.io/';
		$input_config['map']['datatank_uri'] = "http://localhost/";
		$input_config['map']['datatank_package'] = "NMBS";
		$input_config['map']['datatank_resource'] = "ServiceStop";

		$input_config['endpoint'] = 'http://dba:dba@localhost:8890/sparql';

		$i = new Input($input_config);
		$i->execute();*/
	}