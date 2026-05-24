<?php
session_start();
require_once "../config/database.php";

/* =========================
   SECURITE ADMIN
========================= */
if(!isset($_SESSION['admin_id'])){
    header("Location: ../login.php");
    exit();
}

$id_admin = $_SESSION['admin_id'];

/* =========================
   UPLOAD PHOTO ADMIN
========================= */
if(isset($_POST['upload_photo'])){

    if(!empty($_FILES['photo']['name'])){

        $photo = time() . "_" . $_FILES['photo']['name'];

        move_uploaded_file(
            $_FILES['photo']['tmp_name'],
            "../images/" . $photo
        );

        $sql = "UPDATE administrateur SET photo = ? WHERE id_admin = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $photo, $id_admin);
        $stmt->execute();

        header("Location: admin.php");
        exit();
    }
}

/* =========================
   INFOS ADMIN
========================= */
$sql = "SELECT * FROM administrateur WHERE id_admin = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_admin);
$stmt->execute();

$result = $stmt->get_result();
$admin = $result->fetch_assoc();

/* =========================
   PAGE ACTIVE
========================= */
$page = isset($_GET['page']) ? $_GET['page'] : 'actualites';

$allowed_pages = [
    'actualites',
    'eleves',
    'enseignants',
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
<title>Dashboard Admin</title>

<style>

/* =========================
   GLOBAL
========================= */
*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:'Segoe UI',sans-serif;
}

body{
display:flex;
min-height:100vh;
background:linear-gradient(135deg,#fff7d6,#ffffff);
}

/* =========================
   SIDEBAR
========================= */
.sidebar{
width:300px;
background:white;
padding:25px;
box-shadow:0 0 25px rgba(255,204,0,0.2);
display:flex;
flex-direction:column;
align-items:center;
}

/* PHOTO */
.profile-image{
width:130px;
height:130px;
border-radius:50%;
object-fit:cover;
border:5px solid #ffcc00;
}

/* UPLOAD BUTTON */
.upload-label{
margin-top:10px;
background:#ffcc00;
color:white;
padding:10px 18px;
border-radius:25px;
cursor:pointer;
font-weight:bold;
display:inline-block;
transition:0.3s;
}

.upload-label:hover{
transform:scale(1.05);
}

/* TEXT */
.admin-name{
margin-top:15px;
font-size:20px;
font-weight:bold;
color:#444;
text-align:center;
}

.admin-role{
color:#d4a800;
margin-top:5px;
font-weight:bold;
}

/* =========================
   MENU
========================= */
.menu{
width:100%;
margin-top:25px;
display:flex;
flex-direction:column;
gap:10px;
}

.menu a{
text-decoration:none;
}

.menu-card{
background:#fff7dc;
padding:12px;
border-radius:12px;
font-weight:bold;
color:#b8860b;
transition:0.3s;
}

.menu-card:hover{
background:#ffcc00;
color:white;
transform:translateX(5px);
}

/* =========================
   MAIN
========================= */
.main{
flex:1;
padding:30px;
position:relative;
}

.school-logo{
position:absolute;
top:15px;
right:20px;
width:140px;
}

h2{
margin-top:20px;
color:#444;
}

.content-box{
margin-top:25px;
background:white;
padding:25px;
border-radius:18px;
box-shadow:0 10px 25px rgba(255,204,0,0.15);
min-height:500px;
}

</style>
</head>

<body>

<!-- =========================
     SIDEBAR
========================= -->
<div class="sidebar">

    <!-- PHOTO ADMIN -->
    <img
        src="<?php
            echo !empty($admin['photo'])
            ? '../images/'.$admin['photo']
            : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
        ?>"
        class="profile-image"
    >

    <!-- UPLOAD PHOTO -->
    <form method="POST" enctype="multipart/form-data">

        <label class="upload-label">
            📷 Modifier photo
            <input type="file" name="photo" style="display:none" onchange="this.form.submit()">
        </label>

        <input type="hidden" name="upload_photo" value="1">

    </form>

    <!-- NOM -->
    <div class="admin-name">
        <?php echo $admin['nom']; ?>
    </div>

    <!-- ROLE -->
    <div class="admin-role">
        Administrateur
    </div>

    <!-- MENU -->
    <div class="menu">

        <a href="admin.php?page=actualites">
            <div class="menu-card">📰 Actualités</div>
        </a>

        <a href="admin.php?page=eleves">
            <div class="menu-card">👨‍🎓 Élèves</div>
        </a>

        <a href="admin.php?page=enseignants">
            <div class="menu-card">👨‍🏫 Enseignants</div>
        </a>

        <a href="admin.php?page=album">
            <div class="menu-card">🖼️ Album Photo</div>
        </a>

        <a href="../logout.php">
            <div class="menu-card">🚪 Déconnexion</div>
        </a>

    </div>

</div>

<!-- =========================
     MAIN
========================= -->
<div class="main">

    <img class="school-logo" src="https://i.imgur.com/XOAxNyg.png">

    <h2>Dashboard Administrateur</h2>

    <div class="content-box">

        <?php

        switch($page){

            case 'actualites':
                include "add_actualites.php";
                break;

            case 'eleves':
                include "manage_eleves.php";
                break;

            case 'enseignants':
                include "manage_enseignants.php";
                break;

            case 'album':
                include "add_photo_admin.php";
                break;

            default:
                include "add_actualites.php";
                break;
        }

        ?>

    </div>

</div>

</body>
</html>