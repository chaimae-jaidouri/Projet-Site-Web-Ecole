<?php
require_once "../config/database.php";

/* =========================
   DELETE ELEVE
========================= */
if(isset($_GET['delete'])){

    $id = intval($_GET['delete']);

    $stmt = $conn->prepare("DELETE FROM eleves WHERE id_eleve = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: admin.php?page=eleves");
    exit();
}

/* =========================
   AJOUT ELEVE
========================= */
if(isset($_POST['ajouter'])){

    $nom = trim($_POST['nom']);
    $classe_id = $_POST['classe_id'];

    $stmt = $conn->prepare("INSERT INTO eleves (nom, classe_id) VALUES (?, ?)");
    $stmt->bind_param("si", $nom, $classe_id);
    $stmt->execute();

    $message = "Élève ajouté avec succès";
}

/* =========================
   CLASSES
========================= */
$classes = $conn->query("SELECT * FROM classe ORDER BY nom_classe ASC");

/* =========================
   ELEVES
========================= */
$sql = "SELECT eleves.*, classe.nom_classe
        FROM eleves
        INNER JOIN classe ON eleves.classe_id = classe.id_classe
        ORDER BY eleves.id_eleve DESC";

$result = $conn->query($sql);
?>

<style>

/* =========================
   FORM
========================= */
.form-box{
background:white;
padding:20px;
border-radius:18px;
box-shadow:0 10px 25px rgba(255,204,0,0.15);
margin-bottom:25px;
}

input, select{
width:100%;
padding:12px;
margin-top:8px;
margin-bottom:15px;
border-radius:10px;
border:2px solid #ffe082;
}

/* BUTTON */
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

/* TABLE */
table{
width:100%;
border-collapse:collapse;
background:white;
border-radius:15px;
overflow:hidden;
}

th{
background:#ffcc00;
color:white;
padding:12px;
}

td{
padding:12px;
border-bottom:1px solid #eee;
text-align:center;
}

/* ACTIONS */
.actions a{
margin:0 5px;
text-decoration:none;
font-size:18px;
}

.edit{
color:#007bff;
}

.delete{
color:#dc3545;
}

/* MESSAGE */
.msg{
background:#e8f5e9;
color:#2e7d32;
padding:10px;
border-radius:10px;
margin-bottom:15px;
}

</style>

<div>

<h2>👨‍🎓 Gestion des élèves</h2>

<!-- =========================
     FORM AJOUT
========================= -->
<div class="form-box">

<?php if(isset($message)){ ?>
<div class="msg"><?php echo $message; ?></div>
<?php } ?>

<form method="POST">

    <label>Nom élève</label>
    <input type="text" name="nom" required>

    <label>Classe</label>

    <select name="classe_id" required>

        <option value="">Choisir classe</option>

        <?php while($c = $classes->fetch_assoc()){ ?>

            <option value="<?php echo $c['id_classe']; ?>">
                <?php echo $c['nom_classe']; ?>
            </option>

        <?php } ?>

    </select>

    <button type="submit" name="ajouter" class="btn">
        ➕ Ajouter élève
    </button>

</form>

</div>

<!-- =========================
     TABLE ELEVES
========================= -->

<table>

<tr>
    <th>ID</th>
    <th>Nom</th>
    <th>Classe</th>
    <th>Actions</th>
</tr>

<?php while($e = $result->fetch_assoc()){ ?>

<tr>

    <td><?php echo $e['id_eleve']; ?></td>
    <td><?php echo $e['nom']; ?></td>
    <td><?php echo $e['nom_classe']; ?></td>

    <td class="actions">

        <!-- EDIT -->
        <a class="edit"
           href="modifier_eleve.php?id=<?php echo $e['id_eleve']; ?>">
            ✏️
        </a>

        <!-- DELETE -->
        <a class="delete"
           href="admin.php?page=eleves&delete=<?php echo $e['id_eleve']; ?>"
           onclick="return confirm('Voulez-vous vraiment supprimer cet élève ?')">
            🗑️
        </a>

    </td>

</tr>

<?php } ?>

</table>

</div>