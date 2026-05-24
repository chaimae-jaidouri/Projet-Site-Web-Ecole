<?php
require_once "../config/database.php";

$id = $_GET['id'];

$conn->query("DELETE FROM classe_enseignant WHERE id=$id");

header("Location: manage_enseignants.php");
exit();
?>