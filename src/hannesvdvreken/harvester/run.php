<?php
	/**
	 * Copyright (C) 2012 by iRail vzw/asbl
	 *
	 * @author Hannes Van De Vreken (hannes aŧ irail.be) 
	 * @license AGPLv3
	 */

	namespace hannesvdvreken\harvester;

	require_once '../../../vendor/autoload.php';
	require_once 'config/config.php';

	use tdt\cache\Cache;

	define('PRVKEY',file_get_contents($config['private_key']));

	function sign_request($request_uri) {
		/* make nonce */
		$nonce = base64_encode(openssl_random_pseudo_bytes(48));
		$prvkey = openssl_get_privatekey(PRVKEY);

		/* sign */
		openssl_private_encrypt("$request_uri+$nonce", $encrypted, $prvkey);
		$signature = base64_encode($encrypted);

		/* return signature parameters */
		return ["nonce"=>$nonce,"signature"=>$signature];
	}

	/* testing */
	$stops = ['751'];
	$trips = ['562'];
	
	$date = date('Ymd', strtotime('+2 weeks'));
	$i = 0;

	$curl = new \Curl();
	$cache = Cache::getInstance(['system'=>'MemCache']);
	$type = 'trip';
	
	foreach ($trips as $trip)
	{
		/* prepare */
		$remote = $config['remote'][$i];
		$request_uri = "$type/$trip/$date";

		/* sign request */
		$params = sign_request($request_uri);

		/* make request */
		$json = $curl->simple_post($remote.$request_uri, $params);
		$result = json_decode($json);

		if ($result->error != 1)
		{
			$result->request_uri = $request_uri;
			echo json_encode($result);
			exit;
		}

		$cache->set('awaiting/'.$request_uri, ['time'=>date('c'),'nonce'=>$params['nonce']], 60*60);
		
		$i = ( $i + 1 ) % count($config['remote']);
	}