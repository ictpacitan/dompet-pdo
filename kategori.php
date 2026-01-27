<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_login();
$userName = $_SESSION['user_name'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nama = trim($_POST['nama_kategori'] ?? '');
        $tipe = $_POST['tipe'] ?? 'pengeluaran';
        // kumpulkan fingerprint pengakses
        $ip = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $remotePort = $_SERVER['REMOTE_PORT'] ?? '';
        // deteksi sederhana device dari user agent
        $device = 'desktop';
        if (preg_match('/mobile|android|iphone|ipad|tablet|ipod|phone/i', $userAgent)) {
            $device = 'mobile';
        }
        $fp = [
            'ip' => $ip,
            'user_agent' => $userAgent,
            'accept' => $accept,
            'language' => $lang,
            'referer' => $referer,
            'remote_port' => $remotePort,
            'device' => $device,
            'timestamp' => date('c')
        ];
        $fingerprint = json_encode($fp, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $stmt = $db->prepare('INSERT INTO kategori (nama_kategori, tipe, fingerprint) VALUES (:nama, :tipe, :fingerprint)');
        $stmt->execute([':nama' => $nama, ':tipe' => $tipe, ':fingerprint' => $fingerprint]);
    } elseif ($action === 'update') {
        $id = (int)($_POST['id_kategori'] ?? 0);
        $nama = trim($_POST['nama_kategori'] ?? '');
        $tipe = $_POST['tipe'] ?? 'pengeluaran';
        $stmt = $db->prepare('UPDATE kategori SET nama_kategori = :nama, tipe = :tipe WHERE id_kategori = :id');
        $stmt->execute([':nama' => $nama, ':tipe' => $tipe, ':id' => $id]);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id_kategori'] ?? 0);
        $stmt = $db->prepare('DELETE FROM kategori WHERE id_kategori = :id');
        $stmt->execute([':id' => $id]);
    }

    header('Location: kategori.php');
    exit;
}

$categories = [];
try {
    $stmt = $db->query('SELECT * FROM kategori ORDER BY id_kategori DESC');
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // jika tabel belum ada atau error, hasil kosong — tetap tampilkan UI
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keuangan - Kategori</title>
    <!-- Bootstrap 5 CSS -->
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
                        <a class="nav-link active" href="kategori.php">Kategori</a>
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
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4>Kategori</h4>
            <button id="addKategoriBtn" class="btn btn-primary">Tambah Kategori</button>
        </div>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Kategori</th>
                        <th>Tipe</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id_kategori']) ?></td>
                            <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                            <td><?= htmlspecialchars($row['tipe']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-secondary editBtn" data-id="<?= $row['id_kategori'] ?>" data-nama="<?= htmlspecialchars($row['nama_kategori'], ENT_QUOTES) ?>" data-tipe="<?= $row['tipe'] ?>">Edit</button>
                                <form id="deleteForm_<?= $row['id_kategori'] ?>" method="post" action="kategori.php" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id_kategori" value="<?= $row['id_kategori'] ?>">
                                    <button type="button" class="btn btn-sm btn-danger deleteBtn" data-id="<?= $row['id_kategori'] ?>">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">Belum ada kategori.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Kategori -->
    <div class="modal fade" id="kategoriModal" tabindex="-1" aria-labelledby="kategoriModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form id="kategoriForm" method="post" action="kategori.php">
            <div class="modal-header">
              <h5 class="modal-title" id="kategoriModalLabel">Tambah Kategori</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="action" value="create">
                <input type="hidden" name="id_kategori" id="id_kategori" value="">

                <div class="mb-3">
                    <label for="nama_kategori" class="form-label">Nama Kategori</label>
                    <input type="text" class="form-control" id="nama_kategori" name="nama_kategori" required>
                </div>
                <div class="mb-3">
                    <label for="tipe" class="form-label">Tipe</label>
                    <select class="form-select" id="tipe" name="tipe">
                        <option value="pemasukan">Pemasukan</option>
                        <option value="pengeluaran" selected>Pengeluaran</option>
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

    <!-- jQuery (optional) -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <!-- Bootstrap 5 bundle JS (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    (function(){
        var kategoriModalEl = document.getElementById('kategoriModal');
        var kategoriModal = new bootstrap.Modal(kategoriModalEl);

        $('#addKategoriBtn').on('click', function(){
            $('#kategoriForm')[0].reset();
            $('#kategoriModalLabel').text('Tambah Kategori');
            $('#action').val('create');
            $('#id_kategori').val('');
            kategoriModal.show();
        });

        $('.editBtn').on('click', function(){
            var id = $(this).data('id');
            var nama = $(this).data('nama');
            var tipe = $(this).data('tipe');
            $('#kategoriModalLabel').text('Edit Kategori');
            $('#action').val('update');
            $('#id_kategori').val(id);
            $('#nama_kategori').val(nama);
            $('#tipe').val(tipe);
            kategoriModal.show();
        });

        $('.deleteBtn').on('click', function(){
            var id = $(this).data('id');
            if (confirm('Hapus kategori ini?')) {
                $('#deleteForm_' + id).submit();
            }
        });
    })();
    </script>
</body>
</html>
