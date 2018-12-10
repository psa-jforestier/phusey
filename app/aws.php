<?php
/** Amazon Web Service helper */

class AWS
{
    var $version; 
    var $lasterror;
    /** return true if AWS CLI is properly installed */
    public function isAWSCliInstalled()
    {
        $cmd = 'aws --version 2>&1';
        $version = exec($cmd, $output, $ret);
        if ($ret !== 0)
        {
            $this->lasterror = $version;
            return false;
        }
        $this->version = $version;
    }

    public function cloudWatchGetASMetric($instanceId, $region = '', $profile = 'default', $starttime_ts, $endtime_ts, $metricname, $statistic = 'Average')
    {
        $R = ($region != '' ? "--region ".escapeshellarg($region) : '');
        $P = ($profile != '' ? "--profile ".escapeshellarg($profile) : '');
        $metricname = escapeshellarg($metricname);
        $starttime_ts = 0 + ($starttime_ts);
        $endtime_ts = 0 + ($endtime_ts);
        $statistic = escapeshellarg($statistic);
        $instanceId = escapeshellarg($instanceId);
        $cmd = "aws cloudwatch get-metric-statistics $R $P --metric-name $metricname --namespace AWS/AutoScaling --start-time $starttime_ts --end-time $endtime_ts --period 60 --statistics $statistic --dimensions Name=AutoScalingGroupName,Value=$instanceId --output json 2>&1";
        $res = exec($cmd, $output, $ret);
        if ($ret == 0)
        {
            $metrics = json_decode(implode($output));
            $datapoints = $this->improveDatapoints(
                $metrics->Datapoints,
                $starttime_ts
            );
            return $datapoints;
        }
        else
        {
            $this->lasterror = $res;
            return false;
        }
    }
    public function cloudWatchGetEC2Metric($instanceId, $region = '', $profile = 'default', $starttime_ts, $endtime_ts, $metricname, $statistic = 'Average')
    {
        $R = ($region != '' ? "--region ".escapeshellarg($region) : '');
        $P = ($profile != '' ? "--profile ".escapeshellarg($profile) : '');
        // aws cloudwatch get-metric-data^C-metric-name CPUUtilization --namespace AWS/EC2 --start-time 2018-10-08T12:00:00.000Z --end-time 2018-10-08T14:00:00.000Z --period 60 --statistics Average --dimensions Name=InstanceId,Value=i-044eabe403df3dc8c
       
        $metricname = escapeshellarg($metricname);
        $starttime_ts = 0 + ($starttime_ts);
        $endtime_ts = 0 + ($endtime_ts);
        $statistic = escapeshellarg($statistic);
        $instanceId = escapeshellarg($instanceId);
        $cmd = "aws cloudwatch get-metric-statistics $R $P --metric-name $metricname --namespace AWS/EC2 --start-time $starttime_ts --end-time $endtime_ts --period 60 --statistics $statistic --dimensions Name=InstanceId,Value=$instanceId --output json 2>&1";
        $res = exec($cmd, $output, $ret);
        if ($ret == 0)
        {
            $metrics = json_decode(implode($output));
            $datapoints = $this->improveDatapoints(
                $metrics->Datapoints,
                $starttime_ts
            );
            return $datapoints;
        }
        else
        {
            $this->lasterror = $res;
            return false;
        }
    }
    public function cloudWatchGetEC2CPUUtilization($instanceId, $region = '', $profile = 'default', $starttime_ts, $endtime_ts)
    {
        return $this->cloudWatchGetEC2Metric(
            $instanceId,
            $region,
            $profile,
            $starttime_ts,
            $endtime_ts,
            "CPUUtilization",
            "Average"
        );
    }

    public static function cloudWatchSortByTimestamp($a, $b)
    {
        return $a->Timestamp > $b->Timestamp;
    }

    public function improveDatapoints($datapoints, $starttime_ts)
    {
        usort(
            $datapoints,
            "AWS::cloudWatchSortByTimestamp"
        );
        $tmp = array();
        foreach($datapoints as $point)
        {
            $point->Elapsed = strtotime($point->Timestamp) - $starttime_ts; // Because of AWS CloudWatch time precision, this time can be < 0 at the very begining of the test
            if ($point->Elapsed < 0)
            {
                $point->Elapsed = 0;
            }
            $tmp[] = $point;
        }
        return $tmp;
    }
}


