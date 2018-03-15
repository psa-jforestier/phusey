<?php
$samplecount = 10;
$now = microtime(true);
$tmpfile = 'loadtester.tmp';
if (!file_exists($tmpfile))
{
    $values = array($now);
}
else
{
    $values = unserialize(file_get_contents($tmpfile));
    $values[] = $now;
    if (count($values) > $samplecount)
    {
        array_shift($values);
    }
}
file_put_contents($tmpfile, serialize($values));
// Speed computation
$min = $values[0];
$max = $values[count($values) - 1];
$elapsed = $max - $min;
if ($elapsed == 0)
    $rate = '?';
else
    $rate = round(1/($elapsed / count($values)), 3);

?>
<html>
This page will generate an error under heavy load.
<br/>
<b>Current hit rate : <?=$rate?> / s<br>
<pre>
<b>
Hit rate     | effect</b>
0/s to 2/s   | OK
3/s to 9/s   | slow down
9/s to 13/s  | slow down or random fast failure
13/s to ...  | random failure
</pre>
<?php
if ($rate >= 1 && $rate < 2)
{

    $t = rand(1000 * 0.100, 1000 * 1.000) / 1000;
    echo "<br>Wait $t s";
    usleep(1000 * 1000 * $t);
}
else if ($rate >= 2 && $rate < 3)
{
    if (rand(1,2) == 1)
    {
        http_response_code(500);
        echo "<h1>500</h1>";
    }
    $from = 100;
    $to = 1000;
    $t = rand(1000 * 0.100, 1000 * 1.000) / 1000;
    echo "<br>Wait $t s";
    usleep(1000 * 1000 * $t);
}
else if ($rate >= 3)
{
    http_response_code(500);
    echo "<h1>500</h1>";
}


?>
</html>