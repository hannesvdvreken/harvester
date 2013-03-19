<?php
	/**
	 * Copyright (C) 2013 by iRail vzw/asbl
	 *
	 * @author Hannes Van De Vreken (hannes aŧ irail.be) 
	 * @license AGPLv3
	 */

namespace hannesvdvreken\harvester\model;
use hannesvdvreken\harvester\config\Config;

class Stop
{
	private static $M ;
	private static $db_name ;

	public function __construct() {
		$hosts = implode(',',Config::$db_hosts);
		$moniker = "mongodb://" . Config::$db_username . ":" . Config::$db_passwd . "@$hosts/" . Config::$db_name;
		
		$m = new \Mongo($moniker);
		self::$M = $m->{Config::$db_name};
	}

	public function get_all(){
		$db =& self::$M;
		$result = $db->trips->aggregate([['$project' => ['stops' => 1]], ['$unwind' => '$stops'], ['$group' => ['_id' => ['sid'=>'$stops.sid','name'=>'$stops.stop']]]])['result'];
		$sids = [];
		foreach ($result as &$o) {
			$sids[] = $o['_id'];
		}
		return $sids;
	}
}