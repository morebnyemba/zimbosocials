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

$deleteDir = function ($dir) use (&$deleteDir) {
    if (!is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        is_dir("$dir/$file") ? $deleteDir("$dir/$file") : unlink("$dir/$file");
    }
    rmdir($dir);
};

// A prior run that died before reaching cleanup leaves stale files behind;
// starting from a clean directory every time keeps failures self-contained
// instead of accumulating debris across retries that confuses diagnostics.
if (is_dir($tempDir)) {
    echo "<p>Clearing leftover files from a previous attempt...</p>";
    $deleteDir($tempDir);
}

// 1. Extract ZIP
//
// Deliberately not using ZipArchive::extractTo() here. Some upload paths
// (Windows tools re-zipping the archive, certain FTP clients) can produce
// entries with backslash-separated names instead of the ZIP-spec-mandated
// forward slash. extractTo() then creates one flat file per entry with a
// literal backslash in its filename instead of the intended directory
// tree. Extracting entries manually and normalizing both slash styles
// makes this immune to whichever tool mangled the archive on its way here.
echo "<p>Extracting release.zip...</p>";
$zip = new ZipArchive;
$openResult = $zip->open($zipFile);
if ($openResult === TRUE) {
    mkdir($tempDir, 0755, true);
    $entryCount = $zip->numFiles;

    for ($i = 0; $i < $entryCount; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name === false) {
            continue;
        }

        $normalized = str_replace('\\', '/', $name);
        $targetPath = $tempDir . '/' . ltrim($normalized, '/');

        if (substr($normalized, -1) === '/') {
            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0755, true);
            }
            continue;
        }

        $parentDir = dirname($targetPath);
        if (!is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }

        $contents = $zip->getFromIndex($i);
        if ($contents === false) {
            $zip->close();
            die("❌ Failed to read entry '{$name}' from release.zip — the archive is likely corrupted or incomplete. Re-upload it and try again.");
        }

        file_put_contents($targetPath, $contents);
    }

    $zip->close();
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
