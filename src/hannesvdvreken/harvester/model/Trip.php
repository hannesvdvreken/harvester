<?php
	/**
	 * Copyright (C) 2013 by iRail vzw/asbl
	 *
	 * @author Hannes Van De Vreken (hannes aŧ irail.be) 
	 * @license AGPLv3
	 */

namespace hannesvdvreken\harvester\model;
use hannesvdvreken\harvester\config\Config;

class Trip
{
	private static $M ;
	private static $db_name ;

	public function __construct() {
		$hosts = implode(',',Config::$db_hosts);
		$moniker = "mongodb://" . Config::$db_username . ":" . Config::$db_passwd . "@$hosts/" . Config::$db_name;
		
		$m = new \Mongo($moniker);
		self::$M = $m->{Config::$db_name};
	}

	public function save( $trip ){
		$db =& self::$M;
		
		/* this makes sense */
		$stops =& $trip['stops'];
		
		$first = reset($stops);
		$last  = end($stops);

		unset($first->arrival_time);
		unset($first->arrival_delay);
		unset($last->departure_time);
		unset($last->departure_delay);

		/* query using these attributes. should be unique */
		$index = ['date'=>$trip['date'],'tid'=>$trip['tid'],'agency'=>$trip['agency']];
		if ($db->trips->count($index)) {
			/* get */
			$result = $db->trips->findOne($index);

			/* merge */
			foreach ( array_keys($trip['stops']) as $sid ) {
				foreach ( $result['stops'][$sid] as $attr => $value ){
					if ($attr != 'arrival_time' && $attr != 'departure_time')
						$result['stops'][$sid][$attr] = $value;
				}
			}
			/* save */
			$db->trips->save($result);
		}else{
			/* insert */
			$db->trips->insert( $trip );
		}
	}
}