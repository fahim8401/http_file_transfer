<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

$csrf = csrf_token();

// Get list of uploaded files for display
$pdo = db();
$stmt = $pdo->prepare("SELECT id, orig_name, size, downloads, max_downloads, expires_at, created_at 
                       FROM files 
                       ORDER BY created_at DESC 
                       LIMIT 50");
$stmt->execute();
$uploadedFiles = $stmt->fetchAll();

function human_size(int $bytes): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B','KB','MB','GB','TB','PB'];
    $pow = (int) floor(log($bytes, 1024));
    $pow = max(0, min($pow, count($units) - 1));
    $value = $bytes / (1024 ** $pow);
    $precision = ($value < 10 && $pow > 0) ? 1 : 0;
    return number_format($value, $precision) . ' ' . $units[$pow];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Simple File Transfer — Upload</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>
  /* ===== Slightly Darker Gray theme (keep progress bar green) ===== */
  :root{
    --bg:#808896;         /* page background (darker gray) */
    --card:#eef2f7;       /* panel/card bg (soft dark-gray) */
    --text:#0f172a;       /* main text (slate-900) */
    --muted:#475569;      /* muted text (slate-600) */
    --border:#cbd5e1;     /* border (slate-300) */
    --white:#ffffff;
  }
  html,body{height:100%;}
  body{
    margin:0; font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
    background: var(--bg);
    color: var(--text);
    padding:16px;
  }

  /* ===== Layout: main + right aside ===== */
  .wrap{width:min(1140px, 100%); margin:0 auto;}
  .layout{display:flex; gap:16px; align-items:stretch;}
  .main{flex:1; min-width:0;}
  .aside{width:320px; max-width:100%;}
  .aside-sticky{ position: sticky; top:16px; }

  /* ===== Ad slots (WHITE containers) ===== */
  .ad-slot{
    display:flex; align-items:center; justify-content:center;
    background:#ffffff;
    border:1px solid var(--border);
    border-radius:14px;
    text-align:center;
    color:#374151;
    box-shadow:0 10px 24px rgba(0,0,0,.07);
    overflow:hidden; /* crop overflow */
  }
  .ad-top{ height:90px; margin-bottom:14px; }  /* 728×90 / 320×100 */
  .ad-right{ height:600px; }                   /* 300×600 */

  /* make images fit nicely inside slots */
  .ad-slot a, .ad-slot picture, .ad-slot img{
    display:block; width:100%; height:100%;
  }
  .ad-slot img{
    object-fit:contain; /* keep aspect ratio */
  }

  .ad-title{ font-weight:700; color:#111827; }
  .ad-sub{ font-size:12px; color:#6b7280; }

  /* ===== Uploader Panel ===== */
  .panel{
    background: var(--card);
    border-radius: 16px;
    padding: 24px 22px;
    box-shadow: 0 14px 32px rgba(0,0,0,.10);
    border: 1px solid var(--border);
  }
  .subtle{
    color: var(--muted); letter-spacing: 2px; font-size: 12px; text-transform: uppercase;
    text-align:center; margin-bottom: 10px;
  }
  h1.title{
    text-align:center; margin: 4px 0 18px; font-weight: 800; letter-spacing: 1px;
    text-transform: uppercase; color: var(--text);
  }

  /* ===== File picker row ===== */
  .picker{
    display:flex; gap:12px; align-items:center; justify-content:center; flex-wrap:wrap; margin-bottom:18px;
  }
  .btn{
    appearance:none; border:1px solid var(--border); border-radius: 12px; padding: 10px 16px;
    font-weight: 700; cursor:pointer; transition: transform .06s ease, filter .2s ease;
    color:#0f172a; background: linear-gradient(#f8fafc,#e5e7eb);
    box-shadow: 0 6px 14px rgba(0,0,0,.08);
  }
  .btn:hover{ filter: brightness(1.02); }
  .btn:active{ transform: translateY(1px); }
  .btn-outline{
    background: #fff; color: #1f2937; border:1px solid var(--border);
    box-shadow: none;
  }
  .file-hint{ font-size: 13px; color: var(--muted); }

  /* ===== Progress (container gray, FILL green) ===== */
  .progress-box{
    background: #e9edf2;
    padding: 18px; border-radius: 12px; border:1px solid var(--border);
  }
  .bar{
    height: 32px; border-radius: 999px; overflow:hidden;
    background: #d1d5db;
    border: 1px solid #9ca3af;
    box-shadow: inset 0 2px 6px rgba(0,0,0,.08);
  }
  .bar-fill{
    height:100%; width:0%;
    /* keep the green only here */
    background:
      linear-gradient(180deg, #b9ffbd 0%, #54ff7b 60%, #2ed35f 100%),
      repeating-linear-gradient(45deg, rgba(255,255,255,.35) 0 14px, rgba(255,255,255,.15) 14px 28px);
    background-size: auto, 28px 28px;
    animation: move 1.2s linear infinite;
    transition: width .15s ease;
    border-radius: 999px;
    box-shadow: inset 0 0 10px rgba(0,0,0,.1);
  }
  @keyframes move{
    from{ background-position: 0 0, 0 0; }
    to  { background-position: 0 0, 28px 0; }
  }

  .meta-row{
    display:flex; justify-content:space-between; align-items:center; margin-top:10px; font-size: 13px; color: var(--muted);
  }
  .percent{
    display:block; text-align:center; font-size: 40px; font-weight: 900; margin-top: 10px; color: var(--text);
  }

  /* ===== Result & errors ===== */
  .result{ margin-top:18px; }
  .hidden{ display:none !important; }
  .result .linkbox{
    display:flex; gap:8px; margin-top:8px;
  }
  .result input{
    flex:1; border-radius: 10px; padding:10px 12px; border:1px solid var(--border);
    background:#fff; color:#0f172a;
  }
  .error{
    display:none; background: #fff1f2; border:1px solid #fecaca; color:#7f1d1d;
    padding:10px 12px; border-radius:10px; margin-top:14px; font-size:14px;
  }

  /* tiny footer */
  .footer{ margin-top:14px; text-align:center; font-size:12px; color:var(--muted); }

  /* ===== Navigation Bar ===== */
  .navbar{
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 14px 20px;
    margin-bottom: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,.15);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
  }
  .navbar-brand{
    font-size: 22px;
    font-weight: 800;
    color: #fff;
    text-decoration: none;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .navbar-brand:hover{
    color: #fff;
    text-decoration: none;
  }
  .navbar-nav{
    display: flex;
    gap: 8px;
    list-style: none;
    margin: 0;
    padding: 0;
    flex-wrap: wrap;
  }
  .nav-link{
    color: rgba(255,255,255,0.9);
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s ease;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
  }
  .nav-link:hover{
    background: rgba(255,255,255,0.25);
    color: #fff;
    transform: translateY(-1px);
  }
  .nav-link.active{
    background: rgba(255,255,255,0.3);
    color: #fff;
  }

  /* ===== Responsive: aside stacks under main on small screens ===== */
  @media (max-width: 992px){
    .layout{ flex-direction: column; }
    .aside{ width:auto; }
    .aside-sticky{ position: static; }
    .ad-right{ height:250px; } /* smaller creative on mobile */
  }
  @media (max-width: 768px){
    .navbar{
      flex-direction: column;
      text-align: center;
    }
    .navbar-nav{
      width: 100%;
      justify-content: center;
    }
  }
</style>
</head>
<body>

<div class="wrap">

  <!-- ===== Navigation Bar ===== -->
  <nav class="navbar">
    <a href="index.php" class="navbar-brand">
      <span>📁</span> File Transfer
    </a>
    <ul class="navbar-nav">
      <li><a href="index.php" class="nav-link active">Upload</a></li>
      <li><a href="admin.php" class="nav-link">Admin Panel</a></li>
    </ul>
  </nav>

  <!-- ===== Top Banner Ad (Image) ===== -->
  <div class="ad-slot ad-top" id="adTop">
    <!-- Static image ad with responsive sources -->
    <a href="https://your-ad-link.example/top" target="_blank" rel="noopener">
      <picture>
        <!-- ছোট স্ক্রিন হলে 320×100 -->
        <source media="(max-width: 640px)" srcset="ads/top_320x100.svg">
        <!-- ডিফল্ট 728×90 -->
        <img src="ads/top_728x90.svg" alt="Top Banner Ad">
      </picture>
    </a>
  </div>

  <div class="layout">

    <!-- Left: uploader panel -->
    <div class="main">
      <div class="panel">
        <div class="subtle" id="connMsg">Secure File Sharing • Fast Upload • Easy Download</div>
        <h1 class="title" id="phaseTitle">Upload Files</h1>

        <!-- Picker -->
        <div class="picker">
          <label class="btn">
            Choose File
            <input type="file" id="fileInput" hidden>
          </label>
          <button class="btn" id="btnUpload">Start Upload</button>
          <button class="btn btn-outline" id="btnCancel" style="display:none">Cancel</button>
          <span class="file-hint" id="fileHint">No file chosen</span>
        </div>

        <!-- Progress -->
        <div class="progress-box" id="progressBox" style="display:none">
          <div class="bar">
            <div class="bar-fill" id="barFill"></div>
          </div>
          <div class="meta-row">
            <span id="fileSize">FILE SIZE: –</span>
            <span id="speedEta">SPEED: – &nbsp; • &nbsp; ETA: –</span>
          </div>
          <span class="percent" id="pct">0%</span>
        </div>

        <!-- Result -->
        <div class="result hidden" id="result">
          <div>Upload complete! Share this link:</div>
          <div class="linkbox">
            <input id="shareLink" readonly>
            <button class="btn" id="btnCopy">Copy</button>
          </div>
        </div>

        <div class="error" id="errorBox">Error</div>

        <div class="footer">Download link is public.</div>
      </div>

      <!-- ===== Uploaded Files List ===== -->
      <?php if (!empty($uploadedFiles)): ?>
      <div class="panel" style="margin-top: 24px;">
        <h2 class="title" style="font-size: 20px; margin-bottom: 18px;">Recently Uploaded Files</h2>
        <div style="overflow-x: auto;">
          <table style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="border-bottom: 2px solid var(--border); text-align: left;">
                <th style="padding: 10px; color: var(--muted); font-size: 12px; text-transform: uppercase;">File Name</th>
                <th style="padding: 10px; color: var(--muted); font-size: 12px; text-transform: uppercase;">Size</th>
                <th style="padding: 10px; color: var(--muted); font-size: 12px; text-transform: uppercase;">Downloads</th>
                <th style="padding: 10px; color: var(--muted); font-size: 12px; text-transform: uppercase;">Created</th>
                <th style="padding: 10px; color: var(--muted); font-size: 12px; text-transform: uppercase;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($uploadedFiles as $file): 
                $fileLink = 'dl.php?id=' . $file['id'];
                $isExpired = ($file['expires_at'] !== null && new DateTime($file['expires_at']) < new DateTime());
                $isLimited = ($file['max_downloads'] !== null && (int)$file['downloads'] >= (int)$file['max_downloads']);
                $canDownload = !$isExpired && !$isLimited;
              ?>
              <tr style="border-bottom: 1px solid var(--border);">
                <td style="padding: 12px; color: var(--text); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= h($file['orig_name']) ?>">
                  <?= h($file['orig_name']) ?>
                </td>
                <td style="padding: 12px; color: var(--text);">
                  <?= human_size((int)$file['size']) ?>
                </td>
                <td style="padding: 12px; color: var(--text);">
                  <?= (int)$file['downloads'] ?><?= $file['max_downloads'] ? ' / ' . (int)$file['max_downloads'] : '' ?>
                </td>
                <td style="padding: 12px; color: var(--text); font-size: 13px;">
                  <?= date('M d, Y', strtotime($file['created_at'])) ?>
                </td>
                <td style="padding: 12px;">
                  <?php if ($canDownload): ?>
                    <a href="<?= h($fileLink) ?>" class="btn" style="padding: 8px 16px; font-size: 14px; text-decoration: none; display: inline-block;">
                      Download
                    </a>
                  <?php else: ?>
                    <span style="color: var(--muted); font-size: 13px;"><?= $isExpired ? 'Expired' : 'Limit reached' ?></span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="margin-top: 16px; text-align: center;">
          <a href="admin.php" class="btn btn-outline" style="text-decoration: none; display: inline-block;">View All in Admin Panel</a>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Right: sticky Image Ad -->
    <aside class="aside">
      <div class="aside-sticky">
        <div class="ad-slot ad-right" id="adRight">
          <a href="https://your-ad-link.example/right" target="_blank" rel="noopener">
            <picture>
              <!-- মোবাইলে 300×250 -->
              <source media="(max-width: 992px)" srcset="ads/right_300x250.svg">
              <!-- ডিফল্ট 300×600 -->
              <img src="ads/right_300x600.svg" alt="Right Sidebar Ad">
            </picture>
          </a>
        </div>
      </div>
    </aside>

  </div>
</div>

<script>
(function(){
  const $ = id => document.getElementById(id);
  const fileInput = $('fileInput');
  const fileHint  = $('fileHint');
  const btnUpload = $('btnUpload');
  const btnCancel = $('btnCancel');
  const progressBox = $('progressBox');
  const barFill   = $('barFill');
  const pctTxt    = $('pct');
  const fileSize  = $('fileSize');
  const speedEta  = $('speedEta');
  const result    = $('result');
  const shareLink = $('shareLink');
  const btnCopy   = $('btnCopy');
  const errorBox  = $('errorBox');
  const phaseTitle= $('phaseTitle');

  let xhr = null;

  function humanSize(bytes){
    if (bytes === 0) return '0 B';
    const u = ['B','KB','MB','GB','TB','PB']; let i=0; let n=bytes;
    while (n >= 1024 && i < u.length-1){ n/=1024; i++; }
    return n.toFixed(n<10?1:0)+' '+u[i];
  }

  // Show chosen file
  fileInput.addEventListener('change', ()=>{
    if (fileInput.files && fileInput.files.length){
      const f = fileInput.files[0];
      fileHint.textContent = f.name;
      fileSize.textContent = 'FILE SIZE : ' + humanSize(f.size);
    } else {
      fileHint.textContent = 'No file chosen';
      fileSize.textContent = 'FILE SIZE : –';
    }
  });

  // Start upload (AJAX)
  btnUpload.addEventListener('click', ()=>{
    if (!fileInput.files || !fileInput.files.length){
      error('Please choose a file to upload.');
      return;
    }
    error('');
    result.classList.add('hidden');
    progressBox.style.display = '';
    barFill.style.width = '0%';
    pctTxt.textContent = '0%';
    speedEta.textContent = 'SPEED: –  •  ETA: –';
    phaseTitle.textContent = 'Uploading...';
    btnUpload.disabled = true;
    btnCancel.style.display = '';

    const fd = new FormData();
    fd.append('csrf_token', '<?= htmlspecialchars($csrf) ?>');
    fd.append('file', fileInput.files[0]);
    fd.append('expire_days', '3');
    fd.append('max_downloads', '');

    xhr = new XMLHttpRequest();
    xhr.open('POST', 'upload_ajax.php', true);
    xhr.responseType = 'json';

    const start = Date.now();

    xhr.upload.onprogress = (e)=>{
      if (!e.lengthComputable){
        pctTxt.textContent = '...';
        return;
      }
      const pct = Math.round((e.loaded/e.total)*100);
      barFill.style.width = pct + '%';
      pctTxt.textContent = pct + '%';

      const elapsed = (Date.now()-start)/1000;
      const speed = e.loaded/elapsed; // B/s
      const remain = e.total - e.loaded;
      const eta = speed>0 ? (remain/speed) : 0;
      speedEta.textContent = 'SPEED: ' + humanSize(speed) + '/s  •  ETA: ' + (eta>0?eta.toFixed(1):'0') + 's';
    };

    xhr.onload = ()=>{
      btnUpload.disabled = false; btnCancel.style.display = 'none';
      if (xhr.status===200 && xhr.response && xhr.response.ok){
        barFill.style.width = '100%';
        pctTxt.textContent = '100%';
        phaseTitle.textContent = 'Upload Complete ✓';
        shareLink.value = xhr.response.link || '';
        result.classList.remove('hidden');
      } else {
        const msg = (xhr.response && xhr.response.error) ? xhr.response.error : ('Server error ('+xhr.status+')');
        error(msg);
        phaseTitle.textContent = 'Upload Failed ✗';
      }
    };
    xhr.onerror = ()=>{ btnUpload.disabled=false; btnCancel.style.display='none'; error('Network error.'); phaseTitle.textContent='Upload Failed ✗'; };
    xhr.onabort  = ()=>{ btnUpload.disabled=false; btnCancel.style.display='none'; error('Upload canceled.'); phaseTitle.textContent='Canceled'; };

    xhr.send(fd);
  });

  // Cancel
  btnCancel.addEventListener('click', ()=>{ if (xhr){ xhr.abort(); } });

  // Copy link
  btnCopy.addEventListener('click', ()=>{
    shareLink.select(); shareLink.setSelectionRange(0,99999);
    document.execCommand('copy');
    btnCopy.textContent = 'Copied!'; setTimeout(()=>btnCopy.textContent='Copy', 1200);
  });

  function error(msg){
    if (!msg){ errorBox.style.display='none'; errorBox.textContent=''; return; }
    errorBox.style.display='block'; errorBox.textContent = msg;
  }
})();
</script>
</body>
</html>
