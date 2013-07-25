<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Guzzle\Http\Client;

class ExportCSVCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'export:csv';

	/**
	 * 
	 */
	protected $delimiter = ',';
	protected $storage_dir;

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Export to CSV to do AI learning.';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
		$this->storage_dir = storage_path() . '/ai';
	}


	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		// get mongodb connection
		$db = App::make('mongodb');
		$db = $db->connection()->getDb();

		// get redis connection
		$redis = Redis::connection();


		// prepare
		$this->prepare_folders();

		// get one full trip
		$trip = $this->get_one_trip($db, $this->argument('route'));
		$this->write_headers($trip);

		// loop trip
		foreach ($trip as $seq)
		{
			// get all nth stops
			$trips = $this->get_nth_stops($db, $this->argument('route'), $seq['sequence']);

			// loop
			foreach ($trips as $s)
			{
				// get static trip data
				if ($seq == reset($trip))
				{
					// allocate
					if ( ! isset($hashed[$s['date']]))
					{
						$hashed[$s['date']] = array();
					}
					if ( ! isset($hashed[$s['date']][$s['tid']]))
					{
						$hashed[$s['date']][$s['tid']] = array();
					}

					// set data
					$time = strtotime($s['date']);

					$csv = array();
					$csv[] = (integer)date('Y', $time);
					$csv[] = (integer)date('n', $time);
					$csv[] = (integer)date('N', $time);
					$csv[] = (integer)date('j', $time);
					$csv[] = (integer)date('z', $time);

					// allocate
					$data = array(
						'csv'    => $csv,
						'output' => array(),
						'valid'  => true,
						'num_occupants' => 0,
					);
				}
				else
				{
					$data = $hashed[$s['date']][$s['tid']];
				}


				// check if db input useable for AI
				if (($seq == reset($trip) && ! isset($s['departure_time'])) ||
					($seq ==   end($trip) && ! isset($s['arrival_time'  ])) ||
					( ! isset($s['arrival_time']) && ! isset($s['departure_time'])))
				{
					$data['valid'] = false;
					$hashed[$s['date']][$s['tid']] = $data;
					continue;
				}


				// add data
				// all exept first
				if ($seq != reset($trip))
				{
					$arrival_time = strtotime($s['arrival_time']);

					$data['csv'][] = (isset($s['arrive']) ? count($s['arrive']) : 0);
					$data['csv'][] = (integer)date('G', $arrival_time) * 60 + (integer)date('i', $arrival_time);

					$data['output'][] = ($s['arrival_delay'] > 0 ? 1 : 0);
				}
				// all execpt last
				if ($seq != end($trip))
				{
					$departure_time = strtotime($s['departure_time']);

					$data['csv'][] = (isset($s['depart']) ? count($s['depart']) : 0);
					$data['csv'][] = (integer)date('G', $departure_time) * 60 + (integer)date('i', $departure_time);

					$data['output'][] = ($s['departure_delay'] > 0 ? 1 : 0);
				}

				// save to memory
				$hashed[$s['date']][$s['tid']] = $data;
			}
		}

		// filter values and only valids
		$csv     = array();
		$csv_out = array();

		foreach ($hashed as $date => &$trips) 
		{
			foreach ($trips as $tid => &$data) 
			{
				if ($data['valid'])
				{
					$csv[]     = $data['csv'];
					$csv_out[] = $data['output'];
				}
			}
		}

		// output
		$this->write_csv($csv);
		$this->write_csv($csv_out, true);
	}


	/**
	 * @param MongoDB $db
	 * @param string  $route
	 * @param integer $sequence
	 */
	private function get_nth_stops($db, $route, $sequence)
	{
		// query
		$query = array(
			'route' => $this->argument('route'),
			'sequence' => $sequence,
		);

		// sort
		$sort = array(
			'date' => 1,
			'tid'  => 1,
		);

		// get
		$cursor = $db->trips->find($query)->sort($sort);
		return $trips = array_values(iterator_to_array($cursor));
	}


	/**
	 *
	 */
	private function get_one_trip($db, $route)
	{
		// aggregate
		$pipe = array(
			array('$match' => array('route' => $route)),
			array('$group' => array('_id' => array('tid' => '$tid', 'date' => '$date'))),
			array('$project' => array('tid' => '$_id.tid', 'date' => '$_id.date', '_id' => 0)),
			array('$limit' => 1),
		);

		// do query
		$result = $db->trips->aggregate($pipe);
		$result = head($result['result']);

		// get full trip
		$query = array(
			'date' => $result['date'],
			'tid' => $result['tid'],
			'route' => $this->argument('route'),
		);

		// sort
		$sort = array(
			'sequence' => 1,
		);
		
		// query full trip
		$cursor = $db->trips->find($query)->sort($sort);
		return $trip = array_values(iterator_to_array($cursor));
	}


	/**
	 *
	 */
	private function write_csv($data, $output = false)
	{
		// filename
		$fn = "$this->storage_dir/" . $this->argument('route') . ($output? '-out': '') . '.csv';
		File::delete($fn);

		foreach ($data as $row) 
		{
			File::append($fn, join($this->delimiter, $row) . "\n");
		}
	}


	/**
	 *
	 */
	private function prepare_folders()
	{
		// prepare directory structure
		if ( ! File::isDirectory("$this->storage_dir"))
		{
			File::makeDirectory("$this->storage_dir", 0775, true);	
		}
	}


	/**
	 * @param array trip_data
	 */
	private function write_headers($trip_data)
	{
		// init
		$input_meta = array();
		$output_meta = array();
		$route = $this->argument('route');

		// static
		$input_meta[] = 'jaar';
		$input_meta[] = 'maand van het jaar';
		$input_meta[] = 'dag van de week';
		$input_meta[] = 'dag van het maand';
		$input_meta[] = 'dag van het jaar';

		// variable
		foreach ($trip_data as $s)
		{
			// all exept first
			if ($s != reset($trip_data))
			{
				$input_meta[] = "afstappen in {$s['stop']}";
				$input_meta[] = "geplande aankomst in {$s['stop']} in minuten sinds middernacht";

				$output_meta[] = "vertraging bij aankomst in {$s['stop']}?";
			}
			// all execpt last
			if ($s != end($trip_data))
			{
				$input_meta[] = "opstappen in {$s['stop']}";
				$input_meta[] = "gepland vertrek in {$s['stop']} in minuten sinds middernacht";

				$output_meta[] = "vertraging bij vertrek in {$s['stop']}?";
			}
		}

		// counted
		$input_meta[] = 'aantal reizigers totaal';

		// mapping function
		$m = function($value)
		{
			return "\"$value\"";
		};

		// write file
		$output = join($this->delimiter, array_map($m, $output_meta));
		File::delete("$this->storage_dir/$route-output.meta.csv");
		File::put("$this->storage_dir/$route-output.meta.csv", $output);

		// write file
		$input = join($this->delimiter, array_map($m, $input_meta));
		File::delete("$this->storage_dir/$route-input.meta.csv");
		File::put("$this->storage_dir/$route-input.meta.csv", $input);
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			array('route', InputArgument::REQUIRED, 'The route\'s hash id'),
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array();
	}

}