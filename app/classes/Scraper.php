<?php

use hannesvdvreken\railtimescraper\RailTimeScraper;
use Guzzle\Http\Client;

class Scraper {

	/**
	 * main function
	 */
	public function fire ($job, $data)
	{
		var_dump($data);

		// prepare
		list($stop_names, $inverted) = $this->get_arrays();

		// prepare scraper
		$rts = new RailTimeScraper($stop_names, $inverted);
		
		// scrape
		$result = $rts->{$data['type']}($data['id'], $data['date']);

		// save to db
		$result = $this->prepare($result, $data['type']);
		$this->save($result);

		// add trips to queue to pull more data
		if ($data['type'] == 'stop')
		{
			$this->queue_trips($result);
		}
		
		$job->delete();
		//$job->release(1);

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
			if ($redis->sismember($date, $member)) continue;

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
				$pipe->sadd($date, $member);
			}

		});
	}

	/**
	 * prepare data
	 */
	private function prepare($result, $type)
	{
		foreach ($result as &$s) {
		
			// make sure to use integers
			$s['tid'] = (integer)$s['tid'];
			$s['sid'] = (integer)$s['sid'];
			$s['date'] = (integer)$s['date'];

			// unset departure & arrival times for resp. last & first
			if ($type == 'trip')
			{
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
			elseif ($type == 'stop')
			{
				unset($s['arrival_time']);
				unset($s['arrival_delay']);
				unset($s['departure_delay']);
				unset($s['departure_time']);
			}
		}

		return $result;
	}

	/**
	 * save array of data as objects in db
	 */
	private function save ($result)
	{

		foreach ($result as $s) {

			// count
			$count = ServiceStop::where('tid',  $s['tid'])
			                    ->where('sid',  $s['sid'])
			                    ->where('date', $s['date'])
			                    ->count();

			if ($count)
			{
				// merge
				$saved = ServiceStop::where('tid',  $s['tid'])
			                        ->where('sid',  $s['sid'])
			                        ->where('date', $s['date']);
			                        //->get();

			    $saved->update($s);
			}
			else
			{
				// insert
				ServiceStop::insert($s);

			}
		}

	}

}