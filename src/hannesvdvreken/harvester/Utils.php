<?php
	/**
	 * Copyright (C) 2013 by iRail vzw/asbl
	 *
	 * @author Hannes Van De Vreken (hannes aŧ irail.be) 
	 * @license AGPLv3
	 */

namespace hannesvdvreken\harvester;

class Utils
{
	public static function sign_request ( $request_uri )
	{
		/* make nonce */
		$nonce = base64_encode(openssl_random_pseudo_bytes(48));
		$prvkey = openssl_get_privatekey(file_get_contents(config\Config::$private_key));

		/* sign */
		openssl_private_encrypt("$request_uri+$nonce", $encrypted, $prvkey);
		$signature = base64_encode($encrypted);

		/* return signature parameters */
		return ["nonce"=>$nonce,"signature"=>$signature];
	}

	public static function get_scraper ( $request_uri )
	{
		$num = count(config\Config::$remote);
		$hash = hexdec(substr(sha1($request_uri), 0, 15)); // substr. we don't need huge numbers.
		return config\Config::$remote[$hash % $num];
	}
}