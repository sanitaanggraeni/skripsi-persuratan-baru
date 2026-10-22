
<?php
require_once 'koneksi.php';

mysqli_report(
    MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT
);

function aman_edit($nilai) {
    return htmlspecialchars(
        (string)($nilai ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| VALIDASI ID SURAT
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$id) {
    exit('ID surat tidak valid.');
}

/*
|--------------------------------------------------------------------------
| AMBIL DATA SURAT
|--------------------------------------------------------------------------
*/

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT * FROM surat_masuk WHERE id = ?"
);

mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$d = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$d) {
    exit('Data surat tidak ditemukan.');
}

$pesan = '';
$berhasil = false;

$memiliki_id = !empty($d['kode_khusus']);

/*
|--------------------------------------------------------------------------
| PROSES UPDATE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update'])
) {

    $nomor = trim($_POST['nomor_surat'] ?? '');
    $tanggal = trim($_POST['tanggal_surat'] ?? '');
    $pengirim = trim($_POST['pengirim'] ?? '');
    $perihal = trim($_POST['perihal'] ?? '');

    try {

        if (
            $nomor === '' ||
            $tanggal === '' ||
            $pengirim === '' ||
            $perihal === ''
        ) {
            throw new Exception(
                'Semua kolom wajib diisi.'
            );
        }

        if (
            strlen($nomor) > 100 ||
            strlen($pengirim) > 100 ||
            strlen($perihal) > 255
        ) {
            throw new Exception(
                'Panjang data melebihi batas.'
            );
        }

        $tgl = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $tanggal
        );

        if (
            !$tgl ||
            $tgl->format('Y-m-d') !== $tanggal
        ) {
            throw new Exception(
                'Tanggal tidak valid.'
            );
        }

        /*
        | UNTUK SURAT BARU YANG SUDAH
        | MEMILIKI ID KHUSUS:
        | TAHUN TIDAK BOLEH BERUBAH
        */

        if ($memiliki_id) {

            $tahun_lama = substr(
                $d['tanggal_surat'],
                0,
                4
            );

            $tahun_baru = $tgl->format('Y');

            if ($tahun_lama !== $tahun_baru) {
                throw new Exception(
                    'Tahun surat tidak boleh diubah '
                    . 'karena sudah memiliki ID khusus.'
                );
            }
        }

        /*
        | UPDATE HANYA KOLOM YANG
        | DIPERBOLEHKAN
        |
        | kategori_surat dan kode_khusus
        | tidak ikut diperbarui
        */

        $stmt = mysqli_prepare($koneksi, "
            UPDATE surat_masuk
            SET
                nomor_surat = ?,
                tanggal_surat = ?,
                pengirim = ?,
                perihal = ?
            WHERE id = ?
        ");

        mysqli_stmt_bind_param(
            $stmt,
            'ssssi',
            $nomor,
            $tanggal,
            $pengirim,
            $perihal,
            $id
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $pesan = 'Data surat berhasil diperbarui.';
        $berhasil = true;

        /*
        | PERBARUI DATA YANG DITAMPILKAN
        */

        $d['nomor_surat'] = $nomor;
        $d['tanggal_surat'] = $tanggal;
        $d['pengirim'] = $pengirim;
        $d['perihal'] = $perihal;

    } catch (Exception $e) {

        $pesan = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Edit Surat Masuk</title>

<style>

* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #eef2f5;
    margin: 0;
    padding: 30px 15px;
    color: #1e293b;
}

.container {
    background: white;
    max-width: 650px;
    margin: 0 auto;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

h2 {
    margin-top: 0;
    color: #1e3a5f;
}

.form-group {
    margin-bottom: 16px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
}

.form-control[readonly] {
    background: #f1f5f9;
    color: #475569;
}

textarea {
    min-height: 90px;
    resize: vertical;
}

.info {
    background: #eef2ff;
    padding: 13px;
    border-left: 4px solid #4f46e5;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 13px;
    line-height: 1.6;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    padding: 13px;
    border-radius: 7px;
    margin-bottom: 18px;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    padding: 13px;
    border-radius: 7px;
    margin-bottom: 18px;
}

.btn {
    display: inline-block;
    padding: 11px 18px;
    border: none;
    border-radius: 7px;
    font-weight: bold;
    text-decoration: none;
    font-size: 13px;
    cursor: pointer;
}

.btn-simpan {
    background: #16a34a;
    color: white;
}

.btn-kembali {
    background: #64748b;
    color: white;
}

small {
    color: #64748b;
    display: block;
    margin-top: 6px;
}

</style>

</head>

<body>

<div class="container">

<h2>Edit Surat Masuk</h2>

<?php if ($pesan !== ''): ?>

<div class="<?php
    echo $berhasil ? 'alert-success' : 'alert-error';
?>">

    <?php echo aman_edit($pesan); ?>

</div>

<?php endif; ?>

<!-- INFORMASI ID KHUSUS -->

<div class="info">

    <strong>Informasi Arsip</strong>

    <br><br>

    <strong>ID Khusus:</strong>

    <?php
        echo $memiliki_id
            ? aman_edit($d['kode_khusus'])
            : 'Arsip Lama';
    ?>

    <br>

    <strong>Kategori:</strong>

    <?php
        echo !empty($d['kategori_surat'])
            ? aman_edit($d['kategori_surat'])
            : 'Belum dikategorikan';
    ?>

    <br><br>

    <?php if ($memiliki_id): ?>

        ID khusus dan kategori tidak dapat
        diubah melalui halaman ini.

    <?php else: ?>

        Surat ini merupakan arsip lama yang
        belum memiliki ID khusus.

    <?php endif; ?>

</div>

<form method="POST">

    <div class="form-group">

        <label>Nomor Surat</label>

        <input
            type="text"
            name="nomor_surat"
            class="form-control"
            maxlength="100"
            value="<?php
                echo aman_edit($d['nomor_surat']);
            ?>"
            required
        >

    </div>

    <div class="form-group">

        <label>Tanggal Surat</label>

        <input
            type="date"
            name="tanggal_surat"
            class="form-control"
            value="<?php
                echo aman_edit($d['tanggal_surat']);
            ?>"
            required
        >

        <?php if ($memiliki_id): ?>

            <small>
                Tahun surat harus tetap sama
                dengan tahun saat ID dibuat.
            </small>

        <?php endif; ?>

    </div>

    <div class="form-group">

        <label>Pengirim</label>

        <input
            type="text"
            name="pengirim"
            class="form-control"
            maxlength="100"
            value="<?php
                echo aman_edit($d['pengirim']);
            ?>"
            required
        >

    </div>

    <div class="form-group">

        <label>Perihal</label>

        <textarea
            name="perihal"
            class="form-control"
            maxlength="255"
            required
        ><?php echo aman_edit($d['perihal']); ?></textarea>

    </div>

    <button
        type="submit"
        name="update"
        value="1"
        class="btn btn-simpan"
    >
        Simpan Perubahan
    </button>

    <a
        href="index.php?page=surat_masuk"
        class="btn btn-kembali"
    >
        Kembali
    </a>

</form>

</div>

</body>
</html>
