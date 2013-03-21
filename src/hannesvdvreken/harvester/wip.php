<?php
	/**
	 * Copyright (C) 2013 by iRail vzw/asbl
	 *
	 * @author Hannes Van De Vreken (hannes aŧ irail.be) 
	 * @license AGPLv3
	 */

	namespace hannesvdvreken\harvester;

	require_once '../../../vendor/autoload.php';

	$remotes = config\Config::$remote;
	$curl = new \Curl();

	foreach ( $remotes as $remote ) {
		$json = $curl->simple_get($remote.'wip.php',[], [CURLOPT_SSL_VERIFYPEER=>false]);
		echo $json;
	}