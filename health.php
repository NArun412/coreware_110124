<?php
function showErrorPage() {
    if ($GLOBALS['gDevelopmentServer']) {
        echo $GLOBALS['gDatabaseName'] . ":" . $GLOBALS['gDatabaseUsername'] . ":" . $GLOBALS['gDatabaseHostName'] . ":" . htmlText($_SERVER['HTTP_HOST'] . ":" . $_SERVER['HOSTNAME'] . ":" . gethostname()) . "<br>";
        echo $GLOBALS['gPrimaryDatabase']->iDBConnection->connect_errno;
        exit;
    }
    addDebugLog($GLOBALS['gPrimaryDatabase']->iDBConnection->connect_errno, true);
    if (!$GLOBALS['gCommandLine'] && file_exists($GLOBALS['gDocumentRoot'] . "/maintenance.html")) {
        header("Location: /maintenance.html");
        exit;
    } else {
        echo "<h1>The server is down right now. Sorry for the inconvenience. Please try again later.</h1>";
        echo "<p style='display: none'>Database connection error! Please contact customer service. " . $GLOBALS['gDatabaseName'] . ":" . $GLOBALS['gDatabaseUsername'] . ":" . $GLOBALS['gDatabaseHostName'] . ":" . $_SERVER['HTTP_HOST'] . ":" . $_SERVER['HOSTNAME'] . ":" . gethostname() . "</p>";
        exit;
    }
}

$GLOBALS['gDocumentRoot'] = __DIR__;
$startTime = microtime(true);
include_once "shared/commons.inc";
$GLOBALS['gApcuEnabled'] = (extension_loaded('apc') && ini_get('apc.enabled')) || (extension_loaded('apcu') && ini_get('apc.enabled'));
$timestamp = getCachedData("health_check", "",true);

// Don't check database if this is an ELB health check (prevent ASG from thrashing if the issue is RDS)
if(empty($timestamp) && startsWith($_SERVER['HTTP_USER_AGENT'], "ELB-HealthChecker")) {
    $timestamp = date("Y-m-d H:i:s T");
}

if(empty($timestamp)) {
    $GLOBALS['gDatabaseName'] = "corewaredb";
    $GLOBALS['gDatabaseUsername'] = "dbuser";
    $GLOBALS['gDatabasePassword'] = "Mx35d4JLy7hz";
    $GLOBALS['gDatabaseHostName'] = "";
    $GLOBALS['gDatabaseConnections'] = array();

    $GLOBALS['gDevelopmentServer'] = true;
    if (file_exists("shared/connect.inc")) {
        include_once "shared/connect.inc";
    }
    $GLOBALS['gDatabaseConnections'][] = array("domain_name" => "localhost", "development" => true);
    $GLOBALS['gDatabaseConnections'][] = array("domain_name" => "*.dev", "development" => true);
    $GLOBALS['gDatabaseConnections'][] = array("domain_name" => "*.local", "development" => true);
    $GLOBALS['gDatabaseConnections'][] = array("domain_name" => "*.test", "development" => true);
    $foundConnection = false;
    foreach ($GLOBALS['gDatabaseConnections'] as $databaseInfo) {
        if (empty($databaseInfo['domain_name'])) {
            continue;
        }
        $partialSearch = false;
        if (substr($databaseInfo['domain_name'], 0, 2) == "*.") {
            $databaseInfo['domain_name'] = substr($databaseInfo['domain_name'], 2);
            $partialSearch = true;
        }
        if (substr($databaseInfo['domain_name'], 0, 1) == "*") {
            $databaseInfo['domain_name'] = substr($databaseInfo['domain_name'], 1);
            $partialSearch = true;
        }
        if ($databaseInfo['domain_name'] == $_SERVER['HTTP_HOST'] ||
            ($partialSearch && substr($_SERVER['HTTP_HOST'], (-1 * strlen($databaseInfo['domain_name']))) == $databaseInfo['domain_name'])) {
            if (array_key_exists("database_name", $databaseInfo)) {
                $GLOBALS['gDatabaseName'] = $databaseInfo['database_name'];
            }
            if (array_key_exists("user_name", $databaseInfo)) {
                $GLOBALS['gDatabaseUsername'] = $databaseInfo['user_name'];
            }
            if (array_key_exists("password", $databaseInfo)) {
                $GLOBALS['gDatabasePassword'] = $databaseInfo['password'];
            }
            if (array_key_exists("host_name", $databaseInfo)) {
                $GLOBALS['gDatabaseHostName'] = $databaseInfo['host_name'];
            }
            $GLOBALS['gDevelopmentServer'] = $databaseInfo['development'];
            $GLOBALS['gOverrideLocalDatabase'] = $databaseInfo['override_localhost'];
            $GLOBALS['gReadReplicaDatabaseHostName'] = $databaseInfo['read_replica'];
            $foundConnection = true;
            break;
        }
    }
    if (!$foundConnection) {
        foreach ($GLOBALS['gDatabaseConnections'] as $databaseInfo) {
            if (!empty($databaseInfo['domain_name'])) {
                continue;
            }
            if (array_key_exists("database_name", $databaseInfo)) {
                $GLOBALS['gDatabaseName'] = $databaseInfo['database_name'];
            }
            if (array_key_exists("user_name", $databaseInfo)) {
                $GLOBALS['gDatabaseUsername'] = $databaseInfo['user_name'];
            }
            if (array_key_exists("password", $databaseInfo)) {
                $GLOBALS['gDatabasePassword'] = $databaseInfo['password'];
            }
            if (array_key_exists("host_name", $databaseInfo)) {
                $GLOBALS['gDatabaseHostName'] = $databaseInfo['host_name'];
            }
            $GLOBALS['gDevelopmentServer'] = $databaseInfo['development'];
            $GLOBALS['gOverrideLocalDatabase'] = $databaseInfo['override_localhost'];
            $GLOBALS['gReadReplicaDatabaseHostName'] = $databaseInfo['read_replica'];
            break;
        }
    }
    if (!$GLOBALS['gDevelopmentServer']) {
        $GLOBALS['gLogDatabaseQueries'] = false;
    } else if (!$GLOBALS['gOverrideLocalDatabase']) {
        $GLOBALS['gDatabaseHostName'] = "localhost";
    }
    $GLOBALS['gPrimaryDatabase'] = new Database($GLOBALS['gDatabaseName'], $GLOBALS['gDatabaseUsername'], $GLOBALS['gDatabasePassword'], $GLOBALS['gDatabaseHostName']);
    if ($GLOBALS['gForceDevelopmentServer']) {
        $GLOBALS['gDevelopmentServer'] = true;
    }

    if ($GLOBALS['gPrimaryDatabase'] === false || !empty($GLOBALS['gPrimaryDatabase']->iDBConnection->connect_errno)) {
        showErrorPage();
    }

    $resultSet = executeQuery("select now() as now, IF(@@session.time_zone = 'SYSTEM', @@system_time_zone, @@session.time_zone) timezone");
    if(!$row = getNextRow($resultSet)) {
        showErrorPage();
    }
    $timestamp = $row['now'] . " " . $row['timezone'];
    setCachedData("health_check", "", $timestamp, .003, true);
}
$responseTime = round( (microtime(true) - $startTime )* 1000,2);

?>
<!DOCTYPE html>
<html>
<head>

    <meta name="author" content="Coreware, LLC" />

    <title>Healthy Server</title>

</head>

<body>
<h1>Healthy Server</h1>
<p>At <?= $timestamp ?>, this server appears to be healthy.</p>
<p>Response time: <?=$responseTime?> ms</p>
</body>
