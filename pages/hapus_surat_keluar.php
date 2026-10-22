<?php

include '../koneksi.php';

$id = $_GET['id'];

mysqli_query(
    $koneksi,
    "DELETE FROM surat_keluar WHERE id='$id'"
);

header("location:../index.php?page=surat_keluar");

?>