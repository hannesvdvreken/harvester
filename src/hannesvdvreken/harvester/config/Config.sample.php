<?php
	/**
	 * Copyright (C) 2013 by iRail vzw/asbl
	 *
	 * @author Hannes Van De Vreken (hannes aŧ irail.be) 
	 * @license AGPLv3
	 */

namespace hannesvdvreken\harvester\config;

class Config {

	public static $remote = [
								'https://1.harvesters.com/'
							];

	public static $private_key = 'config/private.pem';
	public static $db_username = 'user' ;
	public static $db_name     = 'transport' ;
	public static $db_passwd   = 'password' ;
	public static $db_hosts = [
								'127.0.0.1'
							  ];

	public static $stops_interval = 10;
	public static $trips_interval = 3;
	public static $enable_authentication = true;

}
