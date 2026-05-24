<?php
if(session_status() == PHP_SESSION_NONE){
    session_start();
}
require_once "../config/database.php";

if (!isset($_SESSION['enseignant_id'])) {
    header("Location: ../login.php");
    exit();
}

$id_enseignant = $_SESSION['enseignant_id'];

$success = "";
$error = "";

// Récupération des messages de session après redirection (anti-duplication)
if(isset($_SESSION['remarque_success'])){
    $success = $_SESSION['remarque_success'];
    unset($_SESSION['remarque_success']);
}
if(isset($_SESSION['remarque_error'])){
    $error = $_SESSION['remarque_error'];
    unset($_SESSION['remarque_error']);
}

// Mode Édition / Modification
$mode_edition = false;
$edit_id = 0;
$edit_contenu = "";
$edit_classe_id = 0;
$edit_eleve_id = null;

if (isset($_GET['action']) && $_GET['action'] == 'modifier' && isset($_GET['id'])) {
    $mode_edition = true;
    $edit_id = intval($_GET['id']);
    $stmt_edit = $conn->prepare("SELECT * FROM remarque WHERE id_remarque = ? AND enseignant_id = ?");
    $stmt_edit->bind_param("ii", $edit_id, $id_enseignant);
    $stmt_edit->execute();
    $remarque_data = $stmt_edit->get_result()->fetch_assoc();
    if ($remarque_data) {
        $edit_contenu = $remarque_data['contenu'];
        $edit_classe_id = $remarque_data['classe_id'];
        $edit_eleve_id = $remarque_data['eleve_id'];
    }
}

/* ==========================================================
   TRAITEMENT 1 : SUPPRESSION D'UNE REMARQUE
========================================================== */
if (isset($_POST['action_supprimer'])) {
    $id_remarque_del = intval($_POST['del_remarque_id']);
    $del = $conn->prepare("DELETE FROM remarque WHERE id_remarque = ? AND enseignant_id = ?");
    $del->bind_param("ii", $id_remarque_del, $id_enseignant);
    if ($del->execute()) {
        $_SESSION['remarque_success'] = "🗑️ La remarque a été supprimée avec succès.";
    } else {
        $_SESSION['remarque_error'] = "❌ Impossible de supprimer cette remarque.";
    }
    echo "<script>window.location.href='teacher.php?page=remarques';</script>";
    exit();
}

/* ==========================================================
   TRAITEMENT 2 : AJOUT OU MODIFICATION (POST)
========================================================== */
if (isset($_POST['enregistrer_remarque'])) {
    $classe_id = intval($_POST['classe_id']);
    $remarque = trim($_POST['remarque']);
    $eleve_id = (!empty($_POST['eleve_id'])) ? intval($_POST['eleve_id']) : null; 

    if (isset($_POST['edit_id']) && intval($_POST['edit_id']) > 0) {
        /* --- MODE MODIFICATION --- */
        $id_remarque_update = intval($_POST['edit_id']);
        $up = $conn->prepare("UPDATE remarque SET classe_id = ?, eleve_id = ?, contenu = ? WHERE id_remarque = ? AND enseignant_id = ?");
        $up->bind_param("iisii", $classe_id, $eleve_id, $remarque, $id_remarque_update, $id_enseignant);
        
        if ($up->execute()) {
            $_SESSION['remarque_success'] = "📝 Remarque mise à jour avec succès !";
        } else {
            $_SESSION['remarque_error'] = "❌ Erreur lors de la mise à jour.";
        }
    } else {
        /* --- MODE NOUVEL AJOUT --- */
        $sql = "INSERT INTO remarque (classe_id, eleve_id, contenu, enseignant_id, date_remarque) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisi", $classe_id, $eleve_id, $remarque, $id_enseignant);

        if ($stmt->execute()) {
            // Envoi de la notification
            $messageNotif = "✏️ Nouvelle remarque : " . (mb_strimwidth($remarque, 0, 40, "..."));
            $sqlNotif = "INSERT INTO notification (parent_id, message, lu, date_creation) VALUES (?, ?, 0, NOW())";
            $stmtNotif = $conn->prepare($sqlNotif);

            if ($eleve_id !== null) {
                // Pour un seul étudiant
                $sql_parent_unique = "SELECT parent_id FROM eleves WHERE id_eleve = ? AND parent_id IS NOT NULL";
                $stmt_pu = $conn->prepare($sql_parent_unique);
                $stmt_pu->bind_param("i", $eleve_id);
                $stmt_pu->execute();
                $res_pu = $stmt_pu->get_result()->fetch_assoc();
                if ($res_pu) {
                    $stmtNotif->bind_param("is", $res_pu['parent_id'], $messageNotif);
                    $stmtNotif->execute();
                }
            } else {
                // Pour toute la classe
                $sql_parents = "SELECT DISTINCT parent_id FROM eleves WHERE classe_id = ? AND parent_id IS NOT NULL";
                $stmt_ps = $conn->prepare($sql_parents);
                $stmt_ps->bind_param("i", $classe_id);
                $stmt_ps->execute();
                $result_ps = $stmt_ps->get_result();
                while ($row_p = $result_ps->fetch_assoc()) {
                    $stmtNotif->bind_param("is", $row_p['parent_id'], $messageNotif);
                    $stmtNotif->execute();
                }
            }
            $_SESSION['remarque_success'] = "✅ Remarque ajoutée avec succès et parents notifiés.";
        } else {
            $_SESSION['remarque_error'] = "❌ Erreur lors de l'ajout de la remarque.";
        }
    }

    // REDIRECTION STRICTE ANTI-DUPLICATION (F5 SÉCURISÉ)
    echo "<script>window.location.href='teacher.php?page=remarques';</script>";
    exit();
}

/* ==========================================================
   RECUPERATION DES CLASSES, ELEVES ET HISTORIQUE DES REMARQUES
========================================================== */
$classes = $conn->query("SELECT * FROM classe ORDER BY nom_classe ASC");

$all_eleves = $conn->query("SELECT id_eleve, nom, classe_id FROM eleves ORDER BY nom ASC");
$eleves_list = [];
while($e = $all_eleves->fetch_assoc()){
    $eleves_list[] = $e;
}

// Récupération de l'historique des remarques de cet enseignant pour le tableau de bord
$stmt_history = $conn->prepare("
    SELECT r.*, c.nom_classe, e.nom as nom_eleve 
    FROM remarque r
    LEFT JOIN classe c ON r.classe_id = c.id_classe
    LEFT JOIN eleves e ON r.eleve_id = e.id_eleve
    WHERE r.enseignant_id = ? 
    ORDER BY r.date_remarque DESC
");
$stmt_history->bind_param("i", $id_enseignant);
$stmt_history->execute();
$historique_remarques = $stmt_history->get_result();
?>

<style>
.form-box{ background:white; padding:25px; border-radius:20px; box-shadow:0 10px 25px rgba(255,204,0,0.15); margin-bottom: 30px; }
h2, h3{ color:#d4a800; margin-bottom:20px; }
.input-group{ margin-bottom:15px; }
label{ font-weight:bold; display:block; margin-bottom:8px; color:#555; }
select, textarea{ width:100%; padding:12px; border:2px solid #ffe082; border-radius:10px; outline:none; font-family: inherit;}
textarea{ height:120px; resize:none; }
.btn{ background:#ffcc00; color:white; border:none; padding:12px 18px; border-radius:12px; cursor:pointer; font-weight:bold; font-size: 15px;}
.btn:hover{ background:#e6b800; }
.btn-cancel { background: #bbb; text-decoration:none; padding:11px 15px; margin-left:10px; color:white; border-radius:10px; font-weight:bold; font-size:14px;}
.success{ background:#e8f5e9; color:#2e7d32; padding:12px; border-radius:10px; margin-bottom:15px; font-weight:bold; }
.error-msg{ background:#ffebee; color:#c62828; padding:12px; border-radius:10px; margin-bottom:15px; font-weight:bold; }

/* Styles du tableau de bord */
.table-responsive { background: white; padding: 20px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-top: 20px; }
table { width: 100%; border-collapse: collapse; text-align: left; }
th, td { padding: 12px 15px; border-bottom: 1px solid #eee; }
th { background-color: #fff9e6; color: #d4a800; font-weight: bold; }
.badge { padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: bold; }
.badge-classe { background: #e3f2fd; color: #0d47a1; }
.badge-eleve { background: #fff3e0; color: #e65100; }
.action-links { display: flex; gap: 10px; }
.lnk-edit { color: #28a745; text-decoration: none; font-weight: bold; font-size: 13px; }
.lnk-del { color: #dc3545; background: none; border: none; padding: 0; cursor: pointer; font-weight: bold; font-size: 13px; font-family: inherit; }
</style>

<div class="form-box">
    <h2>✏️ <?php echo $mode_edition ? "Modifier la remarque" : "Ajouter une remarque"; ?></h2>

    <?php if(!empty($success)){ ?><div class="success"><?php echo $success; ?></div><?php } ?>
    <?php if(!empty($error)){ ?><div class="error-msg"><?php echo $error; ?></div><?php } ?>

    <form method="POST">
        <?php if($mode_edition){ ?>
            <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
        <?php } ?>

        <div class="input-group">
            <label>Classe</label>
            <select name="classe_id" id="classe_select" required onchange="filtrerEleves()">
                <option value="">-- Choisir classe --</option>
                <?php 
                $classes->data_seek(0);
                while($c = $classes->fetch_assoc()){ 
                ?>
                    <option value="<?php echo $c['id_classe']; ?>" <?php if($edit_classe_id == $c['id_classe']) echo "selected"; ?>>
                        <?php echo $c['nom_classe']; ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <div class="input-group" id="student_group">
            <label>Étudiant (Optionnel)</label>
            <select name="eleve_id" id="eleve_select">
                <option value="">-- Toute la classe --</option>
            </select>
        </div>

        <div class="input-group">
            <label>Contenu de la remarque</label>
            <textarea name="remarque" required placeholder="Écrivez votre remarque ici..."><?php echo htmlspecialchars($edit_contenu); ?></textarea>
        </div>

        <button type="submit" name="enregistrer_remarque" class="btn">💾 <?php echo $mode_edition ? "Mettre à jour" : "Ajouter la remarque"; ?></button>
        <?php if($mode_edition){ ?>
            <a href="teacher.php?page=remarques" class="btn-cancel">Annuler</a>
        <?php } ?>
    </form>
</div>

<h3>📋 Historique de vos remarques envoyées</h3>
<div class="table-responsive">
    <?php if($historique_remarques->num_rows > 0){ ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Cible</th>
                    <th>Contenu</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $historique_remarques->fetch_assoc()){ ?>
                    <tr>
                        <td style="font-size:13px; color:#666;"><?php echo $row['date_remarque']; ?></td>
                        <td>
                            <?php if(!empty($row['nom_eleve'])){ ?>
                                <span class="badge badge-eleve">👤 <?php echo htmlspecialchars($row['nom_eleve']); ?></span>
                            <?php } else { ?>
                                <span class="badge badge-classe">👥 Classe: <?php echo htmlspecialchars($row['nom_classe']); ?></span>
                            <?php } ?>
                        </td>
                        <td style="font-weight: 500; color:#333;"><?php echo htmlspecialchars($row['contenu']); ?></td>
                        <td>
                            <div class="action-links">
                                <a href="teacher.php?page=remarques&action=modifier&id=<?php echo $row['id_remarque']; ?>" class="lnk-edit">Modifier</a>
                                <button type="button" class="lnk-del" onclick="confirmerSuppressionRemarque(<?php echo $row['id_remarque']; ?>)">Supprimer</button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } else { ?>
        <p style="color:#999; text-align:center; padding:10px;">Vous n'avez encore envoyé aucune remarque.</p>
    <?php } ?>
</div>

<form method="POST" id="deleteRemarqueForm" style="display:none;">
    <input type="hidden" name="del_remarque_id" id="del_remarque_id">
    <input type="hidden" name="action_supprimer" value="1">
</form>

<script>
const listeEleves = <?php echo json_encode($eleves_list); ?>;
const valeurEditionEleve = "<?php echo $edit_eleve_id; ?>";

function filtrerEleves() {
    const classeSelect = document.getElementById('classe_select');
    const studentGroup = document.getElementById('student_group');
    const eleveSelect = document.getElementById('eleve_select');
    
    const classeIdSelectionnee = classeSelect.value;
    
    eleveSelect.innerHTML = '<option value="">-- Toute la classe --</option>';
    
    if(classeIdSelectionnee === "") {
        studentGroup.style.display = "none";
        return;
    }
    
    const elevesFiltres = listeEleves.filter(eleve => eleve.classe_id == classeIdSelectionnee);
    
    if(elevesFiltres.length > 0) {
        elevesFiltres.forEach(eleve => {
            const option = document.createElement('option');
            option.value = eleve.id_eleve;
            option.textContent = eleve.nom;
            if(valeurEditionEleve != "" && eleve.id_eleve == valeurEditionEleve) {
                option.selected = true;
            }
            eleveSelect.appendChild(option);
        });
        studentGroup.style.display = "block";
    } else {
        eleveSelect.innerHTML = '<option value="">Aucun élève dans cette classe</option>';
        studentGroup.style.display = "block";
    }
}

function confirmerSuppressionRemarque(idRemarque) {
    if(confirm('Êtes-vous sûr de vouloir supprimer cette remarque définitivement ?')) {
        document.getElementById('del_remarque_id').value = idRemarque;
        document.getElementById('deleteRemarqueForm').submit();
    }
}

// Lancement automatique du filtre au chargement si on est en mode édition
window.addEventListener('DOMContentLoaded', () => {
    if(document.getElementById('classe_select').value !== "") {
        filtrerEleves();
    }
});
</script>