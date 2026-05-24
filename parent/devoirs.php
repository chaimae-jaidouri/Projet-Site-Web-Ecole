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
   ENFANT + DEVOIRS
========================= */
$sql_enfant = "SELECT id_eleve, classe_id FROM eleves WHERE parent_id = ? LIMIT 1";
$stmt_enfant = $conn->prepare($sql_enfant);
$stmt_enfant->bind_param("i", $id_parent);
$stmt_enfant->execute();
$res_enfant = $stmt_enfant->get_result()->fetch_assoc();

if($res_enfant) {
    $classe_id = $res_enfant['classe_id'];
    $id_eleve = $res_enfant['id_eleve'];

    $sql = "
        SELECT d.*, COALESCE(rd.statut, 0) as statut_realisation
        FROM devoir d
        LEFT JOIN realisation_devoir rd 
        ON d.id_devoir = rd.devoir_id AND rd.eleve_id = ?
        WHERE d.classe_id = ?
        ORDER BY d.id_devoir DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id_eleve, $classe_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = false;
}
?>

<style>
.devoirs-container{
    display:flex;
    flex-direction:column;
    gap:20px;
    margin-top:20px;
}

.devoir-card{
    background:white;
    border-radius:18px;
    box-shadow:0 5px 15px rgba(0,0,0,0.08);
    overflow:hidden;
    transition:0.3s;
}

.devoir-card:hover{
    transform:translateY(-3px);
}

.devoir-content{
    padding:15px;
}

.devoir-title{
    font-size:18px;
    font-weight:bold;
    color:#d4a800;
    margin-bottom:8px;
}

.devoir-desc{
    color:#555;
    margin-bottom:10px;
    line-height:1.5;
}

.devoir-date{
    font-size:13px;
    color:#777;
    margin-top:10px;
    margin-bottom:12px;
}

.devoir-img{
    width:100%;
    max-height:250px;
    object-fit:cover;
}

.badge{
    display:inline-block;
    padding:5px 10px;
    border-radius:12px;
    font-size:12px;
    background:#ffcc00;
    color:white;
    margin-top:8px;
}

.no-image{
    padding:20px;
    text-align:center;
    color:#999;
    font-style:italic;
    background:#fafafa;
}

.text-realise{
    color:#2e7d32;
    font-weight:bold;
}

.text-non-realise{
    color:#c62828;
    font-weight:bold;
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

<h2>📝 Devoirs de mon enfant</h2>

<div class="devoirs-container">

<?php 
if($result && $result->num_rows > 0){
    while($d = $result->fetch_assoc()){ 
?>

    <div class="devoir-card">

        <?php if(!empty($d['image'])){ ?>
            <img src="../images/<?php echo $d['image']; ?>" class="devoir-img">
        <?php } else { ?>
            <div class="no-image">📄 Devoir sans image</div>
        <?php } ?>

        <div class="devoir-content">

            <div class="devoir-title">
                <?php echo htmlspecialchars($d['titre']); ?>
            </div>

            <div class="devoir-desc">
                <?php echo nl2br(htmlspecialchars($d['description'])); ?>
            </div>

            <div class="devoir-date">
                📅 Date limite : <b><?php echo $d['date_limite']; ?></b>
            </div>

            <div>
                Statut :
                <?php if($d['statut_realisation'] == 1){ ?>
                    <span class="text-realise">✔ Réalisé</span>
                <?php } else { ?>
                    <span class="text-non-realise">✖ Non réalisé</span>
                <?php } ?>
            </div>

            <span class="badge">📚 Devoir</span>

        </div>
    </div>

<?php 
    } 
} else {
    echo "<p style='color:#888;'>Aucun devoir disponible.</p>";
}
?>

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

<!-- JS SAME LOGIC -->
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