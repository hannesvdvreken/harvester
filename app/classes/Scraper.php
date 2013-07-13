<?php

use hannesvdvreken\railtimescraper\RailTimeScraper;
use Guzzle\Http\Client;

class Scraper {

	/**
	 * main function when called from the queue
	 */
	public function fire ($job, $data) {

		$this->execute($data);

		$job->delete();

	}

	/**
	 * main function
	 */
	public function execute ($data)
	{
		// timer
		$start = microtime(true);

		// echo
		echo $data['type'] .' '. $data['id'] .' on '. $data['date'] . "\n";

		// prepare
		list($stop_names, $inverted) = $this->get_arrays();
		
		// prepare scraper
		$rts = new RailTimeScraper($stop_names, $inverted);
		
		// scrape
		$result = $rts->{$data['type']}($data['id'], $data['date']);

		// logging
		echo "scraping completed in ". (microtime(true) - $start) ." seconds.\n";
		$start = microtime(true);
		
		// save to db
		$result = $this->prepare($result, $data['type']);
		
		$this->save($result, $data['type']);
		
		// add trips to queue to pull more data
		if ($data['type'] == 'stop')
		{
			$this->queue_trips($result);
		}

		echo "job completed in ". (microtime(true) - $start) ." seconds.\n";
	}

	/**
	 * get static data from cache or from remote
	 */
	private function get_arrays () 
	{

		if (Cache::has('stops')) 
		{
			$sn = Cache::get('stops');
			extract($sn);

		}
		else
		{
			// vars
			$base_url = 'http://api.hannesv.be/';
			$endpoint = 'nmbs/stations.json';
			$cache_time = 120; // minutes

			// get stop names
			$client = new Client($base_url);

			$result = $client->get($endpoint)->send()->json();

			// start empty
			$stop_names = array();
			$inverted = array();

			// loop
			foreach ($result['stations'] as $station) {
				$stop_names[(integer)$station['sid']] = $station['stop'];
				$inverted[$station['stop']] = (integer)$station['sid'];
			}

			// cache result
			Cache::put('stops', compact('stop_names', 'inverted'), $cache_time);
		}

		// return
		return array($stop_names, $inverted);
	}

	/**
	 * add extra jobs to the queue based on scraped data
	 */
	private function queue_trips ($result)
	{
		// failsafe
		if (empty($result)) return false;

		// redis init
		$redis = Redis::connection();

		// static
		$type = 'trip';
		$first = reset($result);
		$date = $first['date'];

		// failsafe
		if (!$date) return false;

		// empty
		$pushed = array();

		// loop results
		foreach ($result as $trip) {
			
			$member = "$type:{$trip['tid']}";

			// added to set?
			if ($redis->sismember("pulled:$date", $member)) continue;

			// add to set later
			$pushed[] = $member;

			// message
			$data = array(
				'type' => $type,
				'id' => $trip['tid'],
				'date' => $date,
			);

			// add message to queue
			Queue::push("Scraper", $data);
		}

		// pipeline
		Redis::pipeline(function($pipe) use($pushed, $date) {

			foreach ($pushed as $member) {
				$pipe->sadd("pulled:$date", $member);
			}

		});
	}

	/**
	 * prepare data
	 */
	private function prepare($result, $type)
	{
		// calculate route signature to allow query on routes
		if ($type == 'trip') {

			// start empty
			$signature = '';
			$origin    = '';
			$headsign  = '';

			// include all stopnames in signature
			foreach ($result as &$s) 
			{
				$signature .= $s['stop'];
				$origin    .= (reset($result) == $s) ? $s['stop'] : '';
				$headsign  .= (end($result)   == $s) ? $s['stop'] : '';
			}

			// hash
			$signature = hash('sha1', $signature);

		}

		// loop
		foreach ($result as &$s) {
		
			// make sure to use integers
			$s['tid'] = (integer)$s['tid'];
			$s['date'] = (integer)$s['date'];

			// safe
			if (isset($s['sid']))
			{
				$s['sid'] = (integer)$s['sid'];
			}

			// do trip stuff
			if ($type == 'trip')
			{
				// set route
				$s['route'] = $signature;

				// headsign & origin
				$s['origin']   = $origin;
				$s['headsign'] = $headsign;

				// unset departure & arrival times for resp. last & first
				if ($s == reset($result))
				{
					// a vehicle does not arrive at a first stop
					unset($s['arrival_time']);
					unset($s['arrival_delay']);
				}
				elseif ($s == end($result))
				{
					// a vehicle does not depart at a terminus
					unset($s['departure_time']);
					unset($s['departure_delay']);
				}
			}
			// do stop stuff
			elseif ($type == 'stop')
			{
				unset($s['arrival_time']);
				unset($s['arrival_delay']);
				unset($s['departure_delay']);
				unset($s['departure_time']);

				unset($s['headsign']);
				unset($s['origin']);
			}
		}

		return $result;
	}

	/**
	 * save array of data as objects in db
	 */
	private function save($result, $type)
	{
		// loop
		foreach ($result as $s) {
			// pull
			$saved = ServiceStop::where('tid',  $s['tid'])
			                    ->where('sid',  $s['sid'])
			                    ->where('date', $s['date'])
			                    ->first();

			if ($saved)
			{
			    // update fields
				foreach($s as $key => $value) {
					$saved->$key = $value;
				}
				
				// save to db
			    $saved->save();
			}
			else
			{
				// insert
				ServiceStop::insert($s);
			}
		}
	}
}