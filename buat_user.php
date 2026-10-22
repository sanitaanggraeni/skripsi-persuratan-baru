<?php
include 'koneksi.php';

$username = "admin";
$password = password_hash("123", PASSWORD_DEFAULT);

mysqli_query($koneksi,"INSERT INTO user (username, password) VALUES ('$username','$password')");

echo "User berhasil dibuat";
?>