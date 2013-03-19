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
		return [ $chunk->sequence, $chunk->tid, $chunk->sid, $chunk->headsign, $chunk->departure_time, $chunk->arrival_time, $chunk->departure_delay, $chunk->arrival_delay, $chunk->date ];
	}

}