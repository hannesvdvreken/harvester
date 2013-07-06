## Data harvester

Using custom artisan commands, you can send messages to a Message Queue, for example ironMQ. Other artisan commands like `queue:worker` or `queue:listen` can then start reading messages and perform long running scraping tasks.

These listening/long polling processes should be run with `--timeout=X`, with `X` larger than 60 (default) and supervised by eg: supervisor.