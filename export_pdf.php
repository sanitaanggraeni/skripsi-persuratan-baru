<?php
/*
|--------------------------------------------------------------------------
| EXPORT LAPORAN SURAT MASUK KE PDF
|--------------------------------------------------------------------------
| Menggunakan Dompdf
| Menampilkan ID Khusus dan Kategori Surat
|--------------------------------------------------------------------------
*/

$buffer_awal = ob_get_level();
ob_start();

try {

    require_once __DIR__ . '/dompdf/autoload.inc.php';
    require_once __DIR__ . '/koneksi.php';

    /*
    |--------------------------------------------------------------------------
    | FUNGSI KEAMANAN OUTPUT
    |--------------------------------------------------------------------------
    */

    if (!function_exists('aman_laporan_masuk')) {

        function aman_laporan_masuk($nilai) {

            return htmlspecialchars(
                (string)($nilai ?? ''),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | AMBIL DATA SURAT MASUK
    |--------------------------------------------------------------------------
    */

    $data = mysqli_query($koneksi, "
        SELECT
            id,
            nomor_surat,
            kode_khusus,
            kategori_surat,
            tanggal_surat,
            pengirim,
            perihal
        FROM surat_masuk
        ORDER BY id DESC
    ");

    /*
    |--------------------------------------------------------------------------
    | LOGO UNIVERSITAS
    |--------------------------------------------------------------------------
    */

    $logo = __DIR__ . '/assets/image/logo.png';

    $logo_html = '';

    if (is_file($logo) && is_readable($logo)) {

        $data_logo = file_get_contents($logo);

        if ($data_logo !== false) {

            $logo_base64 =
                'data:image/png;base64,' .
                base64_encode($data_logo);

            $logo_html =
                '<img src="' .
                $logo_base64 .
                '" class="logo">';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TANGGAL CETAK
    |--------------------------------------------------------------------------
    */

    $bulan_indonesia = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];

    $tanggal_cetak =
        date('d') . ' ' .
        $bulan_indonesia[(int)date('n')] . ' ' .
        date('Y');

    /*
    |--------------------------------------------------------------------------
    | HTML LAPORAN
    |--------------------------------------------------------------------------
    */

    $html = '
    <!DOCTYPE html>
    <html lang="id">

    <head>
    <meta charset="UTF-8">

    <style>

    @page {
        size: A4 landscape;
        margin: 18mm 12mm 18mm 12mm;
    }

    body {
        font-family: Arial, sans-serif;
        font-size: 11px;
        color: #111;
    }

    .header {
        text-align: center;
        border-bottom: 3px solid black;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .logo {
        width: 80px;
        height: auto;
    }

    .nama-kampus {
        font-size: 20px;
        font-weight: bold;
        margin-top: 10px;
    }

    .nama-sistem {
        font-size: 14px;
        margin-top: 5px;
    }

    .judul {
        text-align: center;
        font-size: 18px;
        font-weight: bold;
        margin-top: 20px;
        margin-bottom: 20px;
    }

    .info {
        margin-bottom: 15px;
        font-size: 11px;
    }

    .tabel-surat {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .tabel-surat thead {
        display: table-header-group;
    }

    .tabel-surat th,
    .tabel-surat td {
        border: 1px solid black;
        padding: 6px 4px;
        text-align: center;
        vertical-align: top;
        overflow-wrap: break-word;
        word-wrap: break-word;
    }

    .tabel-surat th {
        background: #d9d9d9;
        font-size: 10px;
        font-weight: bold;
    }

    .tabel-surat td {
        font-size: 9px;
    }

    .tabel-surat tr {
        page-break-inside: avoid;
    }

    .text-left {
        text-align: left !important;
    }

    .kode {
        font-weight: bold;
    }

    .arsip-lama {
        color: #666;
        font-style: italic;
    }

    .ttd {
        width: 100%;
        margin-top: 45px;
        page-break-inside: avoid;
    }

    .ttd-table {
        width: 100%;
        border: none;
    }

    .ttd-table td {
        width: 50%;
        border: none;
        vertical-align: top;
        text-align: center;
    }

    .catatan {
        margin-top: 12px;
        font-size: 9px;
        color: #555;
    }

    </style>

    </head>

    <body>

    <div class="header">

        ' . $logo_html . '

        <div class="nama-kampus">
            UNIVERSITAS BALE BANDUNG
        </div>

        <div class="nama-sistem">
            SISTEM INFORMASI PERSURATAN KAMPUS
        </div>

    </div>

    <div class="judul">
        LAPORAN SURAT MASUK
    </div>

    <div class="info">
        <strong>Tanggal Cetak:</strong>
        ' . aman_laporan_masuk($tanggal_cetak) . '
    </div>

    <table class="tabel-surat">

        <thead>
            <tr>
                <th style="width:4%;">No</th>
                <th style="width:21%;">Nomor Surat</th>
                <th style="width:17%;">ID Khusus</th>
                <th style="width:11%;">Kategori</th>
                <th style="width:12%;">Tanggal Surat</th>
                <th style="width:15%;">Pengirim</th>
                <th style="width:20%;">Perihal</th>
            </tr>
        </thead>

        <tbody>
    ';

    /*
    |--------------------------------------------------------------------------
    | MENAMPILKAN DATA SURAT
    |--------------------------------------------------------------------------
    */

    $no = 1;
    $total_data = 0;

    while ($d = mysqli_fetch_assoc($data)) {

        $total_data++;

        $kode_khusus = !empty($d['kode_khusus'])
            ? $d['kode_khusus']
            : '-';

        $kategori = !empty($d['kategori_surat'])
            ? $d['kategori_surat']
            : 'Arsip Lama';

        $class_kode = empty($d['kode_khusus'])
            ? 'arsip-lama'
            : 'kode';

        $html .= '
        <tr>

            <td>' . $no++ . '</td>

            <td class="text-left">'
                . aman_laporan_masuk($d['nomor_surat']) .
            '</td>

            <td class="' . $class_kode . '">'
                . aman_laporan_masuk($kode_khusus) .
            '</td>

            <td>'
                . aman_laporan_masuk($kategori) .
            '</td>

            <td>'
                . aman_laporan_masuk($d['tanggal_surat']) .
            '</td>

            <td class="text-left">'
                . aman_laporan_masuk($d['pengirim']) .
            '</td>

            <td class="text-left">'
                . aman_laporan_masuk($d['perihal']) .
            '</td>

        </tr>
        ';
    }

    /*
    |--------------------------------------------------------------------------
    | JIKA DATA KOSONG
    |--------------------------------------------------------------------------
    */

    if ($total_data === 0) {

        $html .= '
        <tr>
            <td colspan="7">
                Belum ada data surat masuk.
            </td>
        </tr>
        ';
    }

    $html .= '
        </tbody>
    </table>

    <div class="catatan">
        <strong>Total Surat Masuk:</strong>
        ' . $total_data . ' surat
        <br>
        Keterangan: Arsip Lama adalah surat yang
        tersimpan sebelum penerapan sistem ID khusus.
    </div>

    <div class="ttd">

        <table class="ttd-table">
            <tr>
                <td></td>

                <td>
                    Bandung, '
                    . aman_laporan_masuk($tanggal_cetak) .
                    '<br><br><br><br><br>

                    (___________________)
                    <br>
                    Administrator
                </td>
            </tr>
        </table>

    </div>

    </body>
    </html>
    ';

    /*
    |--------------------------------------------------------------------------
    | KONFIGURASI DOMPDF
    |--------------------------------------------------------------------------
    */

    $options = new \Dompdf\Options();

    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'Arial');

    $dompdf = new \Dompdf\Dompdf($options);

    $dompdf->loadHtml($html, 'UTF-8');

    // Landscape agar tujuh kolom tetap terbaca.
    $dompdf->setPaper('A4', 'landscape');

    $dompdf->render();

    $pdf = $dompdf->output();

    /*
    |--------------------------------------------------------------------------
    | KIRIM FILE PDF
    |--------------------------------------------------------------------------
    */

    while (ob_get_level() > $buffer_awal) {
        ob_end_clean();
    }

    if (headers_sent($file, $line)) {

        error_log(
            "Header PDF sudah terkirim: $file:$line"
        );

        exit(
            'PDF gagal dikirim. '
            . 'Periksa encoding file PHP.'
        );
    }

    $nama_file = 'Laporan_Surat_Masuk.pdf';

    header('Content-Type: application/pdf');

    header(
        'Content-Disposition: attachment; filename="' .
        $nama_file . '"'
    );

    header('Content-Length: ' . strlen($pdf));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');

    echo $pdf;
    exit;

} catch (Throwable $e) {

    error_log(
        'Gagal export PDF Surat Masuk: ' .
        $e->getMessage()
    );

    while (ob_get_level() > $buffer_awal) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }

    exit(
        'Gagal membuat laporan PDF. '
        . 'Periksa log error PHP di hosting.'
    );
}
