<?php
session_start();
require_once 'db.php';

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success_msg = $_SESSION['success_msg'] ?? '';
unset($_SESSION['success_msg']);

// Form Edit State
$is_edit = false;
$item = ['id' => '', 'name' => '', 'category' => '', 'price' => '', 'stock' => ''];

// --- PROCESS POST REQUEST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Akses Ilegal: CSRF Token Tidak Valid!");
    }

    $action = $_POST['action'] ?? '';

    // Action: Save / Update
    if ($action === 'save') {
        $id       = trim($_POST['id'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price    = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
        $stock    = filter_var($_POST['stock'], FILTER_VALIDATE_INT);

        // Validasi Input
        if (strlen($name) < 3) {
            $errors[] = "Nama produk minimal terdiri dari 3 karakter!";
        }
        if ($price === false || $price <= 0) {
            $errors[] = "Harga produk harus berupa angka lebih dari 0!";
        }
        if ($stock === false || $stock < 0) {
            $errors[] = "Jumlah stok tidak boleh bernilai negatif!";
        }

        // Cek Keunikan Nama Produk
        if (empty($errors)) {
            if ($id) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE name = :name AND id != :id");
                $stmt->execute([':name' => $name, ':id' => $id]);
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE name = :name");
                $stmt->execute([':name' => $name]);
            }
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Nama produk sudah terdaftar, silakan gunakan nama lain!";
            }
        }

        // Simpan Data
        if (empty($errors)) {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE items SET name = :name, category = :category, price = :price, stock = :stock WHERE id = :id");
                $stmt->execute([':name' => $name, ':category' => $category, ':price' => $price, ':stock' => $stock, ':id' => $id]);
                $_SESSION['success_msg'] = "Data produk berhasil diperbarui!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO items (name, category, price, stock) VALUES (:name, :category, :price, :stock)");
                $stmt->execute([':name' => $name, ':category' => $category, ':price' => $price, ':stock' => $stock]);
                $_SESSION['success_msg'] = "Produk baru berhasil ditambahkan!";
            }
            header("Location: index.php");
            exit;
        } else {
            $is_edit = !empty($id);
            $item = ['id' => $id, 'name' => $name, 'category' => $category, 'price' => $_POST['price'], 'stock' => $_POST['stock']];
        }
    }

    // Action: Delete
    if ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM items WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $_SESSION['success_msg'] = "Produk berhasil dihapus!";
        }
        header("Location: index.php");
        exit;
    }
}

// Fetch Edit Data
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = :id");
    $stmt->execute([':id' => $edit_id]);
    $fetch_data = $stmt->fetch();
    if ($fetch_data) {
        $is_edit = true;
        $item = $fetch_data;
    }
}

// Fetch All Items
$stmt = $pdo->query("SELECT * FROM items ORDER BY id DESC");
$inventory_list = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Inventory System</title>
    <style>
        * { box-sizing: border-box; font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        body { background-color: #0b192c; color: #e2e8f0; margin: 0; padding: 24px; }
        .wrapper { max-width: 960px; margin: 0 auto; }
        h1 { text-align: center; color: #14b8a6; font-size: 2rem; margin-bottom: 24px; }
        
        .alert { padding: 14px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; }
        .alert-error { background-color: #7f1d1d; color: #fecaca; border: 1px solid #991b1b; }
        .alert-success { background-color: #064e3b; color: #a7f3d0; border: 1px solid #065f46; }
        
        .form-box { background-color: #1e293b; padding: 24px; border-radius: 12px; border: 1px solid #334155; margin-bottom: 32px; }
        .form-box h2 { margin-top: 0; color: #2dd4bf; font-size: 1.25rem; }
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 6px; color: #94a3b8; font-size: 0.9rem; }
        input { width: 100%; padding: 10px 14px; border-radius: 6px; border: 1px solid #475569; background-color: #0f172a; color: #fff; }
        input:focus { outline: 2px solid #14b8a6; border-color: transparent; }
        
        .btn-submit { background-color: #0d9488; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-submit:hover { background-color: #14b8a6; }
        .btn-cancel { background-color: #475569; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 6px; display: inline-block; }
        
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; }
        .item-card { background-color: #1e293b; border-radius: 10px; padding: 18px; border: 1px solid #334155; display: flex; flex-direction: column; justify-content: space-between; }
        .item-card h3 { margin: 0 0 10px 0; color: #2dd4bf; word-break: break-word; }
        .item-details { font-size: 0.9rem; color: #cbd5e1; line-height: 1.6; margin-bottom: 16px; }
        
        .card-buttons { display: flex; gap: 8px; }
        .btn-edit { background-color: #f59e0b; color: #0f172a; text-decoration: none; padding: 6px 14px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; }
        .btn-delete { background-color: #e11d48; color: #fff; border: none; padding: 6px 14px; border-radius: 4px; font-size: 0.85rem; cursor: pointer; font-weight: 600; }
        .btn-delete:hover { background-color: #be123c; }
    </style>
</head>
<body>
<div class="wrapper">
    <h1>Product Inventory System</h1>

    <!-- Notifikasi Error -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Notifikasi Sukses -->
    <?php if ($success_msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Form Input / Edit -->
    <div class="form-box">
        <h2><?= $is_edit ? 'Edit Data Barang' : 'Tambah Barang Baru' ?></h2>
        <form action="index.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label>Nama Produk (Min. 3 Karakter, Unik):</label>
                <input type="text" name="name" value="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="form-group">
                <label>Kategori Produk:</label>
                <input type="text" name="category" value="<?= htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="form-group">
                <label>Harga Satuan (Rp):</label>
                <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($item['price'], ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="form-group">
                <label>Jumlah Stok:</label>
                <input type="number" name="stock" value="<?= htmlspecialchars($item['stock'], ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <button type="submit" class="btn-submit"><?= $is_edit ? 'Perbarui Data' : 'Simpan Barang' ?></button>
            <?php if ($is_edit): ?>
                <a href="index.php" class="btn-cancel">Batal</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Listing Products -->
    <h2>Daftar Inventaris Produk</h2>
    <div class="grid-container">
        <?php if (empty($inventory_list)): ?>
            <p>Belum ada data barang di inventaris.</p>
        <?php else: ?>
            <?php foreach ($inventory_list as $prod): ?>
                <div class="item-card">
                    <div>
                        <h3><?= htmlspecialchars($prod['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <div class="item-details">
                            <div><strong>Kategori:</strong> <?= htmlspecialchars($prod['category'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div><strong>Harga:</strong> Rp <?= number_format($prod['price'], 0, ',', '.') ?></div>
                            <div><strong>Stok:</strong> <?= htmlspecialchars($prod['stock'], ENT_QUOTES, 'UTF-8') ?> unit</div>
                        </div>
                    </div>
                    <div class="card-buttons">
                        <a href="index.php?edit_id=<?= $prod['id'] ?>" class="btn-edit">Edit</a>
                        <form action="index.php" method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus produk ini?');">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $prod['id'] ?>">
                            <button type="submit" class="btn-delete">Hapus</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>