<?php
require_once "../config/database.php";

/* =========================
   AJOUT ENSEIGNANT
========================= */
if(isset($_POST['add'])){

    $nom = $_POST['nom'];
    $email = $_POST['email'];
    $fonction = $_POST['fonction'];
    $sexe = $_POST['sexe'];

    $sql = "INSERT INTO enseignant (nom, email, fonction, sexe)
            VALUES (?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $nom, $email, $fonction, $sexe);
    $stmt->execute();

    $enseignant_id = $conn->insert_id;

    /* classes */
    if(isset($_POST['classes'])){

        foreach($_POST['classes'] as $classe_id){

            $sql2 = "INSERT INTO classe_enseignant (classe_id, enseignant_id)
                     VALUES (?, ?)";

            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("ii", $classe_id, $enseignant_id);
            $stmt2->execute();
        }
    }

    header("Location: manage_enseignants.php");
    exit();
}

/* =========================
   DELETE ENSEIGNANT
========================= */
if(isset($_GET['delete'])){

    $id = $_GET['delete'];

    $conn->query("DELETE FROM enseignant WHERE id_enseignant=$id");
    header("Location: manage_enseignants.php");
    exit();
}

/* =========================
   DATA
========================= */
$enseignants = $conn->query("SELECT * FROM enseignant");
$classes = $conn->query("SELECT * FROM classe");
?>

<!DOCTYPE html>
<html>
<head>
<title>Gestion enseignants</title>

<style>

body{
font-family:Arial;
background:#fff7d6;
padding:20px;
}

.container{
display:flex;
gap:20px;
}

.box{
background:white;
padding:20px;
border-radius:15px;
box-shadow:0 0 10px rgba(0,0,0,0.1);
flex:1;
}

input, select{
width:100%;
padding:10px;
margin:5px 0;
}

button{
background:#ffcc00;
border:none;
padding:10px;
cursor:pointer;
font-weight:bold;
}

.card{
background:#fff;
padding:10px;
margin:10px 0;
border-left:5px solid #ffcc00;
}

.class-tag{
display:inline-block;
background:#eee;
padding:5px 10px;
border-radius:10px;
margin:3px;
position:relative;
}

.remove{
color:red;
margin-left:5px;
cursor:pointer;
}

a{
color:red;
text-decoration:none;
}

</style>

</head>

<body>

<h2>Gestion des enseignants</h2>

<div class="container">

<!-- =========================
     AJOUT
========================= -->
<div class="box">

<h3>Ajouter enseignant</h3>

<form method="POST">

<input type="text" name="nom" placeholder="Nom" required>
<input type="email" name="email" placeholder="Email" required>

<input type="text" name="fonction" placeholder="Fonction" required>

<select name="sexe">
<option value="M">Homme</option>
<option value="F">Femme</option>
</select>

<h4>Classes</h4>

<?php while($c = $classes->fetch_assoc()){ ?>

<label>
<input type="checkbox" name="classes[]" value="<?= $c['id_classe'] ?>">
<?= $c['nom_classe'] ?>
</label><br>

<?php } ?>

<br>
<button name="add">Ajouter</button>

</form>

</div>

<!-- =========================
     LISTE
========================= -->
<div class="box">

<h3>Liste enseignants</h3>

<?php while($e = $enseignants->fetch_assoc()){ ?>

<div class="card">

<b><?= $e['nom'] ?></b><br>
<?= $e['email'] ?><br>
<?= $e['fonction'] ?>

<br><br>

<!-- classes -->
<?php
$id = $e['id_enseignant'];

$q = $conn->query("
SELECT c.*, ce.id as ce_id
FROM classe_enseignant ce
JOIN classe c ON c.id_classe = ce.classe_id
WHERE ce.enseignant_id = $id
");

while($cl = $q->fetch_assoc()){
?>

<span class="class-tag">
<?= $cl['nom_classe'] ?>

<a class="remove"
href="remove_class.php?id=<?= $cl['ce_id'] ?>">
✖
</a>

</span>

<?php } ?>

<br><br>

<a href="?delete=<?= $e['id_enseignant'] ?>"
onclick="return confirm('Supprimer enseignant ?')">
Supprimer
</a>

</div>

<?php } ?>

</div>

</div>

</body>
</html>