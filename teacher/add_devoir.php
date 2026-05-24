<?php
if(session_status() == PHP_SESSION_NONE){ session_start(); }
require_once "../config/database.php";

if (!isset($_SESSION['enseignant_id'])) { header("Location: ../login.php"); exit(); }

/* ==========================================================
   1. TRAITEMENT MISE À JOUR STATUT
========================================================== */
if(isset($_POST['update_statut'])){
    $realisation_id = intval($_POST['realisation_id']);
    $nouveau_statut = intval($_POST['statut']);
    $stmt_upd = $conn->prepare("UPDATE realisation_devoir SET statut = ? WHERE id_realisation = ?");
    $stmt_upd->bind_param("ii", $nouveau_statut, $realisation_id);
    $stmt_upd->execute();
}

/* ==========================================================
   2. AJOUT DEVOIR
========================================================== */
if(isset($_POST['ajouter'])){
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    $date_limite = $_POST['date_limite'];
    $classe_id = intval($_POST['classe_id']);
    $enseignant_id = $_SESSION['enseignant_id'];
    $image = null;

    if(!empty($_FILES['image']['name'])){
        $image = time() . "_" . basename($_FILES['image']['name']);
        move_uploaded_file($_FILES['image']['tmp_name'], "../images/" . $image);
    }

    $sql = "INSERT INTO devoir (titre, description, image, date_limite, classe_id, enseignant_id) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssii", $titre, $description, $image, $date_limite, $classe_id, $enseignant_id);

    if($stmt->execute()){
        $devoir_id = $conn->insert_id;
        $sql_eleves = "SELECT id_eleve, parent_id FROM eleves WHERE classe_id = ?";
        $stmt_eleves = $conn->prepare($sql_eleves);
        $stmt_eleves->bind_param("i", $classe_id);
        $stmt_eleves->execute();
        $result_eleves = $stmt_eleves->get_result();

        $stmt_realisation = $conn->prepare("INSERT INTO realisation_devoir (devoir_id, eleve_id, statut) VALUES (?, ?, 0)");
        $stmtNotif = $conn->prepare("INSERT INTO notification (parent_id, message, lu, date_creation) VALUES (?, ?, 0, NOW())");
        $messageNotif = "📝 Nouveau devoir ajouté : " . $titre;

        while($eleve = $result_eleves->fetch_assoc()){
            $stmt_realisation->bind_param("ii", $devoir_id, $eleve['id_eleve']);
            $stmt_realisation->execute();
            if(!empty($eleve['parent_id'])) {
                $stmtNotif->bind_param("is", $eleve['parent_id'], $messageNotif);
                $stmtNotif->execute();
            }
        }
        $_SESSION['devoir_success'] = "✅ Devoir ajouté avec succès.";
    }
    header("Location: teacher.php?page=devoirs"); exit();
}

/* ==========================================================
   3. SUPPRESSION
========================================================== */
if(isset($_POST['delete_id'])){
    $id_devoir = intval($_POST['delete_id']);
    $res = $conn->query("SELECT titre, image FROM devoir WHERE id_devoir = $id_devoir")->fetch_assoc();
    if($res){
        if(!empty($res['image']) && file_exists("../images/".$res['image'])) unlink("../images/".$res['image']);
        $conn->query("DELETE FROM notification WHERE message = '📝 Nouveau devoir ajouté : {$res['titre']}'");
        $conn->query("DELETE FROM realisation_devoir WHERE devoir_id = $id_devoir");
        $conn->query("DELETE FROM devoir WHERE id_devoir = $id_devoir");
    }
    header("Location: teacher.php?page=devoirs"); exit();
}

// Récupération des données pour l'affichage
$classes = $conn->query("SELECT * FROM classe ORDER BY nom_classe ASC");
$devoirs = $conn->query("SELECT d.*, c.nom_classe FROM devoir d INNER JOIN classe c ON d.classe_id = c.id_classe ORDER BY d.id_devoir DESC");
?>

<style>
    .form-box{ background:white; padding:25px; border-radius:20px; box-shadow:0 10px 25px rgba(255,204,0,0.15); margin-bottom: 30px; }
    .card { background:#fffdf5; border-left:7px solid #ffcc00; padding:20px; border-radius:18px; margin-bottom:20px; }
    .btn{ background:#ffcc00; color:white; border:none; padding:12px 20px; border-radius:12px; cursor:pointer; }
    .btn-delete { background:#c62828; color:white; border:none; padding:8px; border-radius:8px; cursor:pointer; margin-top:10px; }
</style>

<div class="form-box">
    <h2>📝 Ajouter un devoir</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="text" name="titre" placeholder="Titre" required style="width:100%; padding:10px; margin-bottom:10px;">
        <select name="classe_id" required style="width:100%; padding:10px; margin-bottom:10px;">
            <option value="">-- Choisir une classe --</option>
            <?php while($cl = $classes->fetch_assoc()){ echo "<option value='".$cl['id_classe']."'>".$cl['nom_classe']."</option>"; } ?>
        </select>
        <textarea name="description" placeholder="Description" required style="width:100%; height:80px; margin-bottom:10px;"></textarea>
        <input type="date" name="date_limite" required style="width:100%; padding:10px; margin-bottom:10px;">
        <button type="submit" name="ajouter" class="btn">Ajouter devoir</button>
    </form>
</div>

<div class="cards">
<?php while($d = $devoirs->fetch_assoc()){ ?>
    <div class="card">
        <h3><?php echo htmlspecialchars($d['titre']); ?></h3>
        <p>Classe : <b><?php echo $d['nom_classe']; ?></b> | Limite : <?php echo $d['date_limite']; ?></p>
        
        <h4>Suivi des travaux :</h4>
        <?php
      
$sql_eleves = "SELECT r.id_realisation, r.statut, e.nom
               FROM realisation_devoir r 
               JOIN eleves e ON r.eleve_id = e.id_eleve 
               WHERE r.devoir_id = ?";

$eleves = $conn->prepare($sql_eleves);
$eleves->bind_param("i", $d['id_devoir']);
$eleves->execute();
$res_e = $eleves->get_result();
        
        while($e = $res_e->fetch_assoc()){
            echo "<form method='POST' style='margin-bottom:5px;'>";
            echo "<input type='hidden' name='realisation_id' value='".$e['id_realisation']."'>";
            echo "<b>{$e['nom']} {$e['prenom']} : </b>";
            if($e['statut'] == 1) {
                echo "<span style='color:green; font-weight:bold;'>✔ Réalisé</span>";
                echo " <button type='submit' name='update_statut' value='1'>Marquer non</button>";
                echo "<input type='hidden' name='statut' value='0'>";
            } else {
                echo "<span style='color:red; font-weight:bold;'>✘ Non réalisé</span>";
                echo " <button type='submit' name='update_statut' value='1'>Marquer fait</button>";
                echo "<input type='hidden' name='statut' value='1'>";
            }
            echo "</form>";
        }
        ?>
        <form method="POST" onsubmit="return confirm('Supprimer ce devoir ?');">
            <input type="hidden" name="delete_id" value="<?php echo $d['id_devoir']; ?>">
            <button type="submit" class="btn-delete">🗑️ Supprimer le devoir</button>
        </form>
    </div>
<?php } ?>
</div>
