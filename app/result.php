<?php
class ResultSystemMonitoringAgregate
{
	var $csvfile;
	var $db;
	public function __construct($sysfile)
	{
		$this->csvfile = $sysfile;
	}
	public function agregate($sqlitefile)
	{
		printf("Collecting local system monitoring from %s\n", $this->csvfile);		
		$this->db = new SQLite3($sqlitefile);
		$q = '
		drop table if exists localsysmon;
		CREATE TABLE "localsysmon" 
			("id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL UNIQUE, 
			"timestamp" INTEGER, "starttime" DATETIME, load1 INTEGER, load5 INTEGER, load15 INTEGER)
		';
		$this->db->exec($q);
		$this->db->exec('BEGIN TRANSACTION');
		$nbquery = 0;
		
		if (($handle = @fopen($this->csvfile, "r")) !== FALSE)
		{
			while (($data = fgetcsv($handle, 1000, ";")) !== FALSE)
			{
				$num = count($data);
				if ($num === 5)
				{
					$stmt = $this->db->prepare('
					INSERT OR IGNORE into localsysmon
						(timestamp, starttime, load1, load5, load15)
					values 
						(:timestamp, :starttime, :load1, :load5, :load15)
					');
					$stmt->bindValue(':timestamp', $data[0], SQLITE3_INTEGER);
					$stmt->bindValue(':starttime', $data[1], SQLITE3_TEXT);
					$stmt->bindValue(':load1', $data[2], SQLITE3_INTEGER);
					$stmt->bindValue(':load5', $data[3], SQLITE3_INTEGER);
					$stmt->bindValue(':load15', $data[4], SQLITE3_INTEGER);
					$stmt->execute();
					$nbquery++;
					if ($nbquery % 100 == 0)
					{
						$this->db->exec('END TRANSACTION');
						$this->db->exec('BEGIN TRANSACTION');
					}
				}
			}
		}
		$this->db->exec('END TRANSACTION');
		$this->db->close();
		printf("%d local system monitoring data saved\n", $nbquery);
	}
	
}

class ResultAgregate
{
	var $resultdir;
	var $files = array();

	var $visitedSteps = array();
	var $curlErrors = array();
	var $db;
	/**
	 ** $resultdir is a directory or a regex (glob matching) where are the .ser/json file
	 **/
	public function __construct($resultdir)
	{
		$a = glob($resultdir);
		if (count($a) === 0)
		{ // Maybe dir is not a glob pattern but a real dir
			$resultdir.='/*';
			$a = glob($resultdir);
		}
		$this->resultdir = $resultdir;
		$this->files = $a;
	}
	
	/** 
	** Database
	** Table : step contains stepid, url, method
	**/
	public function agregate($sqlitefile)
	{
		printf("Collecting data from %d temporary files to SQLite %s\n", count($this->files), $sqlitefile);
		$this->db = new SQLite3($sqlitefile);
		
		$q = '
		drop table if exists results;
		CREATE  TABLE "results" 
			("id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL UNIQUE, "browserid" INTEGER, "stepid" INTEGER, "loadid" INTEGER, 
			"starttime" DATETIME, "sincestart" REAL, "beforecall" REAL, "aftercall" REAL, "http_code" INTEGER, "curl_errno" INTEGER, "ttfb" REAL, "connect_time" REAL, "pretransfer_time" REAL, "total_time" REAL, "size" INTEGER);
		CREATE INDEX idx_stepid ON results (stepid);
		CREATE INDEX idx_loadid ON results (loadid);
		CREATE INDEX idx_browserid ON results (browserid);
		PRAGMA synchronous = OFF;
		PRAGMA journal_mode = MEMORY;
		';
		$this->db->exec($q);
		$this->db->exec('BEGIN TRANSACTION;');
		$nbquery = 0;
		foreach($this->files as $i=>$file)
		{
			printf("%3d %% | Saving results...\r",
				round(100 * (($i+1) / count($this->files)))
			);
			$data = unserialize(file_get_contents($file));
			foreach($data as $result)
			{
				//See if it is a new url
				if (!isset($this->visitedSteps[$result->stepid]))
					$this->visitedSteps[$result->stepid] = array($result->http_method, $result->url);
				//See if it is a new curl error
				if ($result->errno !== null && !isset($this->curlErrors[$result->errno]))
					$this->curlErrors[0 + $result->errno] = $result->errstr;
				//Save results
				
				$stmt = $this->db->prepare('
					INSERT OR IGNORE into results
						(browserid, stepid, loadid, starttime, beforecall, aftercall, http_code, curl_errno, ttfb, connect_time, pretransfer_time, total_time, size) 
					values 
						(:browserid, :stepid, :loadid, :starttime, :beforecall, :aftercall, :http_code, :curl_errno, :ttfb, :connect_time, :pretransfer_time, :total_time, :size)
				');
				$stmt->bindValue(':browserid', $result->threadnumber, SQLITE3_INTEGER); // TODO
				$stmt->bindValue(':stepid', $result->stepid, SQLITE3_INTEGER);
				$stmt->bindValue(':loadid', $result->workloadid, SQLITE3_INTEGER);
				$stmt->bindValue(':starttime', date('c', $result->starttime), SQLITE3_TEXT);
				$stmt->bindValue(':beforecall', $result->beforecall, SQLITE3_FLOAT);
				$stmt->bindValue(':aftercall', $result->aftercall, SQLITE3_FLOAT);
				$stmt->bindValue(':http_code', $result->http_code, SQLITE3_INTEGER);
				$stmt->bindValue(':curl_errno', $result->errno, SQLITE3_INTEGER);
				$stmt->bindValue(':ttfb', $result->info['starttransfer_time'], SQLITE3_FLOAT); // unit : seconds
				$stmt->bindValue(':connect_time', $result->info['connect_time'], SQLITE3_FLOAT); // unit : seconds
				$stmt->bindValue(':pretransfer_time', $result->info['pretransfer_time'], SQLITE3_FLOAT); // unit : seconds
				$stmt->bindValue(':total_time', $result->info['total_time'], SQLITE3_FLOAT); // unit : seconds
				$stmt->bindValue(':size', $result->info['size_download'], SQLITE3_INTEGER);
				$stmt->execute();
				$nbquery++;
				if ($nbquery % 100 == 0)
				{
					$this->db->exec('END TRANSACTION;');
					$this->db->exec('BEGIN TRANSACTION;');
				}
			}
		}
		echo "\n";
		$this->db->exec('END TRANSACTION');
		$res1 = $this->db->querySingle("select min(beforecall) as started from results", true);
		$started = 0 + $res1['started'];
		$stmt = $this->db->prepare('update results set sincestart=(beforecall - :started)');
		$stmt->bindValue(':started', $started, SQLITE3_FLOAT);
		$stmt->execute();
		$this->saveVisitedSteps();
		$this->saveCurlErrors();
		$this->db->close();
		return count($this->files);
	}
	
	private function saveCurlErrors()
	{
		$q = '
			drop table if exists curlerrors;
			create table "curlerrors" (
				"errno" INTEGER PRIMARY KEY NOT NULL UNIQUE, 
				"errstr" VARCHAR
			);
			PRAGMA synchronous = OFF;
			PRAGMA journal_mode = MEMORY;
		';
		$this->db->exec($q);
		$this->db->exec('BEGIN TRANSACTION');
		foreach($this->curlErrors as $errno=>$errstr)
		{
			$stmt = $this->db->prepare("INSERT OR IGNORE into curlerrors(errno, errstr) values (:errno, :errstr)");
			$stmt->bindValue(':errno', $errno, SQLITE3_INTEGER);
			$stmt->bindValue(':errstr', $errstr, SQLITE3_TEXT);
			$stmt->execute();
		}
		$this->db->exec('END TRANSACTION');
		printf("%d Curl errors saved\n", count($this->curlErrors));
	}
	
	private function saveVisitedSteps()
	{
		$q = '
			drop table if exists visitedsteps;
			CREATE TABLE "visitedsteps" (
				"id" INTEGER PRIMARY KEY NOT NULL UNIQUE, 
				"transacid" INTEGER,
				"url" VARCHAR, 
				"method" CHAR);
			CREATE INDEX idx_transacid ON visitedsteps (transacid);
			PRAGMA synchronous = OFF;
			PRAGMA journal_mode = MEMORY;
			';
		$this->db->exec($q);
		$this->db->exec('BEGIN TRANSACTION');
		foreach($this->visitedSteps as $stepid=>$vs)
		{
			$stmt = $this->db->prepare("INSERT OR IGNORE into visitedsteps(id, url, method) values (:id, :url, :method)");
			$stmt->bindValue(':id', $stepid, SQLITE3_INTEGER);
			$stmt->bindValue(':url', $vs[1], SQLITE3_TEXT);
			$stmt->bindValue(':method', $vs[0], SQLITE3_TEXT);
			$stmt->execute();
		}
		$this->db->exec('END TRANSACTION');
		printf("%d visited steps saved\n", count($this->visitedSteps));
	}
}

class ResultSaveTransaction
{
	var $test;
	var $db;
	public function __construct(PhuseyTest $test)
	{
		$this->test = $test;
	}

	public function agregate($sqlitefile)
	{
		$this->db = new SQLite3($sqlitefile);
		// Browse steps and transaction
		$transactions = array();
		$transactionNames = array();
		$transactionId = 0;
		foreach($this->test->scenario->steps as $i=>$step)
		{
			$stepid = $i + 1;
			
			if ($step instanceof StepStartTransaction)
			{
				$transactionId = $stepid;
				$transactionNames[$transactionId] = $step->name;
			}
			else if ($step instanceof StepStopTransaction)
				$transactionId = 0;
			if ($transactionId !== 0 && $step instanceof StepHttp)
			{
				$transactions[$transactionId][$stepid] = $step;
			}
		}
		
		$q = '
			drop table if exists transactions;
			CREATE TABLE "transactions" (
				"id" INTEGER PRIMARY KEY NOT NULL UNIQUE, 
				"name" VARCHAR);
			PRAGMA synchronous = OFF;
			PRAGMA journal_mode = MEMORY;
			';
		$this->db->exec($q);
		$this->db->exec('BEGIN TRANSACTION');
		foreach($transactions as $trId=>$steps)
		{
			
			$trName = $transactionNames[$trId];
			$stmt = $this->db->prepare("INSERT OR IGNORE into transactions(id, name) values (:id, :name)");
			$stmt->bindValue(':id', $trId, SQLITE3_INTEGER);
			$stmt->bindValue(':name', $trName, SQLITE3_TEXT);
			$stmt->execute();
			foreach($steps as $stepId=>$step)
			{
				$stmt = $this->db->prepare("update visitedsteps set transacid=:trId where id=:stepId");
				$stmt->bindValue(':trId', $trId, SQLITE3_INTEGER);
				$stmt->bindValue(':stepId', $stepId, SQLITE3_INTEGER);
				$stmt->execute();
			}
		}
		$this->db->exec('END TRANSACTION');
		// Update VisitedSteps to add transaction info
		
	}
}
class ResultSaver
{
	var $results;
	public function __construct()
	{
	}
	
	public function setResults($r)
	{
		$this->results = $r; // Serialization may not work due to Volatile object
		//$this->results = (array)$r; // Be sure to remove Volatile object and replace it with an array
		/**
		$this->results = array();
		foreach($r as $result)
		{
			$result->info->certinfo = (array)$result->info->certinfo;
			$result->info = (array)$result->info;
			
			$this->results[] = (array)$result;
		}
		**/
	}
	
	public function saveToSerialize($filename)
	{
	
		$d = dirname($filename);
		@mkdir($d, 0777, true);
		file_put_contents($filename, serialize((array)$this->results));
	}
	
	public function saveToJson($filename)
	{
	
		$d = dirname($filename);
		@mkdir($d, 0777, true);
		file_put_contents($filename, json_encode($this->results, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT));
	}
}