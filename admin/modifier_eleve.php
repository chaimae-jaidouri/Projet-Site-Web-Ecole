<?php
require_once "../config/database.php";

$id = intval($_GET['id']);

/* =========================
   UPDATE ELEVE
========================= */
if(isset($_POST['modifier'])){

    $nom = trim($_POST['nom']);
    $classe_id = $_POST['classe_id'];

    $stmt = $conn->prepare("
        UPDATE eleves
        SET nom = ?, classe_id = ?
        WHERE id_eleve = ?
    ");

    $stmt->bind_param("sii", $nom, $classe_id, $id);
    $stmt->execute();

    header("Location: admin.php?page=eleves");
    exit();
}

/* =========================
   GET ELEVE
========================= */
$stmt = $conn->prepare("SELECT * FROM eleves WHERE id_eleve = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$eleve = $result->fetch_assoc();

/* =========================
   CLASSES
========================= */
$classes = $conn->query("
    SELECT * FROM classe
    ORDER BY nom_classe ASC
");
?>

<style>

body{
font-family:Arial;
background:#f5f5f5;
padding:30px;
}

.form-box{
background:white;
padding:20px;
border-radius:18px;
max-width:500px;
margin:auto;
box-shadow:0 10px 25px rgba(255,204,0,0.15);
}

input, select{
width:100%;
padding:12px;
margin-top:8px;
margin-bottom:15px;
border-radius:10px;
border:2px solid #ffe082;
}

.btn{
background:#ffcc00;
color:white;
padding:12px 18px;
border:none;
border-radius:10px;
cursor:pointer;
font-weight:bold;
}

.btn:hover{
background:#e6b800;
}

</style>

<div class="form-box">

<h2>✏️ Modifier élève</h2>

<form method="POST">

    <label>Nom élève</label>

    <input type="text"
           name="nom"
           value="<?php echo $eleve['nom']; ?>"
           required>

    <label>Classe</label>

    <select name="classe_id" required>

        <?php while($c = $classes->fetch_assoc()){ ?>

        <option value="<?php echo $c['id_classe']; ?>"

        <?php
        if($c['id_classe'] == $eleve['classe_id']){
            echo "selected";
        }
        ?>

        >

        <?php echo $c['nom_classe']; ?>

        </option>

        <?php } ?>

    </select>

    <button type="submit"
            name="modifier"
            class="btn">

        💾 Enregistrer

    </button>

</form>

</div>