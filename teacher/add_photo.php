<?php
if(session_status() == PHP_SESSION_NONE){
    session_start();
}
require_once "../config/database.php";

// Vérification des accès (Enseignant ou Admin)
$id_enseignant = $_SESSION['enseignant_id'] ?? null;
$id_admin = $_SESSION['admin_id'] ?? null;

if (!$id_enseignant && !$id_admin) {
    header("Location: ../login.php");
    exit();
}

// Détection dynamique des paramètres de la page actuelle pour le routage
$current_page_param = '';
foreach ($_GET as $key => $value) {
    if ($key !== 'action' && $key !== 'id' && $key !== 'success') {
        $current_page_param .= htmlspecialchars($key) . '=' . htmlspecialchars($value) . '&';
    }
}

$success = "";
$error = "";

// Récupération du message de succès après redirection anti-duplication
if(isset($_GET['success'])) {
    $success = "📸 Nouveau souvenir partagé dans l'album avec succès !";
}

// Variables pour le mode "Modification"
$mode_edition = false;
$edit_id = 0;
$edit_description = "";
$edit_images_list = [];
$edit_cible_id = "";

if (isset($_GET['action']) && $_GET['action'] == 'modifier' && isset($_GET['id'])) {
    $mode_edition = true;
    $edit_id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM album_photo WHERE id_photo = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $photo_data = $stmt->get_result()->fetch_assoc();
    if ($photo_data) {
        $edit_description = $photo_data['description'];
        $edit_images_list = !empty($photo_data['image']) ? explode(',', $photo_data['image']) : [];
        $edit_cible_id = $photo_data['cible_id'];
    }
}

/* ==========================================================
   TRAITEMENT 1 : SUPPRESSION COMPLÈTE D'UN SOUVENIR
========================================================== */
if (isset($_POST['action_supprimer'])) {
    $id_photo_del = intval($_POST['del_photo_id']);
    
    $stmt_img = $conn->prepare("SELECT image FROM album_photo WHERE id_photo = ?");
    $stmt_img->bind_param("i", $id_photo_del);
    $stmt_img->execute();
    $img_res = $stmt_img->get_result()->fetch_assoc();
    
    if ($img_res && !empty($img_res['image'])) {
        $liste_images = explode(',', $img_res['image']);
        foreach($liste_images as $img_nom) {
            $chemin_image = "../images/" . trim($img_nom);
            if (file_exists($chemin_image)) {
                unlink($chemin_image);
            }
        }
    }
    
    $del = $conn->prepare("DELETE FROM album_photo WHERE id_photo = ?");
    $del->bind_param("i", $id_photo_del);
    if ($del->execute()) {
        $success = "🗑️ Le souvenir et toutes ses photos ont été supprimés.";
    }
}

/* ==========================================================
   TRAITEMENT 2 : SUPPRESSION D'UNE SEULE PHOTO (En mode édition)
========================================================== */
if (isset($_POST['delete_single_image']) && $mode_edition) {
    $img_to_delete = $_POST['image_to_delete'];
    
    if (file_exists("../images/" . $img_to_delete)) {
        unlink("../images/" . $img_to_delete);
    }
    
    $edit_images_list = array_diff($edit_images_list, [$img_to_delete]);
    $nouvelle_chaîne = implode(',', $edit_images_list);
    
    $up_img = $conn->prepare("UPDATE album_photo SET image = ? WHERE id_photo = ?");
    $up_img->bind_param("si", $nouvelle_chaîne, $edit_id);
    $up_img->execute();
    $success = "🗑️ La photo a été retirée du souvenir.";
}

/* ==========================================================
   TRAITEMENT 3 : AJOUT OU MODIFICATION (POST)
========================================================== */
if (isset($_POST['save_photo'])) {
    $description = trim($_POST['description']);
    $cible_id = !empty($_POST['cible_id']) ? intval($_POST['cible_id']) : 0;
    $cible_type = ($cible_id > 0) ? 'classe' : 'general';

    $fichiers_uploads = [];
    if (isset($_FILES['images']) && $_FILES['images']['error'][0] == 0) {
        $total_files = count($_FILES['images']['name']);
        for ($i = 0; $i < $total_files; $i++) {
            if ($_FILES['images']['error'][$i] == 0) {
                $filename = $_FILES['images']['name'][$i];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $nom_unique = "img_" . time() . "_" . $i . "_" . rand(1000, 9999) . "." . $ext;
                $destination = "../images/" . $nom_unique;
                
                if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $destination)) {
                    $fichiers_uploads[] = $nom_unique;
                }
            }
        }
    }

    if (isset($_POST['edit_id']) && intval($_POST['edit_id']) > 0) {
        /* --- MODE MODIFICATION --- */
        $id_photo_update = intval($_POST['edit_id']);
        
        $anciennes_images = !empty($_POST['current_images']) ? explode(',', $_POST['current_images']) : [];
        $toutes_les_images = array_merge($anciennes_images, $fichiers_uploads);
        $chaîne_images_bdd = implode(',', $toutes_les_images);

        $up = $conn->prepare("UPDATE album_photo SET description = ?, image = ?, cible_type = ?, cible_id = ? WHERE id_photo = ?");
        $up->bind_param("sssii", $description, $chaîne_images_bdd, $cible_type, $cible_id, $id_photo_update);
        if ($up->execute()) {
            // Redirection immédiate pour vider le POST et éviter les bugs
            echo "<script>window.location.href='?".substr($current_page_param, 0, -1)."';</script>";
            exit();
        }
    } else {
        /* --- NOUVEL AJOUT COMPLET --- */
        if (!empty($fichiers_uploads)) {
            $chaîne_images_bdd = implode(',', $fichiers_uploads);
            
            $ins = $conn->prepare("INSERT INTO album_photo (image, description, date_publication, enseignant_id, admin_id, cible_type, cible_id) VALUES (?, ?, NOW(), ?, ?, ?, ?)");
            $ins->bind_param("ssiisi", $chaîne_images_bdd, $description, $id_enseignant, $id_admin, $cible_type, $cible_id);
            
            if ($ins->execute()) {
                /* Notif parents */
                $messageNotif = "✨ Nouveau souvenir : Une nouvelle publication a été ajoutée à l'album !";
                if ($cible_type == 'classe') {
                    $parents = $conn->query("SELECT DISTINCT parent_id FROM eleves WHERE classe_id = $cible_id AND parent_id IS NOT NULL");
                } else {
                    $parents = $conn->query("SELECT DISTINCT parent_id FROM eleves WHERE parent_id IS NOT NULL");
                }
                
                if ($parents && $parents->num_rows > 0) {
                    $stmtNotif = $conn->prepare("INSERT INTO notification (parent_id, message, lu, date_creation) VALUES (?, ?, 0, NOW())");
                    while ($p = $parents->fetch_assoc()) {
                        $stmtNotif->bind_param("is", $p['parent_id'], $messageNotif);
                        $stmtNotif->execute();
                    }
                }

                // REDIRECTION MAGIQUE ANTI-DUPLICATION
                echo "<script>window.location.href='?".$current_page_param."success=1';</script>";
                exit();
            }
        } else {
            $error = "❌ Veuillez sélectionner au moins une photo.";
        }
    }
}

// Liste des classes
$classes = $conn->query("SELECT * FROM classe ORDER BY nom_classe ASC");

// Récupération de l'historique complet
$photos_existantes = $conn->query("SELECT a.*, c.nom_classe FROM album_photo a LEFT JOIN classe c ON a.cible_id = c.id_classe ORDER BY a.id_photo DESC");
?>

<style>
.form-box{ background:white; padding:25px; border-radius:20px; box-shadow:0 10px 25px rgba(255,204,0,0.15); margin-bottom: 30px;}
h2, h3{ color:#d4a800; margin-bottom:20px; }
.success{ background:#d4edda; color:#155724; padding:12px; border-radius:10px; margin-bottom:15px; font-weight:bold; }
.error{ background:#f8d7da; color:#721c24; padding:12px; border-radius:10px; margin-bottom:15px; font-weight:bold; }
label{ font-weight:bold; display:block; margin-top:15px; margin-bottom:5px; }
input[type="file"], textarea, select { width:100%; padding:12px; border-radius:10px; border:2px solid #ffe082; outline:none; font-family: inherit;}
textarea { height: 100px; resize: none; }
.btn{ margin-top:20px; background:#ffcc00; color:white; border:none; padding:13px 20px; border-radius:10px; font-weight:bold; cursor:pointer; font-size:15px; transition:0.3s; }
.btn:hover{ background:#e6b800; }
.btn-cancel { background: #bbb; text-decoration:none; padding:11px 15px; margin-left:10px; color:white; border-radius:10px; font-weight:bold; font-size:14px;}

/* Grid d'affichage des Publications (Style Facebook) */
.grid-photos { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; margin-top: 20px; }
.photo-card { background: white; border-radius: 15px; padding: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: left; border: 1px solid #eee; display: flex; flex-direction: column; justify-content: space-between;}
.photo-card p { font-size: 15px; color: #333; margin: 10px 0; font-weight: 500; }

/* MOSAÏQUE DES IMAGES COMBINÉES */
.fb-gallery { display: grid; gap: 4px; border-radius: 10px; overflow: hidden; margin-bottom: 10px; background: #f9f9f9; }
.gallery-1 { grid-template-columns: 1fr; }
.gallery-2 { grid-template-columns: 1fr 1fr; }
.gallery-3 { grid-template-columns: 1fr 1fr; grid-template-rows: 150px 100px; }
.gallery-3 img:first-child { grid-column: span 2; height: 100%; }
.gallery-4, .gallery-multi { grid-template-columns: 1fr 1fr; grid-template-rows: 120px 120px; }

.fb-gallery img { width: 100%; height: 100%; object-fit: cover; cursor: pointer; min-height: 150px;}
.gallery-multi .img-container-more { position: relative; height: 100%; }
.gallery-multi .img-container-more img { filter: brightness(0.5); }
.gallery-multi .more-overlay { position: absolute; top:0; left:0; width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:white; font-size:22px; font-weight:bold; pointer-events:none;}

.actions { margin-top: 15px; display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #eee; padding-top: 10px; }
.btn-edit { background: #28a745; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: bold; }
.btn-del { background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: bold; }

/* Zone d'édition avec petites poubelles */
.edit-images-preview { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
.edit-img-box { position: relative; width: 80px; height: 80px; border-radius: 8px; overflow: hidden; border: 2px solid #ffcc00; }
.edit-img-box img { width: 100%; height: 100%; object-fit: cover; }
.edit-img-box .trash-btn { position: absolute; top: 2px; right: 2px; background: rgba(220, 53, 69, 0.9); color: white; border: none; border-radius: 4px; padding: 2px 5px; cursor: pointer; font-size: 10px; }
</style>

<div class="form-box">
    <h2>📸 <?php echo $mode_edition ? "Modifier le souvenir" : "Partager un ou plusieurs souvenirs"; ?></h2>
    
    <?php if($success != ""){ ?><div class="success"><?php echo $success; ?></div><?php } ?>
    <?php if($error != ""){ ?><div class="error"><?php echo $error; ?></div><?php } ?>

    <form method="POST" enctype="multipart/form-data" action="?<?php echo $current_page_param; ?>action=modifier&id=<?php echo $edit_id; ?>">
        <?php if($mode_edition){ ?>
            <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
            <input type="hidden" name="current_images" value="<?php echo implode(',', $edit_images_list); ?>">
        <?php } ?>

        <label>Légende, commentaire affectueux ou sentiment</label>
        <textarea name="description" placeholder="Ajoutez un commentaire chaleureux à ce souvenir..." required><?php echo htmlspecialchars($edit_description); ?></textarea>

        <?php if($mode_edition && !empty($edit_images_list)){ ?>
            <label>Photos actuelles dans ce souvenir (Cliquez sur la poubelle pour supprimer une photo précise) :</label>
            <div class="edit-images-preview">
                <?php foreach($edit_images_list as $img) { if(!empty($img)){ ?>
                    <div class="edit-img-box">
                        <img src="../images/<?php echo $img; ?>">
                        <button type="submit" name="delete_single_image" value="1" class="trash-btn" onclick="document.getElementById('img_to_del').value='<?php echo $img; ?>';">🗑️</button>
                    </div>
                <?php } } ?>
            </div>
            <input type="hidden" name="image_to_delete" id="img_to_del" value="">
            <label style="margin-top:20px;">Ajouter de nouvelles photos à ce souvenir :</label>
        <?php } else { ?>
            <label>Sélectionner la ou les photos (Maintenez CTRL pour en choisir plusieurs)</label>
        <?php } ?>
        
        <input type="file" name="images[]" <?php echo $mode_edition ? "" : "required"; ?> multiple>

        <label>Visibilité (Destinataire) :</label>
        <select name="cible_id">
            <option value="0">-- Toute l'école (Général) --</option>
            <?php 
            $classes->data_seek(0);
            while($c = $classes->fetch_assoc()){ 
            ?>
                <option value="<?php echo $c['id_classe']; ?>" <?php if($edit_cible_id == $c['id_classe']) echo "selected"; ?>>
                    Classe : <?php echo $c['nom_classe']; ?>
                </option>
            <?php } ?>
        </select>

        <button type="submit" name="save_photo" class="btn">💾 <?php echo $mode_edition ? "Mettre à jour le bloc" : "Publier dans l'album"; ?></button>
        <?php if($mode_edition){ ?>
            <a href="?<?php echo substr($current_page_param, 0, -1); ?>" class="btn-cancel">Annuler</a>
        <?php } ?>
    </form>
</div>

<h3>⚙️ Historique et gestion de l'album photo</h3>
<div class="grid-photos">
    <?php 
    while($p = $photos_existantes->fetch_assoc()) { 
        $images = !empty($p['image']) ? explode(',', $p['image']) : [];
        $total_img = count($images);
    ?>
        <div class="photo-card">
            <div>
                <small style="color:#aaa; display:block; margin-bottom:5px;">
                    🎯 <?php echo ($p['cible_type'] == 'classe' && !empty($p['nom_classe'])) ? "Classe : ".$p['nom_classe'] : "Toute l'école"; ?>
                </small>
                
                <p><?php echo htmlspecialchars($p['description']); ?></p>

                <?php if($total_img > 0 && !empty($images[0])){ ?>
                    <?php if($total_img == 1){ ?>
                        <div class="fb-gallery gallery-1">
                            <img src="../images/<?php echo trim($images[0]); ?>" alt="Souvenir">
                        </div>
                    <?php } elseif($total_img == 2){ ?>
                        <div class="fb-gallery gallery-2">
                            <img src="../images/<?php echo trim($images[0]); ?>">
                            <img src="../images/<?php echo trim($images[1]); ?>">
                        </div>
                    <?php } elseif($total_img == 3){ ?>
                        <div class="fb-gallery gallery-3">
                            <img src="../images/<?php echo trim($images[0]); ?>">
                            <img src="../images/<?php echo trim($images[1]); ?>">
                            <img src="../images/<?php echo trim($images[2]); ?>">
                        </div>
                    <?php } elseif($total_img == 4){ ?>
                        <div class="fb-gallery gallery-4">
                            <img src="../images/<?php echo trim($images[0]); ?>">
                            <img src="../images/<?php echo trim($images[1]); ?>">
                            <img src="../images/<?php echo trim($images[2]); ?>">
                            <img src="../images/<?php echo trim($images[3]); ?>">
                        </div>
                    <?php } else { ?>
                        <div class="fb-gallery gallery-multi">
                            <img src="../images/<?php echo trim($images[0]); ?>">
                            <img src="../images/<?php echo trim($images[1]); ?>">
                            <img src="../images/<?php echo trim($images[2]); ?>">
                            <div class="img-container-more">
                                <img src="../images/<?php echo trim($images[3]); ?>">
                                <div class="more-overlay">+<?php echo ($total_img - 3); ?></div>
                            </div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
            
            <div class="actions">
                <a href="?<?php echo $current_page_param; ?>action=modifier&id=<?php echo $p['id_photo']; ?>" class="btn-edit">Modifier texte/photos</a>
                <button type="button" class="btn-del" onclick="confirmerSuppressionPhoto(<?php echo $p['id_photo']; ?>)">Supprimer le bloc</button>
            </div>
        </div>
    <?php } ?>
</div>

<form method="POST" id="deletePhotoForm" style="display:none;">
    <input type="hidden" name="del_photo_id" id="del_photo_id">
    <input type="hidden" name="action_supprimer" value="1">
</form>

<script>
function confirmerSuppressionPhoto(idPhoto) {
    if (confirm('Voulez-vous supprimer ce souvenir ainsi que toutes les photos associées ?')) {
        document.getElementById('del_photo_id').value = idPhoto;
        document.getElementById('deletePhotoForm').submit();
    }
}
</script>