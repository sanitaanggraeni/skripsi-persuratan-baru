<?php
session_start();
include 'koneksi.php';

if(isset($_POST['login'])){
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username']));
    $password = trim($_POST['password']);

    $query = mysqli_query($koneksi, "SELECT * FROM user WHERE username='$username'");
    $user = mysqli_fetch_assoc($query);

    if($user && $password === $user['password']){
        $_SESSION['status'] = "login";
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header("location:index.php");
        exit;
    } else {
        $error = "Username atau Password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Sistem Persuratan</title>
<style>
body{
    font-family: Arial, sans-serif;
    background: linear-gradient(to right, #2c3e50, #4ca1af);
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
    margin:0;
}
.login-box{
    background:#ffffff;
    padding:30px;
    width:350px;
    border-radius:15px;
    box-shadow:0 5px 20px rgba(0,0,0,0.3);
    text-align:center;
    margin-top: 60px;
}
.logo{width:100px;margin-bottom:10px;}
.kampus{font-size:22px;font-weight:700;color:#2c3e50;margin-bottom:8px;text-transform:uppercase;}
.judul{font-size:15px;font-weight:500;color:#555;margin-bottom:5px}
.subjudul{font-size:12px;color:#888;margin-bottom:25px;}
input{width:100%;padding:10px;margin:8px 0;border:1px solid #ccc;border-radius:5px;box-sizing:border-box;}
.password-box{position:relative;}
.show-password{position:absolute;right:5px;top:50%;transform:translateY(-50%);width:40px;height:35px;border:none;background:none;cursor:pointer;font-size:16px;color:#555;}
button[type="submit"]{width:100%;padding:12px;background:#2c3e50;color:white;border:none;border-radius:5px;cursor:pointer;font-size:14px;margin-top:10px;}
button[type="submit"]:hover{background:#1a252f;}
.error{color:red;margin-bottom:15px;font-size:14px;}
.footer{margin-top:20px;font-size:12px;color:#777;}
</style>
</head>
<body>
<div class="login-box">
    <img src="assets/image/logo.png" class="logo" alt="Logo Kampus">
    <div class="kampus">UNIVERSITAS BALE BANDUNG</div>
    <div class="judul">Sistem Informasi Persuratan Program Studi</div>
    <div class="subjudul">Login Kaprodi dan Staff</div>
    <?php if(isset($error)){ ?>
        <div class="error"><?php echo $error; ?></div>
    <?php } ?>
    <form method="post" action="">
        <input type="text" name="username" placeholder="Username" required>
        <div class="password-box">
            <input type="password" id="password" name="password" placeholder="Password" required>
            <button type="button" class="show-password" onclick="togglePassword()">👁</button>
        </div>
        <button type="submit" name="login">Login</button>
    </form>
    <div class="footer">© 2026 Universitas Bale Bandung</div>
</div>
<script>
function togglePassword(){
    var password = document.getElementById("password");
    if(password.type === "password"){password.type = "text";}else{password.type = "password";}
}
</script>
</body>
</html>