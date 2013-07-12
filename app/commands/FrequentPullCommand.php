<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class FrequentPullCommand extends Command {

	private $number;

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'harvest:delays';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Run this to get all current delays.';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$previous = microtime(true);

		$hash = $this->get_trips();

		// loop
		foreach ($hash as $tid => $value) {

			// min
			$min = $value[1];

			// max
			$max = $value[max(array_keys($value))];

			// string representation
			$str = isset($max['arrival_delay']) ? "- {$max['arrival_delay']} seconds" : 'now';

			// times
			$min_time = date('c');
			$max_time = date('c', strtotime($str));

			// potentially cancelled
			if (isset($min['cancelled']) && isset($max['cancelled']))
			{
				// do nothing
			}
			elseif (isset($min['cancelled']))
			{
				// only beginning part of trip is cancelled
				if ($max['arrival_time'] >= $max_time)
				{
					$this->add($tid, $min['date']);
				}
			}
			elseif (isset($max['cancelled']))
			{
				// only end part of trip is cancelled

				// get latest stop where not cancelled
				$max = $min;

				// loop
				foreach ($value as $seq => &$s) {
					// find latest
					if (isset($s['departure_time']) &&
						isset($s['arrival_time'  ]) &&
						$s['departure_time'] >= $max['departure_time'])
					{
						$max = $s;
					}
				}

				// see if latest is after now
				$str = isset($max['arrival_delay']) ? "- {$max['arrival_delay']} seconds" : 'now';
				$max_time = date('c', strtotime($str));

				// between first and last stop
				if ($min['departure_time'] <= $min_time &&
			        $max['arrival_time'  ] >= $max_time)
				{
					// trip is active
					$this->add($tid, $min['date']);	
				}
			}
			elseif (isset($min['departure_time']) && 
			        isset($max['arrival_time'  ]) &&
				    $min['departure_time'] <= $min_time &&
			        $max['arrival_time'  ] >= $max_time)
			{
				// trip is active
				$this->add($tid, $min['date']);
			}
		}

		// save to redis
		// get connection
		$redis = Redis::connection();

		// save number of added jobs
		$redis->hset('stats:'. date('Ymd', strtotime('- 3 hours')), date('c'), $this->number);
		
		// debug info
		echo "Looped all trips, found {$this->number} active.\n";
		echo "In ". (microtime(true) - $previous) ." seconds.\n";
	}

	/**
	 *
	 */
	private function add($tid, $date) 
	{
		// counter
		$this->number++;

		// message
		$data = array(
			'type' => 'trip',
			'id' => $tid,
			'date' => $date,
		);

		// add message to queue
		Queue::push("Scraper", $data);
	}

	/**
	 * Get all trips.
	 *
	 * @return array
	 */
	private function get_trips() {

		// variables
		$cache_key  = 'trips';
		$cache_time = 10; // minutes
		$date_delay = 3; // hours

		// try cache
		if (Cache::has($cache_key))
		{
			return Cache::get($cache_key);
		}

		// cache fail

		// get all trip id's
		$result = ServiceStop::where('date', (integer)date('Ymd', strtotime("- $date_delay hours")))
		                     ->distinct('tid')
		                     ->get()->toArray();
		
		// build assoc
		$hash = array();

		// loop
		foreach ($result as $s) 
		{
			$hash[$s[0]] = array();
		}

		// get all trips
		$fields = array('tid', 'date', 'sequence', 
			            'arrival_time', 'arrival_delay', 
			            'departure_time', 'cancelled');

		$result = ServiceStop::where('date', (integer)date('Ymd', strtotime("- $date_delay hours")))
		                     ->get($fields)->toArray();
		
		// build hash
		foreach ($result as $s)
		{
			// safety
			if (!isset($s['sequence'])) continue;

			// indexed
			$hash[$s['tid']][$s['sequence']] = $s;
		}

		// cache it
		Cache::put($cache_key, $hash, $cache_time);
		
		// and return
		return $hash;

	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{

		return array();

		/*
		return array(
			array('example', InputArgument::REQUIRED, 'An example argument.'),
		);*/
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{

		return array();

		/*
		return array(
			array('example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null),
		);*/
	}

}