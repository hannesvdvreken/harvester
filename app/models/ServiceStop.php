<?php

use Jenssegers\Mongodb\Model as Eloquent;

class ServiceStop extends Eloquent {

	/**
	 * The database collection used by the model.
	 *
	 * @var string
	 */
	protected $collection = 'trips';

	public $timestamps = false;

}