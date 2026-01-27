<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_login();
// data grafik pengeluaran 30 hari terakhir untuk user yang login
$uid = $_SESSION['user_id'] ?? 0;
$labels = [];
$values = [];
try {
    $startDate = new DateTime('-29 days');
    $endDate = new DateTime(); // today

    // query totals per day
    $stmt = $db->prepare("SELECT tanggal, SUM(jumlah) AS total FROM transaksi t JOIN dompet d ON t.id_dompet = d.id_dompet WHERE d.id_user = :uid AND t.jenis = 'pengeluaran' AND tanggal BETWEEN :start AND :end GROUP BY tanggal ORDER BY tanggal ASC");
    $stmt->execute([':uid' => $uid, ':start' => $startDate->format('Y-m-d'), ':end' => $endDate->format('Y-m-d')]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($rows as $r) $map[$r['tanggal']] = (float)$r['total'];

    $periodEnd = (clone $endDate)->add(new DateInterval('P1D')); // make end inclusive
    $period = new DatePeriod($startDate, new DateInterval('P1D'), $periodEnd);
    foreach ($period as $d) {
        $day = $d->format('Y-m-d');
        $labels[] = $d->format('d M');
        $values[] = isset($map[$day]) ? $map[$day] : 0;
    }
} catch (Exception $e) {
    // ignore and leave arrays empty
}

$chartLabelsJson = json_encode($labels);
$chartDataJson = json_encode($values);

// summary info: user name, dompet count, transaksi count
try {
    $userName = $_SESSION['user_name'] ?? '';

    $stmt = $db->prepare('SELECT COUNT(*) AS cnt FROM dompet WHERE id_user = :uid');
    $stmt->execute([':uid' => $uid]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $dompetCount = (int)($r['cnt'] ?? 0);

    $stmt = $db->prepare('SELECT COUNT(t.id_transaksi) AS cnt FROM transaksi t JOIN dompet d ON t.id_dompet = d.id_dompet WHERE d.id_user = :uid');
    $stmt->execute([':uid' => $uid]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $transCount = (int)($r['cnt'] ?? 0);
} catch (Exception $e) {
    $userName = $_SESSION['user_name'] ?? '';
    $dompetCount = 0;
    $transCount = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keuangan - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Keuangan</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="kategori.php">Kategori</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dompet.php">Dompet</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transaksi.php">Transaksi</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?= htmlspecialchars($userName) ?: 'User' ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                            <li><a class="dropdown-item" href="index.php">Dashboard</a></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Pengeluaran Harian (30 hari terakhir)</h5>
                <div style="height:260px;">
                    <canvas id="expenseChart" style="width:100%;height:100%;"></canvas>
                </div>
            </div>
        </div>
        <h3>Dashboard</h3>
        <div class="row mt-3">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Pengguna</h6>
                        <h5 class="card-title"><?= htmlspecialchars($userName) ?: '—' ?></h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Jumlah Dompet</h6>
                        <h5 class="card-title"><?php echo (int)$dompetCount; ?></h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Jumlah Transaksi</h6>
                        <h5 class="card-title"><?php echo (int)$transCount; ?></h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Kategori</h5>
                        <p class="card-text">Kelola kategori pemasukan dan pengeluaran.</p>
                        <a href="kategori.php" class="btn btn-primary">Buka</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Dompet</h5>
                        <p class="card-text">Kelola dompet Anda (coming soon).</p>
                        <a href="#" class="btn btn-secondary disabled">Buka</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Transaksi</h5>
                        <p class="card-text">Lihat dan catat transaksi (coming soon).</p>
                        <a href="#" class="btn btn-secondary disabled">Buka</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function(){
        const labels = <?php echo $chartLabelsJson; ?>;
        const data = <?php echo $chartDataJson; ?>;
        const ctx = document.getElementById('expenseChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Pengeluaran',
                        data: data,
                        backgroundColor: 'rgba(220,53,69,0.6)',
                        borderColor: 'rgba(220,53,69,1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
    })();
    </script>
</body>
</html>