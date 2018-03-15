<?php
$tmpfile = 'loadtester.tmp';
while(true)
{
    $values = unserialize(file_get_contents($tmpfile));
    $min = $values[0];
    $max = $values[count($values) - 1];
    $elapsed = $max - $min;
    if ($elapsed == 0)
        $rate = '?';
    else
        $rate = round(1/($elapsed / count($values)), 3);
    printf("Hits/s : %3.3f   \n", $rate);
    sleep(1);
}