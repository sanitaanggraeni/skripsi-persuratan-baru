<?php
$host = "localhost";
$user = "pery2491_suratunibba";
$password = "suratuniba123!";
$database = "pery2491_persuratan";

$koneksi = mysqli_connect($host, $user, $password, $database);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>