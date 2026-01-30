<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_login();

$uid = $_SESSION['user_id'] ?? 0;
$userName = $_SESSION['user_name'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $jumlah = (float)($_POST['jumlah'] ?? 0);
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $jenis = $_POST['jenis'] ?? 'pengeluaran';
        $id_kategori = (int)($_POST['id_kategori'] ?? 0);
        $id_dompet = (int)($_POST['id_dompet'] ?? 0);
        $uraian = trim($_POST['uraian'] ?? '');

        try {
            $db->beginTransaction();

            // pastikan dompet milik user
            $stmt = $db->prepare('SELECT saldo_akhir FROM dompet WHERE id_dompet = :id_dompet AND id_user = :uid FOR UPDATE');
            $stmt->execute([':id_dompet' => $id_dompet, ':uid' => $uid]);
            $dom = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$dom) throw new Exception('Dompet tidak ditemukan');

            $stmt = $db->prepare('INSERT INTO transaksi (jumlah, tanggal, jenis, id_kategori, id_dompet, uraian) VALUES (:jumlah, :tanggal, :jenis, :id_kategori, :id_dompet, :uraian)');
            $stmt->execute([
                ':jumlah' => $jumlah,
                ':tanggal' => $tanggal,
                ':jenis' => $jenis,
                ':id_kategori' => $id_kategori,
                ':id_dompet' => $id_dompet,
                ':uraian' => $uraian,
            ]);

            // hitung delta terhadap saldo_akhir
            $delta = ($jenis === 'pemasukan') ? $jumlah : -$jumlah;
            $stmt = $db->prepare('UPDATE dompet SET saldo_akhir = saldo_akhir + :delta WHERE id_dompet = :id_dompet AND id_user = :uid');
            $stmt->execute([':delta' => $delta, ':id_dompet' => $id_dompet, ':uid' => $uid]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            // untuk debugging sementara, tapi jangan tampilkan error sensitif di produksi
            $error = $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id_transaksi'] ?? 0);
        try {
            $db->beginTransaction();
            // ambil transaksi dan pastikan dompet milik user
            $stmt = $db->prepare('SELECT jumlah, jenis, id_dompet FROM transaksi WHERE id_transaksi = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $tr = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tr) throw new Exception('Transaksi tidak ditemukan');

            $stmt = $db->prepare('SELECT id_user FROM dompet WHERE id_dompet = :id_dompet');
            $stmt->execute([':id_dompet' => $tr['id_dompet']]);
            $dom = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$dom || $dom['id_user'] != $uid) throw new Exception('Akses ditolak');

            // rollback perubahan saldo (inverse)
            $delta = ($tr['jenis'] === 'pemasukan') ? -((float)$tr['jumlah']) : +((float)$tr['jumlah']);
            $stmt = $db->prepare('UPDATE dompet SET saldo_akhir = saldo_akhir + :delta WHERE id_dompet = :id_dompet');
            $stmt->execute([':delta' => $delta, ':id_dompet' => $tr['id_dompet']]);

            $stmt = $db->prepare('DELETE FROM transaksi WHERE id_transaksi = :id');
            $stmt->execute([':id' => $id]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $error = $e->getMessage();
        }
    }

    header('Location: transaksi.php');
    exit;
}

// ambil daftar kategori (semua)
$categories = [];
try {
    $stmt = $db->prepare('SELECT * FROM kategori WHERE id_user = :uid ORDER BY nama_kategori ASC');
    $stmt->execute([':uid' => $uid]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ambil dompet milik user
$dompets = [];
try {
    $stmt = $db->prepare('SELECT * FROM dompet WHERE id_user = :uid ORDER BY id_dompet DESC');
    $stmt->execute([':uid' => $uid]);
    $dompets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ambil transaksi yang terkait dengan dompet user
$transactions = [];
try {
    $stmt = $db->prepare('SELECT t.*, k.nama_kategori, d.nama_dompet FROM transaksi t JOIN kategori k ON t.id_kategori = k.id_kategori AND k.id_user = :uid JOIN dompet d ON t.id_dompet = d.id_dompet WHERE d.id_user = :uid ORDER BY t.tanggal DESC, t.id_transaksi DESC');
    $stmt->execute([':uid' => $uid]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keuangan - Transaksi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Keuangan</a>
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
                        <a class="nav-link active" href="transaksi.php">Transaksi</a>
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
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4>Transaksi</h4>
            <button id="addTransaksiBtn" class="btn btn-primary">Tambah Transaksi</button>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tanggal</th>
                        <th>Jumlah</th>
                        <th>Jenis</th>
                        <th>Uraian</th>
                        <th>Kategori</th>
                        <th>Dompet</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactions)): ?>
                        <?php foreach ($transactions as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id_transaksi']) ?></td>
                            <td><?= htmlspecialchars($row['tanggal']) ?></td>
                            <td><?= number_format($row['jumlah'], 2, ',', '.') ?></td>
                            <td><?= htmlspecialchars($row['jenis']) ?></td>
                            <td><?= htmlspecialchars($row['uraian']) ?></td>
                            <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                            <td><?= htmlspecialchars($row['nama_dompet']) ?></td>
                            <td>
                                <form id="deleteForm_<?= $row['id_transaksi'] ?>" method="post" action="transaksi.php" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id_transaksi" value="<?= $row['id_transaksi'] ?>">
                                    <button type="button" class="btn btn-sm btn-danger deleteBtn" data-id="<?= $row['id_transaksi'] ?>">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Belum ada transaksi.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Transaksi -->
    <div class="modal fade" id="transaksiModal" tabindex="-1" aria-labelledby="transaksiModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form id="transaksiForm" method="post" action="transaksi.php">
            <div class="modal-header">
              <h5 class="modal-title" id="transaksiModalLabel">Tambah Transaksi</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="action" value="create">

                <div class="mb-3">
                    <label for="tanggal" class="form-label">Tanggal</label>
                    <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="mb-3">
                    <label for="jumlah" class="form-label">Jumlah</label>
                    <input type="number" step="0.01" class="form-control" id="jumlah" name="jumlah" required>
                </div>
                <div class="mb-3">
                    <label for="jenis" class="form-label">Jenis</label>
                    <select class="form-select" id="jenis" name="jenis">
                        <option value="pemasukan">Pemasukan</option>
                        <option value="pengeluaran" selected>Pengeluaran</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="uraian" class="form-label">Uraian</label>
                    <textarea class="form-control" id="uraian" name="uraian" rows="2" placeholder="Uraian singkat transaksi"></textarea>
                </div>
                <div class="mb-3">
                    <label for="id_kategori" class="form-label">Kategori</label>
                    <select class="form-select" id="id_kategori" name="id_kategori" required>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id_kategori'] ?>"><?= htmlspecialchars($c['nama_kategori']) ?> (<?= $c['tipe'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="id_dompet" class="form-label">Dompet</label>
                    <select class="form-select" id="id_dompet" name="id_dompet" required>
                        <?php foreach ($dompets as $d): ?>
                            <option value="<?= $d['id_dompet'] ?>"><?= htmlspecialchars($d['nama_dompet']) ?> (Saldo: <?= number_format($d['saldo_akhir'],2,',','.') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function(){
        var transaksiModalEl = document.getElementById('transaksiModal');
        var transaksiModal = new bootstrap.Modal(transaksiModalEl);

        $('#addTransaksiBtn').on('click', function(){
            $('#transaksiForm')[0].reset();
            $('#transaksiModalLabel').text('Tambah Transaksi');
            $('#action').val('create');
            transaksiModal.show();
        });

        $('.deleteBtn').on('click', function(){
            var id = $(this).data('id');
            if (confirm('Hapus transaksi ini?')) {
                $('#deleteForm_' + id).submit();
            }
        });
    })();
    </script>
</body>
</html>
