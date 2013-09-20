<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Guzzle\Http\Client;

class ExportGTFSCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'export:gtfs';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Periodically run this to get GTFS schedule.';

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

	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{

		return array();
		
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