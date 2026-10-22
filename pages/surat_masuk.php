
<?php
include 'koneksi.php';

/*
|--------------------------------------------------------------------------
| FUNGSI KEAMANAN OUTPUT
|--------------------------------------------------------------------------
*/

if (!function_exists('e_surat_masuk')) {
    function e_surat_masuk($value) {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

$pesan = '';
$tipe_pesan = '';

/*
|--------------------------------------------------------------------------
| PROSES SIMPAN SURAT MASUK
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['simpan'])) {

    $nomor = trim($_POST['nomor_surat'] ?? '');
    $tanggal = trim($_POST['tanggal_surat'] ?? '');
    $pengirim = trim($_POST['pengirim'] ?? '');
    $perihal = trim($_POST['perihal'] ?? '');
    $kategori = trim($_POST['kategori_surat'] ?? '');

    $path_upload = null;
    $transaksi = false;

    try {

        mysqli_report(
            MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT
        );

        /*
        | VALIDASI FORM
        */

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

        if (!in_array(
            $kategori,
            ['Penting', 'Biasa'],
            true
        )) {
            throw new Exception(
                'Pilih kategori surat terlebih dahulu.'
            );
        }

        $tanggal_obj = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $tanggal
        );

        if (
            !$tanggal_obj ||
            $tanggal_obj->format('Y-m-d') !== $tanggal
        ) {
            throw new Exception(
                'Tanggal surat tidak valid.'
            );
        }

        $tahun = (int)$tanggal_obj->format('Y');

        /*
        | VALIDASI FILE PDF
        */

        if (
            !isset($_FILES['file_surat']) ||
            $_FILES['file_surat']['error'] !== UPLOAD_ERR_OK
        ) {
            throw new Exception(
                'File PDF wajib diunggah.'
            );
        }

        $file = $_FILES['file_surat'];

        $maksimal_file = 5 * 1024 * 1024;

        if (
            $file['size'] <= 0 ||
            $file['size'] > $maksimal_file
        ) {
            throw new Exception(
                'Ukuran PDF maksimal 5 MB.'
            );
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new Exception(
                'File upload tidak valid.'
            );
        }

        $ekstensi = strtolower(
            pathinfo(
                $file['name'],
                PATHINFO_EXTENSION
            )
        );

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (
            $ekstensi !== 'pdf' ||
            $mime !== 'application/pdf'
        ) {
            throw new Exception(
                'File harus berformat PDF yang valid.'
            );
        }

        /*
        | PERSIAPAN FOLDER UPLOAD
        |
        | surat_masuk.php berada di folder pages
        | uploads berada di public_html/uploads
        */

        $folder_upload = dirname(__DIR__) . '/uploads/';

        if (
            !is_dir($folder_upload) ||
            !is_writable($folder_upload)
        ) {
            throw new Exception(
                'Folder uploads tidak tersedia atau tidak dapat ditulis.'
            );
        }

        $nama_file = bin2hex(random_bytes(16)) . '.pdf';

        $path_upload = $folder_upload . $nama_file;

        /*
        | TRANSAKSI DATABASE
        */

        mysqli_begin_transaction($koneksi);
        $transaksi = true;

        /*
        | MENENTUKAN KODE KATEGORI
        */

        $jenis = 'SM';

        $kode_kategori = (
            $kategori === 'Penting'
        ) ? 'P' : 'B';

        /*
        | MEMBUAT DATA PENGHITUNG
        | JIKA BELUM TERSEDIA
        */

        $stmt = mysqli_prepare($koneksi, "
            INSERT INTO nomor_urut_surat
            (
                jenis,
                kategori,
                tahun,
                nomor_terakhir
            )
            VALUES (?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE
                nomor_terakhir = nomor_terakhir
        ");

        mysqli_stmt_bind_param(
            $stmt,
            'ssi',
            $jenis,
            $kategori,
            $tahun
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        /*
        | MENGAMBIL NOMOR URUT TERAKHIR
        | DENGAN PENGUNCIAN TRANSAKSI
        */

        $stmt = mysqli_prepare($koneksi, "
            SELECT nomor_terakhir
            FROM nomor_urut_surat
            WHERE jenis = ?
              AND kategori = ?
              AND tahun = ?
            FOR UPDATE
        ");

        mysqli_stmt_bind_param(
            $stmt,
            'ssi',
            $jenis,
            $kategori,
            $tahun
        );

        mysqli_stmt_execute($stmt);

        mysqli_stmt_bind_result(
            $stmt,
            $nomor_terakhir
        );

        if (!mysqli_stmt_fetch($stmt)) {
            throw new Exception(
                'Gagal mengambil nomor urut.'
            );
        }

        mysqli_stmt_close($stmt);

        /*
        | MEMBUAT ID KHUSUS OTOMATIS
        */

        $nomor_baru = $nomor_terakhir + 1;

        $kode_khusus = sprintf(
            'SM-%s-%04d-%04d',
            $kode_kategori,
            $tahun,
            $nomor_baru
        );

        /*
        | MEMPERBARUI NOMOR URUT
        */

        $stmt = mysqli_prepare($koneksi, "
            UPDATE nomor_urut_surat
            SET nomor_terakhir = ?
            WHERE jenis = ?
              AND kategori = ?
              AND tahun = ?
        ");

        mysqli_stmt_bind_param(
            $stmt,
            'issi',
            $nomor_baru,
            $jenis,
            $kategori,
            $tahun
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        /*
        | MENYIMPAN FILE PDF
        */

        if (!move_uploaded_file(
            $file['tmp_name'],
            $path_upload
        )) {
            throw new Exception(
                'File PDF gagal diunggah.'
            );
        }

        /*
        | SIMPAN DATA KE TABEL SURAT MASUK
        */

        $stmt = mysqli_prepare($koneksi, "
            INSERT INTO surat_masuk
            (
                nomor_surat,
                kategori_surat,
                kode_khusus,
                tanggal_surat,
                pengirim,
                perihal,
                file_surat
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        mysqli_stmt_bind_param(
            $stmt,
            'sssssss',
            $nomor,
            $kategori,
            $kode_khusus,
            $tanggal,
            $pengirim,
            $perihal,
            $nama_file
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        /*
        | KONFIRMASI TRANSAKSI
        */

        mysqli_commit($koneksi);
        $transaksi = false;

        $pesan = 'Surat berhasil disimpan. ID Khusus: '
               . $kode_khusus;

        $tipe_pesan = 'success';

    } catch (Throwable $e) {

        if ($transaksi) {
            mysqli_rollback($koneksi);
        }

        if (
            $path_upload !== null &&
            is_file($path_upload)
        ) {
            unlink($path_upload);
        }

        error_log(
            'Gagal simpan surat masuk: ' .
            $e->getMessage()
        );

        $pesan = 'Penyimpanan gagal. Periksa data, '
               . 'file PDF, dan konfigurasi sistem.';

        $tipe_pesan = 'error';
    }
}

/*
|--------------------------------------------------------------------------
| PROSES PENCARIAN DATA
|--------------------------------------------------------------------------
*/

$cari = trim($_GET['cari'] ?? '');

if ($cari !== '') {

    $keyword = '%' . $cari . '%';

    $stmt_cari = mysqli_prepare($koneksi, "
        SELECT *
        FROM surat_masuk
        WHERE nomor_surat LIKE ?
           OR kode_khusus LIKE ?
           OR kategori_surat LIKE ?
           OR pengirim LIKE ?
           OR perihal LIKE ?
        ORDER BY id DESC
    ");

    mysqli_stmt_bind_param(
        $stmt_cari,
        'sssss',
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword
    );

    mysqli_stmt_execute($stmt_cari);

    $data = mysqli_stmt_get_result($stmt_cari);

} else {

    $data = mysqli_query($koneksi, "
        SELECT *
        FROM surat_masuk
        ORDER BY id DESC
    ");
}

$total_data = mysqli_num_rows($data);

?>

<style>

/* =========================
   FORM INPUT
========================= */

.form-surat{
    background:#ffffff;
    padding:20px;
    border-radius:12px;
    box-shadow:0 3px 10px rgba(0,0,0,0.08);
    margin-bottom:20px;
}

.form-surat label{
    display:block;
    font-weight:600;
    margin-bottom:6px;
    color:#334155;
}

.form-control{
    width:100%;
    max-width:450px;
    padding:10px;
    border:1px solid #cbd5e1;
    border-radius:7px;
    box-sizing:border-box;
    font-size:14px;
}

.form-control:focus{
    outline:none;
    border-color:#667eea;
}

.form-group{
    margin-bottom:15px;
}

.info-id{
    background:#eef2ff;
    padding:12px;
    border-left:4px solid #4f46e5;
    border-radius:6px;
    color:#3730a3;
    max-width:450px;
    box-sizing:border-box;
    font-size:13px;
    margin-bottom:15px;
}

.btn-simpan{
    background:#16a34a;
    color:white;
    border:none;
    border-radius:7px;
    padding:11px 25px;
    cursor:pointer;
    font-weight:bold;
}

.btn-simpan:hover{
    background:#15803d;
}

/* =========================
   PESAN
========================= */

.alert-success{
    background:#dcfce7;
    color:#166534;
    padding:14px;
    border-radius:8px;
    margin-bottom:15px;
}

.alert-error{
    background:#fee2e2;
    color:#991b1b;
    padding:14px;
    border-radius:8px;
    margin-bottom:15px;
}

/* =========================
   PENCARIAN
========================= */

.search-box{
    background:#ffffff;
    padding:20px;
    border-radius:15px;
    box-shadow:0 3px 10px rgba(0,0,0,0.1);
    margin-bottom:20px;
}

.search-input{
    width:350px;
    max-width:100%;
    padding:12px;
    border:1px solid #ddd;
    border-radius:10px;
    font-size:14px;
    outline:none;
    box-sizing:border-box;
}

.search-input:focus{
    border-color:#667eea;
}

.btn-cari{
    padding:12px 20px;
    border:none;
    border-radius:10px;
    background:#667eea;
    color:white;
    cursor:pointer;
    font-weight:bold;
}

.btn-cari:hover{
    background:#5563d6;
}

.btn-reset{
    padding:12px 20px;
    border:none;
    border-radius:10px;
    background:#dc3545;
    color:white;
    cursor:pointer;
    font-weight:bold;
    text-decoration:none;
    display:inline-block;
}

.btn-reset:hover{
    background:#c82333;
}

/* =========================
   TOMBOL EDIT
========================= */

.btn-edit{
    background:#f59e0b;
    color:white;
    padding:6px 12px;
    border-radius:5px;
    text-decoration:none;
    margin-right:5px;
    display:inline-block;
    font-weight:bold;
}

.btn-edit:hover{
    background:#d97706;
}

/* =========================
   TOMBOL HAPUS
========================= */

.btn-hapus{
    background:#dc2626;
    color:white;
    padding:6px 12px;
    border-radius:5px;
    text-decoration:none;
    display:inline-block;
    font-weight:bold;
}

.btn-hapus:hover{
    background:#b91c1c;
}

/* =========================
   TOMBOL LIHAT PDF
========================= */

.btn-lihat{
    background:#2563eb;
    color:white;
    padding:6px 12px;
    border-radius:5px;
    text-decoration:none;
    display:inline-block;
    margin-bottom:5px;
    font-weight:bold;
}

.btn-lihat:hover{
    background:#1d4ed8;
}

/* =========================
   TOMBOL DOWNLOAD
========================= */

.btn-download{
    background:#16a34a;
    color:white;
    padding:6px 12px;
    border-radius:5px;
    text-decoration:none;
    display:inline-block;
    font-weight:bold;
}

.btn-download:hover{
    background:#15803d;
}

/* =========================
   TABEL MODERN
========================= */

.table-wrapper{
    width:100%;
    overflow-x:auto;
}

.table-modern{
    width:100%;
    border-collapse:collapse;
    background:white;
    box-shadow:0 3px 10px rgba(0,0,0,0.1);
}

.table-modern th{
    background:#4f46e5;
    color:white;
    padding:12px;
    font-size:13px;
    font-weight:600;
    white-space:nowrap;
}

.table-modern td{
    padding:10px;
    border-bottom:1px solid #e5e7eb;
    font-size:11px;
    vertical-align:middle;
}

.table-modern tr:nth-child(even){
    background:#f8fafc;
}

.table-modern tr:hover{
    background:#eef2ff;
    transition:0.3s;
}

/* =========================
   BADGE KATEGORI
========================= */

.badge-penting{
    background:#fee2e2;
    color:#b91c1c;
    border-radius:20px;
    padding:5px 10px;
    font-weight:bold;
    display:inline-block;
}

.badge-biasa{
    background:#dbeafe;
    color:#1d4ed8;
    border-radius:20px;
    padding:5px 10px;
    font-weight:bold;
    display:inline-block;
}

.badge-belum{
    background:#f1f5f9;
    color:#64748b;
    border-radius:20px;
    padding:5px 10px;
    display:inline-block;
}

.kode-khusus{
    font-weight:bold;
    color:#4338ca;
    white-space:nowrap;
}

.info-data{
    margin:12px 0;
    font-size:13px;
}

/* =========================
   RESPONSIVE
========================= */

@media(max-width:768px){

    .search-input{
        width:100%;
        margin-bottom:10px;
    }

    .form-surat{
        padding:15px;
    }

    .table-modern{
        min-width:900px;
    }

}

</style>

<h2>Surat Masuk</h2>

<!-- =========================
     EXPORT PDF
========================= -->

<a href="export_pdf.php"
   target="_blank"
   rel="noopener noreferrer">

    <button type="button">
        Export PDF
    </button>

</a>

<br><br>

<!-- =========================
     PESAN HASIL PENYIMPANAN
========================= -->

<?php if ($pesan !== ''): ?>

    <div class="<?php
        echo $tipe_pesan === 'success'
        ? 'alert-success'
        : 'alert-error';
    ?>">

        <?php echo e_surat_masuk($pesan); ?>

    </div>

<?php endif; ?>

<!-- =========================
     FORM INPUT SURAT MASUK
========================= -->

<div class="form-surat">

    <h3>Tambah Arsip Surat Masuk</h3>

    <form
        method="POST"
        enctype="multipart/form-data"
    >

        <div class="form-group">

            <label>Nomor Surat</label>

            <input
                type="text"
                name="nomor_surat"
                class="form-control"
                placeholder="Masukkan nomor surat"
                maxlength="100"
                required
            >

        </div>

        <!-- KATEGORI SURAT -->

        <div class="form-group">

            <label>Kategori Surat</label>

            <select
                name="kategori_surat"
                class="form-control"
                required
            >

                <option value="">
                    -- Pilih Kategori Surat --
                </option>

                <option value="Penting">
                    Surat Penting
                </option>

                <option value="Biasa">
                    Surat Biasa
                </option>

            </select>

        </div>

        <!-- INFO ID KHUSUS -->

        <div class="info-id">

            ID Khusus akan dibuat otomatis
            setelah surat disimpan.

            <br><br>

            <b>Contoh:</b><br>

            Surat Penting:
            SM-P-2026-0001

            <br>

            Surat Biasa:
            SM-B-2026-0001

        </div>

        <div class="form-group">

            <label>Tanggal Surat</label>

            <input
                type="date"
                name="tanggal_surat"
                class="form-control"
                required
            >

        </div>

        <div class="form-group">

            <label>Pengirim</label>

            <input
                type="text"
                name="pengirim"
                class="form-control"
                placeholder="Masukkan nama pengirim"
                maxlength="100"
                required
            >

        </div>

        <div class="form-group">

            <label>Perihal</label>

            <textarea
                name="perihal"
                class="form-control"
                rows="3"
                maxlength="255"
                placeholder="Masukkan perihal surat"
                required
            ></textarea>

        </div>

        <div class="form-group">

            <label>File Surat (PDF)</label>

            <input
                type="file"
                name="file_surat"
                class="form-control"
                accept=".pdf,application/pdf"
                required
            >

            <small>
                Format PDF, maksimal 5 MB.
            </small>

        </div>

        <button
            type="submit"
            name="simpan"
            value="1"
            class="btn-simpan"
        >
            Simpan Surat
        </button>

    </form>

</div>

<hr>

<!-- =========================
     PENCARIAN SURAT MASUK
========================= -->

<h3>Pencarian Surat Masuk</h3>

<div class="search-box">

    <form method="GET">

        <input
            type="hidden"
            name="page"
            value="surat_masuk"
        >

        <input
            type="text"
            name="cari"
            class="search-input"
            placeholder="Cari nomor, ID khusus, kategori, pengirim atau perihal..."
            value="<?php echo e_surat_masuk($cari); ?>"
        >

        <button
            type="submit"
            class="btn-cari"
        >
            Cari
        </button>

        <a
            href="index.php?page=surat_masuk"
            class="btn-reset"
        >
            Reset
        </a>

    </form>

</div>

<!-- =========================
     TOTAL DATA
========================= -->

<div class="info-data">

    📄 Total Data Ditemukan:

    <b>
        <?php echo $total_data; ?>
    </b>

</div>

<hr>

<h3 style="margin-bottom:15px;">
    📥 Data Surat Masuk
</h3>

<!-- =========================
     TABEL DATA SURAT MASUK
========================= -->

<div class="table-wrapper">

<table class="table-modern">

    <thead>

        <tr>

            <th>No</th>

            <th>Nomor Surat</th>

            <th>ID Khusus</th>

            <th>Kategori</th>

            <th>Tanggal</th>

            <th>Pengirim</th>

            <th>Perihal</th>

            <th>File Surat</th>

            <th>Aksi</th>

        </tr>

    </thead>

    <tbody>

    <?php

    $no = 1;

    while ($d = mysqli_fetch_assoc($data)):

        $kategori_data = $d['kategori_surat'] ?? '';

        if ($kategori_data === 'Penting') {

            $class_badge = 'badge-penting';

        } elseif ($kategori_data === 'Biasa') {

            $class_badge = 'badge-biasa';

        } else {

            $class_badge = 'badge-belum';

            $kategori_data = 'Belum dikategorikan';
        }

        $kode_tampil = !empty($d['kode_khusus'])
            ? $d['kode_khusus']
            : '-';

    ?>

        <tr>

            <!-- NOMOR URUT -->

            <td>
                <?php echo $no++; ?>
            </td>

            <!-- NOMOR SURAT -->

            <td>
                <?php
                    echo e_surat_masuk(
                        $d['nomor_surat']
                    );
                ?>
            </td>

            <!-- ID KHUSUS -->

            <td>

                <span class="kode-khusus">

                    <?php
                        echo e_surat_masuk(
                            $kode_tampil
                        );
                    ?>

                </span>

            </td>

            <!-- KATEGORI SURAT -->

            <td>

                <span class="<?php
                    echo e_surat_masuk($class_badge);
                ?>">

                    <?php
                        echo e_surat_masuk(
                            $kategori_data
                        );
                    ?>

                </span>

            </td>

            <!-- TANGGAL -->

            <td>
                <?php
                    echo e_surat_masuk(
                        $d['tanggal_surat']
                    );
                ?>
            </td>

            <!-- PENGIRIM -->

            <td>
                <?php
                    echo e_surat_masuk(
                        $d['pengirim']
                    );
                ?>
            </td>

            <!-- PERIHAL -->

            <td>
                <?php
                    echo e_surat_masuk(
                        $d['perihal']
                    );
                ?>
            </td>

            <!-- FILE PDF -->

            <td>

            <?php if (!empty($d['file_surat'])): ?>

                <?php
                    $url_file = 'uploads/' .
                        rawurlencode(
                            basename($d['file_surat'])
                        );
                ?>

                <a
                    class="btn-lihat"
                    href="<?php echo e_surat_masuk($url_file); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    📄 Lihat
                </a>

                <a
                    class="btn-download"
                    href="<?php echo e_surat_masuk($url_file); ?>"
                    download
                >
                    ⬇ Download
                </a>

            <?php else: ?>

                Tidak Ada File

            <?php endif; ?>

            </td>

            <!-- AKSI -->

            <td>

                <a
                    class="btn-edit"
                    href="edit_surat.php?id=<?php
                        echo (int)$d['id'];
                    ?>"
                >
                    ✏ Edit
                </a>

                <a
                    class="btn-hapus"
                    href="pages/hapus_surat.php?id=<?php
                        echo (int)$d['id'];
                    ?>"
                    onclick="return confirm('Yakin ingin menghapus data ini?')"
                >
                    🗑 Hapus
                </a>

            </td>

        </tr>

    <?php endwhile; ?>

    </tbody>

</table>

</div>

<?php

if (isset($stmt_cari)) {
    mysqli_stmt_close($stmt_cari);
}

?>
