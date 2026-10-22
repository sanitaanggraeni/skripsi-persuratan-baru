<?php
/*
|--------------------------------------------------------------------------
| CETAK SURAT KELUAR MENJADI PDF
|--------------------------------------------------------------------------
| Menggunakan Dompdf
| Format kertas A4
| Menampilkan ID khusus dan kategori
|--------------------------------------------------------------------------
*/

// Tangkap output yang tidak sengaja muncul.
$buffer_awal = ob_get_level();
ob_start();

try {

    require_once __DIR__ . '/dompdf/autoload.inc.php';
    require_once __DIR__ . '/koneksi.php';

    if (!class_exists('\Dompdf\Dompdf')) {
        throw new Exception('Library Dompdf tidak ditemukan.');
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDASI ID
    |--------------------------------------------------------------------------
    */

    $id = filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$id) {
        throw new Exception('ID surat tidak valid.');
    }

    /*
    |--------------------------------------------------------------------------
    | AMBIL DATA SURAT
    |--------------------------------------------------------------------------
    */

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT * FROM surat_keluar WHERE id = ?"
    );

    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    if (!$data) {
        throw new Exception('Data surat tidak ditemukan.');
    }

    /*
    |--------------------------------------------------------------------------
    | FUNGSI KEAMANAN HTML
    |--------------------------------------------------------------------------
    */

    function aman_pdf($nilai) {
        return htmlspecialchars(
            (string)($nilai ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DATA SURAT
    |--------------------------------------------------------------------------
    */

    $nomor_surat = aman_pdf($data['nomor_surat']);
    $jenis_surat = aman_pdf($data['jenis_surat']);
    $perihal = aman_pdf($data['perihal']);
    $lampiran = aman_pdf($data['lampiran'] ?: '-');

    $tujuan = nl2br(
        aman_pdf($data['tujuan'])
    );

    $isi_surat = nl2br(
        aman_pdf($data['isi_surat'])
    );

    $kategori = $data['kategori_surat'] ?: 'Belum dikategorikan';

    $kode_khusus = $data['kode_khusus'] ?: '-';

    $kategori_html = aman_pdf($kategori);
    $kode_html = aman_pdf($kode_khusus);

    /*
    |--------------------------------------------------------------------------
    | TANGGAL SURAT INDONESIA
    |--------------------------------------------------------------------------
    */

    $bulan = [
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

    $tgl = new DateTimeImmutable(
        $data['tanggal_surat']
    );

    $tanggal_cetak =
        $tgl->format('d') . ' ' .
        $bulan[(int)$tgl->format('n')] . ' ' .
        $tgl->format('Y');

    /*
    |--------------------------------------------------------------------------
    | LOGO UNIVERSITAS
    |--------------------------------------------------------------------------
    */

    $logo = __DIR__ . '/assets/image/logofti.png';

    $logo_html = '';

    if (is_file($logo) && is_readable($logo)) {

        $data_logo = file_get_contents($logo);

        if ($data_logo !== false) {
            $logo_base64 = 'data:image/png;base64,'
                . base64_encode($data_logo);

            $logo_html =
                '<img src="' . $logo_base64 . '" class="logo">';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DOKUMEN HTML PDF
    |--------------------------------------------------------------------------
    */

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">

    <style>

    @page {
        size: A4 portrait;
        margin: 20mm 20mm 20mm 20mm;
    }

    body {
        font-family: Arial, sans-serif;
        font-size: 12px;
        line-height: 1.7;
        color: #111;
    }

    .header {
        width: 100%;
        margin-bottom: 20px;
    }

    .header-table {
        width: 100%;
        border-collapse: collapse;
        border-bottom: 2px solid #9a9698;
    }

    .header-table td {
        vertical-align: middle;
        padding-bottom: 5px;
    }

    .logo-area {
        width: 95px;
        text-align: center;
    }

    .logo {
        width: 80px;
        height: auto;
    }

    .text-area {
        text-align: center;
        color: #8f8a8d;
        font-weight: bold;
    }

    .nama-kampus {
        font-size: 24px;
        font-weight: bold;
        text-align: center;
        white-space: nowrap;
        line-height: 1.2;
    }

    .nama-fakultas {
        font-size: 15px;
        font-weight: bold;
        margin-top: 4px;
    }

    .nama-sistem {
        font-size: 12px;
        font-weight: bold;
        margin-top: 5px;
        line-height: 1.3;
    }

    .alamat-kampus {
        text-align: center;
        color: #8f8a8d;
        font-size: 9px;
        font-weight: bold;
        border-bottom: 2px solid #9a9698;
        padding: 5px 0;
        line-height: 1.3;
    }

    .judul {
        text-align: center;
        font-size: 17px;
        font-weight: bold;
        margin-top: 20px;
        margin-bottom: 23px;
        text-transform: uppercase;
    }

    .info {
        margin-top: 15px;
    }

    .info-table {
        width: 100%;
        font-size: 12px;
        border-collapse: collapse;
    }

    .info-table td {
        padding: 3px 0;
        vertical-align: top;
    }

    .info-label {
        width: 105px;
    }

    .info-separator {
        width: 12px;
    }

    .tujuan {
        margin-top: 25px;
    }

    .isi {
        margin-top: 25px;
        text-align: justify;
        line-height: 1.8;
    }

    .ttd {
        margin-top: 55px;
        text-align: right;
        page-break-inside: avoid;
    }

    </style>

    </head>

    <body>

    <div class="header">

        <table class="header-table">

            <tr>

                <td class="logo-area">
                    ' . $logo_html . '
                </td>

                <td class="text-area">

                    <div class="nama-kampus">
                        UNIVERSITAS BALE BANDUNG
                    </div>

                    <div class="nama-fakultas">
                        FAKULTAS TEKNOLOGI INFORMASI
                    </div>

                    <div class="nama-sistem">
                        PROGRAM STUDI : TEKNIK INFORMATIKA DAN
                        <br>
                        SISTEM INFORMASI
                    </div>

                </td>

                <td style="width:40px;"></td>

            </tr>

        </table>

        <div class="alamat-kampus">
            JL. R.A.A. WIRANATAKUSUMAH No. 7
            BALEENDAH KAB. BANDUNG 40258
            <br>
            Tlp. 022-5940443, 5949221,
            Fax. 022-5940443
        </div>

    </div>

    <div class="judul">
        ' . $jenis_surat . '
    </div>

    <div class="info">

        <table class="info-table">

            <tr>
                <td class="info-label"><b>Nomor</b></td>
                <td class="info-separator">:</td>
                <td>' . $nomor_surat . '</td>
            </tr>

            <tr>
                <td><b>Lampiran</b></td>
                <td>:</td>
                <td>' . $lampiran . '</td>
            </tr>

            <tr>
                <td><b>Perihal</b></td>
                <td>:</td>
                <td>' . $perihal . '</td>
            </tr>

        </table>

        <div class="tujuan">
            Kepada Yth.
            <br>
            ' . $tujuan . '
            <br>
            di Tempat
        </div>

    </div>

    <div class="isi">
        ' . $isi_surat . '
    </div>

    <div class="ttd">

        Bandung, ' . $tanggal_cetak . '

        <br><br>

        Ketua Program Studi
        <br>
        Sistem Informasi

        <br><br><br><br>

        <b><u>Rosmalina, S.T., M.Kom.</u></b>

    </div>

    </body>
    </html>
    ';

    /*
    |--------------------------------------------------------------------------
    | PENGATURAN DOMPDF
    |--------------------------------------------------------------------------
    */

    $options = new \Dompdf\Options();

    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'Arial');

    $dompdf = new \Dompdf\Dompdf($options);

    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    /*
    |--------------------------------------------------------------------------
    | TAMBAHKAN IDENTITAS INTERNAL
    | DI METADATA PDF, BUKAN ISI SURAT RESMI
    |--------------------------------------------------------------------------
    */

    // Nama file aman: tidak memakai slash nomor surat.
    $kode_file = preg_replace(
        '/[^A-Za-z0-9_-]/',
        '_',
        (string)$kode_khusus
    );

    if ($kode_file === '' || $kode_file === '_') {
        $kode_file = 'ID-' . $id;
    }

    $nama_pdf = 'Surat_Keluar_' . $kode_file . '.pdf';

    /*
    |--------------------------------------------------------------------------
    | HASIL PDF
    |--------------------------------------------------------------------------
    */


$pdf = $dompdf->output();

// Bersihkan buffer yang dibuat script ini.
while (ob_get_level() > $buffer_awal) {
    ob_end_clean();
}

// Jika header sudah terkirim, catat lokasi sumbernya.
if (headers_sent($file, $line)) {
    error_log(
        "PDF headers already sent at $file:$line"
    );

    http_response_code(500);
    exit(
        'Output sudah dimulai pada: ' .
        basename($file) .
        ' baris ' . $line
    );
}

// Kirim PDF ke browser.
header('Content-Type: application/pdf');
header(
    'Content-Disposition: inline; filename="' .
    $nama_pdf . '"'
);
header('Content-Length: ' . strlen($pdf));
header('X-Content-Type-Options: nosniff');

echo $pdf;
exit;


} catch (Throwable $e) {

    error_log(
        'Gagal cetak PDF surat keluar: ' . $e->getMessage()
    );

    while (ob_get_level() > $buffer_awal) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }

    exit(
        'Gagal membuat PDF. Periksa log error PHP di hosting.'
    );
}
