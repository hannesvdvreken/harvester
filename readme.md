# Data harvester

Scraping is a slow task, usually run by multiple machines. These commands add messages to a queue, with specific data on what to scrape. Workers then listen to the queue and start scraping. Just add more workers to get the job done more quickly.

Just add configuration for a Message Queueing service, for example [ironMQ](http://www.iron.io/mq). Trigger artisan commands using cron to fill the queue. Other artisan commands like `queue:worker` or `queue:listen` can then read messages and perform long running scraping tasks. These listening/long polling processes should be supervised by eg: supervisor.

## Usage

add cron job for daily run to `/etc/crontab`:

```
#m  h  dom mon dow   user    command
 *  3    *   *   *   ubuntu  cd /path/to/application && php artisan harvest:daily
```

also add cron job for consecutive runs:

```
#m  h  dom mon dow   user    command
*/5 *    *   *   *   ubuntu  cd /path/to/application && php artisan harvest:delays
```

If needed, one can run a manual command to pull specific data

```bash
php artisan harvest:manual type id date
```

The last 3 parameters are required. Type can be one of "trip" or "stop". The ID is numeric, and date is formatted `Ymd`.

## Workers

Workers are started with

```
php artisan queue:listen
```

Optionally, add `--timeout=`
