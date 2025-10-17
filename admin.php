<?php
// admin.php — Admin dashboard with proper username/password authentication
declare(strict_types=1);

session_start();

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

/* ========= AUTHENTICATION ========= */
$pdo = db();

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_user_id']);
    unset($_SESSION['admin_username']);
    header('Location: admin.php');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    
    $stmt = $pdo->prepare("SELECT id, username, password_hash, is_admin FROM users WHERE username = :username AND is_admin = 1 LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['admin_user_id'] = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        header('Location: admin.php');
        exit;
    } else {
        $loginError = 'Invalid username or password';
    }
}

// Check if logged in
$isLoggedIn = !empty($_SESSION['admin_user_id']);

// If not logged in, show login form
if (!$isLoggedIn) {
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title>Admin Login</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
      <style>
        :root{
          --bg:#f3f4f6;
          --card:#f9fafb;
          --text:#111827;
          --muted:#6b7280;
          --border:#e5e7eb;
          --accent:#22c55e;
        }
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:var(--bg);color:var(--text)}
        .card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:32px;box-shadow:0 10px 28px rgba(0,0,0,.05);width:min(420px,92%)}
        .title{margin:0 0 24px;font-weight:800;letter-spacing:.3px;text-align:center}
        .form-label{font-weight:600;margin-bottom:8px}
        .form-control{border-radius:10px;border:1px solid var(--border);padding:10px 12px;margin-bottom:16px}
        .btn{border:0;border-radius:10px;padding:12px 16px;font-weight:700;color:#fff;background:var(--accent);cursor:pointer;box-shadow:0 6px 14px rgba(34,197,94,.22);width:100%}
        .btn:hover{filter:brightness(1.03)}
        .alert{padding:12px;border-radius:10px;margin-bottom:16px;background:#fee2e2;border:1px solid #fecaca;color:#7f1d1d}
      </style>
    </head>
    <body>
      <div class="card">
        <h2 class="title">Admin Login</h2>
        <?php if (isset($loginError)): ?>
          <div class="alert"><?= h($loginError) ?></div>
        <?php endif; ?>
        <form method="post">
          <div>
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required autofocus>
          </div>
          <div>
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <button type="submit" name="login" class="btn">Login</button>
        </form>
      </div>
    </body>
    </html>
    <?php
    exit;
}

/* ========= HELPERS ========= */
function human_size(int $bytes, int $decimals = 1): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B','KB','MB','GB','TB','PB'];
    $pow = (int) floor(log($bytes, 1024));
    $pow = max(0, min($pow, count($units) - 1));
    $value = $bytes / (1024 ** $pow);
    $precision = ($value < 10 && $pow > 0) ? $decimals : 0;
    return number_format($value, $precision) . ' ' . $units[$pow];
}
function is_active_row(array $r): bool {
    // No expiration check anymore, only download limit
    $underLimit = ($r['max_downloads'] === null) || ((int)$r['downloads'] < (int)$r['max_downloads']);
    return $underLimit;
}
function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'];
}

/* ========= INPUTS ========= */
$q          = trim((string)($_GET['q'] ?? ''));
$status     = (string)($_GET['status'] ?? 'all');
$sort       = (string)($_GET['sort'] ?? 'id_desc');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = min(200, max(10, (int)($_GET['pp'] ?? 50)));

$csrf = csrf_token();

/* ========= ACTIONS (POST) ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['login'])) {
    $action = (string)($_POST['action'] ?? '');
    $token  = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        exit('Bad CSRF token');
    }

    if ($action === 'delete_selected') {
        $ids = $_POST['ids'] ?? [];
        if (is_array($ids) && $ids) {
            $idInts = array_map('intval', $ids);
            $in = implode(',', array_fill(0, count($idInts), '?'));
            $stmt = $pdo->prepare("DELETE FROM files WHERE id IN ($in)");
            $stmt->execute($idInts);
        }
        header('Location: '.$_SERVER['REQUEST_URI']); exit;
    }

    if ($action === 'delete_expired') {
        // Only delete files that have an expiration date set and are expired
        $pdo->prepare("DELETE FROM files WHERE expires_at IS NOT NULL AND expires_at < datetime('now')")->execute();
        header('Location: '.$_SERVER['REQUEST_URI']); exit;
    }

    if ($action === 'export_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=files_export_'.date('Ymd_His').'.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id','orig_name','size','downloads','max_downloads','expires_at','created_at','status','share_link']);

        [$whereSql, $params] = buildWhere($q, $status);
        $sql = "SELECT id, orig_name, size, downloads, max_downloads, expires_at, created_at FROM files $whereSql ".sqlOrder($sort);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $statusTxt = is_active_row($r) ? 'active' : 'inactive';
            $link = base_url().'/dl.php?id='.$r['id'];
            fputcsv($out, [
                $r['id'],
                $r['orig_name'],
                $r['size'],
                $r['downloads'],
                $r['max_downloads'],
                $r['expires_at'] ?? 'Never',
                $r['created_at'] ?? '',
                $statusTxt,
                $link,
            ]);
        }
        fclose($out);
        exit;
    }
}

/* ========= QUERY BUILDERS ========= */
function buildWhere(string $q, string $status): array {
    $where = [];
    $params = [];

    if ($q !== '') {
        $where[] = "(orig_name LIKE :q)";
        $params[':q'] = "%$q%";
    }
    if ($status === 'active') {
        $where[] = "(max_downloads IS NULL OR downloads < max_downloads)";
    } elseif ($status === 'expired') {
        $where[] = "(expires_at IS NOT NULL AND expires_at <= datetime('now'))";
    } elseif ($status === 'limited') {
        $where[] = "(max_downloads IS NOT NULL AND downloads >= max_downloads)";
    }

    $sql = $where ? ('WHERE '.implode(' AND ', $where)) : '';
    return [$sql, $params];
}
function sqlOrder(string $sort): string {
    return match($sort) {
        'id_asc'        => 'ORDER BY id ASC',
        'size_desc'     => 'ORDER BY size DESC',
        'size_asc'      => 'ORDER BY size ASC',
        'exp_asc'       => 'ORDER BY expires_at ASC',
        'exp_desc'      => 'ORDER BY expires_at DESC',
        'dl_desc'       => 'ORDER BY downloads DESC',
        'dl_asc'        => 'ORDER BY downloads ASC',
        'created_asc'   => 'ORDER BY created_at ASC, id ASC',
        'created_desc'  => 'ORDER BY created_at DESC, id DESC',
        default         => 'ORDER BY id DESC',
    };
}

/* ========= COUNTS & LIST ========= */
[$whereSql, $params] = buildWhere($q, $status);

$total = (int)$pdo->query("SELECT COUNT(*) FROM files")->fetchColumn();

$stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM files $whereSql");
$stmtCnt->execute($params);
$filteredCount = (int)$stmtCnt->fetchColumn();

$sumSize = (int)$pdo->query("SELECT COALESCE(SUM(size),0) FROM files")->fetchColumn();
$sumDownloads = (int)$pdo->query("SELECT COALESCE(SUM(downloads),0) FROM files")->fetchColumn();

$activeCount = (int)$pdo->query("SELECT COUNT(*) FROM files WHERE (max_downloads IS NULL OR downloads < max_downloads)")->fetchColumn();
$expiredCount = (int)$pdo->query("SELECT COUNT(*) FROM files WHERE expires_at IS NOT NULL AND expires_at <= datetime('now')")->fetchColumn();
$limitedCount = (int)$pdo->query("SELECT COUNT(*) FROM files WHERE max_downloads IS NOT NULL AND downloads >= max_downloads")->fetchColumn();

// pagination
$offset = ($page - 1) * $perPage;

$sqlList = "SELECT id, orig_name, size, downloads, max_downloads, expires_at, created_at
            FROM files
            $whereSql
            ".sqlOrder($sort)."
            LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sqlList);
foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build base query string for links (without page)
$qsBase = http_build_query([
    'q'      => $q,
    'status' => $status,
    'sort'   => $sort,
    'pp'     => $perPage,
]);

/* ========= UI ========= */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin Panel — File Transfer</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  :root{
    --bg:#f3f4f6;
    --card:#f9fafb;
    --text:#111827;
    --muted:#6b7280;
    --border:#e5e7eb;
    --accent:#22c55e;
    --accent-50:#ecfdf5;
    --danger-50:#fee2e2;
  }
  body{ background:var(--bg); color:var(--text); padding:16px; font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; }
  .wrap{ width:min(1220px,100%); margin:0 auto; }
  .header-bar{
    display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;
    background:var(--card); border:1px solid var(--border); border-radius:12px; padding:16px;
    box-shadow:0 4px 12px rgba(0,0,0,.05);
  }
  .header-bar h1{ margin:0; font-size:24px; font-weight:800; }
  .header-bar .admin-info{ display:flex; align-items:center; gap:12px; }
  .header-bar .btn-logout{ padding:8px 16px; border-radius:8px; background:#ef4444; color:#fff; text-decoration:none; font-weight:600; }
  .header-bar .btn-logout:hover{ background:#dc2626; }
  
  .layout{ display:flex; gap:16px; }
  .main{ flex:1; min-width:0; }

  .panel{ background:var(--card); border:1px solid var(--border); border-radius:16px; padding:16px; box-shadow:0 10px 26px rgba(0,0,0,.05); }
  .stat{ background:#f3f4f6; border:1px solid var(--border); border-radius:12px; padding:10px 12px; }
  .stat h6{ margin:0; font-weight:800; color:#111827; }
  .muted{ color:var(--muted); }

  table.table{ background:#fff; color:#111; border-radius:12px; overflow:hidden; }
  thead.table-light th{ background:#f3f4f6 !important; }
  table.table th{ white-space:nowrap; }
  .status-badge{ border-radius:999px; padding:.2rem .5rem; font-size:.8rem; }
  .status-active{ background:var(--accent-50); color:#0b7a14; }
  .status-inactive{ background:var(--danger-50); color:#9b1c1c; }

  .searchbar input{ border-radius:999px; }
  .btn-pill{ border-radius:999px; }
  .btn{ box-shadow:none; }
  .btn-light{ border:1px solid var(--border); background:#fff; }
</style>
</head>
<body>

<div class="wrap">
  <div class="header-bar">
    <h1>Admin Panel</h1>
    <div class="admin-info">
      <span>Welcome, <strong><?= h($_SESSION['admin_username']) ?></strong></span>
      <a href="?logout" class="btn-logout">Logout</a>
    </div>
  </div>

  <div class="layout">
    <div class="main">
      <div class="panel mb-3">
        <div class="row g-2">
          <div class="col-md-2"><div class="stat"><h6>Total files</h6><div><?= (int)$total ?></div></div></div>
          <div class="col-md-2"><div class="stat"><h6>Active</h6><div><?= (int)$activeCount ?></div></div></div>
          <div class="col-md-2"><div class="stat"><h6>Expired</h6><div><?= (int)$expiredCount ?></div></div></div>
          <div class="col-md-2"><div class="stat"><h6>Limited</h6><div><?= (int)$limitedCount ?></div></div></div>
          <div class="col-md-2"><div class="stat"><h6>Total size</h6><div><?= human_size($sumSize) ?></div></div></div>
          <div class="col-md-2"><div class="stat"><h6>Total downloads</h6><div><?= (int)$sumDownloads ?></div></div></div>
        </div>
      </div>

      <div class="panel mb-3">
        <form class="row gy-2 gx-2 align-items-end">
          <div class="col-md-4 searchbar">
            <label class="form-label">Search (name)</label>
            <input type="text" name="q" value="<?= h($q) ?>" class="form-control" placeholder="e.g. report.pdf">
          </div>
          <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <?php foreach (['all'=>'All','active'=>'Active','expired'=>'Expired','limited'=>'Limited'] as $k=>$v): ?>
                <option value="<?=h($k)?>" <?= $status===$k?'selected':'' ?>><?=h($v)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Sort</label>
            <select name="sort" class="form-select">
              <?php
              $opts = [
                'id_desc'=>'Newest (ID ↓)','id_asc'=>'Oldest (ID ↑)',
                'created_desc'=>'Created ↓','created_asc'=>'Created ↑',
                'size_desc'=>'Size ↓','size_asc'=>'Size ↑',
                'exp_desc'=>'Expires ↓','exp_asc'=>'Expires ↑',
                'dl_desc'=>'Downloads ↓','dl_asc'=>'Downloads ↑',
              ];
              foreach ($opts as $k=>$v): ?>
                <option value="<?=h($k)?>" <?= $sort===$k?'selected':'' ?>><?=h($v)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-1">
            <label class="form-label">Per page</label>
            <input type="number" name="pp" value="<?= (int)$perPage ?>" min="10" max="200" class="form-control">
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-light btn-pill w-100">Apply</button>
            <a class="btn btn-outline-light btn-pill w-100" href="admin.php">Reset</a>
          </div>
        </form>
      </div>

      <form method="post" class="panel mb-3">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <div class="d-flex flex-wrap gap-2 mb-2">
          <button name="action" value="delete_selected" class="btn btn-danger btn-sm btn-pill" onclick="return confirm('Delete selected rows? This cannot be undone.');">Delete selected</button>
          <button name="action" value="delete_expired" class="btn btn-warning btn-sm btn-pill" onclick="return confirm('Delete all expired rows?');">Delete expired</button>
          <button name="action" value="export_csv" class="btn btn-success btn-sm btn-pill">Export CSV (current filter)</button>
          <div class="ms-auto text-end">
            <span class="badge text-bg-light">Filtered: <?= (int)$filteredCount ?></span>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:28px"><input type="checkbox" onclick="document.querySelectorAll('.rowchk').forEach(cb=>cb.checked=this.checked)"></th>
                <th>ID</th>
                <th>Name</th>
                <th>Size</th>
                <th>Downloads</th>
                <th>Expires</th>
                <th>Created</th>
                <th>Status</th>
                <th>Link</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="9" class="text-center text-muted">No records</td></tr>
              <?php else: foreach ($rows as $r):
                $active = is_active_row($r);
                $badgeClass = $active ? 'status-active' : 'status-inactive';
                $badgeText  = $active ? 'Active' : 'Inactive';
                $link = base_url().'/dl.php?id='.$r['id'];
                ?>
                <tr>
                  <td><input type="checkbox" class="rowchk" name="ids[]" value="<?= (int)$r['id'] ?>"></td>
                  <td><?= (int)$r['id'] ?></td>
                  <td class="text-truncate" style="max-width:260px" title="<?= h($r['orig_name']) ?>"><?= h($r['orig_name']) ?></td>
                  <td><?= human_size((int)$r['size']) ?></td>
                  <td><?= (int)$r['downloads'] ?><?= $r['max_downloads'] ? ' / '.(int)$r['max_downloads'] : '' ?></td>
                  <td><span title="<?= h($r['expires_at'] ?? 'Never') ?>"><?= h($r['expires_at'] ?? 'Never') ?></span></td>
                  <td><?= h($r['created_at'] ?? '') ?></td>
                  <td><span class="status-badge <?= $badgeClass ?>"><?= $badgeText ?></span></td>
                  <td>
                    <a class="btn btn-outline-primary btn-sm btn-pill" href="<?= h($link) ?>" target="_blank">Open</a>
                    <button type="button" class="btn btn-outline-secondary btn-sm btn-pill" onclick="copyLink('<?= h($link) ?>', this)">Copy</button>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <?php
        $totalPages = (int)ceil($filteredCount / $perPage);
        if ($totalPages > 1):
          $qs = $qsBase ? ($qsBase.'&') : '';
        ?>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page<=1?'disabled':'' ?>">
              <a class="page-link" href="?<?= $qs ?>page=<?= max(1,$page-1) ?>">Prev</a>
            </li>
            <?php
              $start = max(1, $page-2);
              $end   = min($totalPages, $page+2);
              for ($i=$start; $i<=$end; $i++):
            ?>
              <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?<?= $qs ?>page=<?= $i ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
              <a class="page-link" href="?<?= $qs ?>page=<?= min($totalPages,$page+1) ?>">Next</a>
            </li>
          </ul>
        </nav>
        <?php endif; ?>

      </form>
    </div>
  </div>
</div>

<script>
function copyLink(link, btn){
  (navigator.clipboard?.writeText(link) || Promise.reject()).then(()=>{
    btn.textContent = 'Copied!';
    setTimeout(()=>btn.textContent='Copy', 1200);
  }).catch(()=>{
    const tmp = document.createElement('input');
    tmp.value = link; document.body.appendChild(tmp); tmp.select(); document.execCommand('copy'); tmp.remove();
    btn.textContent = 'Copied!'; setTimeout(()=>btn.textContent='Copy', 1200);
  });
}
</script>
</body>
</html>
