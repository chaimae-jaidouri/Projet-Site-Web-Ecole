<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "ecole_db";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Erreur connexion : " . $conn->connect_error);
}

?>