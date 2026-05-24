<?php
require_once "../config/database.php";

$sql = "SELECT * FROM classe ORDER BY nom_classe ASC";
$result = $conn->query($sql);
?>

<div class="card">

<h3>📚 Classes</h3>

<div class="classes-container">

<?php while($classe = $result->fetch_assoc()) { ?>

<div class="classe-box">

    <h4>
        <?php echo $classe['nom_classe']; ?>
    </h4>

    <?php

    $id_classe = $classe['id_classe'];

    $sql_eleves = "SELECT * FROM eleves
                   WHERE classe_id = $id_classe
                   ORDER BY nom ASC";

    $res_eleves = $conn->query($sql_eleves);

    ?>

    <table>

        <tr>
            <th>N°</th>
            <th>Nom élève</th>
        </tr>

        <?php
        $i = 1;

        while($eleve = $res_eleves->fetch_assoc()) {
        ?>

        <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo $eleve['nom']; ?></td>
        </tr>

        <?php } ?>

    </table>

</div>

<?php } ?>

</div>

</div>

<style>

.classes-container{
display:flex;
flex-direction:column;
gap:25px;
margin-top:20px;
}

.classe-box{
background:#fffdf4;
padding:20px;
border-radius:20px;
box-shadow:0 10px 25px rgba(255,204,0,0.15);
}

.classe-box h4{
color:#d4a800;
margin-bottom:15px;
}

table{
width:100%;
border-collapse:collapse;
}

table th{
background:#ffcc00;
color:white;
padding:10px;
}

table td{
padding:10px;
border-bottom:1px solid #eee;
}

</style>