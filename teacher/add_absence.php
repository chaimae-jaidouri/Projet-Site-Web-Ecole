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
$date_aujourdhui = date("Y-m-d");

$seances = [
    1 => "08:30-10:25",
    2 => "10:30-12:00",
    3 => "12:30-14:30"
];

// On garde en mémoire la classe sélectionnée pour la réafficher après un rafraîchissement
$classe_selectionnee = isset($_POST['classe_id']) ? intval($_POST['classe_id']) : 0;

/* ==========================================================
   TRAITEMENT 1 : SUPPRESSION D'UNE SAISIE (Via POST pour éviter les bugs)
========================================================== */
if (isset($_POST['action_supprimer'])) {
    $eleve_id = intval($_POST['del_eleve']);
    $seance_id = intval($_POST['del_seance']);
    $classe_selectionnee = intval($_POST['classe_id']); // Conserver la classe active
    
    $del = $conn->prepare("DELETE FROM absence WHERE date_absence = ? AND eleve_id = ? AND seance = ?");
    $del->bind_param("sii", $date_aujourdhui, $eleve_id, $seance_id);
    if($del->execute()) {
        $success = "🗑️ Enregistrement d'absence annulé et supprimé.";
    }
}

/* ==========================================================
   TRAITEMENT 2 : ENREGISTREMENT ET MODIFICATION (UPDATE)
========================================================== */
if (isset($_POST['save_absence'])) {
    $classe_selectionnee = intval($_POST['classe_id']);
    
    if (isset($_POST['status'])) {
        foreach ($_POST['status'] as $eleve_id => $seances_data) {
            foreach ($seances_data as $seance => $statut) {
                
                $statut = strtoupper(trim($statut));
                if ($statut == "A" || $statut == "P") {
                    
                    // Vérifier si la séance existe déjà aujourd'hui
                    $check = $conn->prepare("SELECT id_absence, statut FROM absence WHERE date_absence = ? AND eleve_id = ? AND seance = ?");
                    $check->bind_param("sii", $date_aujourdhui, $eleve_id, $seance);
                    $check->execute();
                    $existe = $check->get_result()->fetch_assoc();

                    if($existe) {
                        // Si le statut a changé, on met à jour (Modification)
                        if($existe['statut'] != $statut) {
                            $up = $conn->prepare("UPDATE absence SET statut = ?, enseignant_id = ? WHERE id_absence = ?");
                            $up->bind_param("sii", $statut, $id_enseignant, $existe['id_absence']);
                            $up->execute();
                        }
                    } else {
                        // Sinon, nouvelle insertion
                        $ins = $conn->prepare("INSERT INTO absence (date_absence, statut, eleve_id, enseignant_id, seance) VALUES (?, ?, ?, ?, ?)");
                        $ins->bind_param("ssiis", $date_aujourdhui, $statut, $eleve_id, $id_enseignant, $seance);
                        $ins->execute();
                    }

                    /* ==========================================================
                       ENVOI NOTIFICATION (Uniquement si nouvellement ABSENT "A")
                    ========================================================== */
                    if ($statut == "A" && (!$existe || $existe['statut'] != "A")) {
                        $sql_p = "SELECT parent_id, nom FROM eleves WHERE id_eleve = ?";
                        $stmt_p = $conn->prepare($sql_p);
                        $stmt_p->bind_param("i", $eleve_id);
                        $stmt_p->execute();
                        $info_eleve = $stmt_p->get_result()->fetch_assoc();

                        if ($info_eleve && !empty($info_eleve['parent_id'])) {
                            $messageNotif = "🚨 Absence : Votre enfant " . $info_eleve['nom'] . " a été marqué(e) ABSENT(E) pour la séance de " . $seances[$seance] . ".";
                            $sqlNotif = "INSERT INTO notification (parent_id, message, lu, date_creation) VALUES (?, ?, 0, NOW())";
                            $stmtNotif = $conn->prepare($sqlNotif);
                            $stmtNotif->bind_param("is", $info_eleve['parent_id'], $messageNotif);
                            $stmtNotif->execute();
                        }
                    }
                }
            }
        }
        $success = "✅ Les modifications ont été enregistrées avec succès.";
    }
}

$classes = $conn->query("SELECT * FROM classe ORDER BY nom_classe ASC");
?>

<style>
.form-box{ background:white; padding:25px; border-radius:20px; box-shadow:0 10px 25px rgba(255,204,0,0.15); }
h2{ color:#d4a800; margin-bottom:20px; }
.success{ background:#d4edda; color:#155724; padding:12px; border-radius:10px; margin-bottom:15px; font-weight:bold; }
label{ font-weight:bold; display:block; margin-bottom:10px; }
select{ width:100%; padding:12px; border-radius:10px; border:2px solid #ffe082; outline:none; }
table{ width:100%; border-collapse:collapse; margin-top:20px; }
th{ background:#ffcc00; color:white; padding:12px; font-size:14px; }
td{ text-align:center; border:1px solid #eee; padding:10px; }
.student{ font-weight:bold; background:#fffdf5; text-align:left; padding-left:15px; }
.abs { width:45px; height:35px; text-align:center; border:2px solid #ddd; border-radius:8px; font-weight:bold; font-size:15px; }
.btn{ margin-top:25px; background:#ffcc00; color:white; border:none; padding:13px 20px; border-radius:10px; font-weight:bold; cursor:pointer; }
.btn-del { background:none; border:none; color: #dc3545; font-size: 20px; cursor: pointer; vertical-align: middle; margin-left: 5px; padding:0; }
.btn-del:hover { color: #a71d2a; }
.info{ margin-top:15px; background:#fff8dc; padding:12px; border-radius:10px; color:#a37b00; font-weight:bold; display:inline-block; }
</style>

<div class="form-box">
    <h2>🚫 Gestion des absences</h2>

    <?php if($success != ""){ ?>
        <div class="success"><?php echo $success; ?></div>
    <?php } ?>

    <form method="POST" id="absenceForm">
        <label>Sélectionner la classe</label>
        <select name="classe_id" onchange="document.getElementById('absenceForm').submit();" required>
            <option value="">-- Choisir classe --</option>
            <?php while($c = $classes->fetch_assoc()){ ?>
                <option value="<?php echo $c['id_classe']; ?>" <?php if($classe_selectionnee == $c['id_classe']) echo "selected"; ?>>
                    <?php echo $c['nom_classe']; ?>
                </option>
            <?php } ?>
        </select>

        <div class="info">📅 Date du jour : <strong><?php echo date("d/m/Y"); ?></strong></div>

        <?php if($classe_selectionnee > 0){ 
            $eleves = $conn->query("SELECT * FROM eleves WHERE classe_id = $classe_selectionnee ORDER BY nom ASC");

            // Récupérer ce qui est déjà saisi aujourd'hui
            $deja_saisi = [];
            $get_abs = $conn->query("SELECT eleve_id, seance, statut FROM absence WHERE date_absence = '$date_aujourdhui'");
            while($data_abs = $get_abs->fetch_assoc()) {
                $deja_saisi[$data_abs['eleve_id']][$data_abs['seance']] = $data_abs['statut'];
            }
        ?>

        <table>
            <tr>
                <th>Élève</th>
                <?php foreach($seances as $key => $horaire){ ?>
                    <th>Séance <?php echo $key; ?><br><small><?php echo $horaire; ?></small></th>
                <?php } ?>
            </tr>

            <?php while($e = $eleves->fetch_assoc()){ ?>
            <tr>
                <td class="student"><?php echo htmlspecialchars($e['nom']); ?></td>
                
                <?php foreach($seances as $key => $horaire){ 
                    $valeur_actuelle = $deja_saisi[$e['id_eleve']][$key] ?? ""; 
                ?>
                <td>
                    <input class="abs" type="text" maxlength="1" placeholder="P/A" value="<?php echo $valeur_actuelle; ?>" name="status[<?php echo $e['id_eleve']; ?>][<?php echo $key; ?>]" onkeyup="this.value=this.value.toUpperCase()">
                    
                    <?php if($valeur_actuelle != "") { ?>
                        <button type="button" class="btn-del" title="Supprimer cette saisie" onclick="confirmerSuppression(<?php echo $e['id_eleve']; ?>, <?php echo $key; ?>)">×</button>
                    <?php } ?>
                </td>
                <?php } ?>
            </tr>
            <?php } ?>
        </table>

        <button type="submit" name="save_absence" class="btn">💾 Enregistrer / Modifier</button>
        <?php } ?>
    </form>
</div>

<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="classe_id" value="<?php echo $classe_selectionnee; ?>">
    <input type="hidden" name="del_eleve" id="del_eleve">
    <input type="hidden" name="del_seance" id="del_seance">
    <input type="hidden" name="action_supprimer" value="1">
</form>

<script>
function confirmerSuppression(eleveId, seanceId) {
    if (confirm('Voulez-vous vraiment annuler et effacer cette présence/absence ?')) {
        document.getElementById('del_eleve').value = eleveId;
        document.getElementById('del_seance').value = seanceId;
        document.getElementById('deleteForm').submit();
    }
}
</script>