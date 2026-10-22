<?php

include '../koneksi.php';

if(isset($_GET['id'])){

    $id = $_GET['id'];

    // Ambil nama file PDF
    $query = mysqli_query(
        $koneksi,
        "SELECT file_surat FROM surat_masuk WHERE id='$id'"
    );

    $data = mysqli_fetch_assoc($query);

    // Hapus file PDF dari folder uploads
    if($data && $data['file_surat'] != ''){

        $file = '../uploads/' . $data['file_surat'];

        if(file_exists($file)){
            unlink($file);
        }
    }

    // Hapus data surat dari database
    mysqli_query(
        $koneksi,
        "DELETE FROM surat_masuk WHERE id='$id'"
    );
}

// Kembali ke halaman Surat Masuk
header("Location: ../index.php?page=surat_masuk");
exit;

?>