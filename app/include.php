<?php
$here = dirname(__FILE__);
include_once($here.'/console.php');
include_once($here.'/scenario.php');
include_once($here.'/step.php');
include_once($here.'/workload.php');
include_once($here.'/vbrowser.php');
include_once($here.'/curl.php');

$CONFIG['default_scenario'] = $here.'./res/default_scenario.php';

define('PHUSEY_RESPONSE_BODY', 'body');
define('PHUSEY_RESPONSE_HEADER', 'header');
define('PHUSEY_RESPONSE_BODY_AND_HEADER', 'bodyheader');

