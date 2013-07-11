<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Guzzle\Http\Client;

class DailyCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'harvest:daily';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Once a day, run this command to get all trips.';

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

		// init
		$redis = Redis::connection();

		// get all stops
		$base_url = 'http://api.hannesv.be/';
		$endpoint = 'nmbs/stations.json';

		// perform
		$client = new Client($base_url);
		$result = $client->get($endpoint)->send()->json();

		// static
		$type = 'stop';
		$date = $this->option('date');

		$pushed = array();

		// loop
		foreach( $result['stations'] as $station )
		{
			// debug
			//if ($station['stop'] != 'Londerzeel') continue;

			$member = "$type:{$station['sid']}";

			// added to set?
			if ($redis->sismember("pulled:$date", $member)) continue;

			// add to set later
			$pushed[] = $member;

			// message
			$data = array(
				'type' => 'stop',
				'id' => (integer)$station['sid'], 
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
		return array(
			array('date', null, InputOption::VALUE_OPTIONAL, 'Optional date.', date('Ymd')),
		);
	}

}