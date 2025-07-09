<?php
session_start();

// CSRF Token generate
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle file listing
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['init'])) {
    header('Content-Type: application/json');
    $all = array_diff(scandir(__DIR__), ['.', '..']);
    $files = array_filter($all, function($f) {
        return !preg_match('/\.(php|env|htaccess|ini)$/i', $f) && is_file(__DIR__ . '/' . $f);
    });
    $files = array_map(function($f) {
        $filePath = __DIR__ . '/' . $f;
        return [
            'name' => $f,
            'size' => filesize($filePath),
            'date' => date('Y-m-d H:i', filemtime($filePath))
        ];
    }, array_values($files));
    usort($files, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    echo json_encode(['files' => $files]);
    exit;
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    header('Content-Type: application/json');
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    if (isset($_FILES['uploadFile'])) {
        $file = $_FILES['uploadFile'];
        $originalName = basename($file['name']);
        
        // Handle duplicate filenames
        $targetFile = __DIR__ . '/' . $originalName;
        $counter = 1;
        $info = pathinfo($originalName);
        $base = $info['filename'] ?? $originalName;
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
        
        while (file_exists($targetFile)) {
            $targetFile = __DIR__ . '/' . $base . " ($counter)" . $ext;
            $counter++;
        }
        
        $finalName = basename($targetFile);

        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            $all = array_diff(scandir(__DIR__), ['.', '..']);
            $files = array_filter($all, function($f) {
                return !preg_match('/\.(php|env|htaccess|ini)$/i', $f) && is_file(__DIR__ . '/' . $f);
            });
            $files = array_map(function($f) {
                $filePath = __DIR__ . '/' . $f;
                return [
                    'name' => $f,
                    'size' => filesize($filePath),
                    'date' => date('Y-m-d H:i', filemtime($filePath))
                ];
            }, array_values($files));
            usort($files, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });

            echo json_encode([
                'status' => 'success',
                'message' => $finalName . ' uploaded successfully',
                'files' => $files
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to upload file.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No file provided.']);
    }
    exit;
}

// Handle file download
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['download'])) {
    $filename = $_GET['download'] ?? '';
    $filePath = __DIR__ . '/' . $filename;
    
    if (preg_match('/\.(php|env|htaccess|ini)$/i', $filename) || !file_exists($filePath)) {
        header('HTTP/1.0 404 Not Found');
        echo 'File not found';
        exit;
    }
    
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $filename = $_POST['filename'] ?? '';
    if (empty($filename)) {
        echo json_encode(['status' => 'error', 'message' => 'No filename provided']);
        exit;
    }

    $filePath = __DIR__ . '/' . $filename;

    // Prevent deletion of PHP files and critical files
    if (preg_match('/\.(php|env|htaccess|ini)$/i', $filename) || !file_exists($filePath)) {
        echo json_encode(['status' => 'error', 'message' => 'File not found or invalid file type']);
        exit;
    }

    if (unlink($filePath)) {
        $all = array_diff(scandir(__DIR__), ['.', '..']);
        $files = array_filter($all, function($f) {
            return !preg_match('/\.(php|env|htaccess|ini)$/i', $f) && is_file(__DIR__ . '/' . $f);
        });
        $files = array_map(function($f) {
            $filePath = __DIR__ . '/' . $f;
            return [
                'name' => $f,
                'size' => filesize($filePath),
                'date' => date('Y-m-d H:i', filemtime($filePath))
            ];
        }, array_values($files));
        usort($files, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        
        echo json_encode([
            'status' => 'success',
            'message' => $filename . ' deleted successfully',
            'files' => $files
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Could not delete file']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Advanced File Manager</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #4361ee;
      --primary-dark: #3a56d4;
      --secondary: #7209b7;
      --success: #4cc9f0;
      --danger: #f72585;
      --dark: #2b2d42;
      --light: #f8f9fa;
      --gray: #8d99ae;
      --border: #e0e0e0;
      --card-shadow: 0 4px 12px rgba(0,0,0,0.08);
      --transition: all 0.3s ease;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
      color: var(--dark);
      min-height: 100vh;
      padding-bottom: 20px;
    }
    
    .header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
      color: white;
      padding: 1.5rem 0;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      position: relative;
      overflow: hidden;
    }
    
    .header::before {
      content: "";
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
      transform: rotate(30deg);
    }
    
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }
    
    .header-content {
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: relative;
      z-index: 2;
    }
    
    .logo {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 1.8rem;
      font-weight: 700;
    }
    
    .logo i {
      color: var(--success);
      background: rgba(255,255,255,0.2);
      border-radius: 50%;
      padding: 10px;
    }
    
    .btn {
      padding: 0.75rem 1.5rem;
      border-radius: 8px;
      border: none;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);
    }
    
    .btn-primary {
      background: var(--primary);
      color: white;
    }
    
    .btn-primary:hover {
      background: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: 0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08);
    }
    
    .btn-success {
      background: var(--success);
      color: white;
    }
    
    .btn-success:hover {
      background: #3ab5d8;
    }
    
    .btn-danger {
      background: var(--danger);
      color: white;
    }
    
    .btn-danger:hover {
      background: #e1156f;
    }
    
    .main-content {
      margin-top: 2rem;
    }
    
    .card {
      background: white;
      border-radius: 16px;
      box-shadow: var(--card-shadow);
      overflow: hidden;
      margin-bottom: 2rem;
      transition: var(--transition);
    }
    
    .card:hover {
      box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    
    .card-header {
      padding: 1.5rem;
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .card-title {
      font-size: 1.5rem;
      font-weight: 600;
      color: var(--dark);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .card-body {
      padding: 1.5rem;
    }
    
    .file-table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .file-table th {
      background: #f8f9fc;
      padding: 1rem;
      text-align: left;
      font-weight: 600;
      color: var(--gray);
      border-bottom: 2px solid var(--border);
    }
    
    .file-table td {
      padding: 1rem;
      border-bottom: 1px solid var(--border);
    }
    
    .file-table tr:last-child td {
      border-bottom: none;
    }
    
    .file-table tr:hover td {
      background: #f9fafd;
    }
    
    .file-icon {
      color: var(--primary);
      margin-right: 10px;
      font-size: 1.2rem;
    }
    
    .file-actions {
      display: flex;
      gap: 8px;
    }
    
    .btn-sm {
      padding: 0.4rem 0.8rem;
      border-radius: 6px;
      font-size: 0.85rem;
    }
    
    .empty-state {
      text-align: center;
      padding: 3rem;
      color: var(--gray);
    }
    
    .empty-state i {
      font-size: 4rem;
      margin-bottom: 1rem;
      color: #e0e0e0;
    }
    
    /* Floating upload window styles */
    #uploadAlert {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 500px;
      max-width: 90%;
      background: white;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.15);
      z-index: 1050;
      display: none;
      flex-direction: column;
      max-height: 80vh;
      overflow: hidden;
    }
    
    #uploadAlert.visible {
      display: flex;
    }
    
    #uploadAlert.minimized {
      top: auto;
      bottom: 20px;
      left: auto;
      right: 20px;
      transform: none;
      width: 300px;
      height: 40px;
      max-height: 40px;
    }
    
    .upload-header {
      padding: 1.25rem;
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
      color: white;
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: move;
    }
    
    .minimized .upload-header {
      padding: 0.5rem 1rem;
    }
    
    .upload-title {
      font-weight: 600;
      font-size: 1.2rem;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .minimized .upload-title {
      font-size: 0.9rem;
    }
    
    .upload-badge {
      background: rgba(255,255,255,0.3);
      border-radius: 20px;
      padding: 0.25rem 0.75rem;
      font-size: 0.9rem;
    }
    
    .minimized .upload-badge {
      font-size: 0.8rem;
      padding: 0.1rem 0.5rem;
    }
    
    .window-controls {
      display: flex;
      gap: 10px;
    }
    
    .window-btn {
      background: rgba(255,255,255,0.2);
      border: none;
      color: white;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: var(--transition);
    }
    
    .minimized .window-btn {
      width: 26px;
      height: 26px;
      font-size: 0.9rem;
    }
    
    .window-btn:hover {
      background: rgba(255,255,255,0.3);
      transform: scale(1.1);
    }
    
    .upload-content {
      padding: 1.5rem;
      flex-grow: 1;
      overflow-y: auto;
    }
    
    .minimized .upload-content {
      display: none;
    }
    
    .upload-item {
      padding: 1rem;
      border-radius: 12px;
      background: #f9f9ff;
      margin-bottom: 15px;
      border: 1px solid #eef0f7;
    }
    
    .upload-item-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }
    
    .file-name {
      font-weight: 500;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      max-width: 220px;
    }
    
    .file-size {
      color: var(--gray);
      font-size: 0.85rem;
    }
    
    .progress-container {
      height: 12px;
      background: #edf2f7;
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: 8px;
    }
    
    .progress-bar {
      height: 100%;
      border-radius: 10px;
      background: linear-gradient(90deg, var(--success), var(--primary));
      transition: width 0.3s ease;
      position: relative;
    }
    
    .progress-text {
      font-size: 0.85rem;
      color: var(--gray);
      display: flex;
      justify-content: space-between;
    }
    
    .status-badge {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 500;
    }
    
    .status-queued {
      background: rgba(108, 117, 125, 0.1);
      color: var(--gray);
    }
    
    .status-uploading {
      background: rgba(13, 110, 253, 0.1);
      color: var(--primary);
    }
    
    .status-completed {
      background: rgba(25, 135, 84, 0.1);
      color: #198754;
    }
    
    .status-canceled {
      background: rgba(220, 53, 69, 0.1);
      color: #dc3545;
    }
    
    .action-btn {
      background: none;
      border: none;
      color: var(--gray);
      cursor: pointer;
      transition: var(--transition);
      font-size: 1.1rem;
    }
    
    .action-btn:hover {
      color: var(--danger);
      transform: scale(1.1);
    }
    
    .drag-handle {
      cursor: move;
    }
    
    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1040;
      display: none;
    }
    
    .overlay.visible {
      display: block;
    }
    
    @media (max-width: 768px) {
      .header-content {
        flex-direction: column;
        gap: 15px;
      }
      
      .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
      }
    }
  </style>
</head>
<body>
  <!-- Header -->
  <header class="header">
    <div class="container">
      <div class="header-content">
        <div class="logo">
          <i class="fas fa-folder-open"></i>
          <span>CloudFile Manager</span>
        </div>
        <div>
          <button id="uploadBtn" class="btn btn-success">
            <i class="fas fa-cloud-upload-alt"></i>
            Upload Files
          </button>
        </div>
      </div>
    </div>
  </header>
  
  <!-- Main Content -->
  <div class="container">
    <div class="main-content">
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">
            <i class="fas fa-file-alt"></i>
            Your Files
          </h2>
          <div class="file-info">
            <span id="fileCount">0 files</span>
            <span id="totalSize">(0 KB)</span>
          </div>
        </div>
        <div class="card-body">
          <table class="file-table">
            <thead>
              <tr>
                <th style="width: 50%;">File Name</th>
                <th>Size</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="fileList">
              <tr>
                <td colspan="4" class="empty-state">
                  <i class="fas fa-folder-open"></i>
                  <h3>No files found</h3>
                  <p>Upload your first file to get started</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Overlay for modal -->
  <div id="overlay" class="overlay"></div>
  
  <!-- Upload Modal -->
  <div id="uploadAlert" class="upload-alert">
    <div class="upload-header drag-handle">
      <div class="upload-title">
        <i class="fas fa-cloud-upload-alt"></i>
        <span>Upload Files</span>
        <span id="uploadCountBadge" class="upload-badge">0</span>
      </div>
      <div class="window-controls">
        <button id="minimizeBtn" class="window-btn">
          <i class="fas fa-minus"></i>
        </button>
        <button id="closeBtn" class="window-btn">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>
    <div class="upload-content" id="uploadContent">
      <div class="mb-4">
        <label for="fileInput" class="btn btn-primary w-100">
          <i class="fas fa-plus"></i> Select Files
        </label>
        <input type="file" id="fileInput" multiple style="display: none;">
      </div>
      
      <div id="selectedFiles" class="mb-4"></div>
      
      <div id="uploadProgress" class="mb-4"></div>
      
      <button id="startUploadBtn" class="btn btn-success w-100" disabled>
        <i class="fas fa-upload"></i> Start Upload
      </button>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";
    const uploadManager = {
      queue: [],
      current: null,
      minimized: false,
      activeUploads: 0,
      position: { x: 0, y: 0 },
      selectedFiles: []
    };
    
    // DOM Elements
    const uploadAlert = document.getElementById('uploadAlert');
    const overlay = document.getElementById('overlay');
    const uploadContent = document.getElementById('uploadContent');
    const uploadCountBadge = document.getElementById('uploadCountBadge');
    const selectedFilesContainer = document.getElementById('selectedFiles');
    const uploadProgressContainer = document.getElementById('uploadProgress');
    const startUploadBtn = document.getElementById('startUploadBtn');
    
    // Format file size
    function formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Refresh file list
    function refreshFileList(files) {
      const tbody = document.getElementById('fileList');
      const fileCount = document.getElementById('fileCount');
      const totalSize = document.getElementById('totalSize');
      
      if (!files || files.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="4" class="empty-state">
              <i class="fas fa-folder-open"></i>
              <h3>No files found</h3>
              <p>Upload your first file to get started</p>
            </td>
          </tr>
        `;
        fileCount.textContent = '0 files';
        totalSize.textContent = '(0 KB)';
        return;
      }
      
      let totalBytes = 0;
      let html = '';
      
      files.forEach((f, i) => {
        totalBytes += f.size;
        const size = formatFileSize(f.size);
        
        html += `
          <tr>
            <td>
              <i class="fas fa-file file-icon"></i>
              ${f.name}
            </td>
            <td>${size}</td>
            <td>${f.date}</td>
            <td class="file-actions">
              <a href="?download=${encodeURIComponent(f.name)}" class="btn btn-sm btn-success">
                <i class="fas fa-download"></i>
              </a>
              <button class="btn btn-sm btn-danger delete-btn" data-filename="${f.name}">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>
        `;
      });
      
      tbody.innerHTML = html;
      fileCount.textContent = `${files.length} ${files.length === 1 ? 'file' : 'files'}`;
      totalSize.textContent = `(${formatFileSize(totalBytes)})`;
    }
    
    // Show selected files
    function showSelectedFiles() {
      if (uploadManager.selectedFiles.length === 0) {
        selectedFilesContainer.innerHTML = '<div class="text-center p-2 text-muted">No files selected</div>';
        startUploadBtn.disabled = true;
        return;
      }
      
      let html = '<div class="mb-2"><strong>Selected Files:</strong></div><ul class="list-group">';
      
      uploadManager.selectedFiles.forEach(file => {
        html += `
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <i class="fas fa-file text-primary me-2"></i>
              <span class="file-name">${file.name}</span>
            </div>
            <div>
              <span class="badge bg-secondary">${formatFileSize(file.size)}</span>
              <button class="btn btn-sm btn-danger remove-file-btn" data-name="${file.name}">
                <i class="fas fa-times"></i>
              </button>
            </div>
          </li>
        `;
      });
      
      html += '</ul>';
      selectedFilesContainer.innerHTML = html;
      startUploadBtn.disabled = false;
      
      // Add event listeners for remove buttons
      document.querySelectorAll('.remove-file-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const fileName = this.getAttribute('data-name');
          uploadManager.selectedFiles = uploadManager.selectedFiles.filter(f => f.name !== fileName);
          showSelectedFiles();
        });
      });
    }
    
    // Show upload progress
    function showUploadProgress() {
      if (uploadManager.queue.length === 0 && uploadManager.current === null) {
        uploadProgressContainer.innerHTML = '<div class="text-center p-2 text-muted">No active uploads</div>';
        return;
      }
      
      let html = '<div class="mb-2"><strong>Upload Progress:</strong></div>';
      
      // Current upload
      if (uploadManager.current) {
        const item = uploadManager.current;
        const progress = item.progress || 0;
        const status = item.status || 'uploading';
        const statusClass = `status-${status}`;
        const statusText = item.statusText || 'Uploading...';
        
        html += `
          <div class="upload-item">
            <div class="upload-item-header">
              <div>
                <div class="file-name">${item.file.name}</div>
                <div class="file-size">${formatFileSize(item.file.size)}</div>
              </div>
              <button class="action-btn cancel-btn" data-id="${item.id}">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <div class="progress-container">
              <div class="progress-bar" style="width: ${progress}%"></div>
            </div>
            <div class="progress-text">
              <span>${progress}%</span>
              <span class="status-badge ${statusClass}">${statusText}</span>
            </div>
          </div>
        `;
      }
      
      // Queued uploads
      uploadManager.queue.forEach(item => {
        html += `
          <div class="upload-item">
            <div class="upload-item-header">
              <div>
                <div class="file-name">${item.file.name}</div>
                <div class="file-size">${formatFileSize(item.file.size)}</div>
              </div>
              <button class="action-btn cancel-btn" data-id="${item.id}">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <div class="progress-container">
              <div class="progress-bar" style="width: 0%"></div>
            </div>
            <div class="progress-text">
              <span>0%</span>
              <span class="status-badge status-queued">Queued</span>
            </div>
          </div>
        `;
      });
      
      uploadProgressContainer.innerHTML = html;
      
      // Add event handlers for cancel buttons
      document.querySelectorAll('.cancel-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const id = this.getAttribute('data-id');
          cancelUpload(id);
        });
      });
    }
    
    // Cancel an upload by ID
    function cancelUpload(id) {
      // If it's the current upload
      if (uploadManager.current && uploadManager.current.id === id) {
        uploadManager.current.xhr.abort();
        uploadManager.current.status = 'canceled';
        uploadManager.current.statusText = 'Upload canceled';
        showUploadProgress();
        setTimeout(() => {
          uploadManager.current = null;
          processQueue();
        }, 1500);
        return;
      }
      
      // If it's in the queue
      const index = uploadManager.queue.findIndex(item => item.id === id);
      if (index !== -1) {
        uploadManager.queue.splice(index, 1);
        showUploadProgress();
      }
    }
    
    // Process the upload queue
    function processQueue() {
      // If there's an upload in progress, do nothing
      if (uploadManager.current) return;
      
      // If queue is empty, we're done
      if (uploadManager.queue.length === 0) {
        showUploadProgress();
        return;
      }
      
      // Get next item from queue
      const nextItem = uploadManager.queue.shift();
      uploadManager.current = nextItem;
      nextItem.status = 'uploading';
      nextItem.statusText = 'Uploading...';
      
      // Update UI
      showUploadProgress();
      
      // Start the upload
      uploadFile(nextItem);
    }
    
    // Handle file upload
    function uploadFile(uploadItem) {
      const formData = new FormData();
      formData.append('uploadFile', uploadItem.file);
      formData.append('csrf_token', csrfToken);
      
      const xhr = new XMLHttpRequest();
      xhr.open('POST', '', true);
      
      // Store xhr reference for possible cancellation
      uploadItem.xhr = xhr;
      
      // Handle upload progress
      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          const percent = Math.round((e.loaded / e.total) * 100);
          uploadItem.progress = percent;
          uploadItem.statusText = `Uploading... ${percent}%`;
          showUploadProgress();
        }
      });
      
      // Handle upload completion
      xhr.onload = function() {
        try {
          const res = JSON.parse(xhr.responseText);
          if (res.status === 'success') {
            uploadItem.status = 'completed';
            uploadItem.statusText = 'Upload completed';
            uploadItem.progress = 100;
            refreshFileList(res.files);
          } else {
            uploadItem.status = 'error';
            uploadItem.statusText = res.message || 'Upload failed';
          }
        } catch (e) {
          uploadItem.status = 'error';
          uploadItem.statusText = 'Upload failed';
        }
        
        // Update UI and clear current upload
        showUploadProgress();
        setTimeout(() => {
          uploadManager.current = null;
          processQueue();
        }, 1500);
      };
      
      // Handle upload errors
      xhr.onerror = function() {
        uploadItem.status = 'error';
        uploadItem.statusText = 'Upload failed';
        showUploadProgress();
        setTimeout(() => {
          uploadManager.current = null;
          processQueue();
        }, 1500);
      };
      
      // Handle upload abort
      xhr.onabort = function() {
        uploadItem.status = 'canceled';
        uploadItem.statusText = 'Upload canceled';
        showUploadProgress();
      };
      
      // Send the request
      xhr.send(formData);
    }
    
    // Initialize modal drag functionality
    function initDrag() {
      const header = document.querySelector('.drag-handle');
      let isDragging = false;
      let startX, startY, initialX, initialY;
      
      header.addEventListener('mousedown', startDrag);
      document.addEventListener('mouseup', stopDrag);
      document.addEventListener('mousemove', drag);
      
      function startDrag(e) {
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        initialX = uploadManager.position.x;
        initialY = uploadManager.position.y;
        uploadAlert.style.cursor = 'grabbing';
      }
      
      function stopDrag() {
        isDragging = false;
        uploadAlert.style.cursor = 'default';
      }
      
      function drag(e) {
        if (!isDragging) return;
        e.preventDefault();
        
        const dx = e.clientX - startX;
        const dy = e.clientY - startY;
        
        // Calculate new position
        const newX = initialX + dx;
        const newY = initialY + dy;
        
        // Apply boundaries
        const maxX = window.innerWidth - uploadAlert.offsetWidth;
        const maxY = window.innerHeight - uploadAlert.offsetHeight;
        
        uploadManager.position.x = Math.max(0, Math.min(maxX, newX));
        uploadManager.position.y = Math.max(0, Math.min(maxY, newY));
        
        uploadAlert.style.left = uploadManager.position.x + 'px';
        uploadAlert.style.top = uploadManager.position.y + 'px';
      }
    }
    
    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
      // Load files on page load
      fetch('?init=1')
        .then(response => response.json())
        .then(data => {
          if (data.files) refreshFileList(data.files);
        })
        .catch(error => {
          console.error('Error loading files:', error);
        });
      
      // Handle file deletion
      document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-btn')) {
          const btn = e.target.closest('.delete-btn');
          const filename = btn.getAttribute('data-filename');
          
          Swal.fire({
            title: 'Delete File?',
            text: `Are you sure you want to delete ${filename}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
          }).then((result) => {
            if (result.isConfirmed) {
              const formData = new FormData();
              formData.append('action', 'delete');
              formData.append('filename', filename);
              formData.append('csrf_token', csrfToken);
              
              fetch('', {
                method: 'POST',
                body: formData
              })
              .then(response => response.json())
              .then(data => {
                if (data.status === 'success') {
                  refreshFileList(data.files);
                  Swal.fire('Deleted!', data.message, 'success');
                } else {
                  Swal.fire('Error!', data.message, 'error');
                }
              })
              .catch(error => {
                console.error('Error deleting file:', error);
                Swal.fire('Error!', 'Could not delete file', 'error');
              });
            }
          });
        }
      });
      
      // Initialize drag functionality
      initDrag();
      
      // File input handling
      document.getElementById('fileInput').addEventListener('change', function(e) {
        const files = Array.from(e.target.files);
        if (files.length > 0) {
          files.forEach(file => {
            uploadManager.selectedFiles.push({
              name: file.name,
              size: file.size,
              file: file
            });
          });
          showSelectedFiles();
        }
      });
      
      // Start upload button
      startUploadBtn.addEventListener('click', function() {
        if (uploadManager.selectedFiles.length === 0) return;
        
        // Add files to upload queue
        uploadManager.selectedFiles.forEach(file => {
          const id = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
          uploadManager.queue.push({
            id: id,
            file: file.file,
            progress: 0,
            status: 'queued',
            statusText: 'Queued'
          });
        });
        
        // Clear selected files
        uploadManager.selectedFiles = [];
        showSelectedFiles();
        
        // Update badge
        uploadCountBadge.textContent = uploadManager.queue.length;
        
        // Show progress
        showUploadProgress();
        
        // Start processing queue
        processQueue();
      });
      
      // Upload button click - show modal
      document.getElementById('uploadBtn').addEventListener('click', function() {
        // Reset modal
        uploadManager.selectedFiles = [];
        showSelectedFiles();
        showUploadProgress();
        uploadCountBadge.textContent = '0';
        
        // Position modal in center
        uploadManager.position = {
          x: (window.innerWidth - uploadAlert.offsetWidth) / 2,
          y: (window.innerHeight - uploadAlert.offsetHeight) / 2
        };
        uploadAlert.style.left = uploadManager.position.x + 'px';
        uploadAlert.style.top = uploadManager.position.y + 'px';
        
        // Show modal and overlay
        uploadAlert.classList.add('visible');
        overlay.classList.add('visible');
        uploadAlert.classList.remove('minimized');
      });
      
      // Minimize button
      document.getElementById('minimizeBtn').addEventListener('click', function() {
        uploadAlert.classList.toggle('minimized');
      });
      
      // Close button
      document.getElementById('closeBtn').addEventListener('click', function() {
        uploadAlert.classList.remove('visible');
        overlay.classList.remove('visible');
      });
      
      // Close modal when clicking on overlay
      overlay.addEventListener('click', function() {
        uploadAlert.classList.remove('visible');
        overlay.classList.remove('visible');
      });
    });
  </script>
</body>
</html>