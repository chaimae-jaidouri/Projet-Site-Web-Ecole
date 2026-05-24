<?php
if(session_status() == PHP_SESSION_NONE){
    session_start();
}
require_once "../config/database.php";

// Vérification de la session parent
$id_parent = $_SESSION['parent_id'] ?? null;

/* ==========================================================
   TRAITEMENT : MARQUER COMME LU AU CLIC SUR FERMER
========================================================== */
if (isset($_POST['marquer_lu']) && isset($_POST['id_notif_del'])) {
    $id_del = intval($_POST['id_notif_del']);
    $up = $conn->prepare("UPDATE notification SET lu = 1 WHERE id_notification = ?");
    $up->bind_param("i", $id_del);
    $up->execute();
    
    // Rafraîchissement propre de la page pour effacer le pop-up
    echo "<script>window.location.href=window.location.pathname;</script>";
    exit();
}

/* ==========================================================
   RÉCUPÉRATION DE LA NOTIFICATION NON LUE (LU = 0)
========================================================== */
$notif_flash = null;
$id_notif_a_marquer_lue = 0;

if($id_parent){
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

// Récupérer l'historique de toutes les notifications du parent pour l'affichage sur la page
$toutes_les_notifs = [];
if($id_parent){
    $get_all = $conn->prepare("SELECT * FROM notification WHERE parent_id = ? ORDER BY date_creation DESC");
    $get_all->bind_param("i", $id_parent);
    $get_all->execute();
    $toutes_les_notifs = $get_all->get_result();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Notifications</title>
    <style>
        .notif-container { max-width: 600px; margin: 30px auto; font-family: Arial, sans-serif; }
        .notif-box { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 15px; border-left: 5px solid #ffcc00; }
        .notif-box.lu { border-left: 5px solid #ccc; opacity: 0.7; }
        .notif-date { font-size: 12px; color: #999; display: block; margin-top: 8px; }
        h2 { color: #d4a800; }
        
        /* Style du Pop-up de notification en direct */
        #popupNotif {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 20px;
            border-left: 6px solid #ffcc00;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-radius: 12px;
            z-index: 9999;
            max-width: 350px;
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .btn-close {
            background: #ffcc00;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 12px;
            transition: 0.2s;
        }
        .btn-close:hover { background: #e6b800; }
    </style>
</head>
<body>

<div class="notif-container">
    <h2>🔔 Centre de Notifications</h2>
    <hr style="border: 1px solid #ffe082; margin-bottom: 20px;">

    <?php 
    if ($toutes_les_notifs && $toutes_les_notifs->num_rows > 0) {
        while($n = $toutes_les_notifs->fetch_assoc()) { 
    ?>
            <div class="notif-box <?php echo $n['lu'] == 1 ? 'lu' : ''; ?>">
                <p style="margin: 0; font-weight: 500; color: #333;"><?php echo htmlspecialchars($n['message']); ?></p>
                <span class="notif-date">📅 <?php echo $n['date_creation']; ?></span>
            </div>
    <?php 
        }
    } else {
        echo "<p style='color: #999; text-align: center;'>Aucune notification pour le moment.</p>";
    } 
    ?>
</div>

<audio id="notifAudio" src="../assests/audio.mp3" preload="auto"></audio>

<div id="popupNotif">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
        <span style="font-size: 24px;">✨</span>
        <strong style="color: #d4a800; font-size: 16px;">Nouveau Message</strong>
    </div>
    <p id="popupText" style="margin: 0; color: #444; font-size: 14px; line-height: 1.4;"></p>

    <form method="POST">
        <input type="hidden" name="id_notif_del" value="<?php echo $id_notif_a_marquer_lue; ?>">
        <button type="submit" name="marquer_lu" class="btn-close">Fermer</button>
    </form>
</div>

<script>
window.addEventListener('DOMContentLoaded', function () {
    // Récupération sécurisée du message envoyé par PHP
    let messageFlash = <?php echo json_encode($notif_flash); ?>;

    if (messageFlash) {
        // 1. Injection du texte et affichage du Pop-up
        document.getElementById("popupText").innerText = messageFlash;
        document.getElementById("popupNotif").style.display = "block";

        // 2. Déclenchement du son audio.mp3 depuis le dossier assests
        let audio = document.getElementById("notifAudio");
        if (audio) {
            audio.currentTime = 0;
            audio.play().catch(function (error) {
                // Gestion du blocage de sécurité des navigateurs modernes (Chrome/Safari)
                console.log("Le son jouera automatiquement dès que l'utilisateur aura cliqué une fois sur la page :", error);
            });
        }
    }
});
</script>

</body>
</html>