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
        //Traitement du fichier de log
        $logFiles = scandir($config["logFile"]["path"]);
        $fileName = $config["logFile"]["pattern"];
        $fileName = str_replace("{date}", $date, $fileName);
        $fileFound = false;
        $url = initWriteUrl($config["host"], $config["port"], $config["databaseName"]);
        foreach ($logFiles as $file) {
            if ($file == $fileName) {
                $fileFound = true;
                displayMessage($config['translations']['logFile']['findSuccess'], $fileName);
                importFile($config["logFile"]["path"] . "/" . $fileName, "data", $url, $config);
            }
        }
        if (!$fileFound) {
            displayMessage($config['translations']['logFile']['findError'], $fileName);
        }
        break;

    case 'reset':
        // Paramètres de connexion à InfluxDB
        $influxDbHost = "http://localhost:8086"; // Adresse du serveur InfluxDB
        $database = "alpvalley"; // Nom de la base de données

        // Requête InfluxDB pour supprimer les données
        // Supprime toutes les données de la mesure "my_measurement" dans un intervalle de temps
        $deleteQuery = "DROP MEASUREMENT data";

        // Créer l'URL complète pour exécuter une requête
        $url = "$influxDbHost/query?db=$database";

        // Initialiser la requête cURL
        $ch = curl_init($url);

        // Configurer les options cURL
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['q' => $deleteQuery]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Exécuter la requête et récupérer la réponse
        $response = curl_exec($ch);

        // Gérer les erreurs
        if (curl_errno($ch)) {
            echo 'Erreur cURL : ' . curl_error($ch);
        } else {
            echo "Requête de suppression exécutée avec succès. Réponse InfluxDB : " . $response;
        }

        // Fermer la session cURL
        curl_close($ch);

    default:
        displayMessage($config['translations']['error']['unknwonMode']);
        break;
}