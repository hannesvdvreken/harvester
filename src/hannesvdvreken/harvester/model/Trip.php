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

	public function exists( $tid, $date, $agency ) {
		$db =& self::$M;

		return $db->trips->count(compact('tid','date','agency'));
	}

	public function set_platform( $tid, $date, $agency, $sid, $platform ) {
		$db =& self::$M;

		$q = compact('tid','date','agency','sid');
		$db->trips->update($q, ['platform'=>$platform], ['upsert'=>false]);
		
		return $this;
	}

	public function save( $service_stops ) {
		$db =& self::$M;
		
		/* this makes sense:
		   a train does not arrive at first stop and does not depart at last stop */
		$first = reset($service_stops);
		$last  = end($service_stops);

		unset($first->arrival_time);
		unset($first->arrival_delay);
		unset($last->departure_time);
		unset($last->departure_delay);

		/* prepare static part of search query */
		$index = [ 	'date' => $first->date,
					'tid' => $first->tid, 
					'agency' => $first->agency ];

		foreach ($service_stops as $service_stop) {
			$index['sid'] = $service_stop->sid;
			if ($db->trips->count($index)) {
				$saved = $db->trips->findOne($index);
				foreach (get_object_vars($service_stop) as $attr => $value) {
					if ($attr == 'arrival_time'   && isset($saved[$attr]) ||
					    $attr == 'departure_time' && isset($saved[$attr]) ) {continue;}
					if (($attr == 'arrival_delay' || $attr == 'departure_delay') &&
						 $saved[$attr] != $service_stop->$attr )
					{
						 $saved[$attr."_history"][] = ['time' => date('c',time()), 'value' => $service_stop->$attr ];
					}
					$saved[$attr] = $service_stop->$attr;
				}
				$db->trips->save($saved);
			}else{
				$db->trips->insert( $service_stop );
			}
		}

		return $this;
	}
}