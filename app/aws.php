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
            $tihs->lasterror = $version;
            return false;
        }
        $this->version = $version;
    }

    public function cloudWatchGetEC2CPUUtilization($instanceId, $region = '', $profile = 'default', $starttime_ts, $endtime_ts)
    {
        $R = ($region != '' ? "--region $region" : '');
        $P = ($profile != '' ? "--profile $profile" : '');
        $cmd = "aws cloudwatch get-metric-statistics $R $P --metric-name CPUUtilization --namespace AWS/EC2 --start-time $starttime_ts --end-time $endtime_ts --period 60 --statistics Average --dimensions Name=InstanceId,Value=$instanceId --output json 2>&1";
        $res = exec($cmd, $output, $ret);
        if ($ret == 0)
        {
            $metrics = json_decode(implode($output));
            $datapoints = $metrics->Datapoints;
            usort(
                $datapoints,
                "AWS::cloudWatchSortByTimestamp"
            );
            return $datapoints;
        }
        else
        {
            $this->lasterror = $res;
            return false;
        }
    }

    public static function cloudWatchSortByTimestamp($a, $b)
    {
        return $a->Timestamp > $b->Timestamp;
    }
}

