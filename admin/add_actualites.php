<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../config/database.php";

/* =========================
   SECURITE ADMIN
========================= */
if(!isset($_SESSION['admin_id'])){
    header("Location: ../login.php");
    exit();
}

/* =========================
   AJOUT ACTUALITE
========================= */
if(isset($_POST['ajouter'])){

    $titre = trim($_POST['titre']);
    $contenu = trim($_POST['contenu']);
    $admin_id = $_SESSION['admin_id'];
    $image = null;

    if(!empty($_FILES['image']['name'])){
        $image = time() . "_" . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], "../images/" . $image);
    }

    $sql = "INSERT INTO actualite (titre, contenu, image, admin_id, date_publication) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $titre, $contenu, $image, $admin_id);
    
    if($stmt->execute()) {
/* =========================
           CREER NOTIFICATIONS
        ========================= */
        $parents = $conn->query("SELECT id_parent FROM parents");
        $message = "Nouvelle actualité : " . $titre;

        // Il y a 2 points d'interrogation '?' ici
        $sqlNotif = "INSERT INTO notification (parent_id, message, lu, date_creation) VALUES (?, ?, 0, NOW())";
        $stmtNotif = $conn->prepare($sqlNotif);

        while($p = $parents->fetch_assoc()){
            // On utilise uniquement "is" (i = id_parent, s = message) et on supprime la variable $type
            $stmtNotif->bind_param("is", $p['id_parent'], $message);
            $stmtNotif->execute();
        }
        
        $_SESSION['message'] = "Actualité ajoutée avec succès";
    }

    header("Location: admin.php?page=actualites");
    exit();
}

/* =========================
   SUPPRESSION ACTUALITE + NOTIFICATIONS
========================= */
if(isset($_POST['delete_id'])){

    $id = intval($_POST['delete_id']);

    // 1. Récupérer le titre pour pouvoir cibler et supprimer ses notifications
    $sqlSelect = "SELECT titre, image FROM actualite WHERE id_actualite = ?";
    $stmtSelect = $conn->prepare($sqlSelect);
    $stmtSelect->bind_param("i", $id);
    $stmtSelect->execute();
    $res = $stmtSelect->get_result()->fetch_assoc();

    if($res) {
        // Supprimer le fichier image du dossier
        if(!empty($res['image'])){
            $file = "../images/" . $res['image'];
            if(file_exists($file)){
                unlink($file);
            }
        }

        // 2. Nettoyer les notifications liées à cette actualité chez tous les parents
        $messageNotif = "Nouvelle actualité : " . $res['titre'];
        $sqlDelNotif = "DELETE FROM notification WHERE type = 'actualite' AND message = ?";
        $stmtDelNotif = $conn->prepare($sqlDelNotif);
        $stmtDelNotif->bind_param("s", $messageNotif);
        $stmtDelNotif->execute();
    }

    // 3. Supprimer l'actualité de la base
    $sql = "DELETE FROM actualite WHERE id_actualite = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $_SESSION['message'] = "Actualité et ses notifications supprimées";

    header("Location: admin.php?page=actualites");
    exit();
}

/* =========================
   MODIFICATION (UPDATE)
========================= */
if(isset($_POST['update'])){

    $id = intval($_POST['id']);
    $titre = trim($_POST['titre']);
    $contenu = trim($_POST['contenu']);

    $sql = "UPDATE actualite SET titre=?, contenu=? WHERE id_actualite=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $titre, $contenu, $id);
    $stmt->execute();

    $_SESSION['message'] = "Actualité modifiée avec succès";

    header("Location: admin.php?page=actualites");
    exit();
}

/* =========================
   LISTE ACTUALITES
========================= */
$sql = "SELECT * FROM actualite ORDER BY id_actualite DESC";
$result = $conn->query($sql);
?>

<style>
.form-box{
background:white;
padding:25px;
border-radius:20px;
box-shadow:0 10px 25px rgba(255,204,0,0.15);
}

input, textarea{
width:100%;
padding:12px;
margin-top:8px;
margin-bottom:15px;
border-radius:12px;
border:2px solid #ffe082;
}

textarea{
height:120px;
}

.btn{
background:#ffcc00;
color:white;
padding:10px 15px;
border:none;
border-radius:10px;
cursor:pointer;
font-weight:bold;
margin-top:5px;
}

.btn:hover{
background:#e6b800;
}

.delete{
background:red;
}

.edit{
background:#2196f3;
}

.success{
background:#e8f5e9;
color:#2e7d32;
padding:10px;
border-radius:10px;
margin-bottom:10px;
}

.card{
background:#fffdf5;
padding:20px;
margin-top:15px;
border-radius:15px;
box-shadow:0 5px 15px rgba(255,204,0,0.1);
}

.card img{
width:180px;
margin-top:10px;
border-radius:10px;
}
</style>

<div class="form-box">
<h2>📰 Actualités</h2>

<?php if(isset($_SESSION['message'])){ ?>
    <div class="success">
        <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
    </div>
<?php } ?>

<form method="POST" enctype="multipart/form-data">
    <input type="text" name="titre" placeholder="Titre" required>
    <textarea name="contenu" placeholder="Contenu" required></textarea>
    <input type="file" name="image">
    <button type="submit" name="ajouter" class="btn">Publier</button>
</form>
</div>

<?php while($a = $result->fetch_assoc()){ ?>
<div class="card">
<?php if(isset($_GET['edit']) && $_GET['edit'] == $a['id_actualite']){ ?>
<form method="POST">
    <input type="hidden" name="id" value="<?php echo $a['id_actualite']; ?>">
    <input type="text" name="titre" value="<?php echo htmlspecialchars($a['titre']); ?>">
    <textarea name="contenu"><?php echo htmlspecialchars($a['contenu']); ?></textarea>
    <button type="submit" name="update" class="btn edit">Sauvegarder</button>
</form>
<?php } else { ?>
    <h3><?php echo htmlspecialchars($a['titre']); ?></h3>
    <p><?php echo nl2br(htmlspecialchars($a['contenu'])); ?></p>
    <?php if(!empty($a['image'])){ ?>
        <img src="../images/<?php echo $a['image']; ?>">
    <?php } ?>
    <br><br>
    <a href="?page=actualites&edit=<?php echo $a['id_actualite']; ?>">
        <button class="btn edit">✏ Modifier</button>
    </a>
    <form method="POST" onsubmit="return confirm('Supprimer ?');" style="display:inline;">
        <input type="hidden" name="delete_id" value="<?php echo $a['id_actualite']; ?>">
        <button type="submit" class="btn delete">🗑 Supprimer</button>
    </form>
<?php } ?>
</div>
<?php } ?>