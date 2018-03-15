<?php
/**
 ** This is the default scenario for PHP-PALF.
 ** Any scenario must define two functions : Phusey_Scenario and Phusey_Workload
 **/

/**
 ** return a prepared Scenario containing the steps
 **/
function Phusey_Scenario()
{
	// Create a scenario containing navigation steps
	$scenario = new Scenario("Sample scenario");
	$scenario->addCustomErrorTrigger(
		new CustomErrorPregMatch("/.*Oops.*/", PHUSEY_RESPONSE_BODY)
	);
	// Start a transaction. All steps time inside a transaction are cumulated
	// If your scenario do not contains any transaction, steps are measured individually
	$hostname = "http://localhost";
	$scenario->addStep(new StepTransactionStart('System warmup'));
	$scenario->addStep(new StepHttpGet("$hostname/index.html"));
	$scenario->addStep(new StepHttpGet("$hostname/index.php"));
	$scenario->addStep(new StepSleep(0.250)); // Pause for 1/4s
	$scenario->addStep(new StepTransactionStart('Call app'));
	$scenario->addStep(new StepHttpGet("$hostname/app.php"));
	$scenario->addStep(new StepTransactionStop()); // No more aggregation measurment in transaction
	$scenario->addStep(new StepHttpGet("$hostname/slow.php"));
	$scenario->addStep(new StepHttpGet("$hostname/slow.php?from=0.1&to=1"));
	$scenario->addStep(new StepHttpPost("$hostname/post.php",
		array("key"=>"value")
		));
	return $scenario;
}



/**
 ** return a description of the workload 
 **/
function Phusey_Workload()
{
	$workload = new Workload($scenario);
	$workload->startSingleBrowser(60); // Loop the scenario during 60s
	$workload->startParallelBrowsers(10, 60); // Loop the scenario in 10 browsers during 60s
	$workload->startRampingBrowsers(10, 20, 2, 60); // Start from 10 to 20 browsers (2 by 2) for a total duration of 60s (each level will have a duration of 60 / ((20 - 10) / 2) = 12s)
	return $workload;
}

// Optionnal functions
/**
function Phusey_BeforeTest()
// Called just before the test begins
{
}

function Phusey_AfterTest()
// Called just after the test stops
{
}

function Phusey_BeforeBrowser()
// Called before each browser start (before the st step)
{
}

function Phusey_AfterBrowser()
// Called after each browser stop (after the last step)
{
}
**/



