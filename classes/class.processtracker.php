<?php

/*	This software is the unpublished, confidential, proprietary, intellectual
	property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
	or used in any manner without expressed written consent from Kim David Software, LLC.
	Kim David Software, LLC owns all rights to this work and intends to keep this
	software confidential so as to maintain its value as a trade secret.

	Copyright 2004-Present, Kim David Software, LLC.

	WARNING! This code is part of the Kim David Software's Coreware system.
	Changes made to this source file will be lost when new versions of the
	system are installed.
*/

class ProcessTracker {

    private static $iLastTime;
    private static $iTimeBuckets = array();

    public static function reset() {
        self::$iLastTime = getMilliseconds();
        self::$iTimeBuckets = array();
    }
    public static function logTime($bucketName) {
        $bucket = self::$iTimeBuckets[$bucketName];
        if (empty($bucket)) {
            $bucket = array("average_time" => 0, "time" => 0, "count" => 0);
        }
        self::$iLastTime = self::$iLastTime ?: getMilliseconds();
        $time = (getMilliseconds() - self::$iLastTime) / 1000;
        self::$iLastTime = getMilliseconds();
        $bucket['time'] += $time;
        $bucket['average_time'] = (($bucket['average_time'] * $bucket['count']) + $time) / ++$bucket['count'];
        self::$iTimeBuckets[$bucketName] = $bucket;
    }

    public static function getResults() {
        $totalTime = 0.0;
        foreach (self::$iTimeBuckets as $bucket) {
            $totalTime += floatval($bucket['time']);
        }
        $totalTime = $totalTime ?: 1;
        $results = [];
        foreach(self::$iTimeBuckets as $bucketName => $bucket) {
            $results[] = (sprintf( "%s%% %s: time %s (%s, average %s)", str_pad(number_format(($bucket['time'] / $totalTime) * 100,2), 6," ", STR_PAD_LEFT),
                $bucketName, number_format($bucket['time'],2), $bucket['count'], number_format($bucket['average_time'], 4)));
        }
        return $results;
    }

    public static function logResults() {
        foreach(self::getResults() as $line) {
            addDebugLog($line);
        }
    }

}
