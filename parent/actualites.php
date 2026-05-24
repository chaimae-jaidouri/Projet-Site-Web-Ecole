<?php
/* =========================
   ACTUALITES
========================= */
$sql = "
SELECT *
FROM actualite
ORDER BY id_actualite DESC
";
$result = $conn->query($sql);

/* =========================
   NOTIFICATION FLASH (IMPORTANT)
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
?>

<style>
.news-card{
    background:white;
    padding:20px;
    border-radius:18px;
    margin-bottom:20px;
    box-shadow:0 10px 25px rgba(255,204,0,0.15);
    animation:fadeNews 0.5s ease;
}
.news-title{
    font-size:22px;
    font-weight:bold;
    color:#d4a800;
    margin-bottom:12px;
}
.news-content{
    color:#555;
    line-height:1.7;
    margin-bottom:15px;
}
.news-image{
    width:100%;
    max-width:500px;
    border-radius:15px;
    margin-top:10px;
}
.news-date{
    margin-top:12px;
    font-size:13px;
    color:#999;
}
.empty{
    color:#888;
    font-size:16px;
}
@keyframes fadeNews{
    from{opacity:0; transform:translateY(15px);}
    to{opacity:1; transform:translateY(0);}
}
</style>

<h2>📰 Actualités</h2>
<br>

<?php
if($result->num_rows > 0){
    while($actu = $result->fetch_assoc()){
?>
        <div class="news-card">

            <div class="news-title">
                <?php echo htmlspecialchars($actu['titre']); ?>
            </div>

            <div class="news-content">
                <?php echo nl2br(htmlspecialchars($actu['contenu'])); ?>
            </div>

            <?php if(!empty($actu['image'])){ ?>
                <img src="../images/<?php echo $actu['image']; ?>" class="news-image">
            <?php } ?>

            <div class="news-date">
                📅 <?php echo $actu['date_publication']; ?>
            </div>

        </div>
<?php
    }
} else {
    echo "<div class='empty'>Aucune actualité disponible.</div>";
}
?>

<!-- AUDIO -->
<audio id="notifAudio" src="/ecole/assests/audio.mp3" preload="auto"></audio>

<!-- POPUP -->
<div id="popupNotif" class="popup-notif">
    <h4 style="margin:0 0 8px 0; color:#dc3545;">🔔 Nouvelle Alerte actualité</h4>
    <p id="popupText" style="margin:0; font-size:14px; color:#333; font-weight:bold;"></p>
    <button onclick="document.getElementById('popupNotif').style.display='none'"
    style="margin-top:10px; cursor:pointer; font-weight:bold; background:#eee; border:none; padding:5px 10px; border-radius:5px;">
        Fermer
    </button>
</div>

<script>
window.addEventListener('DOMContentLoaded', function () {

    let messageFlash = <?php echo json_encode($notif_flash); ?>;

    if(messageFlash){

        let audio = document.getElementById("notifAudio");

        if(audio){
            audio.currentTime = 0;

            audio.play().catch(error => {
                console.log("Audio bloqué :", error);
            });
        }

        document.getElementById("popupText").innerText = messageFlash;
        document.getElementById("popupNotif").style.display = "block";
    }

});
</script>