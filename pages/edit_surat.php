<?php
include 'koneksi.php';

$id = $_GET['id'];

$data = mysqli_query($koneksi,"SELECT * FROM surat_masuk WHERE id='$id'");
$d = mysqli_fetch_array($data);
?>

<h2>Edit Surat Masuk</h2>

<form method="post">

<label>Nomor Surat</label><br>
<input type="text" name="nomor_surat" value="<?php echo $d['nomor_surat']; ?>"><br><br>

<label>Tanggal Surat</label><br>
<input type="date" name="tanggal_surat" value="<?php echo $d['tanggal_surat']; ?>"><br><br>

<label>Pengirim</label><br>
<input type="text" name="pengirim" value="<?php echo $d['pengirim']; ?>"><br><br>

<label>Perihal</label><br>
<textarea name="perihal"><?php echo $d['perihal']; ?></textarea><br><br>

<button type="submit" name="update">Update</button>

</form>

<?php

if(isset($_POST['update'])){

$nomor = $_POST['nomor_surat'];
$tanggal = $_POST['tanggal_surat'];
$pengirim = $_POST['pengirim'];
$perihal = $_POST['perihal'];

mysqli_query($koneksi,"UPDATE surat_masuk SET 
nomor_surat='$nomor',
tanggal_surat='$tanggal',
pengirim='$pengirim',
perihal='$perihal'
WHERE id='$id'");

header("location:surat_masuk.php");

}

?>