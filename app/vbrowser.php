<?php
include_once(dirname(__FILE__).'/lock.php');
class VBrowserHelper
{
	var $generatedId = 0;
	private static $instance = null;
	private function __construct()
	{
	}
	public static function getInstance()
	{
		if (self::$instance === null)
			self::$instance = new VBrowserHelper();
		return self::$instance;
	}
	public function getNewId()
	{
		$lock = Lock::getInstance()->lock(__FILE__.':'.__LINE__);
		$this->generatedId ++;
		Lock::getInstance()->unlock($lock);
		return $this->generatedId;
	}
}




class VBrowser
{
	var $scenario;
	var $browserId;
	var $settings = array();
	var $curl;
	public function __construct(Scenario $scenario)
	{
		$this->scenario = $scenario;
		$this->browserId = VBrowserHelper::getInstance()->getNewId();
	}
	public function setSettings(array $settings)
	{
		$this->settings = $settings;
	}
	private function configureCurl()
	{
		if ($this->curl === NULL)
			throw new Exception("Cant configure curl because curl object is not initialized");
		foreach($this->settings as $s=>$v)
		{
			switch($s)
			{
				case 'PROXYHOSTPORT' : 
					$this->curl->setProxyHostPort($v);
					break;
				case 'PROXYUSERPASS' : 
					$this->curl->setProxyUserPass($v);
					break;
				case 'PROXY' : 
					if($v === 'AUTO')
						$this->curl->setAutoProxy();
					else
						throw new Exception("Settings PROXY do not support value \"$v\".");
					break;
				case 'NOPROXYFOR' :
					$this->curl->noProxyFor($v);
			}
		}
	}
	public function execute()
	{
		echo "Browser ",$this->browserId ,"\n";
		$currenttransaction = '';
		$this->curl = new CURL();
		$this->configureCurl(); // Add settings in curl
		foreach($this->scenario->steps as $step)
		{
			if ($step instanceof StepTransactionStart)
				$currenttransaction = $step->transactionname;
				// No need to execute something when it is a transaction start
			else
				$this->executeOneStep($step);
		}
	}
	
	private function executeOneStep(Step $step)
	{
		if ($step instanceof StepHttp)
		{
			$step->execute($this->curl);
		}
		else
			$step->execute();
	}
}