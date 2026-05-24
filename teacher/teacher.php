<?php
session_start();
require_once "../config/database.php";

/* =========================
   SECURITE LOGIN
========================= */
if(!isset($_SESSION['enseignant_id'])){
    header("Location: ../login.php");
    exit();
}

$id_enseignant = $_SESSION['enseignant_id'];

/* =========================
   UPLOAD PHOTO PROF
========================= */
if(isset($_POST['upload_photo'])){

    if(!empty($_FILES['photo']['name'])){

        $photo = time() . "_" . $_FILES['photo']['name'];

        move_uploaded_file(
            $_FILES['photo']['tmp_name'],
            "../images/" . $photo
        );

        $sql = "UPDATE enseignant SET photo = ? WHERE id_enseignant = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $photo, $id_enseignant);
        $stmt->execute();

        header("Location: teacher.php");
        exit();
    }
}

/* =========================
   INFOS ENSEIGNANT
========================= */
$sql = "SELECT * FROM enseignant WHERE id_enseignant = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_enseignant);
$stmt->execute();

$result = $stmt->get_result();
$enseignant = $result->fetch_assoc();

/* =========================
   FONCTION AUTOMATIQUE
========================= */
$fonction = ($enseignant['sexe'] == 'F') ? "Enseignante" : "Enseignant";

/* =========================
   PAGE ACTIVE
========================= */
$page = isset($_GET['page']) ? $_GET['page'] : 'actualites';

/* =========================
   PAGES AUTORISEES
========================= */
$allowed_pages = [
    'actualites',
    'classes',
    'devoirs',
    'absence',
    'remarques',
    'album'
];

if(!in_array($page, $allowed_pages)){
    $page = 'actualites';
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Espace Enseignant</title>

<style>
/* (TON CSS RESTE IDENTIQUE — JE NE CHANGE RIEN POUR PAS CASSER TON DESIGN) */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',sans-serif;}
body{display:flex;min-height:100vh;background:linear-gradient(135deg,#fff7d6,#ffffff);}
.sidebar{width:300px;background:white;padding:25px;box-shadow:0 0 25px rgba(255,204,0,0.2);display:flex;flex-direction:column;align-items:center;}
.profile-image{width:130px;height:130px;border-radius:50%;object-fit:cover;border:5px solid #ffcc00;}
.upload-label{margin-top:10px;background:#ffcc00;color:white;padding:10px 18px;border-radius:25px;cursor:pointer;font-weight:bold;display:inline-block;}
.teacher-name{margin-top:15px;font-size:20px;font-weight:bold;color:#444;text-align:center;}
.teacher-role{color:#d4a800;margin-top:5px;font-weight:bold;}
.menu{width:100%;margin-top:25px;display:flex;flex-direction:column;gap:10px;}
.menu a{text-decoration:none;}
.menu-card{background:#fff7dc;padding:12px;border-radius:12px;font-weight:bold;color:#b8860b;transition:0.3s;}
.menu-card:hover{background:#ffcc00;color:white;transform:translateX(5px);}
.main{flex:1;padding:30px;position:relative;}
.school-logo{position:absolute;top:15px;right:20px;width:140px;}
h2{margin-top:20px;color:#444;}
.content-box{margin-top:25px;background:white;padding:25px;border-radius:18px;box-shadow:0 10px 25px rgba(255,204,0,0.15);min-height:500px;}
</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

    <img src="<?php
        echo !empty($enseignant['photo'])
        ? '../images/'.$enseignant['photo']
        : 'https://cdn-icons-png.flaticon.com/512/847/847969.png';
    ?>" class="profile-image">

    <form method="POST" enctype="multipart/form-data">

        <label class="upload-label">
            📷 Modifier photo
            <input type="file" name="photo" style="display:none" onchange="this.form.submit()">
        </label>

        <input type="hidden" name="upload_photo" value="1">

    </form>

    <div class="teacher-name">
        <?php echo $enseignant['nom']; ?>
    </div>

    <div class="teacher-role">
        <?php echo $fonction; ?>
    </div>

    <!-- MENU -->
    <div class="menu">

        <a href="teacher.php?page=actualites"><div class="menu-card">📰 Actualités</div></a>
        <a href="teacher.php?page=classes"><div class="menu-card">📚 Classes</div></a>
        <a href="teacher.php?page=devoirs"><div class="menu-card">📝 Devoirs</div></a>
        <a href="teacher.php?page=absence"><div class="menu-card">🚫 Absences</div></a>
        <a href="teacher.php?page=remarques"><div class="menu-card">✏️ Remarques</div></a>
        <a href="teacher.php?page=album"><div class="menu-card">🖼️ Album Photo</div></a>
        <a href="../logout.php"><div class="menu-card">🚪 Déconnexion</div></a>

    </div>

</div>

<!-- MAIN -->
<div class="main">

    <img class="school-logo" src="https://i.imgur.com/XOAxNyg.png">

    <h2>Bienvenue dans l’espace enseignant</h2>

    <div class="content-box">

        <?php

        switch($page){

            case 'actualites':
                include "teacher_actualites.php";
                break;

            case 'classes':
                include "classes.php";
                break;

            case 'devoirs':
                include "add_devoir.php";
                break;

            case 'absence':
                include "add_absence.php";
                break;

            case 'remarques':
                include "add_remarques.php";
                break;

            case 'album':
                include "add_photo.php";
                break;

            default:
                include "teacher_actualites.php";
                break;
        }

        ?>

    </div>

</div>

</body>
</html>