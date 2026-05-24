<?php
session_start();
require_once "../config/database.php";

/* ==========================================================
   SÉCURITÉ PARENT
========================================================== */
if(!isset($_SESSION['parent_id'])){
  header("Location: ../login.php");
  exit();
}

$id_parent = $_SESSION['parent_id'];

/* ==========================================================
   UPLOAD DE LA PHOTO DE L'ÉLÈVE (DANS LA TABLE ELEVES)
========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
  if(!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] == 0){

    // 1. Récupération de l'ID de l'élève associé à ce parent
    $sql_get_eleve = "SELECT id_eleve FROM eleves WHERE parent_id = ? LIMIT 1";
    $stmt_ge = $conn->prepare($sql_get_eleve);
    $stmt_ge->bind_param("i", $id_parent);
    $stmt_ge->execute();
    $res_ge = $stmt_ge->get_result()->fetch_assoc();

    if ($res_ge) {
        $id_eleve = $res_ge['id_eleve'];

        // 2. Sécurisation du nom du fichier avec l'ID unique de l'élève
        $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photo_name = "eleve_" . $id_eleve . "_" . time() . "." . $extension;

        // Création du dossier d'images s'il n'existe pas encore
        if (!is_dir("../images/")) {
            mkdir("../images/", 0777, true);
        }

        // 3. Déplacement de l'image et mise à jour de la table 'eleves'
        if(move_uploaded_file($_FILES['photo']['tmp_name'], "../images/" . $photo_name)){
          $sql_update_eleve = "UPDATE eleves SET photo = ? WHERE id_eleve = ?";
          $stmt_ue = $conn->prepare($sql_update_eleve);
          $stmt_ue->bind_param("si", $photo_name, $id_eleve);
          $stmt_ue->execute();
        }
    }

    // Redirection stricte pour vider le cache POST (évite les renvois au rafraîchissement)
    header("Location: parent.php");
    exit();
  }
}

/* ==========================================================
   RÉCUPÉRATION DES INFOS PARENT
========================================================== */
$sql = "SELECT * FROM parents WHERE id_parent = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_parent);
$stmt->execute();
$result_parent = $stmt->get_result();
$parent = $result_parent->fetch_assoc();

/* ==========================================================
   RÉCUPÉRATION DE L'ÉLÈVE (CLASSE ET PHOTO)
========================================================== */
$nom_classe_eleve = "Classe non définie";
$photo_eleve = "";

$sql_eleve = "
SELECT c.nom_classe, e.photo
FROM eleves e
JOIN classe c ON e.classe_id = c.id_classe
WHERE e.parent_id = ?
LIMIT 1
";

$stmt_eleve = $conn->prepare($sql_eleve);
$stmt_eleve->bind_param("i", $id_parent);
$stmt_eleve->execute();
$res_eleve = $stmt_eleve->get_result()->fetch_assoc();

if($res_eleve){
  $nom_classe_eleve = $res_eleve['nom_classe'];
  $photo_eleve = $res_eleve['photo']; 
}

/* ==========================================================
   COMPTEUR DE NOTIFICATIONS NON LUES
========================================================== */
$sql_count = "
SELECT COUNT(*) as total
FROM notification
WHERE parent_id = ?
AND lu = 0
";

$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param("i", $id_parent);
$stmt_count->execute();
$res_count = $stmt_count->get_result()->fetch_assoc();

$notif_non_lues = $res_count['total'];

/* ==========================================================
   GESTION DE LA PAGE ACTIVE (ROUTAGE)
========================================================== */
$page = isset($_GET['page']) ? $_GET['page'] : 'actualites';

$allowed_pages = [
  'actualites',
  'absence',
  'album',
  'devoir',
  'remarque',
  'notifications'
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
<title>Espace Parents - Rihab el Marjane</title>

<style>
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

.sidebar{
width:300px;
background:white;
padding:25px;
box-shadow:0 0 25px rgba(255,204,0,0.2);
display:flex;
flex-direction:column;
align-items:center;
}

.profile-image{
width:130px;
height:130px;
border-radius:50%;
object-fit:cover;
border:5px solid #ffcc00;
}

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

.menu{
width:100%;
margin-top:25px;
display:flex;
flex-direction:column;
gap:10px;
}

.menu a{
text-decoration:none;
position: relative;
display: block;
width: 100%;
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

.badge-whatsapp {
background-color: red;
color: white;
font-size: 12px;
font-weight: bold;
padding: 2px 7px;
border-radius: 50%;
position: absolute;
right: 15px;
top: 50%;
transform: translateY(-50%);
}

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
animation: floatAnimation 3s ease-in-out infinite;
}

@keyframes floatAnimation {
  0% { transform: translateY(0px); }
  50% { transform: translateY(-5px); }
  100% { transform: translateY(0px); }
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

<div class="sidebar">

  <img
    src="<?php
      echo (!empty($photo_eleve) && file_exists("../images/".$photo_eleve))
      ? '../images/'.$photo_eleve
      : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
    ?>"
    class="profile-image"
    alt="Profil Élève"
  >

  <form method="POST" enctype="multipart/form-data">
    <label class="upload-label">
      📷 Modifier photo
      <input type="file" name="photo" accept="image/*" style="display:none" onchange="this.form.submit()">
    </label>
  </form>

  <div class="admin-name">
    <?php echo htmlspecialchars($parent['nom']); ?>
  </div>

  <div class="admin-role">
    <?php echo htmlspecialchars($nom_classe_eleve); ?>
  </div>

  <div class="menu">
    <a href="parent.php?page=actualites"><div class="menu-card">📰 Actualités</div></a>
    <a href="parent.php?page=absence"><div class="menu-card">⚠️ Absences</div></a>
    <a href="parent.php?page=remarque"><div class="menu-card">📝 Remarques</div></a>
    <a href="parent.php?page=devoir"><div class="menu-card">📚 Devoirs</div></a>
    <a href="parent.php?page=album"><div class="menu-card">🖼️ Album Photo</div></a>

    <a href="parent.php?page=notifications">
      <div class="menu-card">
        🔔 Notifications
        <?php if($notif_non_lues > 0): ?>
          <span class="badge-whatsapp"><?php echo $notif_non_lues; ?></span>
        <?php endif; ?>
      </div>
    </a>

    <a href="../logout.php"><div class="menu-card">🚪 Déconnexion</div></a>
  </div>

</div>

<div class="main">
  <img class="school-logo" src="https://i.imgur.com/XOAxNyg.png" alt="Logo École">

  <h2>Espace Parental Dashboard</h2>

  <div class="content-box">
    <?php
    switch($page){
      case 'actualites': include "actualites.php"; break;
      case 'absence': include "absence.php"; break;
      case 'remarque': include "remarques.php"; break;
      case 'devoir': include "devoirs.php"; break;
      case 'album': include "album_photo.php"; break;
      case 'notifications': include "notifications.php"; break;
      default: include "actualites.php"; break;
    }
    ?>
  </div>
</div>

</body>
</html>