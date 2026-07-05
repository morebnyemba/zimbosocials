<?php
/**
 * Zimbo Socials - One-Click cPanel Auto-Extractor
 * 
 * Instructions:
 * 1. Upload `release.zip` and `deploy.php` to your `public_html` folder.
 * 2. Visit `https://yourdomain.com/deploy.php`
 */

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$zipFile = __DIR__ . '/release.zip';
$tempDir = __DIR__ . '/temp_extract';
$appTarget = realpath(__DIR__ . '/..') . '/my-app';
$publicTarget = __DIR__;

if (!file_exists($zipFile)) {
    die("Error: release.zip not found in the current directory. Please upload it alongside this script.");
}

if (!class_exists('ZipArchive')) {
    die("Error: PHP ZipArchive extension is missing. Please enable it in cPanel (Select PHP Version).");
}

echo "<h2>🚀 Zimbo Socials Auto-Extractor</h2>";
echo "<p>Starting deployment process...</p>";

// 1. Extract ZIP
echo "<p>Extracting release.zip...</p>";
$zip = new ZipArchive;
$openResult = $zip->open($zipFile);
if ($openResult === TRUE) {
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    $entryCount = $zip->numFiles;
    $extractOk = $zip->extractTo($tempDir);
    $zipStatus = $zip->status;
    $zip->close();

    if (!$extractOk || $zipStatus !== ZipArchive::ER_OK) {
        die("❌ extractTo() reported a failure (zip status: {$zipStatus}). The archive likely didn't upload completely — re-upload release.zip (in binary/FTP mode, not ASCII) and try again. Archive claimed {$entryCount} entries.");
    }
    echo "<p>✅ Extracted successfully ({$entryCount} entries).</p>";
} else {
    die("❌ Failed to open release.zip (ZipArchive error code: {$openResult}). The file is likely corrupted or incomplete — re-upload it and try again.");
}

// 2. Move my-app folder to /home/user/my-app
echo "<p>Moving application core files to {$appTarget}...</p>";
$sourceApp = $tempDir . '/my-app';
if (!is_dir($sourceApp)) {
    $topLevel = is_dir($tempDir) ? array_diff(scandir($tempDir), ['.', '..']) : [];
    $freeSpace = @disk_free_space(__DIR__);
    $freeMb = $freeSpace !== false ? round($freeSpace / 1024 / 1024, 1) : 'unknown';
    $found = $topLevel ? implode(', ', $topLevel) : '(nothing — extraction directory is empty)';
    die("❌ my-app directory not found in archive. Extracted top-level contents instead: {$found}. Free disk space on this account: {$freeMb} MB. "
        . "This usually means the extraction was cut short by a disk quota or inode limit — check your cPanel disk usage, free up space, and re-run the deploy. "
        . "If the account has plenty of space, the uploaded release.zip is likely corrupted/incomplete — re-upload it and try again.");
}

if (is_dir($appTarget)) {
    // If my-app already exists, we could delete it or merge it. For safety, let's merge/overwrite.
    // A simple rename won't work if target exists, so we copy then delete.
    echo "<p>Warning: my-app folder already exists, merging new files...</p>";
    // Copy recursive
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceApp, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $targetPath = $appTarget . DIRECTORY_SEPARATOR . $iterator->getSubPathname();
        if ($item->isDir()) {
            if (!is_dir($targetPath)) mkdir($targetPath, 0755, true);
        } else {
            copy($item, $targetPath);
        }
    }
} else {
    rename($sourceApp, $appTarget);
}
echo "<p>✅ Core files secured.</p>";

// 3. Move public_html files to current directory
echo "<p>Moving public assets...</p>";
$sourcePublic = $tempDir . '/public_html';
if (is_dir($sourcePublic)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourcePublic, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $targetPath = $publicTarget . DIRECTORY_SEPARATOR . $iterator->getSubPathname();
        if ($item->isDir()) {
            if (!is_dir($targetPath)) mkdir($targetPath, 0755, true);
        } else {
            // Don't overwrite deploy.php or release.zip just in case, though they shouldn't be in the archive
            if (basename($item) !== 'deploy.php' && basename($item) !== 'release.zip') {
                copy($item, $targetPath);
            }
        }
    }
    echo "<p>✅ Public assets deployed.</p>";
} else {
    die("❌ public_html directory not found in archive.");
}

// 4. Cleanup temp dir
echo "<p>Cleaning up temporary files...</p>";
$deleteDir = function($dir) use (&$deleteDir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? $deleteDir("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
};
$deleteDir($tempDir);

// 5. Delete release.zip
unlink($zipFile);

// 6. Redirect to cpanel-installer.php
echo "<p>✅ Deployment complete! Redirecting to setup wizard...</p>";
echo "<script>
    setTimeout(function() {
        window.location.href = 'cpanel-installer.php';
    }, 2000);
</script>";

// Self-destruct deploy.php
unlink(__FILE__);
exit;
