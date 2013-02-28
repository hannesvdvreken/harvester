<?php

namespace hannesvdvreken\harvester\transform;

/**
 * important to make an 'alias'
 * http://stackoverflow.com/questions/3449122/extending-a-class-with-an-other-namespace-with-the-same-classname
 */
use tdt\input\ATransformer;

class TripTransform extends ATransformer{
    
	public function execute(&$chunk)
	{
		echo json_encode($chunk);
		return $chunk;
	}

}
