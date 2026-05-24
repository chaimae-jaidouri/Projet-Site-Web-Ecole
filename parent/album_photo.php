<?php
$sql = "
SELECT *
FROM album_photo
ORDER BY id_photo DESC
";
$result = $conn->query($sql);

/* ==========================================================
   NOTIFICATION FLASH SÉCURISÉE (Le son joue avant de marquer lu)
========================================================== */
$notif_flash = null;
$id_notif_a_marquer_lue = 0;

if(isset($id_parent)){
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
        $id_notif_a_marquer_lue = $res_notif['id_notification'];
    }
}

// Traitement POST pour passer le statut à "lu" quand on ferme le pop-up
if (isset($_POST['marquer_lu']) && isset($_POST['id_notif_del'])) {
    $id_del = intval($_POST['id_notif_del']);
    $up = $conn->prepare("UPDATE notification SET lu = 1 WHERE id_notification = ?");
    $up->bind_param("i", $id_del);
    $up->execute();
    
    // Nettoyage de l'URL pour un rafraîchissement propre
    echo "<script>window.location.href=window.location.pathname;</script>";
    exit();
}
?>

<style>
.fb-parent-gallery { display: grid; gap: 4px; border-radius: 15px; overflow: hidden; margin-top: 10px; background: #f9f9f9; max-width: 500px; }
.p-gallery-1 { grid-template-columns: 1fr; }
.p-gallery-2 { grid-template-columns: 1fr 1fr; }
.p-gallery-3 { grid-template-columns: 1fr 1fr; grid-template-rows: 200px 120px; }
.p-gallery-3 img:first-child { grid-column: span 2; height: 100%; }
.p-gallery-4, .p-gallery-multi { grid-template-columns: 1fr 1fr; grid-template-rows: 150px 150px; }

.fb-parent-gallery img { width: 100%; height: 100%; object-fit: cover; min-height: 200px; display: block; }
.p-gallery-multi .img-more-container { position: relative; height: 100%; }
.p-gallery-multi .img-more-container img { filter: brightness(0.4); }
.p-gallery-multi .more-overlay-text { position: absolute; top:0; left:0; width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:white; font-size:24px; font-weight:bold; pointer-events:none;}
</style>

<h2>📸 Album Photo</h2>

<?php while($photo = $result->fetch_assoc()){ 
    $images_parents = !empty($photo['image']) ? explode(',', $photo['image']) : [];
    $total_images_parents = count($images_parents);
?>

<div class="card" style="margin-bottom:25px; padding:15px; background:white; border-radius:15px; box-shadow:0 4px 15px rgba(0,0,0,0.05);">
    <p style="font-size: 15px; color: #333; font-weight: 500;"><?php echo htmlspecialchars($photo['description']); ?></p>

    <?php if($total_images_parents > 0 && !empty($images_parents[0])){ ?>
        
        <?php if($total_images_parents == 1){ ?>
            <div class="fb-parent-gallery p-gallery-1">
                <img src="../images/<?php echo trim($images_parents[0]); ?>" alt="Souvenir">
            </div>
        <?php } elseif($total_images_parents == 2){ ?>
            <div class="fb-parent-gallery p-gallery-2">
                <img src="../images/<?php echo trim($images_parents[0]); ?>">
                <img src="../images/<?php echo trim($images_parents[1]); ?>">
            </div>
        <?php } elseif($total_images_parents == 3){ ?>
            <div class="fb-parent-gallery p-gallery-3">
                <img src="../images/<?php echo trim($images_parents[0]); ?>">
                <img src="../images/<?php echo trim($images_parents[1]); ?>">
                <img src="../images/<?php echo trim($images_parents[2]); ?>">
            </div>
        <?php } elseif($total_images_parents == 4){ ?>
            <div class="fb-parent-gallery p-gallery-4">
                <img src="../images/<?php echo trim($images_parents[0]); ?>">
                <img src="../images/<?php echo trim($images_parents[1]); ?>">
                <img src="../images/<?php echo trim($images_parents[2]); ?>">
                <img src="../images/<?php echo trim($images_parents[3]); ?>">
            </div>
        <?php } else { ?>
            <div class="fb-parent-gallery p-gallery-multi">
                <img src="../images/<?php echo trim($images_parents[0]); ?>">
                <img src="../images/<?php echo trim($images_parents[1]); ?>">
                <img src="../images/<?php echo trim($images_parents[2]); ?>">
                <div class="img-more-container">
                    <img src="../images/<?php echo trim($images_parents[3]); ?>">
                    <div class="more-overlay-text">+<?php echo ($total_images_parents - 3); ?></div>
                </div>
            </div>
        <?php } ?>

    <?php } ?>

    <p style="margin-top:12px;color:#999;font-size:13px;">
        📅 <?php echo $photo['date_publication']; ?>
    </p>
</div>

<?php } ?>

<audio id="notifAudio" src="/ecole/assests/audio.mp3" preload="auto"></audio>

<div id="popupNotif"
style="
display:none;
position:fixed;
bottom:20px;
right:20px;
background:white;
padding:15px;
border-left:5px solid red;
box-shadow:0 5px 20px rgba(0,0,0,0.2);
border-radius:10px;
z-index:9999;
">
    <p id="popupText" style="margin:0;font-weight:bold;"></p>

    <form method="POST" style="margin-top:10px;">
        <input type="hidden" name="id_notif_del" value="<?php echo $id_notif_a_marquer_lue; ?>">
        <button type="submit" name="marquer_lu" style="cursor:pointer; background:#ffcc00; color:white; border:none; padding:6px 12px; border-radius:5px; font-weight:bold;">
            Fermer
        </button>
    </form>
</div>

<script>
window.addEventListener('DOMContentLoaded', function () {
    let messageFlash = <?php echo json_encode($notif_flash); ?>;

    if(messageFlash){
        // Affichage immédiat du texte et du bloc
        document.getElementById("popupText").innerText = messageFlash;
        document.getElementById("popupNotif").style.display = "block";

        // Lancement immédiat du son
        let audio = document.getElementById("notifAudio");
        if(audio){
            audio.currentTime = 0;
            audio.play().catch(err => {
                console.log("Lecture audio en attente d'interaction :", err);
            });
        }
    }
});
</script>