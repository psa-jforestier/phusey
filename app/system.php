<?php
/**
 ** System supervisor class : monitor CPU% and MEM%
 **/

if (!function_exists('sys_getloadavg'))
{
	if (stristr(PHP_OS, 'win'))
	{
		function sys_getloadavg()
		{
			$cmd = 'wmic cpu get loadpercentage';
			exec($cmd, $output, $ret);
			$load = 0.0 + $output[1];
			return array(0=>$load, 1=>$load, 2=>$load);
		}
	}
	
}
	// wmic cpu get loadpercentage
 
class SystemLocalMonitoringTask extends Threaded
{
	var $stop = false;
	var $frequency = 10; // Nb of second;
	var $file;
	public function __construct($file, $frequency)
	{
		$this->file = $file;
		$this->frequency = $frequency;
		@unlink($this->file);
	}
	
	public function work()
	{
		printf("Monitoring of local system started :\n\tfile : %s\n\tfrequency : every %s seconds\n", $this->file, $this->frequency);
		while($this->stop === false)
		{
			$now = time();
			$waitto = $this->frequency + ($this->frequency * (int)($now/$this->frequency));
			$load = sys_getloadavg();
            $fd = fopen($this->file, 'a');
            if ($fd !== false)
            {
			    fputs($fd, "$now;".date('c', $now).";${load[0]};${load[1]};${load[2]}\r\n");
                fclose($fd);
            }
			sleep(1); // Need to sleep at least 1s, because on fast Linux, $waitto can result in too short sleep time
			@time_sleep_until($waitto);
		}
		printf("Monitoring of local system stoped\n");
	}
}

class SystemLocalMonitoringThreaded extends Thread 
{
	private $task;
	public function __construct(Threaded $task)
	{
		$this->task = $task;
	}

	public function run()
	{
		$this->task->work();
	}
	
	public function callStop()
	{
		$this->task->stop = true;
	}
}

/**
$t = new SystemLocalMonitoringThreaded(
	new SystemLocalMonitoringTask("./load.csv", 10)
);

$t->start();
sleep(20);
echo "End of sleep\n";
$t->callStop();
$t->join();
echo "Thread stopped";
sleep(10);
**/
