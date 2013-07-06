<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class DelayPullCommand extends Command {

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

		$result = ServiceStop::where('date', (integer)date('Ymd'))->distinct('tid')->get()->toArray();

		foreach ($result as $tid) {
			// array($tid)
			$tid = reset($tid);

			// get max & min
			$min = ServiceStop::where('tid', $tid)
			                  ->where('date', (integer)date('Ymd'))
			                  ->orderBy('sequence', 'asc')
			                  ->first()->toArray();
			$max = ServiceStop::where('tid', $tid)
			                  ->where('date', (integer)date('Ymd'))
			                  ->orderBy('sequence', 'desc')
			                  ->first()->toArray();

			// has no correct data yet
			if (!isset($min['departure_time']) ||
				!isset($max['arrival_time']))
			{
				// message
				$data = array(
					'type' => 'trip',
					'id' => $tid,
					'date' => $min['date'],
				);

				// add message to queue
				Queue::push("Scraper", $data);
			}

			// determine if between init and terminus
			elseif ($min['departure_time'] <= date('c') &&
				    $max['arrival_time'] >= date('c', strtotime("- {$max['arrival_delay']} seconds")))
			{
				// message
				$data = array(
					'type' => 'trip',
					'id' => $tid,
					'date' => $min['date'],
				);

				// add message to queue
				Queue::push("Scraper", $data);
			}
		}
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