<?php
	/**
	 * Copyright (C) 2013 by iRail vzw/asbl
	 *
	 * @author Hannes Van De Vreken (hannes aŧ irail.be) 
	 * @license AGPLv3
	 */

namespace hannesvdvreken\harvester\model;
use hannesvdvreken\harvester\config\Config;

class Logger
{
	private static $M ;
	private static $db_name ;

	public function __construct() {
		$hosts = implode(',',Config::$db_hosts);
		$moniker = "mongodb://" . Config::$db_username . ":" . Config::$db_passwd . "@$hosts/" . Config::$db_name;
		
		$m = new \Mongo($moniker);
		self::$M = $m->{Config::$db_name};
	}

	public function log( $request ){
		$db =& self::$M;
		$request['logtime'] = date('c');
		$db->log->insert( $request );
	}
}