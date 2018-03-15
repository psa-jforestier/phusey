<?php
/**
 * Point your webbrowser to this file to serve directly your report to your browser
 */
passthru("php app/console report scenario/SampleTest.php  --precision 3 --resolution 10 --quiet --output -");
