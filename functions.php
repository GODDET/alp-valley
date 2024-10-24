<?php

function displayMessage($message, $element = null)
{
    $message = str_replace("{element}", $element, $message);
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
    echo $response;
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

function importFile($filename, $tableName, $url, $config)
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

                $value = str_replace("\"", "\\\"", $value);
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