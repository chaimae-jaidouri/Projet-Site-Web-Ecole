<?php

session_start();

require_once "config/database.php";

$error = "";

if(isset($_POST['login'])){

    $identifiant = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    /* =========================
       PARENT
    ========================= */

    if($role == "parent"){

        $sql = "SELECT * FROM parents WHERE username = ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param("s",$identifiant);

        $stmt->execute();

        $result = $stmt->get_result();

        if($result->num_rows > 0){

            $user = $result->fetch_assoc();

            if($password == $user['password']){

                $_SESSION['parent_id'] = $user['id_parent'];

                header("Location: parent/parent.php");

                exit();

            }else{

                $error = "Mot de passe incorrect";

            }

        }else{

            $error = "Parent introuvable";

        }

    }

    /* =========================
       ENSEIGNANT
    ========================= */

    else if($role == "teacher"){

        $sql = "SELECT * FROM enseignant WHERE email = ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param("s",$identifiant);

        $stmt->execute();

        $result = $stmt->get_result();

        if($result->num_rows > 0){

            $user = $result->fetch_assoc();

            if($password == $user['password']){

                $_SESSION['enseignant_id'] = $user['id_enseignant'];

                header("Location: teacher/teacher.php");

                exit();

            }else{

                $error = "Mot de passe incorrect";

            }

        }else{

            $error = "Enseignant introuvable";

        }

    }

    /* =========================
       ADMIN
    ========================= */

    else if($role == "admin"){

        $sql = "SELECT * FROM administrateur WHERE email = ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param("s",$identifiant);

        $stmt->execute();

        $result = $stmt->get_result();

        if($result->num_rows > 0){

            $user = $result->fetch_assoc();

            if($password == $user['password']){

                $_SESSION['admin_id'] = $user['id_admin'];

                header("Location: admin/admin.php");

                exit();

            }else{

                $error = "Mot de passe incorrect";

            }

        }else{

            $error = "Administrateur introuvable";

        }

    }

}

?>

<!DOCTYPE html>
<html lang="fr">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Connexion</title>

<style>

body{
    margin:0;
    font-family:'Segoe UI',sans-serif;
    background:linear-gradient(135deg,#fff7d6,#ffffff);
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}

.role-container{
    display:flex;
    gap:25px;
}

.role-card{
    width:200px;
    height:220px;
    background:white;
    border-radius:25px;
    overflow:hidden;
    cursor:pointer;
    transition:0.3s;
    box-shadow:0 10px 30px rgba(0,0,0,0.1);
}

.role-card:hover{
    transform:translateY(-10px);
    box-shadow:0 15px 40px rgba(255,204,0,0.3);
}

.role-card img{
    width:100%;
    height:150px;
    object-fit:cover;
}

.role-title{
    text-align:center;
    padding:10px;
    color:#d4a800;
    font-weight:bold;
}

.login-container{
    display:none;
    width:380px;
    background:white;
    padding:40px;
    border-radius:20px;
    text-align:center;
    box-shadow:0 20px 50px rgba(255,204,0,0.15);
}

.logo{
    width:120px;
    margin-bottom:10px;
}

h2{
    color:#d4a800;
}

input{
    width:100%;
    padding:12px;
    margin:10px 0;
    border-radius:10px;
    border:2px solid #f5e6a5;
    outline:none;
}

button{
    width:100%;
    padding:12px;
    background:#ffd84d;
    border:none;
    border-radius:10px;
    cursor:pointer;
    font-weight:bold;
}

button:hover{
    background:#ffcc00;
}

.back{
    margin-top:10px;
    cursor:pointer;
    color:#888;
}

.error{
    background:#ffe5e5;
    color:red;
    padding:10px;
    border-radius:10px;
    margin-bottom:10px;
}

</style>

</head>

<body>

<div id="roles" class="role-container">

    <div class="role-card" onclick="openLogin('parent')">

        <img src="https://tse4.mm.bing.net/th/id/OIP.pqJH3ieC5wO-DOzAa7ghVwHaJO?pid=Api&P=0&h=180">

        <div class="role-title">
            Parent
        </div>

    </div>

    <div class="role-card" onclick="openLogin('teacher')">

        <img src="https://tse4.mm.bing.net/th/id/OIP.d-tkmHsms4w34sQ1QmREfAHaHa?pid=Api&P=0&h=180">

        <div class="role-title">
            Enseignant
        </div>

    </div>

    <div class="role-card" onclick="openLogin('admin')">

        <img src="data:image/png;base64,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">

        <div class="role-title">
            Administrateur
        </div>

    </div>

</div>

<div id="loginBox" class="login-container">

    <img src="https://i.imgur.com/XOAxNyg.png" class="logo">

    <h2 id="title">Connexion</h2>

    <?php if($error){ ?>

        <div class="error">
            <?php echo $error; ?>
        </div>

    <?php } ?>

    <form method="POST">

        <input type="hidden" name="role" id="role_input">

        <input
        type="text"
        name="username"
        id="user_field"
        placeholder="Identifiant"
        required>

        <input
        type="password"
        name="password"
        placeholder="Mot de passe"
        required>

        <button type="submit" name="login">
            Se connecter
        </button>

    </form>

    <div class="back" onclick="goBack()">
        ← Retour
    </div>

</div>

<script>

function openLogin(role){

    document.getElementById("roles").style.display = "none";

    document.getElementById("loginBox").style.display = "block";

    document.getElementById("role_input").value = role;

    const title = document.getElementById("title");

    const field = document.getElementById("user_field");

    if(role == "parent"){

        title.innerText = "Espace Parent";

        field.placeholder = "Nom utilisateur parent";

    }

    if(role == "teacher"){

        title.innerText = "Espace Enseignant";

        field.placeholder = "Email enseignant";

    }

    if(role == "admin"){

        title.innerText = "Espace Administrateur";

        field.placeholder = "Email administrateur";

    }

}

function goBack(){

    document.getElementById("roles").style.display = "flex";

    document.getElementById("loginBox").style.display = "none";

}

<?php if($error){ ?>

openLogin("<?php echo $_POST['role']; ?>");

<?php } ?>

</script>

</body>
</html>
