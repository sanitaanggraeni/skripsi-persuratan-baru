
<?php
include 'koneksi.php';

/*
|--------------------------------------------------------------------------
| FUNGSI OUTPUT AMAN
|--------------------------------------------------------------------------
*/

if (!function_exists('e_surat_keluar')) {
    function e_surat_keluar($value) {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

mysqli_report(
    MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT
);

$pesan = '';
$tipe_pesan = '';

/*
|--------------------------------------------------------------------------
| DAFTAR JENIS SURAT
|--------------------------------------------------------------------------
*/

$daftar_jenis_surat = [
    'Surat Aktif Kuliah',
    'Surat Aktif Kuliah Kembali',
    'Rekomendasi Beasiswa',
    'Rekomendasi Lomba',
    'Pengajuan Judul Skripsi',
    'Pengajuan Pembimbing Skripsi',
    'Seminar Proposal',
    'Undangan Seminar UPS1/UPS2',
    'Permohonan Cuti',
    'Pengusulan Pengajar',
    'Surat Pengantar KKN',
    'Surat Tugas Dosen',
    'Surat Ajuan Penelitian dan KKN'
];

/*
|--------------------------------------------------------------------------
| NOMOR SURAT RESMI OTOMATIS
| FORMAT LAMA TETAP DIPERTAHANKAN
|--------------------------------------------------------------------------
*/

$bulan_romawi = [
    1 => 'I',
    2 => 'II',
    3 => 'III',
    4 => 'IV',
    5 => 'V',
    6 => 'VI',
    7 => 'VII',
    8 => 'VIII',
    9 => 'IX',
    10 => 'X',
    11 => 'XI',
    12 => 'XII'
];

function buat_nomor_surat_keluar($nomor, $tanggal, $bulan_romawi) {

    $tgl = new DateTimeImmutable($tanggal);

    $bulan = $bulan_romawi[(int)$tgl->format('n')];
    $tahun = $tgl->format('Y');

    return sprintf('%02d', $nomor)
        . '/SI-FTI UNIBBA/Surat/'
        . $bulan
        . '/'
        . $tahun;
}

$query_nomor = mysqli_query($koneksi, "
    SELECT COALESCE(MAX(id), 0) AS id_terakhir
    FROM surat_keluar
");

$data_nomor = mysqli_fetch_assoc($query_nomor);

$nomor_urut = (int)$data_nomor['id_terakhir'] + 1;

$nomor_surat_otomatis = buat_nomor_surat_keluar(
    $nomor_urut,
    date('Y-m-d'),
    $bulan_romawi
);

/*
|--------------------------------------------------------------------------
| SIMPAN DATA SURAT KELUAR
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['simpan'])) {

    $lampiran = trim($_POST['lampiran'] ?? '');
    $tanggal = trim($_POST['tanggal_surat'] ?? '');

    $jenis_dropdown = trim(
        $_POST['jenis_surat_dropdown'] ?? ''
    );

    $jenis_manual = trim(
        $_POST['jenis_surat_manual'] ?? ''
    );

    $kategori = trim(
        $_POST['kategori_surat'] ?? ''
    );

    $tujuan = trim($_POST['tujuan'] ?? '');
    $perihal = trim($_POST['perihal'] ?? '');
    $isi_surat = trim($_POST['isi_surat'] ?? '');

    $transaksi = false;

    try {

        /*
        | VALIDASI DATA
        */

        if (
            $tanggal === '' ||
            $tujuan === '' ||
            $perihal === '' ||
            $isi_surat === ''
        ) {
            throw new Exception(
                'Semua data wajib diisi.'
            );
        }

        if (!in_array(
            $kategori,
            ['Penting', 'Biasa'],
            true
        )) {
            throw new Exception(
                'Kategori surat harus dipilih.'
            );
        }

        if ($jenis_dropdown === 'manual') {

            if ($jenis_manual === '') {
                throw new Exception(
                    'Jenis surat manual wajib diisi.'
                );
            }

            $jenis = $jenis_manual;

        } elseif (in_array(
            $jenis_dropdown,
            $daftar_jenis_surat,
            true
        )) {

            $jenis = $jenis_dropdown;

        } else {

            throw new Exception(
                'Jenis surat tidak valid.'
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
                'Tanggal surat tidak valid.'
            );
        }

        $tahun = (int)$tgl->format('Y');

        /*
        | MULAI TRANSAKSI
        */

        mysqli_begin_transaction($koneksi);
        $transaksi = true;

        /*
        | KATEGORI ID KHUSUS
        */

        $jenis_kode = 'SK';

        $kode_kategori = (
            $kategori === 'Penting'
        ) ? 'P' : 'B';

        /*
        | MEMASTIKAN COUNTER TERSEDIA
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
            $jenis_kode,
            $kategori,
            $tahun
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        /*
        | MENGUNCI NOMOR URUT
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
            $jenis_kode,
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
                'Nomor urut tidak ditemukan.'
            );
        }

        mysqli_stmt_close($stmt);

        /*
        | MEMBUAT ID KHUSUS
        */

        $nomor_baru = $nomor_terakhir + 1;

        $kode_khusus = sprintf(
            'SK-%s-%04d-%04d',
            $kode_kategori,
            $tahun,
            $nomor_baru
        );

        /*
        | UPDATE COUNTER
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
            $jenis_kode,
            $kategori,
            $tahun
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        /*
        | NOMOR SURAT RESMI
        |
        | Mengikuti pola lama yang sudah
        | digunakan dalam aplikasi.
        */

        $query_terakhir = mysqli_query($koneksi, "
            SELECT COALESCE(MAX(id), 0) AS terakhir
            FROM surat_keluar
        ");

        $hasil_terakhir = mysqli_fetch_assoc(
            $query_terakhir
        );

        $nomor_resmi_urut =
            (int)$hasil_terakhir['terakhir'] + 1;

        $nomor = buat_nomor_surat_keluar(
            $nomor_resmi_urut,
            $tanggal,
            $bulan_romawi
        );

        /*
        | SIMPAN SURAT KELUAR
        */

        $stmt = mysqli_prepare($koneksi, "
            INSERT INTO surat_keluar
            (
                nomor_surat,
                lampiran,
                tanggal_surat,
                jenis_surat,
                kategori_surat,
                kode_khusus,
                tujuan,
                perihal,
                isi_surat
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        mysqli_stmt_bind_param(
            $stmt,
            'sssssssss',
            $nomor,
            $lampiran,
            $tanggal,
            $jenis,
            $kategori,
            $kode_khusus,
            $tujuan,
            $perihal,
            $isi_surat
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        /*
        | SELESAIKAN TRANSAKSI
        */

        mysqli_commit($koneksi);
        $transaksi = false;

        $pesan = 'Surat berhasil disimpan. '
            . 'ID Khusus: ' . $kode_khusus
            . ' | Nomor Surat: ' . $nomor;

        $tipe_pesan = 'success';

        /*
        | PERBARUI NOMOR PREVIEW
        */

        $nomor_urut = $nomor_resmi_urut + 1;

        $nomor_surat_otomatis =
            buat_nomor_surat_keluar(
                $nomor_urut,
                date('Y-m-d'),
                $bulan_romawi
            );

    } catch (Throwable $e) {

        if ($transaksi) {
            mysqli_rollback($koneksi);
        }

        error_log(
            'Gagal simpan surat keluar: '
            . $e->getMessage()
        );

        $pesan = 'Gagal menyimpan surat. '
            . 'Periksa data dan konfigurasi database.';

        $tipe_pesan = 'error';
    }
}

/*
|--------------------------------------------------------------------------
| PENCARIAN DATA
|--------------------------------------------------------------------------
*/

$cari = trim($_GET['cari'] ?? '');

if ($cari !== '') {

    $keyword = '%' . $cari . '%';

    $stmt_cari = mysqli_prepare($koneksi, "
        SELECT *
        FROM surat_keluar
        WHERE nomor_surat LIKE ?
           OR kode_khusus LIKE ?
           OR kategori_surat LIKE ?
           OR jenis_surat LIKE ?
           OR tujuan LIKE ?
           OR perihal LIKE ?
        ORDER BY id DESC
    ");

    mysqli_stmt_bind_param(
        $stmt_cari,
        'ssssss',
        $keyword,
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
        FROM surat_keluar
        ORDER BY id DESC
    ");
}

$total_data = mysqli_num_rows($data);

?>

<style>

.form-surat-keluar {
    background: #ffffff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}

.form-surat-keluar label {
    display: block;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
}

.form-group {
    margin-bottom: 15px;
}

.form-control {
    display: block;
    width: 100%;
    max-width: 500px;
    padding: 10px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    box-sizing: border-box;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
}

.form-control[readonly] {
    background: #f1f5f9;
}

.info-id {
    background: #eef2ff;
    border-left: 4px solid #4f46e5;
    border-radius: 6px;
    max-width: 500px;
    padding: 12px;
    color: #3730a3;
    font-size: 13px;
    box-sizing: border-box;
}

.btn-simpan {
    background: #16a34a;
    color: white;
    padding: 11px 25px;
    border: none;
    border-radius: 7px;
    font-weight: bold;
    cursor: pointer;
}

.btn-simpan:hover {
    background: #15803d;
}

.alert-success {
    padding: 14px;
    background: #dcfce7;
    color: #166534;
    border-radius: 8px;
    margin-bottom: 15px;
}

.alert-error {
    padding: 14px;
    background: #fee2e2;
    color: #991b1b;
    border-radius: 8px;
    margin-bottom: 15px;
}

.search-box {
    background: #ffffff;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.search-input {
    width: 350px;
    max-width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 10px;
    font-size: 14px;
    box-sizing: border-box;
}

.search-input:focus {
    outline: none;
    border-color: #667eea;
}

.btn-cari {
    padding: 12px 20px;
    border: none;
    border-radius: 10px;
    background: #667eea;
    color: white;
    cursor: pointer;
    font-weight: bold;
}

.btn-reset {
    padding: 12px 20px;
    border: none;
    border-radius: 10px;
    background: #dc3545;
    color: white;
    text-decoration: none;
    display: inline-block;
    font-weight: bold;
}

.btn-cetak {
    background: #16a34a;
    color: white;
    padding: 8px 12px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-block;
    font-weight: bold;
}

.btn-hapus {
    background: #dc3545;
    color: white;
    padding: 8px 12px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-block;
    font-weight: bold;
    margin-top: 5px;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.table-modern {
    width: 100%;
    border-collapse: collapse;
    background: white;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.table-modern th {
    background: #4f46e5;
    color: white;
    padding: 12px;
    font-size: 13px;
    font-weight: 600;
}

.table-modern td {
    padding: 10px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 11px;
    vertical-align: middle;
}

.table-modern tr:nth-child(even) {
    background: #f8fafc;
}

.table-modern tr:hover {
    background: #eef2ff;
}

.info-data {
    background: #eef2ff;
    padding: 12px;
    border-left: 5px solid #4f46e5;
    border-radius: 8px;
    margin-bottom: 15px;
}

.badge-penting {
    display: inline-block;
    background: #fee2e2;
    color: #b91c1c;
    border-radius: 20px;
    padding: 5px 10px;
    font-weight: bold;
}

.badge-biasa {
    display: inline-block;
    background: #dbeafe;
    color: #1d4ed8;
    border-radius: 20px;
    padding: 5px 10px;
    font-weight: bold;
}

.badge-belum {
    display: inline-block;
    background: #f1f5f9;
    color: #64748b;
    border-radius: 20px;
    padding: 5px 10px;
}

.kode-khusus {
    color: #4338ca;
    font-weight: bold;
    white-space: nowrap;
}

@media(max-width:768px) {
    .form-surat-keluar {
        padding: 15px;
    }

    .table-modern {
        min-width: 950px;
    }

    .search-input {
        width: 100%;
        margin-bottom: 10px;
    }
}

</style>

<h2>Surat Keluar</h2>

<a href="export_pdf_keluar.php"
   target="_blank"
   rel="noopener noreferrer">
    <button type="button">
        Export PDF Surat Keluar
    </button>
</a>

<br><br>

<?php if ($pesan !== ''): ?>

<div class="<?php
    echo $tipe_pesan === 'success'
        ? 'alert-success'
        : 'alert-error';
?>">

    <?php echo e_surat_keluar($pesan); ?>

</div>

<?php endif; ?>

<!-- FORM SURAT KELUAR -->

<div class="form-surat-keluar">

<h3>Tambah Surat Keluar</h3>

<form method="POST">

    <div class="form-group">

        <label>Nomor Surat</label>

        <input
            type="text"
            name="nomor_surat"
            id="nomor_surat"
            class="form-control"
            value="<?php
                echo e_surat_keluar(
                    $nomor_surat_otomatis
                );
            ?>"
            readonly
        >

        <small>
            Nomor surat resmi dibuat otomatis.
        </small>

    </div>

    <!-- KATEGORI SURAT -->

    <div class="form-group">

        <label>Kategori Surat</label>

        <select
            name="kategori_surat"
            id="kategori_surat"
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

    <!-- INFORMASI ID KHUSUS -->

    <div class="form-group">

        <div class="info-id">

            ID khusus dibuat otomatis
            ketika surat disimpan.

            <br><br>

            <b>Contoh:</b>

            <br>

            Surat Penting:
            SK-P-2026-0001

            <br>

            Surat Biasa:
            SK-B-2026-0001

        </div>

    </div>

    <!-- LAMPIRAN -->

    <div class="form-group">

        <label>Lampiran</label>

        <input
            type="text"
            name="lampiran"
            class="form-control"
            maxlength="255"
            placeholder="Contoh: 1 Berkas"
        >

    </div>

    <!-- TANGGAL -->

    <div class="form-group">

        <label>Tanggal Surat</label>

        <input
            type="date"
            name="tanggal_surat"
            id="tanggal_surat"
            class="form-control"
            required
        >

    </div>

    <!-- JENIS SURAT -->

    <div class="form-group">

        <label>Jenis Surat</label>

        <select
            name="jenis_surat_dropdown"
            id="jenis_surat_dropdown"
            class="form-control"
            required
            onchange="cekJenisSurat(); isiTemplateSurat();"
        >

            <option value="">
                -- Pilih Jenis Surat --
            </option>

            <?php foreach ($daftar_jenis_surat as $item): ?>

                <option value="<?php
                    echo e_surat_keluar($item);
                ?>">

                    <?php echo e_surat_keluar($item); ?>

                </option>

            <?php endforeach; ?>

            <option value="manual">
                Lainnya / Isi Manual
            </option>

        </select>

    </div>

    <!-- JENIS MANUAL -->

    <div
        id="jenis_manual_box"
        class="form-group"
        style="display:none;"
    >

        <label>Jenis Surat Manual</label>

        <input
            type="text"
            name="jenis_surat_manual"
            id="jenis_surat_manual"
            class="form-control"
            maxlength="100"
            placeholder="Ketik jenis surat sendiri..."
        >

    </div>

    <!-- TUJUAN -->

    <div class="form-group">

        <label>Tujuan</label>

        <textarea
            name="tujuan"
            rows="4"
            class="form-control"
            required
            placeholder="Ketik tujuan surat di sini..."
        ></textarea>

    </div>

    <!-- PERIHAL -->

    <div class="form-group">

        <label>Perihal</label>

        <textarea
            name="perihal"
            rows="3"
            class="form-control"
            maxlength="255"
            required
        ></textarea>

    </div>

    <!-- ISI SURAT -->

    <div class="form-group">

        <label>Isi Surat</label>

        <textarea
            name="isi_surat"
            id="isi_surat"
            rows="10"
            class="form-control"
            required
            placeholder="Tuliskan isi lengkap surat di sini..."
        ></textarea>

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

<!-- PENCARIAN -->

<h3>Pencarian Surat Keluar</h3>

<div class="search-box">

<form method="GET">

    <input
        type="hidden"
        name="page"
        value="surat_keluar"
    >

    <input
        type="text"
        name="cari"
        class="search-input"
        placeholder="Cari nomor, ID khusus, kategori, jenis, tujuan atau perihal..."
        value="<?php
            echo e_surat_keluar($cari);
        ?>"
    >

    <button
        type="submit"
        class="btn-cari"
    >
        Cari
    </button>

    <a
        href="index.php?page=surat_keluar"
        class="btn-reset"
    >
        Reset
    </a>

</form>

</div>

<hr>

<h3 style="margin-bottom:15px;">
    📤 Data Surat Keluar
</h3>

<div class="info-data">

    📄 Total Data Ditemukan:

    <b><?php echo $total_data; ?></b>

</div>

<!-- TABEL SURAT KELUAR -->

<div class="table-wrapper">

<table class="table-modern">

<thead>

<tr>
    <th>No</th>
    <th>Nomor Surat</th>
    <th>ID Khusus</th>
    <th>Kategori</th>
    <th>Tanggal</th>
    <th>Jenis Surat</th>
    <th>Tujuan</th>
    <th>Perihal</th>
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

    <td>
        <?php echo $no++; ?>
    </td>

    <td>
        <?php
            echo e_surat_keluar(
                $d['nomor_surat']
            );
        ?>
    </td>

    <td>

        <span class="kode-khusus">

            <?php
                echo e_surat_keluar(
                    $kode_tampil
                );
            ?>

        </span>

    </td>

    <td>

        <span class="<?php
            echo e_surat_keluar($class_badge);
        ?>">

            <?php
                echo e_surat_keluar(
                    $kategori_data
                );
            ?>

        </span>

    </td>

    <td>
        <?php
            echo e_surat_keluar(
                $d['tanggal_surat']
            );
        ?>
    </td>

    <td>
        <?php
            echo e_surat_keluar(
                $d['jenis_surat']
            );
        ?>
    </td>

    <td>
        <?php
            echo nl2br(
                e_surat_keluar(
                    $d['tujuan']
                )
            );
        ?>
    </td>

    <td>
        <?php
            echo e_surat_keluar(
                $d['perihal']
            );
        ?>
    </td>

    <td>

        <a
            class="btn-cetak"
            href="cetak_surat.php?id=<?php
                echo (int)$d['id'];
            ?>"
            target="_blank"
            rel="noopener noreferrer"
        >
            🖨 Cetak
        </a>

        <br><br>

        <a
            class="btn-hapus"
            href="pages/hapus_surat_keluar.php?id=<?php
                echo (int)$d['id'];
            ?>"
            onclick="return confirm('Yakin ingin menghapus surat ini?');"
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

<!-- JAVASCRIPT -->

<script>

/*
|--------------------------------------------------------------------------
| JENIS SURAT MANUAL
|--------------------------------------------------------------------------
*/

function cekJenisSurat() {

    var dropdown = document.getElementById(
        "jenis_surat_dropdown"
    );

    var boxManual = document.getElementById(
        "jenis_manual_box"
    );

    var inputManual = document.getElementById(
        "jenis_surat_manual"
    );

    if (dropdown.value === "manual") {

        boxManual.style.display = "block";
        inputManual.required = true;

    } else {

        boxManual.style.display = "none";
        inputManual.required = false;
        inputManual.value = "";
    }
}

/*
|--------------------------------------------------------------------------
| PENYESUAIAN PREVIEW NOMOR SURAT
|--------------------------------------------------------------------------
*/

function updatePreviewNomorSurat() {

    var tanggalInput = document.getElementById(
        "tanggal_surat"
    );

    var nomorInput = document.getElementById(
        "nomor_surat"
    );

    if (!tanggalInput.value) {
        return;
    }

    var bagian = tanggalInput.value.split("-");

    if (bagian.length !== 3) {
        return;
    }

    var romawi = [
        "I", "II", "III", "IV",
        "V", "VI", "VII", "VIII",
        "IX", "X", "XI", "XII"
    ];

    var bulanIndex = parseInt(bagian[1], 10) - 1;

    if (bulanIndex < 0 || bulanIndex > 11) {
        return;
    }

    var urutan = <?php echo (int)$nomor_urut; ?>;

    var nomorAwal = String(urutan).padStart(2, "0");

    nomorInput.value =
        nomorAwal +
        "/SI-FTI UNIBBA/Surat/" +
        romawi[bulanIndex] +
        "/" +
        bagian[0];
}

document.getElementById("tanggal_surat")
    .addEventListener(
        "change",
        updatePreviewNomorSurat
    );

/*
|--------------------------------------------------------------------------
| TEMPLATE ISI SURAT
|--------------------------------------------------------------------------
*/

function isiTemplateSurat() {

    var jenis = document.getElementById(
        "jenis_surat_dropdown"
    ).value;

    var isiSurat = document.getElementById(
        "isi_surat"
    );

    var template = "";

    if (jenis === "Surat Aktif Kuliah") {

        template = `Dengan hormat,

Yang bertanda tangan di bawah ini menerangkan bahwa mahasiswa yang bersangkutan benar merupakan mahasiswa aktif pada Fakultas Teknologi Informasi Universitas Bale Bandung.

Surat keterangan ini dibuat untuk dipergunakan sebagaimana mestinya.

Demikian surat ini dibuat, atas perhatian dan kerja samanya kami ucapkan terima kasih.`;

    }

    else if (jenis === "Surat Aktif Kuliah Kembali") {

        template = `Dengan hormat,

Bersama surat ini kami menerangkan bahwa mahasiswa yang bersangkutan telah aktif kembali mengikuti kegiatan perkuliahan pada Fakultas Teknologi Informasi Universitas Bale Bandung.

Surat ini dibuat sebagai keterangan resmi untuk digunakan sebagaimana mestinya.

Demikian surat ini kami sampaikan, atas perhatian dan kerja samanya kami ucapkan terima kasih.`;

    }

    else if (jenis === "Rekomendasi Beasiswa") {

        template = `Dengan hormat,

Sehubungan dengan adanya pengajuan beasiswa, maka dengan ini kami memberikan rekomendasi kepada mahasiswa yang bersangkutan untuk mengikuti proses seleksi beasiswa tersebut.

Mahasiswa tersebut dinilai layak untuk mendapatkan kesempatan mengikuti program beasiswa sesuai dengan ketentuan yang berlaku.

Demikian surat rekomendasi ini dibuat untuk dipergunakan sebagaimana mestinya.`;

    }

    else if (jenis === "Rekomendasi Lomba") {

        template = `Dengan hormat,

Bersama surat ini kami memberikan rekomendasi kepada mahasiswa yang bersangkutan untuk mengikuti kegiatan lomba sesuai dengan bidang dan ketentuan yang berlaku.

Diharapkan mahasiswa tersebut dapat mengikuti kegiatan dengan baik serta menjaga nama baik Fakultas Teknologi Informasi Universitas Bale Bandung.

Demikian surat rekomendasi ini dibuat untuk dipergunakan sebagaimana mestinya.`;

    }

    else if (jenis === "Pengajuan Judul Skripsi") {

        template = `Dengan hormat,

Bersama surat ini kami sampaikan bahwa mahasiswa yang bersangkutan mengajukan judul skripsi untuk dapat diproses lebih lanjut sesuai dengan ketentuan akademik yang berlaku.

Demikian surat pengajuan ini dibuat untuk dipergunakan sebagaimana mestinya.`;

    }

    else if (jenis === "Pengajuan Pembimbing Skripsi") {

        template = `Dengan hormat,

Sehubungan dengan proses penyusunan skripsi, maka mahasiswa yang bersangkutan mengajukan permohonan penetapan dosen pembimbing skripsi.

Demikian surat pengajuan ini dibuat untuk dapat diproses sesuai dengan ketentuan yang berlaku.`;

    }

    else if (jenis === "Seminar Proposal") {

        template = `Dengan hormat,

Bersama surat ini kami sampaikan bahwa mahasiswa yang bersangkutan telah memenuhi persyaratan untuk melaksanakan Seminar Proposal.

Demikian surat ini dibuat untuk dapat dipergunakan sebagaimana mestinya.`;

    }

    else if (jenis === "Undangan Seminar UPS1/UPS2") {

        template = `Dengan hormat,

Bersama surat ini kami mengundang Bapak/Ibu untuk hadir dalam kegiatan Seminar UPS1/UPS2 mahasiswa Fakultas Teknologi Informasi Universitas Bale Bandung.

Demikian undangan ini kami sampaikan, atas perhatian dan kehadirannya kami ucapkan terima kasih.`;

    }

    else if (jenis === "Permohonan Cuti") {

        template = `Dengan hormat,

Bersama surat ini mahasiswa yang bersangkutan mengajukan permohonan cuti akademik sesuai dengan ketentuan yang berlaku di Universitas Bale Bandung.

Demikian surat permohonan ini dibuat untuk dapat diproses sebagaimana mestinya.`;

    }

    else if (jenis === "Pengusulan Pengajar") {

        template = `Dengan hormat,

Bersama surat ini kami mengusulkan tenaga pengajar untuk mata kuliah pada Fakultas Teknologi Informasi Universitas Bale Bandung.

Demikian surat pengusulan ini dibuat untuk dapat diproses sebagaimana mestinya.`;

    }

    else if (jenis === "Surat Pengantar KKN") {

        template = `Dengan hormat,

Bersama surat ini kami sampaikan surat pengantar bagi mahasiswa yang akan melaksanakan kegiatan Kuliah Kerja Nyata sesuai dengan ketentuan yang berlaku.

Demikian surat pengantar ini dibuat untuk dipergunakan sebagaimana mestinya.`;

    }

    else if (jenis === "Surat Tugas Dosen") {

        template = `Dengan hormat,

Bersama surat ini kami menugaskan dosen yang bersangkutan untuk melaksanakan tugas sesuai dengan kebutuhan Fakultas Teknologi Informasi Universitas Bale Bandung.

Demikian surat tugas ini dibuat untuk dilaksanakan dengan penuh tanggung jawab.`;

    }

    else if (jenis === "Surat Ajuan Penelitian dan KKN") {

        template = `Dengan hormat,

Bersama surat ini kami mengajukan permohonan pelaksanaan penelitian dan/atau kegiatan Kuliah Kerja Nyata bagi mahasiswa yang bersangkutan.

Demikian surat ajuan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.`;

    }

    else if (jenis === "manual") {

        template = "";
    }

    isiSurat.value = template;
}

</script>
