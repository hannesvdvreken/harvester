<?php

/*
|--------------------------------------------------------------------------
| Register The Artisan Commands
|--------------------------------------------------------------------------
|
| Each available Artisan command must be registered with the console so
| that it is available to be called. We'll register every command so
| the console gets access to each of the command object instances.
|
*/

Artisan::add(new DailyCommand);
Artisan::add(new FrequentPullCommand);
Artisan::add(new ManualQueueCommand);
Artisan::add(new ManualScrapeCommand);
Artisan::add(new ExportGTFSCommand);
Artisan::add(new ExportCSVCommand);