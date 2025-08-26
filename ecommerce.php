<?php
// ecommerce.php — Shop + Cart
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "mini_shop_v2";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("DB connection failed: " . $conn->connect_error);

// --- Add to cart ---
if (isset($_GET['add'])) {
  $id = (int)$_GET['add'];
  $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + 1;
  header("Location: ecommerce.php");
  exit;
}

// --- Update qty ---
if (isset($_POST['update_qty'])) {
  foreach ($_POST['qty'] ?? [] as $pid => $q) {
    $q = max(0, (int)$q);
    if ($q === 0) unset($_SESSION['cart'][$pid]);
    else $_SESSION['cart'][$pid] = $q;
  }
  header("Location: ecommerce.php");
  exit;
}

// --- Remove item ---
if (isset($_GET['remove'])) {
  $id = (int)$_GET['remove'];
  unset($_SESSION['cart'][$id]);
  header("Location: ecommerce.php");
  exit;
}

// --- Checkout (simulate) ---
$confirmation = null;
if (isset($_POST['checkout'])) {
  $total = 0.0;
  if (!empty($_SESSION['cart'])) {
    $ids = implode(",", array_map('intval', array_keys($_SESSION['cart'])));
    $res = $conn->query("SELECT id, price FROM products WHERE id IN ($ids)");
    $prices = [];
    while ($r = $res->fetch_assoc()) $prices[$r['id']] = (float)$r['price'];
    foreach ($_SESSION['cart'] as $pid => $qty) {
      $total += ($prices[$pid] ?? 0) * $qty;
    }
  }
  $confirmation = "✅ Order confirmed! Total: $" . number_format($total, 2);
  $_SESSION['cart'] = []; // clear cart
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>UzairMart</title>
<style>
  :root { --bg:#0f172a; --card:#111827; --text:#e5e7eb; --accent:#22c55e; --muted:#94a3b8; --danger:#ef4444; }
  *{box-sizing:border-box}
  body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial;background:linear-gradient(135deg,#0b1220,#111827);}
  header{position:sticky;top:0;z-index:10;background: linear-gradient(90deg, #26b3a7ff, #e61616ff);;display:flex;gap:20px;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #000000ff;color:var(--text)}
  header a{color:#9ae6b4;text-decoration:none;font-weight:600}
  .wrap{max-width:1100px;margin:24px auto;padding:0 16px}
  .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
  @media(max-width:1024px){.grid{grid-template-columns:repeat(3,1fr)}}
  @media(max-width:768px){.grid{grid-template-columns:repeat(2,1fr)}}
  @media(max-width:480px){.grid{grid-template-columns:1fr}}
  .card{background:linear-gradient(180deg,#0f172a,#0b1220);border:1px solid #1f2937;border-radius:16px;overflow:hidden;box-shadow:0px 0px 5px rgba(238, 36, 9, 1)}
  .card img{width:100%;height:200px;object-fit:cover;display:block}
  .pad{padding:12px 14px;color:var(--text)}
  .name{font-size:1.05rem;font-weight:600;margin:4px 0}
  .price{color:#86efac;font-weight:700}
  .muted{color:var(--muted);font-size:.9rem}
  .btn{display:inline-block;background:var(--accent);color:#062a12;border:none;padding:10px 14px;border-radius:10px;font-weight:700;cursor:pointer}
  .btn:hover{filter:brightness(.95)}
  .danger{background:var(--danger);color:#fff}
  .cart{margin-top:28px;background:linear-gradient(180deg,#0f172a,#0b1220); box-shadow: 0px 0px 5px red; border:1px solid #1f2937;border-radius:16px;padding:14px;color:var(--text)}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px;border-bottom:1px solid #1f2937;text-align:left}
  .right{text-align:right}
  .actions{display:flex;gap:8px;flex-wrap:wrap}
  .notice{margin:16px 0;padding:12px 14px;border-radius:12px;background:#052e1a;color:#9ae6b4;border:1px solid #164e2d}
  footer{color:#64748b;text-align:center;padding:24px}
</style>
</head>
<body>
<header>
  <div style="display:flex;align-items:center;gap:10px">
    <span style="font-size:1.1rem;font-weight:800">🛒 UzairMart</span>
  </div>
  <nav class="actions">
    <a href="admin.php">💼 Admin</a>
  </nav>
</header>

<div class="wrap">
    <marquee behavior="smooth" direction="right" style> New Arrivals Daily! Stay Ahead with UzairMart!
</marquee>

<style>
marquee{
  font-size: 18px;
  font-weight: bold;
  color: #fff;
  background: linear-gradient(90deg, #26b3a7ff, #e61616ff); /* gradient effect */
  padding: 10px 0;
  border-radius: 6px;
  letter-spacing: 1px;
  text-transform: uppercase;
  box-shadow: 0 4px 6px rgba(0,0,0,0.2);
}
</style>
  <h2 style="color:#e2e8f0;margin:8px 0 14px">Products</h2>
  <div class="grid">
    <?php
      $res = $conn->query("SELECT * FROM products ORDER BY id DESC");
      while ($row = $res->fetch_assoc()):
    ?>
      <div class="card">
        <img src="<?= htmlspecialchars($row['image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>">
        <div class="pad">
          <div class="name"><?= htmlspecialchars($row['name']) ?></div>
          <div class="muted">$ <span class="price"><?= number_format((float)$row['price'],2) ?></span></div>
          <div style="margin-top:10px">
            <a class="btn" href="?add=<?= (int)$row['id'] ?>">Add to Cart</a>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  </div>

  <div class="cart">
    <h3 style="margin:6px 0 12px">Your Cart</h3>
    <?php if (!empty($_SESSION['cart'])): ?>
      <form method="post">
        <table>
          <tr><th>Item</th><th>Qty</th><th class="right">Line Total</th><th></th></tr>
          <?php
            $total = 0.0;
            $ids = implode(",", array_map('intval', array_keys($_SESSION['cart'])));
            $rows = [];
            if ($ids) {
              $res = $conn->query("SELECT id, name, price FROM products WHERE id IN ($ids)");
              while ($r = $res->fetch_assoc()) $rows[$r['id']] = $r;
            }
            foreach ($_SESSION['cart'] as $pid => $qty):
              $name = $rows[$pid]['name'] ?? 'Unknown';
              $price = (float)($rows[$pid]['price'] ?? 0);
              $line = $price * $qty;
              $total += $line;
          ?>
            <tr>
              <td><?= htmlspecialchars($name) ?></td>
              <td><input type="number" min="0" name="qty[<?= (int)$pid ?>]" value="<?= (int)$qty ?>" style="width:70px"></td>
              <td class="right">$<?= number_format($line, 2) ?></td>
              <td class="right"><a class="btn danger" href="?remove=<?= (int)$pid ?>">Remove</a></td>
            </tr>
          <?php endforeach; ?>
          <tr>
            <th colspan="2" class="right">Total</th>
            <th class="right">$<?= number_format($total, 2) ?></th>
            <th></th>
          </tr>
        </table>
        <div class="actions" style="margin-top:12px">
          <button class="btn" name="update_qty">Update Quantities</button>
          <button class="btn" name="checkout">Checkout</button>
        </div>
      </form>
    <?php else: ?>
      <div class="muted">Your cart is empty.</div>
    <?php endif; ?>
  </div>

  <?php if ($confirmation): ?>
    <div class="notice"><?= htmlspecialchars($confirmation) ?></div>
  <?php endif; ?>
</div>

<footer>@copyright +923059430303, uzairk9430303@gmail.com</footer>
</body>
</html>
