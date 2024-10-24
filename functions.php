<?php

function displayMessage($message, $element = null)
{
    if (!is_null($element)) {
        $message = str_replace("{element}", $element, $message);
    }
    echo $message . "\n";
}
function initUrl($host, $port, $database, $query)
{
    return "http://$host:$port/query?db=$database&q=" . urlencode($query);
}

function initWriteUrl($host, $port, $database)
{
    return "http://$host:$port/write?db=$database";
}

function initDsn($host, $port, $database, $user, $password)
{
    return "pgsql:host=$host;port=$port;dbname=$database;user=$user;password=$password";
}

function executeQuery($url)
{
    // Initialize cURL session
    $ch = curl_init();

    // Set the URL and other necessary options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // Execute the cURL session and get the result
    $response = curl_exec($ch);

    // Close cURL session
    curl_close($ch);

    // Decode the JSON response
    $response = json_decode($response, true);

    return $response;

}

function executePostQuery($url, $data, $config)
{
    // Initialiser la requête cURL
    $ch = curl_init($url);

    // Configurer les options cURL
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Exécuter la requête et récupérer la réponse
    $response = curl_exec($ch);

    // Gérer les erreurs
    if (curl_errno($ch)) {
        displayMessage($config['translations']['database']['importError'], curl_errno($ch));
    } else {
        displayMessage($config['translations']['database']['importSuccess']);
    }

    // Fermer la session cURL
    curl_close($ch);
}
function checkQuery($response, $message)
{
    if (is_null($response)) {
        displayMessage($message);
        exit();
    }
}

function isDatabaseExists($databaseName, $response)
{
    $databaseFound = false;
    foreach ($response["results"] as $statements) {
        foreach ($statements["series"] as $series) {
            if ($series["name"] == "databases") {
                foreach ($series["values"] as $databases) {
                    if (in_array($databaseName, $databases)) {
                        $databaseFound = true;
                        break;
                    }
                }
            }
        }

    }
    return $databaseFound;
}

function importFromFile($filename, $tableName, $url, $config)
{
    if (($handle = fopen($filename, "r")) !== FALSE) {

        // Read the file line by line
        while (($line = fgets($handle)) !== FALSE) {
            // Trim any extra whitespace/newline characters
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Split the line by the pipe '|' character
            $fields = explode('|', $line);

            // Process each field (for example, print them)
            // This assumes that each log line has a fixed number of fields
            // 2024-10-14 06:22:51,323|INFO|original_language=fr | language=french | transcript= Bonjour madame. | answer=Bonjour! Je suis Simone Hérault, la voix officielle de la SNCF. Comment puis-je vous aider aujourd'hui? | insult=0 | manipulate=0 | return_code=1 | return_text=False | dev=false | npc=simone
            $data = "$tableName ";
            $aDate = explode(",", $fields[0]);
            $timestamp = strtotime($aDate[0]) * 1000 * 1000 * 1000;
            $timestamp += ($aDate[1] ?? 1) * 1000 * 1000;
            $values = [];
            $values["user_id"] = '';
            $values["original_language"] = splitField($fields[2]);
            $values["language"] = splitField($fields[3]);
            $values["question"] = splitField($fields[4]);
            $values["answer"] = splitField($fields[5]);
            $values["insult"] = splitField($fields[6]);
            $values["manipulate"] = splitField($fields[7]);
            $values["optin_requested_at"] = "";
            $values["return_code"] = splitField($fields[8]);
            $values["return_text"] = splitField($fields[9]);
            $values["reviewed_ok_at"] = "";
            $values["reviewed_ko_at"] = "";
            $values["dev"] = splitField($fields[10]);
            $values["npc"] = splitField($fields[11]);

            foreach ($values as $key => $value) {
                if (!is_null($value)) {
                    $value = str_replace("\"", "\\\"", $value);
                }
                $data .= $key . "=\"" . $value . "\",";
            }
            $data .= "origin=\"LOG\" " . $timestamp;
            displayMessage($data);
            executePostQuery($url, $data, $config);
            //break;
        }

        // Close the file
        fclose($handle);
    }
}

function splitField($field)
{
    $aField = explode("=", trim($field));
    array_shift($aField);
    return implode("=", $aField);
}

function importFromPsql($dsn, $tableName, $url, $config)
{
    try {
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = $config["query"]["databaeWA"];
        $stmt = $pdo->query($sql);


        // Récupération des résultats et affichage
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data = "$tableName ";
            $aDate = explode(".", $row["created_at"]);
            $timestamp = strtotime($aDate[0]) * 1000 * 1000 * 1000;
            $aDateNano = explode("+", $aDate[1]);
            $timestamp += ($aDateNano[0] ?? 1000000);
            $values = [];
            $values["user_id"] = $row["user_id"];
            $values["original_language"] = "";
            $values["language"] = "";
            $values["question"] = $row["message"];
            $values["answer"] = $row["answer"];
            $values["insult"] = $row["insulting"];
            $values["manipulate"] = $row["bad_behavior"];
            $values["optin_requested_at"] = $row["optin_requested_at"];
            $values["return_code"] = "";
            $values["return_text"] = "";
            $values["reviewed_ok_at"] = $row["reviewed_ok_at"];
            $values["reviewed_ko_at"] = $row["reviewed_ko_at"];
            $values["dev"] = "";
            $values["npc"] = "";

            foreach ($values as $key => $value) {
                if (!is_null($value)) {
                    $value = str_replace("\"", "\\\"", $value);
                }
                $data .= $key . "=\"" . $value . "\",";
            }
            $data .= "origin=\"WA\" " . $timestamp;
            displayMessage($data);
            //executePostQuery($url, $data, $config);
            //break;
        }

    } catch (PDOException $e) {
        // En cas d'erreur, afficher le message d'erreur
        displayMessage($config['translations']['database']['connexionPsqlError'], $config["postgreSQL"]["databaseName"]);
    }

    // Fermeture de la connexion
    $pdo = null;
}

function checkPsql($dsn)
{
    try {
        $pdo = new PDO($dsn);
    } catch (PDOException $e) {
        return false;
    }
    return true;
}
