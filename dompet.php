<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_login();

$uid = $_SESSION['user_id'] ?? 0;
$userName = $_SESSION['user_name'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nama = trim($_POST['nama_dompet'] ?? '');
        $saldo = (float)($_POST['saldo_awal'] ?? 0);
        $stmt = $db->prepare('INSERT INTO dompet (nama_dompet, id_user, saldo_awal, saldo_akhir) VALUES (:nama, :uid, :saldo, :saldo)');
        $stmt->execute([':nama' => $nama, ':uid' => $uid, ':saldo' => $saldo]);
    } elseif ($action === 'update') {
        $id = (int)($_POST['id_dompet'] ?? 0);
        $nama = trim($_POST['nama_dompet'] ?? '');
        $saldo = (float)($_POST['saldo_awal'] ?? 0);
        $stmt = $db->prepare('UPDATE dompet SET nama_dompet = :nama, saldo_awal = :saldo, saldo_akhir = :saldo WHERE id_dompet = :id AND id_user = :uid');
        $stmt->execute([':nama' => $nama, ':saldo' => $saldo, ':id' => $id, ':uid' => $uid]);
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id_dompet'] ?? 0);
        $stmt = $db->prepare('DELETE FROM dompet WHERE id_dompet = :id AND id_user = :uid');
        $stmt->execute([':id' => $id, ':uid' => $uid]);
    }

    header('Location: dompet.php');
    exit;
}

$dompets = [];
try {
    $stmt = $db->prepare('SELECT * FROM dompet WHERE id_user = :uid ORDER BY id_dompet DESC');
    $stmt->execute([':uid' => $uid]);
    $dompets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // ignore
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keuangan - Dompet</title>
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
                        <a class="nav-link active" href="dompet.php">Dompet</a>
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
            <h4>Dompet</h4>
            <button id="addDompetBtn" class="btn btn-primary">Tambah Dompet</button>
        </div>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Dompet</th>
                        <th>Saldo Awal</th>
                        <th>Saldo Akhir</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($dompets)): ?>
                        <?php foreach ($dompets as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id_dompet']) ?></td>
                            <td><?= htmlspecialchars($row['nama_dompet']) ?></td>
                            <td><?= number_format($row['saldo_awal'], 2, ',', '.') ?></td>
                            <td><?= number_format($row['saldo_akhir'], 2, ',', '.') ?></td>
                            <td>
                                <button class="btn btn-sm btn-secondary editBtn" data-id="<?= $row['id_dompet'] ?>" data-nama="<?= htmlspecialchars($row['nama_dompet'], ENT_QUOTES) ?>" data-saldo="<?= $row['saldo_awal'] ?>">Edit</button>
                                <form id="deleteForm_<?= $row['id_dompet'] ?>" method="post" action="dompet.php" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id_dompet" value="<?= $row['id_dompet'] ?>">
                                    <button type="button" class="btn btn-sm btn-danger deleteBtn" data-id="<?= $row['id_dompet'] ?>">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">Belum ada dompet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Dompet -->
    <div class="modal fade" id="dompetModal" tabindex="-1" aria-labelledby="dompetModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form id="dompetForm" method="post" action="dompet.php">
            <div class="modal-header">
              <h5 class="modal-title" id="dompetModalLabel">Tambah Dompet</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="action" value="create">
                <input type="hidden" name="id_dompet" id="id_dompet" value="">

                <div class="mb-3">
                    <label for="nama_dompet" class="form-label">Nama Dompet</label>
                    <input type="text" class="form-control" id="nama_dompet" name="nama_dompet" required>
                </div>
                <div class="mb-3">
                    <label for="saldo_awal" class="form-label">Saldo Awal</label>
                    <input type="number" step="0.01" class="form-control" id="saldo_awal" name="saldo_awal" value="0.00" required>
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
        var dompetModalEl = document.getElementById('dompetModal');
        var dompetModal = new bootstrap.Modal(dompetModalEl);

        $('#addDompetBtn').on('click', function(){
            $('#dompetForm')[0].reset();
            $('#dompetModalLabel').text('Tambah Dompet');
            $('#action').val('create');
            $('#id_dompet').val('');
            $('#saldo_awal').val('0.00');
            dompetModal.show();
        });

        $('.editBtn').on('click', function(){
            var id = $(this).data('id');
            var nama = $(this).data('nama');
            var saldo = $(this).data('saldo');
            $('#dompetModalLabel').text('Edit Dompet');
            $('#action').val('update');
            $('#id_dompet').val(id);
            $('#nama_dompet').val(nama);
            $('#saldo_awal').val(saldo);
            dompetModal.show();
        });

        $('.deleteBtn').on('click', function(){
            var id = $(this).data('id');
            if (confirm('Hapus dompet ini?')) {
                $('#deleteForm_' + id).submit();
            }
        });
    })();
    </script>
</body>
</html>