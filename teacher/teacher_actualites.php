<?php
require_once "../config/database.php";

/* =========================
   RECUPERATION ACTUALITES
========================= */
$sql = "SELECT * FROM actualite ORDER BY date_publication DESC";
$result = $conn->query($sql);
?>

<style>

/* =========================
   TITRE
========================= */
.title{
font-size:26px;
font-weight:bold;
color:#d4a800;
margin-bottom:20px;
}

/* =========================
   CONTAINER
========================= */
.news-container{
display:flex;
flex-direction:column;
gap:20px;
}

/* =========================
   CARD ACTUALITE
========================= */
.news-card{
background:#fffdf5;
padding:20px;
border-radius:18px;
box-shadow:0 8px 20px rgba(255,204,0,0.12);
transition:0.3s;
}

.news-card:hover{
transform:translateY(-3px);
}

/* TITLE */
.news-card h3{
color:#b8860b;
margin-bottom:10px;
}

/* TEXT */
.news-card p{
color:#555;
line-height:1.6;
}

/* IMAGE */
.news-card img{
width:100%;
max-width:350px;
margin-top:15px;
border-radius:12px;
}

/* DATE */
.date{
margin-top:10px;
font-size:13px;
color:#888;
}

/* EMPTY */
.empty{
text-align:center;
color:#999;
font-size:16px;
padding:30px;
}

</style>

<div>

<div class="title">
📰 Actualités de l’école
</div>

<?php if($result->num_rows == 0){ ?>

    <div class="empty">
        Aucune actualité disponible pour le moment.
    </div>

<?php } else { ?>

<div class="news-container">

    <?php while($a = $result->fetch_assoc()){ ?>

        <div class="news-card">

            <h3>
                <?php echo htmlspecialchars($a['titre']); ?>
            </h3>

            <p>
                <?php echo nl2br(htmlspecialchars($a['contenu'])); ?>
            </p>

            <?php if(!empty($a['image'])){ ?>

                <img src="../images/<?php echo $a['image']; ?>">

            <?php } ?>

            <div class="date">
                📅 <?php echo $a['date_publication']; ?>
            </div>

        </div>

    <?php } ?>

</div>

<?php } ?>

</div>