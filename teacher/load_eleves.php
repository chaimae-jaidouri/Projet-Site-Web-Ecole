<?php
require_once "../config/database.php";

$classe_id = $_POST['classe_id'];

$sql = "SELECT * FROM eleves WHERE classe_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $classe_id);
$stmt->execute();

$result = $stmt->get_result();

echo '<option value="">-- Choisir élève --</option>';

while($e = $result->fetch_assoc()){
    echo '<option value="'.$e['id_eleve'].'">'.$e['nom'].'</option>';
}
?>