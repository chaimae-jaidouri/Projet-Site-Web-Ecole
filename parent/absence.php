<?php
if(session_status() == PHP_SESSION_NONE){
    session_start();
}
require_once "../config/database.php";

if (!isset($_SESSION['parent_id'])) {
    header("Location: ../login.php");
    exit();
}

$id_parent = $_SESSION['parent_id'];

/* ==========================================================
   1. VÉRIFICATION EN DIRECT D'UNE NOUVELLE NOTIFICATION
========================================================== */
$notif_flash = null;
$check_notif = $conn->prepare("SELECT id_notification, message FROM notification WHERE parent_id = ? AND lu = 0 ORDER BY id_notification DESC LIMIT 1");
$check_notif->bind_param("i", $id_parent);
$check_notif->execute();
$res_notif = $check_notif->get_result()->fetch_assoc();

if ($res_notif) {
    $notif_flash = $res_notif['message'];
    // On la marque comme lue pour éviter qu'elle ne re-sonne au prochain clic
    $up_notif = $conn->prepare("UPDATE notification SET lu = 1 WHERE id_notification = ?");
    $up_notif->bind_param("i", $res_notif['id_notification']);
    $up_notif->execute();
}

/* ==========================================================
   2. RÉCUPÉRATION DE L'ENFANT ET DE SES ABSENCES
========================================================== */
$sql_enfant = "SELECT id_eleve, nom FROM eleves WHERE parent_id = ? LIMIT 1";
$stmt_enfant = $conn->prepare($sql_enfant);
$stmt_enfant->bind_param("i", $id_parent);
$stmt_enfant->execute();
$res_enfant = $stmt_enfant->get_result()->fetch_assoc();

if($res_enfant) {
    $eleve_id = $res_enfant['id_eleve'];
    $nom_enfant = $res_enfant['nom'];

    $sql = "SELECT date_absence, seance, statut FROM absence WHERE eleve_id = ? ORDER BY date_absence DESC, seance ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $eleve_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $historique_absences = [];
    while($row = $result->fetch_assoc()){
        $date = $row['date_absence'];
        $historique_absences[$date][$row['seance']] = $row['statut'];
    }
} else {
    $historique_absences = [];
}
?>

<style>
.abs-table{ width:100%; border-collapse:collapse; background:white; border-radius:15px; overflow:hidden; box-shadow:0 5px 15px rgba(0,0,0,0.08); margin-top:15px; }
.abs-table th{ background:#ffcc00; color:white; padding:12px; text-align:center; }
.abs-table td{ padding:12px; text-align:center; border:1px solid #eee; font-weight:bold; }
.present{ background:#28a745; color:white; border-radius:8px; padding:5px 10px; }
.absent{ background:#dc3545; color:white; border-radius:8px; padding:5px 10px; }
.title{ margin-bottom:5px; color:#444; }
.subtitle{ color:#888; font-size:14px; margin-bottom:20px; }
.no-data{ text-align:center; color:#aaa; padding:30px; font-style:italic; background:white; border-radius:15px; }

/* Style du Pop-up d'alerte */
.popup-notif { position:fixed; bottom:20px; right:20px; background:#fff; border-left:6px solid #dc3545; box-shadow:0 5px 25px rgba(0,0,0,0.2); padding:20px; border-radius:10px; z-index:9999; max-width:350px; display:none; }
</style>

<h2 class="title">🚫 Absence de mon enfant</h2>
<p class="subtitle">Enfant : <strong><?php echo htmlspecialchars($nom_enfant ?? '-'); ?></strong></p>

<?php if(empty($historique_absences)){ ?>
    <div class="no-data">Aucune absence ou présence enregistrée pour le moment.</div>
<?php } else { ?>

<table class="abs-table">
    <tr>
        <th>Date</th>
        <th>Jour</th>
        <th>08:30 - 10:25</th>
        <th>10:30 - 12:00</th>
        <th>12:30 - 14:30</th>
    </tr>

    <?php
    $jours_fr = [1 => "Lundi", 2 => "Mardi", 3 => "Mercredi", 4 => "Jeudi", 5 => "Vendredi", 6 => "Samedi", 7 => "Dimanche"];
    foreach($historique_absences as $date_dispo => $seances_data){
        $num_jour = date('N', strtotime($date_dispo));
    ?>
    <tr>
        <td style="color:#ffcc00;"><?php echo date('d/m/Y', strtotime($date_dispo)); ?></td>
        <td><?php echo $jours_fr[$num_jour] ?? ''; ?></td>

        <?php for($s = 1; $s <= 3; $s++){
            $val = $seances_data[$s] ?? "-";
            if($val == "P"){ echo "<td><span class='present'>Présent</span></td>"; }
            elseif($val == "A"){ echo "<td><span class='absent'>Absent</span></td>"; }
            else{ echo "<td>-</td>"; }
        } ?>
    </tr>
    <?php } ?>
</table>
<?php } ?>

<audio id="notifAudio" src="/ecole/assests/audio.mp3" preload="auto"></audio>

<div id="popupNotif" class="popup-notif">
    <h4 style="margin:0 0 8px 0; color:#dc3545;">🔔 Nouvelle Alerte Absence</h4>
    <p id="popupText" style="margin:0; font-size:14px; color:#333; font-weight:bold;"></p>
    <button onclick="document.getElementById('popupNotif').style.display='none'" style="margin-top:10px; cursor:pointer; font-weight:bold; background:#eee; border:none; padding:5px 10px; border-radius:5px;">Fermer</button>
</div>

<script>
window.addEventListener('DOMContentLoaded', (event) => {
    // On récupère le message généré par PHP s'il existe
    let messageFlash = "<?php echo $notif_flash; ?>";
    
    if(messageFlash !== "") {
        // 1. Jouer le son
        let audio = document.getElementById("notifAudio");
        audio.play().catch(error => console.log("L'audio attend une action utilisateur :", error));
        
        // 2. Afficher le Pop-up
        document.getElementById("popupText").innerText = messageFlash;
        document.getElementById("popupNotif").style.display = "block";
    }
});
</script>