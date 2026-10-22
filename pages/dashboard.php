<?php
include 'koneksi.php';

if (!function_exists('tanggal_indo_dashboard')) {
    function tanggal_indo_dashboard($tanggal)
    {
        if ($tanggal == '' || $tanggal == null || $tanggal == '0000-00-00') {
            return '-';
        }

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

        $pecah = explode('-', $tanggal);

        if (count($pecah) != 3) {
            return $tanggal;
        }

        return $pecah[2] . ' ' . $bulan[(int)$pecah[1]] . ' ' . $pecah[0];
    }
}

// TOTAL
$total_masuk = mysqli_num_rows(mysqli_query($koneksi,"SELECT * FROM surat_masuk"));
$total_keluar = mysqli_num_rows(mysqli_query($koneksi,"SELECT * FROM surat_keluar"));
$total_arsip = mysqli_num_rows(
    mysqli_query(
        $koneksi,
        "SELECT * FROM surat_masuk
         WHERE file_surat IS NOT NULL
         AND file_surat != ''"
    )
);

$statistik_jenis = mysqli_query($koneksi,"
SELECT
    jenis_surat,
    COUNT(*) as total
FROM surat_keluar
GROUP BY jenis_surat
ORDER BY total DESC
LIMIT 5
");

// SURAT MASUK TERBARU
$surat_masuk_terbaru = mysqli_query($koneksi,"
SELECT *
FROM surat_masuk
ORDER BY id DESC
LIMIT 5
");

// SURAT KELUAR TERBARU
$surat_keluar_terbaru = mysqli_query($koneksi,"
SELECT *
FROM surat_keluar
ORDER BY id DESC
LIMIT 5
");

// DATA GRAFIK
$data = mysqli_query($koneksi,"
SELECT MONTH(tanggal_surat) as bulan, COUNT(*) as total
FROM surat_masuk
GROUP BY MONTH(tanggal_surat)
");

$bulan = [];
$total = [];

while($d = mysqli_fetch_array($data)){
    $bulan[] = "Bulan " . $d['bulan'];
    $total[] = (int)$d['total'];
}
?>

<style>

.dashboard-card{
    flex:1;
    min-width:220px;
    height:250px;
    padding:25px;
    border-radius:25px;
    color:white;
    text-align:center;

    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;

    box-shadow:0 12px 25px rgba(0,0,0,0.18);
    transition:0.3s;
    overflow:hidden;
}

.dashboard-card:hover{
    transform:translateY(-5px);
}

.card-icon{
    font-size:40px;
    margin-bottom:10px;
}

.card-title{
    font-size:18px;
    margin-bottom:10px;
    font-weight:600;
}

.card-total{
    font-size:36px;
    font-weight:bold;
}

.card-subtitle{
    font-size:13px;
    opacity:0.9;
}

.dashboard-box{
    background:white;
    padding:20px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,0.1);
}

.dashboard-table{
    width:100%;
    border-collapse:collapse;
    table-layout:auto;
}

.dashboard-table th{
    padding:12px;
    text-align:left;
    color:white;
}

.dashboard-table td{
    padding:12px;
    border-bottom:1px solid #e5e7eb;
    vertical-align:top;
}

.dashboard-table tr:nth-child(even){
    background:#f8fafc;
}

.dashboard-table tr:hover{
    background:#eef2ff;
}

.tanggal-col{
    width:140px;
    min-width:140px;
    white-space:nowrap;
    text-align:center;
}

.statistik-item{
    margin-bottom:15px;
}

.progress{
    width:100%;
    background:#e5e7eb;
    border-radius:20px;
    overflow:hidden;
    height:12px;
}

.progress-bar{
    height:12px;
    background:linear-gradient(135deg,#667eea,#764ba2);
}

.jumlah{
    float:right;
    font-weight:bold;
}

</style>

<h2>Dashboard</h2>

<!-- CARD -->
<div style="
display:flex;
gap:20px;
margin-bottom:30px;
flex-wrap:wrap;
">

    <div
        class="dashboard-card"
        style="background:linear-gradient(135deg,#667eea,#764ba2);">

        <div class="card-icon">📥</div>

        <div class="card-title">
            Surat Masuk
        </div>

        <div class="card-total">
            <?php echo $total_masuk; ?>
        </div>

        <div class="card-subtitle">
            Total surat masuk yang diterima
        </div>

    </div>

    <div
        class="dashboard-card"
        style="background:linear-gradient(135deg,#43cea2,#185a9d);">

        <div class="card-icon">📤</div>

        <div class="card-title">
            Surat Keluar
        </div>

        <div class="card-total">
            <?php echo $total_keluar; ?>
        </div>

        <div class="card-subtitle">
            Total surat keluar yang dibuat
        </div>

    </div>

    <div class="dashboard-box">

        <h3>
        📊 Jenis Surat Keluar Terbanyak
        </h3>

        <?php

        while($j = mysqli_fetch_array($statistik_jenis)){

            $persen = $j['total'] * 10;

            if($persen > 100){
                $persen = 100;
            }

        ?>

        <div class="statistik-item">

            <span>
                <?php echo $j['jenis_surat']; ?>
            </span>

            <span class="jumlah">
                <?php echo $j['total']; ?>
            </span>

            <br><br>

            <div class="progress">

                <div
                    class="progress-bar"
                    style="width:<?php echo $persen; ?>%;">
                </div>

            </div>

        </div>

        <?php } ?>

    </div>

    <div
        class="dashboard-card"
        style="background:linear-gradient(135deg,#f59e0b,#ea580c);">

        <div class="card-icon">📂</div>

        <div class="card-title">
            Arsip Digital
        </div>

        <div class="card-total">
            <?php echo $total_arsip; ?>
        </div>

        <div class="card-subtitle">
            File PDF yang tersimpan
        </div>

    </div>

    <!-- CHART CARD -->
    <div class="dashboard-box">
        <h3>Statistik Surat Masuk</h3>
        <canvas id="chart" height="180"></canvas>
    </div>

</div>

<script>
var ctx = document.getElementById('chart').getContext('2d');

// gradient warna
var gradient = ctx.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, 'rgba(102,126,234,0.8)');
gradient.addColorStop(1, 'rgba(118,75,162,0.1)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($bulan); ?>,
        datasets: [{
            label: 'Jumlah Surat',
            data: <?php echo json_encode($total); ?>,
            fill: true,
            backgroundColor: gradient,
            borderColor: '#667eea',
            tension: 0.4,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#667eea',
            pointRadius: 5
        }]
    },
    options: {
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

<br><br>

<div style="
display:flex;
gap:20px;
flex-wrap:wrap;
">

    <!-- SURAT MASUK TERBARU -->
    <div style="
        flex:1;
        background:white;
        padding:20px;
        border-radius:15px;
        box-shadow:0 5px 15px rgba(0,0,0,0.1);
    ">

        <h3>📥 5 Surat Masuk Terbaru</h3>

        <table class="dashboard-table">

            <tr style="background:#667eea; color:white;">
                <th>Pengirim</th>
                <th class="tanggal-col">Tanggal</th>
            </tr>

            <?php while($sm = mysqli_fetch_array($surat_masuk_terbaru)){ ?>

            <tr>
                <td><?php echo $sm['pengirim']; ?></td>
                <td class="tanggal-col">
                    <?php echo tanggal_indo_dashboard($sm['tanggal_surat']); ?>
                </td>
            </tr>

            <?php } ?>

        </table>

    </div>

    <!-- SURAT KELUAR TERBARU -->
    <div style="
        flex:1;
        background:white;
        padding:20px;
        border-radius:15px;
        box-shadow:0 5px 15px rgba(0,0,0,0.1);
    ">

        <h3>📤 5 Surat Keluar Terbaru</h3>

        <table class="dashboard-table">

            <tr style="background:#43cea2; color:white;">
                <th>Tujuan</th>
                <th class="tanggal-col">Tanggal</th>
            </tr>

            <?php while($sk = mysqli_fetch_array($surat_keluar_terbaru)){ ?>

            <tr>
                <td><?php echo $sk['tujuan']; ?></td>
                <td class="tanggal-col">
                    <?php echo tanggal_indo_dashboard($sk['tanggal_surat']); ?>
                </td>
            </tr>

            <?php } ?>

        </table>

    </div>

</div>