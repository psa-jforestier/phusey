<?php

/**

https://stackoverflow.com/questions/49046406/count-and-agregate-data-by-time


 Nb de transaction / 10s
 select
count(id),
10 * cast (sincestart / 10 as int) as since
from
results R
group by since

Nb de VU
select
count(distinct(browserid)) as nb,
10 * cast (sincestart / 10 as int) as since
from
results R
group by since
order by since
 */
class Reporting
{
    var $file;
    var $test;
    var $db;
    var $starttime_ts;
    var $endtime_ts;
    var $duration;
    var $visitedsteps;
    var $visitedtransactions;
    static $httpcodeToStr = array( // https://fr.wikipedia.org/wiki/Liste_des_codes_HTTP
        0=>"Network",
        1=>"Custom",2=>"Custom",3=>"Custom",4=>'Custom',5=>'Custom',
        200=>"OK",
        301=>"Moved permanently", 302=>"Found", 303=>"See other",
        400=>"Bad request", 401=>"Unauthorized", 402=>"Payment Required", 403=>"Forbidden", 404=>"Not found",
        500=>"Internal Server Error", 502=>'Bad Gateway / Proxy error', 503=>'Service Unavailable', 504=>'Gateway Time-out'
    );

    static $httpcodeToColor = array( // See https://www.w3schools.com/colors/colors_names.asp
        0=>"crimson",
        1=>"darkred",2=>"maroon",3=>"darkred",4=>'maroon',5=>'darkred',
        200=>"limegreen", 201=>"green",
        300=>'orange', 301=>'orange', 302=>'orangered',
        400=>"darkred", 401=>"darkred", 402=>"darkred", 403=>"maroon", 404=>"red",
        500=>"deeppink", 502=>'fuschia', 503=>'hotpink', 504=>'lightpink'
    );

    var $scale_httpsteps, $scale_hit, $scale_httpresults, $scale_resptime = NULL;
    var $precision = 4;
    var $resolution = 10.0;

    var $aws;
    var $awsEC2 = false; // array or false
    var $awsEC2Metrics = false; // array or false
    /**
     * @$file : a SQLITE database file
     */
    public function __construct($file, PhuseyTest $test)
    {
        $this->file = $file;
        $this->test = $test;
        $this->aws = NULL;
    }

    public function setScales($scale_httpsteps, $scale_hit, $scale_httpresults, $scale_resptime)
    {
        $this->scale_httpsteps = $scale_httpsteps;
        $this->scale_hit = $scale_hit;
        $this->scale_httpresults = $scale_httpresults;
        $this->scale_resptime = $scale_resptime;
    }

    public function setDecimalPrecision($precision = 4)
    {
        if (!is_numeric($precision) || ($precision < 0 || $precision > 6 ))
        {
            throw new PhuseyException("Invalid precision : $precision is not a number or is not from 0 to 6");
        }
        $this->precision = (int)(0 + $precision);
    }

    public function setTimeResolution($resolution = 10.0)
    {
        if (!is_numeric($resolution) || $resolution < 0)
        {
            throw new PhuseyException("Invalid resolution : $resolution must be a positive round number");
        }
        // resolution is a float number in a string, used by SQL queries to make float computation
        $this->resolution = sprintf("%.1f", (0.0 + $resolution)); 
    }

    /**
     * AWS $aws
     * Array $awsEC2 : array of array of string "instanceID[,region,[profile]]"
     * Array $awsEC2Metrics : string "CloudwatchMetric1[,CloudwatchMetric2...]"
     */
    public function setAWSEC2(\AWS $aws, $awsEC2, $awsEC2Metrics)
    {
        $this->aws = $aws;
        $tmp = array();
        foreach($awsEC2 as $instance)
        {
            $a = explode(',', $instance);
            $tmp[] = array('id'=>$a[0], 'region'=>@$a[1], 'profile'=>@$a[2]);
        }
        $this->awsEC2 = $tmp;
        $tmp = array();
        $tmp = explode(',', $awsEC2Metrics);
        $this->awsEC2Metrics = $tmp;
    }
    
    /**
     * @$output : file
     */
    public function save()
    {

        $this->db = new SQLite3($this->file);
        
        $this->print_header(); 
        $this->print_scenario_and_workload_info();
        $this->print_test_info();
        /**
        $this->print_visited_steps();
        $this->print_response_time_by_steps();
        $this->print_response_time_by_transactions();
         */
        $this->print_response_time();
        /*
        $this->print_response_time_by_transactions_during_test();
        $this->print_virtual_browser();
        $this->print_http_code();
        $this->print_http_stability();
         */
        if ($this->awsEC2 !== false)
        {
            $this->print_aws();
            foreach($this->awsEC2Metrics as $metricname)
            {
                $this->print_aws_EC2($metricname);
            }
        }
    }

    private function httpcodeToColor($httpcode)
    {
        $c = @Reporting::$httpcodeToColor[$httpcode];
        if ($c === NULL) {
            return 'lightgray';
        }
        return $c;
    }

    private function httpcodeToStr($httpcode)
    {
       
        return @Reporting::$httpcodeToStr[$httpcode];
    }


    public function print_header()
    {
        ?>
<html>
	<head>
	<!--Load the AJAX API-->
	<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
	// Load the Visualization API and the piechart package.
	    google.charts.load('current', {packages: ['corechart', 'line', 'scatter', 'bar']});
        // Global data used by the charts
        var ORIGIN_DATA = new Date('Jan 01 2000').getTime(); // In microsecond
        var elapsedFormatter = null;
        function elapsedFormat(data)
        {
            if (elapsedFormatter == null)
            {
                elapsedFormatter = new google.visualization.DateFormat({ 
                    pattern: "H:mm:ss" 
                });
            }
            return elapsedFormatter.format(data, 0);
        }

    </script>
    <style>
        hr {
            clear:both;
        }
        pre {
            font-size:80%;
        }
        table.T1 td.right {
            text-align: right;
        }
        table.T1 td.smaller, th.smaller {
            font-size:80%;
        }
        .env {
            float:left;
            margin: 4px;
            border: 1px solid #444;
        }
        .info {
            font-size:80%;
        }
        </style>
    </head>
        <?php
    }

    public function print_scenario_and_workload_info()
    {
        echo "<div class='env'><b>Expected scenario</b><pre>";
        $this->test->printScenarioInfo();
        echo "</pre></div>";
        echo "<div class='env'><b>Expected workload</b><pre>";
        $this->test->printWorkloadInfo();
        echo "</pre></div><hr/>";
    }
    public function print_test_info()
    {
        $res1 = $this->db->querySingle("
            select min(R.starttime) as starttime, max(R.aftercall) - min(R.beforecall) as duration, count(*) as nb
            from 
            results R
        ", true);
        $this->starttime_ts = $starttime_ts = strtotime($res1['starttime']);
        $this->endtime_ts = $starttime_ts + $res1['duration'];
        $this->duration = $res1['duration'];
        
        ?>
    <script type="text/javascript">
        var END_DATA = ORIGIN_DATA + (1000 * <?=$this->duration?>); 
    </script>
        <h1>Test summary</h1>
        <table border="1">
        <tr>
        <td><b>Start time :</b></td><td><?= date('c', $this->starttime_ts)?></td>
        <td><b>Duration :</b></td><td><?= round($this->duration)?> seconds (<?= secondsToHMS($this->duration) ?>)</td>
        <td><b>Stop time :</b></td><td><?= date('c', $this->endtime_ts)?></td>
        </tr>
        </table>
        <div class="env">
        <table border="1" class="T1">
        <tr><th>Return code</th><th>Number</th><th class="smaller">%</th><th>hit/s</th></tr>
        <?php
        $res = $this->db->query("
            SELECT http_code, 
            count(*) as nb,
            1.0 * COUNT(*) / (SELECT COUNT(*) FROM results) AS percentage
            FROM results
            GROUP BY http_code
            HAVING 1.0 * COUNT(*) / (SELECT COUNT(*) FROM results)"); 
        $sum = 0;
        while ($row = $res->fetchArray()) 
        {
            echo "<tr>\n";
            echo "<td style='background-color:".$this->httpcodeToColor($row['http_code']) ."'>${row['http_code']} ", $this->httpcodeToStr($row['http_code']) ,"</td>\n";
            echo "<td class='right'>${row['nb']}</td>\n";
            echo "<td class='right smaller'>". round(100*$row['percentage'])."</td>\n";
            echo "<td class='right'>", sprintf("%.2f", ($row['nb'] / $this->duration)) ,"</td>\n";
            echo "</tr>\n";
            $sum += $row['nb'];
        }
        if ($this->duration == 0 )
            echo "<tr><th>Invalid duration</th></tr>";
        else
            echo "<tr><th>Sum</th><th align=right>$sum</th><th></th><th>", sprintf("%.2f", ($sum / $this->duration)),"</th></tr>";
        echo "</table>\n";
        ?>
        </div>
        <div class="env">
        <h2>Network errors</h2>
        As reported by Curl.
        <table border="1" class="T1"><tr><th>Error</th><th>Number</th><th>%</th>
        <?php
        $sum = 0;
        $res = $this->db->query("
            select
                curl_errno, count(*) as nb, E.errstr, 
                1.0 * count(*) /  (select count(*) from results where curl_errno is not null ) as percentage
            from
                results R, curlerrors E
            where curl_errno is not null
                and E.errno = curl_errno
            group by curl_errno");
        while ($row = $res->fetchArray()) 
        {
            echo "<tr>\n";
            echo "<td>${row['errstr']}</td>\n";
            echo "<td class='right'>${row['nb']}</td>\n";
            echo "<td class='right smaller'>". round(100*$row['percentage'])."</td>\n";
            echo "</tr>\n";
            $sum += $row['nb'];
        }
        echo "<tr><th>Sum</th><th align=right>$sum</th></tr>";
        echo "</table>\n";
        echo "</div><hr/>";

    }

    private function get_visited_steps()
    {
        if ($this->visitedsteps === NULL)
        {
            // get all transaction
            $q = "select stepid, count(stepid) as cpt, url, method, name, sum(failed) as failed
            from
            (
            select R.stepid, V.url, V.method, T.name, case when http_code between 200 and 399 then 0 else 1 end failed
                        from results R, visitedsteps V
                        left join transactions T on T.id = V.transacid
                        where V.id = R.stepid
            )
            group by stepid
            order by stepid
            ";
            $res = $this->db->query($q);
            $return = array();
            while ($row = $res->fetchArray()) 
            {
                $return[$row['stepid']] = $row;
            }
            $this->visitedsteps = $return;
        }
        return $this->visitedsteps;
         
    }

    private function get_visited_transactions()
    {
        if ($this->visitedtransactions === NULL)
        {
            // get all transaction
            $q = "select * from transactions";
            $res = $this->db->query($q);
            $return = array();
            while ($row = $res->fetchArray()) 
            {
                $return[$row['id']] = $row;
            }
            $this->visitedtransactions = $return;
        }
        return $this->visitedtransactions;
    }

    public function print_response_time_by_transactions_during_test()
    {
        $resolution = $this->resolution;
        $p = $this->precision;
        $responsetimeByTr = array();
        $visitedtransactions = $this->get_visited_transactions();
        foreach($visitedtransactions as $transacid=>$tr)
        {
            // Request response time for this tr
            
            $res = $this->db->query("
            select
                round(min(R.total_time), $p) as min_total_time, 
                round(max(R.total_time), $p) as max_total_time, 
                round(avg(R.total_time), $p) as avg_total_time,
                $resolution * cast (R.sincestart / $resolution as int) as since
            from
                results R, visitedsteps V
            left join transactions T on T.id = V.transacid
            where V.id = R.stepid
                and T.id=$transacid
            group by since
            ");
            while ($row = $res->fetchArray()) 
            {
                $responsetimeByTr[$transacid][] = $row;
            }
        }
        
        echo "<h1>Response time by transactions during the test</h1>\n";
        echo "<div class='info'>Min, avg and max time, in seconds, of transaction during the whole test. Times are computed by adding all min/avg/max time of HTTP steps of a transaction.</div>";
	if (count($visitedtransactions) == 0)
	{
		echo "<br><b>No transaction visited during the test.</b></br>";
		return;
	}
        ?>
        Time resolution is : <?=$resolution?> seconds.<br/>
        <div id="print_response_time_by_transactions_during_test_chart" style="width: 100%;min-height:450px;background-color:blue">Loading the graphics...</div>
        <script type="text/javascript">
google.charts.setOnLoadCallback(print_response_time_by_transactions_during_test);

function print_response_time_by_transactions_during_test() {
    var joinedData = null;
    var cols_to_merge = [];
    <?php
    foreach($responsetimeByTr as $transacid=>$rows)
    {
        $transacname = $visitedtransactions[$transacid]['name'];
        ?>
        /** <?=htmlentities($transacname)?>  **/
        var data_tr<?=$transacid?> = new google.visualization.DataTable();
        data_tr<?=$transacid?>.addColumn('datetime', 'X');
        data_tr<?=$transacid?>.addColumn('number', '<?=htmlentities($transacname)?>');
        data_tr<?=$transacid?>.addColumn({id:'min<?=$transacid?>', type:'number', role:'interval'});
        data_tr<?=$transacid?>.addColumn({id:'max<?=$transacid?>', type:'number', role:'interval'});
        data_tr<?=$transacid?>.addRows([
        <?php
        foreach($responsetimeByTr[$transacid] as $row)
        {
            ?>
            [ new Date(ORIGIN_DATA + 1000 * <?=$row['since']?> ), 
                <?= $row['avg_total_time'] ?>, <?= $row['min_total_time'] ?>, <?= $row['max_total_time'] ?>
            ],
            <?php
        }
        ?>
        ]); /* addrows tr<?=$transacid?>  */
        if (joinedData == null)
            joinedData = data_tr<?=$transacid?>;
        else
            joinedData = google.visualization.data.join(joinedData, data_tr<?=$transacid?>, 'full', [[0, 0]], cols_to_merge, [1,2,3]);
        cols_to_merge.push(cols_to_merge.length + 1);
        cols_to_merge.push(cols_to_merge.length + 1);
        cols_to_merge.push(cols_to_merge.length + 1);
        <?php
    }
    ?>
    var options = {
        legend: { position: 'top', alignment: 'start', textStyle: { fontSize: 10} },
        chartArea: {width: "90%", height: "70%", left:80},
        lineWidth:1,
        hAxis: {
            title: 'Elapsed time', format: 'mm:ss', minValue:new Date(ORIGIN_DATA), maxValue:new Date(END_DATA)
        },
        <?=ifNotNull("vAxis : { maxValue: %d},", $this->scale_resptime)?>
        intervals: { 'style':'area' },lineWidth: 1,
    };
    elapsedFormat(joinedData);
    var chart = new google.visualization.LineChart(document.getElementById('print_response_time_by_transactions_during_test_chart'));

    chart.draw(joinedData, options);
}
        </script>
        <?php
        
    }

    public function print_response_time_by_transactions()
    {
       
        $p = $this->precision;
        echo "<h1>Response time by transactions</h1>\n";
        echo "<div class='info'>Min, avg and max time, in seconds, of HTTP steps grouped by transaction. Times are computed by adding all min/avg/max time of HTTP steps of a transaction.</div>";
        $res = $this->db->query("select
            transacid, 
            round(sum(max_total_time), $p) as max_total_time, 
            round(sum(min_total_time), $p) as min_total_time, 
            round(sum(avg_total_time), $p) as avg_total_time, 
            name, 
            sum(nb) as nb
            from
            (
                select
                    max(R.total_time) as max_total_time,
                    min(R.total_time) as min_total_time,
                    avg(R.total_time) as avg_total_time,
                    count(R.id) as nb,
                    T.name as name,
                    R.stepid, 
                    V.transacid
                from
                    results R,visitedsteps V
                    left join transactions T on T.id = V.transacid
                where V.id = R.stepid
                group by R.stepid
            )
            group by transacid
            order by transacid");
        ?>
        <div id="print_response_time_by_transactions_chart" style="width: 100%;min-height:700px;background-color:blue">Loading the graphics...</div>
        <script type="text/javascript">
        google.charts.setOnLoadCallback(print_response_time_by_transactions);
        function print_response_time_by_transactions() {
            var data = new google.visualization.arrayToDataTable([
                ['Transaction', '\u2211 of HTTP steps', 'Min time', 'Average time', 'Max time'],
                <?php
                while ($row = $res->fetchArray()) 
                {
                    ?>
                    ['<?=($row['name'] == null ? 'No name' : htmlentities($row['name']))?>', <?=$row['nb']?>, <?=$row['min_total_time']?>, <?=$row['avg_total_time']?>, <?=$row['max_total_time']?>
                    ],
                    <?php
                }
                ?>
            ]);
            var view = new google.visualization.DataView(data);
            view.setColumns([0, 
                    1,
                    2,{ calc: "stringify",
                            sourceColumn: 2,
                            type: "string",
                            role: "annotation" },
                    3,{ calc: "stringify",
                            sourceColumn: 3,
                            type: "string",
                            role: "annotation" },
                    4,{ calc: "stringify",
                            sourceColumn: 4,
                            type: "string",
                            role: "annotation"}
                    ]);
            var options = {
                bars: 'horizontal',
                fontSize : 10,
                chartArea: {width: '70%'},
                legend: { position: 'top' },
                vAxis: {textPosition: 'out'},
                annotations: {
                    alwaysOutside: true,
                    textStyle: { fontSize: 8}
                },
                colors: ['lightblue', 'green', 'orange', "red"],
                
                isStacked: true,
                //seriesType: 'bars',
                //orientation: 'vertical',
                series: {
                    0: {targetAxisIndex: 1, type:'steppedArea'}, // Nb of http steps
                    1: {targetAxisIndex: 0}, // min
                    2: {targetAxisIndex: 0}, // avg
                    3: {targetAxisIndex: 0}  // max time
                },
                hAxes: {
                    <?=ifNotNull("0 : { maxValue: %d},", $this->scale_resptime)?>
                    <?=ifNotNull("1 : { maxValue: %d},", $this->scale_httpsteps)?>
                }
            };
            var chart = new google.charts.Bar(document.getElementById("print_response_time_by_transactions_chart"));
	        chart.draw(view, google.charts.Bar.convertOptions(options));
        }
        </script>
        <?php

    }

    public function print_response_time_by_steps()
    {
        $steps = $this->get_visited_steps();
        $p = $this->precision;
        echo "<h1>Response time by steps</h1>\n";
        echo "<div class='info'>Min, avg and max time, in seconds, of HTTP steps.</div>";
        $resp = array();
        foreach($steps as $step)
        {
            $q = "select
            round(min(total_time), $p) as min_total_time, 
            round(max(total_time), $p) as max_total_time, 
            round(avg(total_time), $p) as avg_total_time, 
            count(*) as nb
            from
            results R
            where R.stepid=".$step['stepid'];
            $res = $this->db->querySingle($q, true);
            $resp[$step['stepid']] = $res;
        }
        ?>
        <div id="print_response_time_by_steps_chart" style="width: 100%;min-height:700px;background-color:blue">Loading the graphics...</div>
        <script type="text/javascript">
        google.charts.setOnLoadCallback(print_response_time_by_steps);
        function print_response_time_by_steps() {
            var data = new google.visualization.arrayToDataTable([
                ['Transaction', '\u2211 of HTTP steps', 'Min time', 'Average time', 'Max time'],
                <?php
                foreach($resp as $stepid=>$responseTime)
                {
                    $transaction = $steps[$stepid];
                    $trname = $transaction['url'];
                    ?>
                    ['<?=$trname ?>', <?=$responseTime['nb']?>, <?=$responseTime['min_total_time']?>, <?=$responseTime['avg_total_time']?>, <?=$responseTime['max_total_time']?>
                    ],
                    <?php
                }
                ?>
            ]);
            var view = new google.visualization.DataView(data);
            view.setColumns([0, 
                    1,
                    2,{ calc: "stringify",
                            sourceColumn: 2,
                            type: "string",
                            role: "annotation" },
                    3,{ calc: "stringify",
                            sourceColumn: 3,
                            type: "string",
                            role: "annotation" },
                    4,{ calc: "stringify",
                            sourceColumn: 4,
                            type: "string",
                            role: "annotation"}
                    ]);
            var options = {
                bars: 'horizontal',
                fontSize : 10,
                chartArea: {width: '70%'},
                legend: { position: 'top' },
                vAxis: {textPosition: 'out'},
                annotations: {
                    alwaysOutside: true,
                    textStyle: { fontSize: 8}
                },
                colors: ['lightblue', 'green', 'orange', "red"],
                
                isStacked: true,
                //seriesType: 'bars',
                //orientation: 'vertical',
                series: {
                    0: {targetAxisIndex: 1, type:'steppedArea'}, // Nb of http steps
                    1: {targetAxisIndex: 0}, // min
                    2: {targetAxisIndex: 0}, // avg
                    3: {targetAxisIndex: 0}  // max time
                },
                hAxes: {
                    <?=ifNotNull("0 : { maxValue: %d},", $this->scale_resptime)?>
                    <?=ifNotNull("1 : { maxValue: %d},", $this->scale_httpsteps)?>
                }
            };
            var chart = new google.charts.Bar(document.getElementById("print_response_time_by_steps_chart"));
	        chart.draw(view, google.charts.Bar.convertOptions(options));
        }
        </script>
        <?php
    }

    public function print_visited_steps()
    {
        $steps = $this->get_visited_steps();
        echo "<h1>Visited steps</h1>\n";
        echo "<div class='info'>Number of HTTP steps called during this test.</div>";
        echo "<table border='1'>";
        echo "<tr><th>Transaction</th><th>URL</th><th>Method</th><th>Nb</th><th>Failed</th></tr>\n";
        foreach($steps as $row)
        {
            echo "<tr>\n";
            echo "<td>${row['name']}</td>\n";
            echo "<td>${row['url']}</td>\n";
            echo "<td>${row['method']}</td>\n";
            echo "<td class='rigth'>${row['cpt']}</td>\n";
            echo "<td class='rigth'>${row['failed']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }

    public function print_http_code()
    {
        $resolution = $this->resolution;
        $res = $this->db->query("
        select sincestart as since,
        sum(code_net)/$resolution as count_net,   -- error type 0 : network
        sum(code_cust)/$resolution as count_cust, -- custom error (1..9)
        sum(code_1xx)/$resolution as count_1xx,   -- all 100..199
        sum(code_200)/$resolution as count_200,   -- return code 200
        sum(code_2xx)/$resolution as count_2xx,   -- all 200.299 (including 200)
        sum(code_3xx)/$resolution as count_3xx,
        sum(code_404)/$resolution as count_404,
        sum(code_4xx)/$resolution as count_4xx,
        sum(code_500)/$resolution as count_500,
        sum(code_5xx)/$resolution as count_5xx,
        sum(code_other)/$resolution as count_other -- all error >= 600
        from (
            select
            $resolution * cast (sincestart/ $resolution as int) as sincestart,
            case http_code when 0 then 1 else 0 end code_net,
            case when http_code between 1 and 99 then 1 else 0 end code_cust,
            case when http_code between 100 and 199 then 1 else 0 end code_1xx,
            case http_code when 200 then 1 else 0 end code_200,
            case when http_code between 200 and 299 then 1 else 0 end code_2xx,
            case when http_code between 300 and 399 then 1 else 0 end code_3xx,
            case http_code when 404 then 1 else 0 end code_404,
            case when http_code between 400 and 499 then 1 else 0 end code_4xx,
            case http_code when 500 then 1 else 0 end code_500,
            case when http_code between 500 and 599 then 1 else 0 end code_5xx,
            case when http_code >= 600 then 1 else 0 end code_other
            from results
        )
        group by sincestart
        order by since");
        ?>
        <h1>HTTP Results</h1>
        <div class='info'>Repartition of HTTP result code by second during the test.</div>
        Time resolution is : <?=$resolution?> seconds.<br/>
        <div id="print_http_code_chart" style="width: 100%;min-height:450px;background-color:blue">Loading the graphics...</div>
        <script type="text/javascript">
google.charts.setOnLoadCallback(print_http_code);
function print_http_code() {
    var data = google.visualization.arrayToDataTable([
        ['Time', '200', '2xx (except 200)', '3xx', '404', '4xx (except 404)', '5xx', 'Network error', 'Soft error, other'],
        <?php
    while ($row = $res->fetchArray()) 
    {
        ?>
        [ 
            new Date(ORIGIN_DATA + 1000 * <?=$row['since']?> ), <?=$row['count_200']?>, <?=$row['count_2xx'] - $row['count_200']?>,
            <?=$row['count_3xx']?>, <?=$row['count_404']?>, <?=$row['count_4xx'] - $row['count_404']?>, 
            <?=$row['count_5xx']?>, <?=$row['count_net']?>, <?= ($row['count_1xx'] + $row['count_cust'] + $row['count_other'])?>,
        ],
        <?php
    }
        ?>
    ]);

    var options = {
        legend: { position: 'top', alignment: 'start', textStyle: { fontSize: 10} },
        colors:[
            '<?=$this->httpcodeToColor(200)?>', // 200
            '<?=$this->httpcodeToColor(201)?>', // 2xx
            '<?=$this->httpcodeToColor(300)?>', // 3xx
            '<?=$this->httpcodeToColor(404)?>', // 404
            '<?=$this->httpcodeToColor(400)?>', // 4xx
            '<?=$this->httpcodeToColor(500)?>', // 5xx
            '<?=$this->httpcodeToColor(0)?>', // net
            '<?=$this->httpcodeToColor(1)?>', // cust + other
        ],
        chartArea: {width: "90%", height: "70%", left:80},
        lineWidth:1,
        hAxis: {
            title: 'Elapsed time', format: 'mm:ss', minValue:new Date(ORIGIN_DATA), maxValue:new Date(END_DATA)
        },
        vAxis: { <?=ifNotNull("maxValue: %s", $this->scale_httpresults)?> }
    };
    elapsedFormat(data);
    var chart = new google.visualization.LineChart(document.getElementById('print_http_code_chart'));

    chart.draw(data, options);
}
        </script>
        <?php

    }

    public function print_virtual_browser()
    {
        $p = $this->precision;
        $resolution = $this->resolution;
        $q = "select
        count(distinct(browserid)) as nbVbrowser,
        round(count(id)/$resolution, $p) as nbHit,
        $resolution * cast (sincestart / $resolution as int) as since
        from
        results R
        group by since
        order by since";
        $res = $this->db->query($q);
        ?>
        <h1>Virtual browsers</h1>
        <div class='info'>Number of active virtual browsers vs HTTP transaction by seconds (hit).</div>
        Time resolution is : <?=$resolution?> seconds.<br/>
        <div id="print_virtual_browser_chart" style="width: 100%;min-height:450px;background-color:blue">Loading the graphics...</div>
        <script type="text/javascript">
google.charts.setOnLoadCallback(print_virtual_browser);

function print_virtual_browser() {
    
    var data = google.visualization.arrayToDataTable([
        ['Time', 'Nb browser', 'Hit/s'],
        <?php
    while ($row = $res->fetchArray()) 
    {
        ?>
        [ new Date(ORIGIN_DATA + 1000 * <?=$row['since']?> ), <?=$row['nbVbrowser']?>, <?=$row['nbHit']?>,],
        <?php
    }
        ?>
    ]);

    var options = {
        legend: { position: 'top', alignment: 'start', textStyle: { fontSize: 10} },
        chartArea: {width: "90%", height: "70%", left:80},
        colors:['black','blue'],
        lineWidth:1,
        series: {
            0: {targetAxisIndex: 0, lineWidth:2, lineDashStyle: [1, 1]},
            1: {targetAxisIndex: 1},
            2: {targetAxisIndex: 1},
        },
        hAxis: {
            title: 'Elapsed time', format: 'mm:ss', minValue:new Date(ORIGIN_DATA), maxValue:new Date(END_DATA)
        },
        vAxes: {
            0: {title: 'Nb of browser'},
            1: {title: 'Hit/s', titleTextStyle: {color: 'blue'} <?=ifNotNull(", maxValue: %d", $this->scale_hit)?> }
        }
    };
    elapsedFormat(data);
    var chart = new google.visualization.LineChart(document.getElementById('print_virtual_browser_chart'));

    chart.draw(data, options);
}
        </script>
        <?php
    }

    public function print_response_time()
    {
        $p = $this->precision;
        $resolution = $this->resolution;
        $q = "select
            round(min(total_time), $p) as min_time,
            round(max(total_time), $p) as max_time,
            round(avg(total_time), $p) as avg_time,
            $resolution * cast (sincestart / $resolution as int) as since
            from
            results 
            group by since
            order by since";
        $res = $this->db->query($q);
        ?>
        <h1>Response time</h1>
        Time resolution is : <?=$resolution?> seconds.<br/>
        <h2>All steps, including HTTP error or network error</h2>
        <div class='info'>Response time by HTTP steps, even if theys failed.</div><br/>
        <div id="print_response_time_chart" style="width: 100%;min-height:450px;background-color:blue">Loading the graphics...</div>
        <script type="text/javascript">
google.charts.setOnLoadCallback(print_response_time);

function print_response_time() {
    
    var data = google.visualization.arrayToDataTable([
        ['Time', 'Min total time', 'Average total time', 'Max total time'],
        <?php
    while ($row = $res->fetchArray()) 
    {
        ?>
        [ new Date(ORIGIN_DATA + 1000 * <?=$row['since']?> ), <?=$row['min_time']?>, <?=$row['avg_time']?>, <?=$row['max_time']?> ],
        <?php
    }
        ?>
    ]);

    var options = {
        legend: { position: 'top', alignment: 'start', textStyle: { fontSize: 10} },
        <?=ifNotNull("vAxis: {maxValue: %d}, ", $this->scale_resptime)?>
        chartArea: {width: "90%", height: "70%", left:80},
        colors:['green','orange', 'red'],
        chartArea: {width: "90%", height: "70%", left:80},
        lineWidth:1,
        hAxis: {
            title: 'Elapsed time', format: 'mm:ss', minValue:new Date(ORIGIN_DATA), maxValue:new Date(END_DATA)
        },
    };
    elapsedFormat(data);
    var chart = new google.visualization.LineChart(document.getElementById('print_response_time_chart'));

    chart.draw(data, options);
}
        </script>
        <?php
        $p = $this->precision;
        $q = "select
            since,
            round(min(time_ok), $p) as min_time_ok,
            round(avg(time_ok), $p) as avg_time_ok,
            round(max(time_ok), $p) as max_time_ok,
            round(min(time_ko), $p) as min_time_ko,
            round(avg(time_ko), $p) as avg_time_ko,
            round(max(time_ko), $p) as max_time_ko
            from
            (select
            $resolution * cast (sincestart / $resolution as int) as since,
            case when http_code between 200 and 399 then total_time end time_ok,
            case when http_code not between 200 and 399 then total_time end time_ko
            from 
            results)
            group by since";
        $res = $this->db->query($q);
        ?>
        <h2>HTTP 200..399 OK..redirect vs HTTP error or network error</h2>
        <div class='info'>Response time by HTTP steps, OK steps and redirect (200..399) distinguished from failed steps.</div><br/>
        <div id="print_response_time_chart2" style="width: 100%;min-height:450px;background-color:blue">Loading the graphics...</div>
        <script type="text/javascript">
google.charts.setOnLoadCallback(print_response_time2);

function print_response_time2() {
    
    var datamin = new google.visualization.DataTable();
    var datamax = new google.visualization.DataTable();
    datamin.addColumn('datetime', 'X');
    datamin.addColumn('number', 'OK min');
    datamin.addColumn('number', 'OK average');
    datamin.addColumn('number', 'OK max');
    datamax.addColumn('datetime', 'X');
    datamax.addColumn('number', 'KO min');
    datamax.addColumn('number', 'KO average');
    datamax.addColumn('number', 'KO max');
        <?php
    while ($row = $res->fetchArray()) 
    {
        if ($row['min_time_ok'] !== NULL)
        {
            ?>
            datamin.addRow([ new Date(ORIGIN_DATA + 1000 * <?=$row['since']?> ), <?=$row['min_time_ok']?>, <?=$row['avg_time_ok']?>, <?=$row['max_time_ok']?>]);
            <?php
        }
        if ($row['min_time_ko'] !== NULL)
        {
            ?>
            datamax.addRow([ new Date(ORIGIN_DATA + 1000 * <?=$row['since']?> ), <?=$row['min_time_ko']?>, <?=$row['avg_time_ko']?>, <?=$row['max_time_ko']?>]);
            <?php
        }
    }
        ?>

    var data = google.visualization.data.join(datamin, datamax, 'full', [[0, 0]], [1,2,3], [1,2,3]);
    var options = {
        legend: { position: 'top', alignment: 'start', textStyle: { fontSize: 10} },
        <?=ifNotNull("vAxis: {maxValue: %d}, ", $this->scale_resptime)?>
        chartArea: {width: "90%", height: "70%", left:80},
        colors:['lime','green', 'darkgreen','red', 'orange', 'maroon'],
        chartArea: {width: "90%", height: "70%", left:80},
        lineWidth:1,
        hAxis: {
            title: 'Elapsed time', format: 'mm:ss', minValue:new Date(ORIGIN_DATA), maxValue:new Date(END_DATA)
        },
    };
    elapsedFormat(data);
    var chart = new google.visualization.LineChart(document.getElementById('print_response_time_chart2'));
    chart.draw(data, options);
}
        </script>
        <?php
    }

    public function print_errors_by_transactions()
    {
        $p = $this->precision;
        $resolution = $this->resolution;
        $q = "select
        since, transacid, name, 
        round(sum(failed) / $resolution, $p)  as failed, 
        round(sum(success) / $resolution, $p) as success
        from
        (
            select 
            $resolution * cast (R.sincestart / $resolution as int) as since,
            R.stepid, V.transacid, V.url, V.method, T.name, 
            case when http_code between 200 and 399 then 0 else 1 end failed,
            case when http_code between 200 and 399 then 1 else 0 end success
            from results R, visitedsteps V
            left join transactions T on T.id = V.transacid
            where V.id = R.stepid
        ) group by transacid, since
        order by since, transacid";
    }

    public function print_http_stability()
    {
        $p = $this->precision;
        $resolution = $this->resolution;
        $q = "select
        min(http_code) as min_http_code,
        max(http_code) as max_http_code,
        round(count(http_code) / $resolution, $p) as nb,
        $resolution * cast (sincestart / $resolution as int) as since
        from
        results R
        group by since
        order by since";
        $res = $this->db->query($q);
        ?>
        <h1>HTTP stability (result code variation)</h1>
        <div class="info">HTTP returned code during the test</div>
        Time resolution is : <?=$resolution?> seconds.<br/>
        <div id="print_http_stability_chart" style="width: 100%;min-height:450px;background-color:blue">Loading the graphics...</div>
        <script type="text/javascript">
google.charts.setOnLoadCallback(print_http_stability);

function print_http_stability() {
    
    var data = google.visualization.arrayToDataTable([
        ['Time', 'HTTP code min', 'HTTP code max', 'Number of HTTP transaction'],
        <?php
    while ($row = $res->fetchArray()) 
    {
        ?>

        [ new Date(ORIGIN_DATA + 1000 * <?=$row['since']?> ), <?=$row['min_http_code']?>, <?=$row['max_http_code']?>, <?=$row['nb']?> ],
        [null, null,null, null],

        <?php
    }
        ?>
    ]);

    var options = {
        legend: { position: 'top', alignment: 'start', textStyle: { fontSize: 10} },
        chartArea: {width: "90%", height: "70%", left:80},
        colors:['grey','black', 'blue'],
        series: {
            0: {targetAxisIndex: 0},
            1: {targetAxisIndex: 0},
            2: {targetAxisIndex: 1},
          },
        lineWidth:1,
        hAxis: {
            title: 'Elapsed time', format: 'mm:ss', minValue:new Date(ORIGIN_DATA), maxValue:new Date(END_DATA)
        },
        vAxes: {
            0: {title: 'HTTP Code', minValue:0, maxValue:600},
            1: {title: 'Nb of transaction', titleTextStyle: {color: 'blue'}},
        }
    };
   
    elapsedFormat(data);
    var chart = new google.visualization.ScatterChart(document.getElementById('print_http_stability_chart'));

    chart.draw(data, options);
}
        </script>
        <?php
    }

    public function print_aws()
    {
        ?>
        <hr/>
        <h1>AWS statistics</h1>
        <div class="info"><?= $this->aws->version ?></div>
        <?php
    }

    public function print_aws_EC2($metricname = NULL)
    {
        $googlechartwidgetname = "print_aws_ec2_".md5($metricname).'_chart';
        $googlechartcallback = "print_aws_ec2_".md5($metricname);
        ?>
        <h2><?=$metricname?></h2>
        <div id="<?=$googlechartwidgetname?>" style="width: 100%;min-height:450px;background-color:blue">Loading the graphics...</div>
        <?php
        $data_by_instances = array();
        foreach($this->awsEC2 as $instance)
        {
            $data = $this->aws->cloudWatchGetEC2Metric(
                $instance['id'],
                $instance['region'],
                $instance['profile'],
                $this->starttime_ts,
                $this->endtime_ts,
                $metricname
            );
            if ($data === false)
            {
                echo $this->aws->lasterror;
                return;
            }
            else
            {
                $data_by_instances[$instance['id']] = $data;
            }
        }
        ?>
<script type="text/javascript">
google.charts.setOnLoadCallback(<?=$googlechartcallback?>);
function <?=$googlechartcallback?>() 
{
    var joinedData = null;
    var cols_to_merge = [];
        <?php
        $id = 0;
        foreach($data_by_instances as $instanceId=>$data)
        {
            ?>
            /** <?=htmlentities($instanceId)?> [ **/
    var data_<?=$id?> = new google.visualization.DataTable();
    data_<?=$id?>.addColumn('datetime', 'X');
    data_<?=$id?>.addColumn('number', '<?=$instanceId?>');
    data_<?=$id?>.addRows([
            <?php
                foreach($data as $row)
                {
                ?>

[ new Date(ORIGIN_DATA + 1000 * <?=$row->Elapsed?> ), <?= sprintf("%3.3f", $row->Average) ?> ],
            <?php
                }
                ?>
    ]);
            /** ] <?=htmlentities($instanceId)?>**/
        if (joinedData == null)
            joinedData = data_<?=$id?>;
        else
            joinedData = google.visualization.data.join(joinedData, data_<?=$id?>, 'full', [[0, 0]], cols_to_merge, [1]);
        cols_to_merge.push(cols_to_merge.length + 1);
            <?php
            $id++;
        }
        ?>
var options = {
    legend: { position: 'top', alignment: 'start', textStyle: { fontSize: 10} },
    chartArea: {width: "90%", height: "70%", left:80},
    lineWidth:1,
    hAxis: {
        title: 'Elapsed time', format: 'mm:ss', minValue:new Date(ORIGIN_DATA), maxValue:new Date(END_DATA)
    },
    
    intervals: { 'style':'area' },lineWidth: 1,
    
};
var chart = new google.visualization.LineChart(document.getElementById('<?=$googlechartwidgetname?>'));
chart.draw(joinedData, options);
}
</script>
        <?php
    }
}
