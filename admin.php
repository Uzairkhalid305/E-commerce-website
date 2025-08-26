<?php
// admin.php — CRUD + image via URL or upload
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "mini_shop_v2";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("DB connection failed: " . $conn->connect_error);

// Ensure uploads dir exists
$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }

// Helper: handle image input (prefer URL; else file upload)
function handle_image(&$errorMsg) {
  // If URL provided, trust it (best to avoid upload permissions issues)
  if (!empty($_POST['image_url'])) {
    $url = trim($_POST['image_url']);
    // basic sanity check
    if (preg_match('~^https?://~i', $url)) return $url;
    $errorMsg = "Image URL must start with http:// or https://";
    return null;
  }

  // Else try upload
  if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
    $tmp  = $_FILES['image_file']['tmp_name'];
    $name = basename($_FILES['image_file']['name']);
    $size = (int)$_FILES['image_file']['size'];

    // Validate MIME & size
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmp);
    finfo_close($finfo);

    $allowed = ['image/jpeg'=>'.jpg','image/png'=>'.png','image/gif'=>'.gif','image/webp'=>'.webp'];
    if (!isset($allowed[$mime])) { $errorMsg = "Only JPG, PNG, GIF, WEBP allowed."; return null; }
    if ($size > 3*1024*1024) { $errorMsg = "Max image size is 3MB."; return null; }

    $ext  = $allowed[$mime];
    $dest = 'uploads/' . (uniqid('img_', true)) . $ext;
    if (move_uploaded_file($tmp, __DIR__ . '/' . $dest)) return $dest;

    $errorMsg = "Failed to move uploaded file.";
    return null;
  }

  // Nothing given
  $errorMsg = "Provide an Image URL or upload a file.";
  return null;
}

// ADD
$flash = null; $err = null;
if (isset($_POST['add'])) {
  $name  = $conn->real_escape_string(trim($_POST['name'] ?? ''));
  $price = (float)($_POST['price'] ?? 0);

  $img = handle_image($err);
  if ($name && $price > 0 && $img) {
    $stmt = $conn->prepare("INSERT INTO products (name, price, image) VALUES (?,?,?)");
    $stmt->bind_param('sds', $name, $price, $img);
    $stmt->execute();
    $stmt->close();
    $flash = "✅ Added “{$name}”.";
    header("Location: admin.php?ok=1"); exit;
  } else if (!$err) {
    $err = "Name, price, and image are required.";
  }
}

// DELETE
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  // (Optional) remove local file if stored as local path
  $q = $conn->query("SELECT image FROM products WHERE id=$id");
  if ($q && $row = $q->fetch_assoc()) {
    if (strpos($row['image'], 'http') !== 0) {
      $p = __DIR__ . '/' . $row['image'];
      if (is_file($p)) @unlink($p);
    }
  }
  $conn->query("DELETE FROM products WHERE id=$id");
  header("Location: admin.php?ok=1"); exit;
}

// LOAD product for edit
$edit = null;
if (isset($_GET['edit'])) {
  $id = (int)$_GET['edit'];
  $r = $conn->query("SELECT * FROM products WHERE id=$id");
  $edit = $r? $r->fetch_assoc() : null;
}

// UPDATE
if (isset($_POST['update'])) {
  $id    = (int)$_POST['id'];
  $name  = $conn->real_escape_string(trim($_POST['name'] ?? ''));
  $price = (float)($_POST['price'] ?? 0);

  $imgPath = null; $upErr = null;
  // If user provided a new URL or file, we handle it; otherwise keep old image
  if (!empty($_POST['image_url']) || (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK)) {
    $imgPath = handle_image($upErr);
  }

  if ($name && $price > 0) {
    if ($imgPath) {
      // (Optional) remove old local file if replacing with new local file
      $q = $conn->query("SELECT image FROM products WHERE id=$id");
      if ($q && $row = $q->fetch_assoc()) {
        if (strpos($row['image'],'http')!==0) {
          $p = __DIR__ . '/' . $row['image'];
          if (is_file($p)) @unlink($p);
        }
      }
      $stmt = $conn->prepare("UPDATE products SET name=?, price=?, image=? WHERE id=?");
      $stmt->bind_param('sdsi', $name, $price, $imgPath, $id);
    } else {
      $stmt = $conn->prepare("UPDATE products SET name=?, price=? WHERE id=?");
      $stmt->bind_param('sdi', $name, $price, $id);
    }
    $stmt->execute(); $stmt->close();
    header("Location: admin.php?ok=1"); exit;
  } else {
    $err = $upErr ?: "Invalid name/price.";
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>UzairMart</title>
<style>
  :root { --bg:#0b1220; --panel:#0f172a; --border:#1f2937; --text:#e5e7eb; --muted:#94a3b8; --accent:#22c55e; --danger:#ef4444; --warn:#f59e0b; }
  *{box-sizing:border-box}
  body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial;background:linear-gradient(135deg,#0b1220,#0a1020);color:var(--text)}
  header{display:flex;justify-content:space-between;align-items:center; background: linear-gradient(90deg, #26b3a7ff, #e61616ff);padding:14px 18px;border-bottom:1px solid var(--border)}
  header a{color:#9ae6b4;text-decoration:none;font-weight:700}
  .wrap{max-width:1000px;margin:22px auto;padding:0 16px}
  .panel{background:linear-gradient(180deg,#0f172a,#0b1220); box-shadow: 0px 0px 5px red;border:1px solid var(--border);border-radius:16px;padding:14px;margin-bottom:18px}
  .grid{display:grid; grid-template-columns:1fr 1fr;gap:16px}
  @media(max-width:820px){.grid{grid-template-columns:1fr}}
  label{display:block;margin:8px 0 4px;color:#cbd5e1}
  input[type=text],input[type=number],input[type=url]{width:100%;padding:10px;border-radius:10px;border:1px solid var(--border);background:#0b1220;color:var(--text)}
  input[type=file]{width:100%}
  .btn{background:var(--accent);border:none;color:#052d15;padding:10px 14px;border-radius:10px;font-weight:800;cursor:pointer}
  .btn:hover{filter:brightness(.95)}
  .danger{background:var(--danger);color:#fff}
  .warn{background:var(--warn);color:#111}
  table{width:100%;border-collapse:collapse}
  th,td{border-bottom:1px solid var(--border);padding:10px;text-align:left}
  img{border-radius:8px}
  .msg{margin:10px 0;padding:10px;border-radius:10px}
  .ok{background:#052e1a;border:1px solid #164e2d;color:#9ae6b4}
  .err{background:#3a0d10;border:1px solid #7f1d1d;color:#fecaca}
</style>
</head>
<body>
<header>
  <strong>UzairMart</strong>
  <nav><a href="ecommerce.php">🏪 View Shop</a></nav>
</header>

<div class="wrap">
  <?php if (isset($_GET['ok'])): ?>
    <div class="msg ok">✅ Done.</div>
  <?php endif; ?>
  <?php if (!empty($err)): ?>
    <div class="msg err">❌ <?= htmlspecialchars($err) ?></div>
  <?php endif; ?>

  <div class="panel">
    <h2 style="margin:6px 0 12px">Add Product</h2>
    <form method="post" enctype="multipart/form-data" class="grid">
      <div>
        <label>Product Name</label>
        <input type="text" name="name" placeholder="e.g., Classic T-Shirt" required>
      </div>
      <div>
        <label>Price (USD)</label>
        <input type="number" step="0.01" name="price" placeholder="e.g., 19.99" required>
      </div>
      <div>
        <label>Image URL (Recommended)</label>
        <input type="url" name="image_url" placeholder="https://...">
      </div>
      <div>
        <label>OR Upload Image</label>
        <input type="file" name="image_file" accept="image/*">
        <small style="color:#94a3b8;display:block;margin-top:6px">Allowed: JPG, PNG, GIF, WEBP (max 3MB)</small>
      </div>
      <div style="grid-column:1/-1">
        <button class="btn" name="add">Add Product</button>
      </div>
    </form>
  </div>

  <?php if ($edit): ?>
  <div class="panel">
    <h2 style="margin:6px 0 12px">Edit Product #<?= (int)$edit['id'] ?></h2>
    <form method="post" enctype="multipart/form-data" class="grid">
      <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
      <div>
        <label>Product Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($edit['name']) ?>" required>
      </div>
      <div>
        <label>Price (USD)</label>
        <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($edit['price']) ?>" required>
      </div>
      <div>
        <label>New Image URL (optional)</label>
        <input type="url" name="image_url" placeholder="https://...">
      </div>
      <div>
        <label>OR Upload New Image</label>
        <input type="file" name="image_file" accept="image/*">
      </div>
      <div style="grid-column:1/-1">
        <img src="<?= htmlspecialchars($edit['image']) ?>" alt="" width="140" height="auto">
      </div>
      <div style="grid-column:1/-1">
        <button class="btn warn" name="update">Update Product</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div class="panel">
    <h2 style="margin:6px 0 12px">All Products</h2>
    <table>
      <tr><th>ID</th><th>Name</th><th>Price</th><th>Image</th><th>Actions</th></tr>
      <?php
        $res = $conn->query("SELECT * FROM products ORDER BY id DESC");
        while ($row = $res->fetch_assoc()):
      ?>
        <tr>
          <td><?= (int)$row['id'] ?></td>
          <td><?= htmlspecialchars($row['name']) ?></td>
          <td>$<?= number_format((float)$row['price'],2) ?></td>
          <td><img src="<?= htmlspecialchars($row['image']) ?>" width="60" height="60" style="object-fit:cover"></td>
          <td>
            <a class="btn" href="admin.php?edit=<?= (int)$row['id'] ?>">Edit</a>
            <a class="btn danger" onclick="return confirm('Delete this product?')" href="admin.php?delete=<?= (int)$row['id'] ?>">Delete</a>
          </td>
        </tr>
      <?php endwhile; ?>
    </table>
  </div>
</div>
</body>
</html>
