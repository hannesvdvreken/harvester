<?php

namespace hannesvdvreken\harvester\transform;

class StopTransform extends tdt\input\ATransformer{
    
	public function execute(&$chunk)
	{
		echo json_encode($chunk);
		return $chunk;
	}

}
