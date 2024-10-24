<?php

require_once 'functions.php';

if (PHP_SAPI === 'cli') {
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
}

$config = json_decode(file_get_contents('config.json'), true);

$mode = $_GET['mode'] ?? 'import';

$date = $_GET['date'] ?? date("Y-m-d", strtotime("yesterday"));

switch ($mode) {
    case 'check':

        //Check if the influxDB database exists
        $query = $config['query']['databaseList'];
        $url = initUrl($config["host"], $config["port"], $config["databaseName"], $query);
        $response = executeQuery($url);
        checkQuery($response, $config['translations']['database']['connexionError']);
        if (isDatabaseExists($config["databaseName"], $response)) {
            displayMessage($config['translations']['database']['checkSuccess'], $config["databaseName"]);
        } else {
            displayMessage($config['translations']['database']['checkError'], $config["databaseName"]);
        }

        //Check if the log directory exists
        if (file_exists($config["logFile"]["path"])) {
            displayMessage($config['translations']['logFile']['checkSuccess'], $config["logFile"]["path"]);
        } else {
            displayMessage($config['translations']['logFile']['checkError'], $config["logFile"]["path"]);
        }

        //Check if the Psql database exists
        $dsn = initDsn($config["postgreSQL"]["host"], $config["postgreSQL"]["port"], $config["postgreSQL"]["databaseName"], $config["postgreSQL"]["user"], $config["postgreSQL"]["password"]);
        if (checkPsql($dsn)) {
            displayMessage($config['translations']['database']['checkPsqlSuccess'], $config["postgreSQL"]["databaseName"]);
        } else {
            displayMessage($config['translations']['database']['checkPsqlError'], $config["postgreSQL"]["databaseName"]);
        }
        break;

    case 'init':
        $query = $config['query']['databaseList'];
        $url = initUrl($config["host"], $config["port"], $config["databaseName"], $query);
        $response = executeQuery($url);
        checkQuery($response, $config['translations']['database']['connexionError']);
        if (!isDatabaseExists($config["databaseName"], $response)) {
            $query = $config['query']['databaseCreate'];
            $query = str_replace("{element}", $config["databaseName"], $query);
            $url = initUrl($config["host"], $config["port"], $config["databaseName"], $query);
            $response = executeQuery($url);
            checkQuery($response, $config['translations']['database']['connexionError']);
            displayMessage($config['translations']['database']['createSuccess'], $config["databaseName"]);
        } else {
            displayMessage($config['translations']['database']['checkSuccess'], $config["databaseName"]);
        }
        if (!file_exists($config["logFile"]["path"])) {
            mkdir($config["logFile"]["path"]);
            displayMessage($config['translations']['logFile']['createSuccess'], $config["logFile"]["path"]);
        } else {
            displayMessage($config['translations']['logFile']['checkSuccess'], $config["logFile"]["path"]);
        }
        break;

    case 'import':
        //Traitement via le fichier de log
        /*
        $logFiles = scandir($config["logFile"]["path"]);
        $fileName = $config["logFile"]["pattern"];
        $fileName = str_replace("{date}", $date, $fileName);
        $fileFound = false;
        $url = initWriteUrl($config["host"], $config["port"], $config["databaseName"]);
        foreach ($logFiles as $file) {
            if ($file == $fileName) {
                $fileFound = true;
                displayMessage($config['translations']['logFile']['findSuccess'], $fileName);
                importFromFile($config["logFile"]["path"] . "/" . $fileName, "data", $url, $config);
            }
        }
        if (!$fileFound) {
            displayMessage($config['translations']['logFile']['findError'], $fileName);
        }
        */

        //Traitement via la base de données
        $url = initWriteUrl($config["host"], $config["port"], $config["databaseName"]);
        $dsn = initDsn($config["postgreSQL"]["host"], $config["postgreSQL"]["port"], $config["postgreSQL"]["databaseName"], $config["postgreSQL"]["user"], $config["postgreSQL"]["password"]);
        importFromPsql($dsn, "data", $url, $config);
        break;

    default:
        displayMessage($config['translations']['error']['unknwonMode']);
        break;
}