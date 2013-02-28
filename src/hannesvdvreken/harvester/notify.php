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

	$result      = $_POST['result'];
	$nonce       = $_POST['nonce'];
	$request_uri = $_POST['request_uri'];

	/* init cache and curl */
	use tdt\cache\Cache;
	$cache = Cache::getInstance(['system'=>'MemCache']);

	/* check the nonce */
	$c = $cache->get('awaiting/'.$request_uri);
	if ($c['nonce'] != $nonce)
	{
		/* do nothing, it's a scam! */
		exit;
	}

	/* clear memory */
	//$cache->set('awaiting/'.$request_uri, [], 0);

	/* do the tdt\input part */
	use tdt\input\Input;

	list( $type ) = explode('/', $request_uri);
	$transformclass = ucfirst($type) . 'Transform';

	$input_config['source'] = $result;
	$input_config['extract'] = 'JSON';
	$input_config['transform'] = [];
	$input_config['transform'][] = "hannesvdvreken\\harvester\\transform\\$transformclass";
	$input_config['map'] = 'RDF';
	$input_config['mapfile'] = $config['mapfile'];
	$input_config['load'] = 'CLI';

	$i = new Input($input_config);
	$i->execute();