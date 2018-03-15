# PHUSEY
A PHP performance and load test framework.

## Overview
PHUSEY is an open-source PHP framework to test performance of a web application.

It can be use to test your web application (a full website, or an API) even if your application is not written in PHP. In fact, PHP-PALF is completely independent of your application technologies.

PHUSEY need very little PHP knowledge to start. You only write your test scenario and your workload by calling some PHUSEY functions and classes.

PHUSEY will execute your scenario under the workload you defined, collect some metrics, and generate a report. You will find response time, error status, ect... 

### Prerequesites

To work with PHUSEY, you need to have a powerfull client server dedicated to play the scenario. This server will try to overload your application, depending of the workload you defined.

You need to have PHP7 and it *must* be compiled with *Zend thread safe* support and the *pthread* PHP extension. To analyze data, you must also have the *SQLite* PHP extension.

The client server need to have HTTP access to the application you want to test. It can be hosted behind a firewall.

PHUSEY has been tested with a client server running Microsoft Windows or Linux, but any PHP server can be used.

### How it works

The workflow of a PHUSEY load test is :
- define a scenario : which HTTP endpoint would you like to call. Add some pause, some settings (timeout ect).
- define a workload : how many virtual browser will play for ever your scenario in parallel.
- the scenario and the workload *are defined inside a single PHP source code file*, extending the FUSEY framework. You have to create and manage this file with you favorite IDE.
- run the workload, from the client server (the one with PHP7-ZTS-PTHREAD installed on it)
- collect data from the client server (response time ect) and agregate them into a SQLite file
- an HTML report is generated based on metric collected during the test

Also, some system metrics are collected during test, allowing you to correlate CPU usage or some Amazon Webservice (AWS) metrics.

PHUSEY do not provide a tool to record actions, or a GUI to play, develop or schedule.

Also, PHUSEY do not interprete response stream: HTML and Javascript are not executed.

## How to start

Download or clone the repository on your client server. Go to the `scenario` folder and open the file `SampleTest.php`. It is a self explained PHP source code defining a test scenario and a workload. Just create a new file based on this sample to start a new test for your own application.

## Basic usage

All commands to PHUSEY are using a single SH or BATCH script `phusey.sh` or `phusey.bat`. This script allow you to start the test, collect results, and generate report.

`$> ./phusey.sh <action> test [option]`

```
<action> :
  if no action is indicated, will execute "run", "collate" and "report" on a test
  ru,run     : run the test
  co,collate : collate statistics from a previous run of a test
  re,report  : create a report from statistics from a previous run of a test
  ruco,run-collate    : run and collate
  core,collate-report : collate and create report

test :
  A PHP file extending the PhuseyTest class, describing the scenario of the test.

[options] :
  --quiet : print only error or report if in stdout
  When <action> is "run" :
    -nw, --no-wait : run test immediately, do not wait a round time.
  When <action> is "collate" :
    -c, --collate-file <file.sqlite> : use this file to write collated results
  When <action> is "report" :
    -O, --output <file> : report file (HTML format).
      Default is to save report on the same directory as test, named with testName+testVersion.html.
          testName, testVersion are defined on the PHP test you wrote.
      If <file> is an existing directory, save report into this dir.
      Use "-" to output to stdout.
    -c, --collate-file <file.sqlite> : use this file to read collated date
    -s-httpsteps : scale max of http steps
    -s-hit       : scale max of hits/s
    -s-httpresults : scale max of http result (nb of 200, 3xx ...)
    -s-resptime  : scale max of response time
        -p, --precision : float precision (number of decimal, default 3)
        -r, --resolution : time resolution (default 10s)
```

### Start a test, collect metrics, and generate report

`$> ./phusey.sh path/to/test.php`

This command will run the scenario and workload defined in `path/to/test.php`, will collect statistcs and metrics, and generate an HTML report in same folder as the PHP scenario.

## Advanced usage

### Run only the scenario under the workload

`$> ./phusey.sh run [--no-wait] ./path/to/test.php`

Will run the scenario under the workload you defined, located in `./path/to/test.php`.

A lot of temporary files will be generated, in `./tmp/results/<id>/` folder. You will find here JSON, CSV or PHP serialized intermediate work files. Keep them if you want to collate result and generate a report later.

`--no-wait` : use this option to start the test as soon as possible. By default, PHUSEY will wait a few second before starting the scenario to allow the client server OS to calm down and to rest before beeing heavy loaed by the test.

### Collate statistics from a previous run of a test

`$> ./phusey.sh collate [--collate-file ./data.sqlite] ./path/to/test.php`

Will collate statistics from a previous runned scenario and workload. Stats will be agregated in a single SQLite file (it can be huge if your test is complex).

`--collate-file ./data.sqlite` : change default location of the resulting SQLite file. By default, it is located on `./tmp/results/<id>/data.sqlite`

### Create a report from statistics from a previous run of a test

`$> ./phusey.sh report [--output file.html] [--collate-file ./data.sqlite] [other options] ./path/to/test.php`

Generate an HTML report containing default graphes, based on an analysis of the SQLite database generated by the data collation.

`--output file.html` : HTML report filename. Use "-" as the filename to report to STDOUT. By default, it is saved on the scenario folder.

`--collate-file ./data.sqlite` : data file used to generate the report. This file should be generated by the `collate` command.

#### Other options

Scaling report :

`-s-httpsteps value` : maximum value of HTTP steps. Used in graphs to fix the Y scale (by default, it is auto-scaled). By adjusting this value, you can compare different report if auto-scale are too different.

`-s-hit value` : maxium value of hit/s Y scale.

`-s-httpresult value` : maxium value of HTTP result Y scale.

`-s-resptime value` : maxium value of responstime Y scale.

Data accuracy

`--precision N` : float precision of graphed value. Number of decimal. Default is 3. Higher precision will result in a larger report filesize.

`--resolution N` : time resolution. Default is 10. Number of second to compute average value. Higher precision will result in a larger report filesize.

## Write your own PHUSEY scenario and workload

Define your test scenario and workload in a PHP file. Here is a basic and minimal scenario and workload file :
```

```

## Security concerns

- Test only the application you have hands on. Do not try to test Google.com or other website, you  have the risk to be blacklisted.
- Do not make your injector plubicly available : a malicious user can start a test and you will not be warned of this. Also do not make the reports publics do prevent you from data disclosure.

## TODO

Implement functionnal error : add possibility to test if JSON or XML are well formatted

Logging : log response when an error occured


Collector : AWS collect memory usage and IO of host (Elastic Beanstalk and EC2, via CloudWatch).

SLA : add SLA management (considere a step in error if time is higher than a threshold)

## How to contribute

Branch "master" is the main develop branch.
Stable version will be always tagged.

## install pthreads on windows
Use PHP7.x "ZTS (Zend Thread Safe)"
Download pthread libs here : http://windows.php.net/downloads/pecl/releases/pthreads/ (chose last version) . Use correct 32 vs 64 lib.
Copy pthreadVC2.dll in same dir as php.exe, php_pthreads.dll in ext/ dir of PHP.
Modifiy php.ini and add extension=php_pthreads.dll