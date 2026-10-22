<?php
session_start();

// CEK LOGIN
if(!isset($_SESSION['status']) || $_SESSION['status'] != "login"){
    header("location:login.php");
    exit;
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Pengguna';
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'staff';

// Tampilan role saja, tidak mengubah isi database
if($role == 'admin'){
    $role_tampil = 'Kaprodi';
} elseif($role == 'staff'){
    $role_tampil = 'Staff';
} else {
    $role_tampil = ucfirst($role);
}

$inisial = strtoupper(substr($username, 0, 1));

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sistem Informasi Persuratan Prodi</title>

    <!-- CHART.JS -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            background: #ecf0f1;
            color: #1f2937;
        }

        /* =========================
           HEADER MODERN RAPI
        ========================= */

        .header {
            background: linear-gradient(135deg, #17385c, #28566a);
            color: white;
            padding: 16px 24px;
            box-shadow: 0 5px 16px rgba(0,0,0,0.18);
        }

        .header-inner {
            display: grid;
            grid-template-columns: 1.2fr 1fr 0.8fr;
            align-items: center;
            gap: 20px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .logo-kampus {
            width: 72px;
            height: 72px;
            object-fit: contain;
            background: #ffffff;
            padding: 7px;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.22);
        }

        .identitas-kampus h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 1px;
            line-height: 1.1;
            white-space: nowrap;
        }

        .identitas-kampus h2 {
            margin: 6px 0 3px;
            font-size: 15px;
            color: #ffffff;
            font-weight: 700;
            line-height: 1.1;
        }

        .identitas-kampus p {
            margin: 0;
            font-size: 13px;
            color: #dbeafe;
            line-height: 1.3;
        }

        .header-center {
            text-align: center;
        }

        .badge-sistem {
            display: inline-block;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.25);
            padding: 5px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .header-center h3 {
            margin: 0;
            font-size: 25px;
            font-weight: 800;
            line-height: 1.1;
        }

        .header-center p {
            margin: 6px 0 0;
            font-size: 13px;
            color: #dbeafe;
            line-height: 1.3;
        }

        .header-right {
            display: flex;
            justify-content: flex-end;
        }

        .user-card {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255,255,255,0.13);
            border: 1px solid rgba(255,255,255,0.20);
            padding: 11px 16px;
            border-radius: 16px;
            min-width: 190px;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: white;
            color: #17385c;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: 800;
            font-size: 19px;
            flex-shrink: 0;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            line-height: 1.25;
        }

        .user-info span {
            font-size: 11px;
            color: #dbeafe;
        }

        .user-info strong {
            font-size: 16px;
            color: #ffffff;
            margin-top: 2px;
        }

        .user-info small {
            font-size: 12px;
            color: #ffffff;
            margin-top: 2px;
        }

        /* =========================
           LAYOUT
        ========================= */

        .container {
            display: flex;
            min-height: calc(100vh - 105px);
        }

        .sidebar {
            width: 240px;
            background: #243447;
            color: white;
            min-height: 100vh;
            padding: 20px 15px;
            box-shadow: 4px 0 14px rgba(0,0,0,0.08);
        }

        .sidebar-title {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #cbd5e1;
            margin-bottom: 15px;
            padding-left: 8px;
        }

        .sidebar a {
            display: block;
            color: #e5e7eb;
            text-decoration: none;
            margin: 8px 0;
            padding: 11px 13px;
            border-radius: 10px;
            font-size: 14px;
            transition: 0.2s;
        }

        .sidebar a:hover {
            background: #334f6f;
            color: white;
            transform: translateX(3px);
        }

        .sidebar a.active {
            background: #3b82f6;
            color: white;
            font-weight: bold;
        }

        .sidebar hr {
            border: none;
            border-top: 1px solid rgba(255,255,255,0.15);
            margin: 18px 0;
        }

        .logout-link {
            background: #dc2626;
            color: white !important;
            text-align: center;
            font-weight: bold;
        }

        .logout-link:hover {
            background: #b91c1c !important;
            transform: none !important;
        }

        .content {
            padding: 25px;
            width: 100%;
            background: #ecf0f1;
            overflow-x: auto;
        }

        .card {
            background: white;
            padding: 20px;
            width: 200px;
            border-radius: 10px;
            box-shadow: 0px 2px 5px rgba(0,0,0,0.2);
        }

        @media (max-width: 1100px) {
            .header-inner {
                grid-template-columns: 1fr;
                text-align: left;
            }

            .header-center {
                text-align: left;
            }

            .header-right {
                justify-content: flex-start;
            }

            .identitas-kampus h1 {
                white-space: normal;
            }
        }

        @media (max-width: 900px) {
            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-height: auto;
            }

            .logo-kampus {
                width: 62px;
                height: 62px;
            }

            .identitas-kampus h1 {
                font-size: 20px;
            }

            .header-center h3 {
                font-size: 21px;
            }
        }
    </style>
</head>

<body>

<!-- HEADER -->
<div class="header">
    <div class="header-inner">

        <div class="header-left">
            <img src="assets/image/logo.png" class="logo-kampus" alt="Logo Universitas">

            <div class="identitas-kampus">
                <h1>UNIVERSITAS BALE BANDUNG</h1>
                <h2>FAKULTAS TEKNOLOGI INFORMASI</h2>
                <p>Program Studi Teknik Informatika dan Sistem Informasi</p>
            </div>
        </div>

        <div class="header-center">
            <div class="badge-sistem">
                Sistem Internal Program Studi
            </div>

            <h3>Sistem Informasi Persuratan</h3>

            <p>
                Pengelolaan surat masuk, surat keluar, dan arsip digital prodi
            </p>
        </div>

        <div class="header-right">
            <div class="user-card">
                <div class="user-avatar">
                    <?php echo $inisial; ?>
                </div>

                <div class="user-info">
                    <span>Pengguna Aktif</span>
                    <strong><?php echo htmlspecialchars($username); ?></strong>
                    <small><?php echo htmlspecialchars($role_tampil); ?></small>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="container">

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-title">Menu Utama</div>

        <a href="index.php?page=dashboard" class="<?php echo ($page == 'dashboard') ? 'active' : ''; ?>">
            📊 Dashboard
        </a>

        <a href="index.php?page=surat_masuk" class="<?php echo ($page == 'surat_masuk') ? 'active' : ''; ?>">
            📥 Surat Masuk
        </a>

        <!-- KHUSUS KAPRODI / ADMIN -->
        <?php if($_SESSION['role'] == 'admin'){ ?>
            <a href="index.php?page=surat_keluar" class="<?php echo ($page == 'surat_keluar') ? 'active' : ''; ?>">
                📤 Surat Keluar
            </a>
        <?php } ?>

        <hr>

        <a href="logout.php" class="logout-link">
            Logout
        </a>
    </div>

    <!-- KONTEN -->
    <div class="content">

        <?php
        if ($page == 'dashboard') {
            include 'pages/dashboard.php';
        } elseif ($page == 'surat_masuk') {
            include 'pages/surat_masuk.php';
        } elseif ($page == 'surat_keluar') {
            include 'pages/surat_keluar.php';
        } else {
            echo "<h3>Halaman tidak ditemukan</h3>";
        }
        ?>

    </div>

</div>

</body>
</html>