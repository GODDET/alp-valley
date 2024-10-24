<?php

// Paramètres de connexion à PostgreSQL
$host = "127.0.0.1";
$dbname = "alpvalley";
$user = "alpvalley";
$password = "alpvalley73!";
$port = "5432"; // Le port par défaut de PostgreSQL

// Chaîne de connexion
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password";

try {
    // Création d'une nouvelle connexion PDO
    $pdo = new PDO($dsn);

    // Configuration de PDO pour gérer les erreurs en mode exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Requête SQL (modifiez la requête en fonction de vos besoins)
    $sql = "SELECT em_user.*,em_ai.answer from public.eic_message em_user LEFT JOIN (SELECT previous_entry_id,eic_message.message answer FROM public.eic_message WHERE role='ai') em_ai ON em_ai.previous_entry_id = em_user.id JOIN public.eic_user eu ON eu.id = em_user.user_id WHERE role='user'";

    // Préparation et exécution de la requête
    $stmt = $pdo->query($sql);

    // Récupération des résultats et affichage
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: " . $row['id'] . " - Nom: " . $row['user_id'] . "<br>";
    }

} catch (PDOException $e) {
    // En cas d'erreur, afficher le message d'erreur
    echo "Erreur de connexion : " . $e->getMessage();
}

// Fermeture de la connexion
$pdo = null;
