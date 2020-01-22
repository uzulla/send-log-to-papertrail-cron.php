# Watch log by cron and send papertrail, php sample

Cronでログファイルをチェックして増分をPapertrailに送り込むサンプルコード

## setup 

```
# signup papertail
$ cp sample.env .env
# write settings
$ vi .env

```

## run

```
$ cron.php

or

$ LOG_FILE_PATH=hoge.log LOG_POINTER_FILE_PATH=hoge.log.point cron.php
```

## add cron

```
# cron -e
MAILTO=you@example.jp
*/5 * * * * /path/to/cron.php
```
