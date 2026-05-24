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

/* =========================
   NOTIFICATION FLASH (SAME LOGIC)
========================= */
$notif_flash = null;

$check_notif = $conn->prepare("
SELECT id_notification, message 
FROM notification 
WHERE parent_id = ? AND lu = 0 
ORDER BY id_notification DESC 
LIMIT 1
");

$check_notif->bind_param("i", $id_parent);
$check_notif->execute();

$res_notif = $check_notif->get_result()->fetch_assoc();

if($res_notif){

    $notif_flash = $res_notif['message'];

    $up = $conn->prepare("
    UPDATE notification 
    SET lu = 1 
    WHERE id_notification = ?
    ");

    $up->bind_param("i", $res_notif['id_notification']);
    $up->execute();
}

/* =========================
   CLASSE ELEVE
========================= */
$sql_enfant = "SELECT classe_id FROM eleves WHERE parent_id = ? LIMIT 1";
$stmt_enfant = $conn->prepare($sql_enfant);
$stmt_enfant->bind_param("i", $id_parent);
$stmt_enfant->execute();
$res_enfant = $stmt_enfant->get_result()->fetch_assoc();

if($res_enfant) {
    $classe_id = $res_enfant['classe_id'];

    $sql = "
    SELECT r.*,
           e.nom AS enseignant_nom
    FROM remarque r
    LEFT JOIN enseignant e ON r.enseignant_id = e.id_enseignant
    WHERE r.classe_id = ?
    ORDER BY r.id_remarque DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $classe_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = false;
}
?>

<style>
.remarques-container{
    display:flex;
    flex-direction:column;
    gap:18px;
    margin-top:20px;
}

.remarque-card{
    background:white;
    border-radius:18px;
    box-shadow:0 5px 15px rgba(0,0,0,0.08);
    padding:15px;
    transition:0.3s;
    border-left:6px solid #ffcc00;
}

.remarque-card:hover{
    transform:translateY(-3px);
}

.remarque-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:10px;
}

.remarque-from{
    font-weight:bold;
    color:#d4a800;
}

.remarque-type{
    font-size:12px;
    padding:4px 10px;
    border-radius:12px;
    color:white;
    font-weight:bold;
}

.admin{ background:#007bff; }
.teacher{ background:#28a745; }

.remarque-text{
    color:#555;
    line-height:1.5;
    margin-top:8px;
    font-size:15px;
}

.remarque-date{
    font-size:12px;
    color:#888;
    margin-top:10px;
}

.no-data{
    text-align:center;
    color:#aaa;
    padding:30px;
    font-style:italic;
}

/* POPUP */
.popup-notif{
position:fixed;
bottom:20px;
right:20px;
background:white;
padding:15px;
border-left:5px solid #dc3545;
box-shadow:0 5px 20px rgba(0,0,0,0.2);
border-radius:10px;
z-index:9999;
display:none;
}
</style>

<h2>✏️ Remarques de la classe</h2>

<div class="remarques-container">

<?php if(!$result || $result->num_rows == 0){ ?>
    <div class="no-data">
        Aucune remarque pour cette classe pour le moment.
    </div>
<?php } else { ?>

    <?php while($r = $result->fetch_assoc()){ ?>
        <div class="remarque-card">

            <div class="remarque-header">

                <div class="remarque-from">
                    👤 <?php echo !empty($r['enseignant_nom']) 
                        ? "Prof. " . htmlspecialchars($r['enseignant_nom']) 
                        : "Administrateur"; ?>
                </div>

                <div>
                    <?php if(!empty($r['admin_id'])){ ?>
                        <span class="remarque-type admin">Admin</span>
                    <?php } else { ?>
                        <span class="remarque-type teacher">Prof</span>
                    <?php } ?>
                </div>

            </div>

            <div class="remarque-text">
                <?php echo nl2br(htmlspecialchars($r['contenu'])); ?>
            </div>

            <div class="remarque-date">
                📅 <?php echo $r['date_remarque']; ?>
            </div>

        </div>
    <?php } ?>

<?php } ?>

</div>

<!-- AUDIO -->
<audio id="notifAudio" src="/ecole/assests/audio.mp3" preload="auto"></audio>

<!-- POPUP -->
<div id="popupNotif" class="popup-notif">
    <p id="popupText" style="margin:0;font-weight:bold;"></p>
    <button onclick="document.getElementById('popupNotif').style.display='none'">
        Fermer
    </button>
</div>

<script>
window.addEventListener('DOMContentLoaded', function () {

    let messageFlash = <?php echo json_encode($notif_flash); ?>;

    if(messageFlash !== null && messageFlash !== ""){

        let audio = document.getElementById("notifAudio");

        if(audio){
            audio.currentTime = 0;
            audio.play().catch(err => {
                console.log("Audio bloqué :", err);
            });
        }

        document.getElementById("popupText").innerText = messageFlash;
        document.getElementById("popupNotif").style.display = "block";
    }

});
</script>