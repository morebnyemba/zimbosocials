<#
.SYNOPSIS
    Builds an upload-ready cPanel deployment package for Zimbo Socials.

.DESCRIPTION
    Produces two archives under dist/ that match the layout expected by
    public/cpanel-installer.php (app and public_html as siblings under the cPanel home):

      dist/my-app.zip      -> extract into  /home/<user>/my-app
      dist/public_html.zip -> extract into  /home/<user>/public_html

    Steps:
      1. Compiles front-end assets (npm run build -> public/build).
      2. Installs production-only Composer dependencies (--no-dev, optimized autoloader).
      3. Stages the app (excluding dev/local-only files) and the public folder.
      4. Rewrites public_html/index.php so vendor/bootstrap resolve to ../my-app/...
      5. Zips both staging folders.
      6. Restores dev Composer dependencies so local tests keep working.

.PARAMETER SkipAssets
    Skip "npm run build" (reuse the existing public/build output).

.PARAMETER SkipComposer
    Skip the production composer install / restore (ship the current vendor as-is).
#>
param(
    [switch]$SkipAssets,
    [switch]$SkipComposer
)

$ErrorActionPreference = 'Stop'

$root  = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$dist  = Join-Path $root 'dist'
$stage = Join-Path $dist 'staging'
$appStage    = Join-Path $stage 'my-app'
$publicStage = Join-Path $stage 'public_html'

Write-Host "==> Project root: $root"

# ── 1. Front-end assets ───────────────────────────────────────────────────────
if (-not $SkipAssets) {
    Write-Host "==> Building front-end assets (npm run build)..."
    & npm run build
    if ($LASTEXITCODE -ne 0) { throw "npm run build failed." }
} else {
    Write-Host "==> Skipping asset build (--SkipAssets)."
}

if (-not (Test-Path (Join-Path $root 'public/build/manifest.json'))) {
    throw "public/build/manifest.json missing - asset build did not produce a manifest."
}

# ── 2. Production Composer dependencies ───────────────────────────────────────
if (-not $SkipComposer) {
    Write-Host "==> Installing production Composer dependencies (--no-dev)..."
    & composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
    if ($LASTEXITCODE -ne 0) { throw "composer install --no-dev failed." }
} else {
    Write-Host "==> Skipping composer production install (--SkipComposer)."
}

# ── 3. Stage files ────────────────────────────────────────────────────────────
Write-Host "==> Staging files..."
if (Test-Path $dist) { Remove-Item $dist -Recurse -Force }
New-Item -ItemType Directory -Path $appStage, $publicStage -Force | Out-Null

# Application -> my-app (exclude local-only, dev, and the public/ webroot)
$excludeDirs = @(
    (Join-Path $root '.git'),
    (Join-Path $root 'node_modules'),
    (Join-Path $root 'dist'),
    (Join-Path $root 'public'),
    (Join-Path $root 'tests'),
    (Join-Path $root '.github'),
    (Join-Path $root '.vscode'),
    (Join-Path $root '.idea'),
    (Join-Path $root '.zed'),
    (Join-Path $root '.nova')
)
# robocopy: /E recurse incl. empty, /XD exclude dirs (full paths), /XF exclude files
& robocopy $root $appStage /E /XD $excludeDirs /XF '.env' '*.zip' '.phpunit.result.cache' 'auth.json' /NFL /NDL /NJH /NJS /NP | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy (app) failed with code $LASTEXITCODE." }

# Never ship secrets or local logs; keep an empty log file so the dir exists.
$envFile = Join-Path $appStage '.env'
if (Test-Path $envFile) { Remove-Item $envFile -Force }
Get-ChildItem -Path (Join-Path $appStage 'storage/logs') -Filter '*.log' -ErrorAction SilentlyContinue | Remove-Item -Force

# Public folder -> public_html (exclude prior build zips)
& robocopy (Join-Path $root 'public') $publicStage /E /XF '*.zip' /NFL /NDL /NJH /NJS /NP | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy (public) failed with code $LASTEXITCODE." }

# ── 4. cPanel-correct index.php (vendor/bootstrap live in ../my-app) ──────────
Write-Host "==> Writing cPanel public_html/index.php..."
$indexContent = @'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// On cPanel the Laravel app lives in ../my-app while this file is in public_html.
$appBase = __DIR__.'/../my-app';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $appBase.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $appBase.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $appBase.'/bootstrap/app.php';

// public_html is the web root on cPanel.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
'@
Set-Content -Path (Join-Path $publicStage 'index.php') -Value $indexContent -Encoding utf8 -NoNewline

# ── 5. Zip ────────────────────────────────────────────────────────────────────
Write-Host "==> Creating unified release.zip archive..."
Add-Type -AssemblyName System.IO.Compression.FileSystem
$level = [System.IO.Compression.CompressionLevel]::Optimal
$releaseZip = Join-Path $dist 'release.zip'
[System.IO.Compression.ZipFile]::CreateFromDirectory($stage, $releaseZip, $level, $false)

# ── 6. Copy Auto-Extractor ────────────────────────────────────────────────────
Write-Host "==> Copying deploy.php..."
Copy-Item -Path (Join-Path $root 'scripts\deploy.php') -Destination (Join-Path $dist 'deploy.php')

# ── 7. Restore dev dependencies ───────────────────────────────────────────────
if (-not $SkipComposer) {
    Write-Host "==> Restoring dev Composer dependencies..."
    & composer install --no-interaction --prefer-dist | Out-Null
    if ($LASTEXITCODE -ne 0) { Write-Warning "composer install (restore) failed - run it manually to restore dev deps." }
}

Remove-Item $stage -Recurse -Force

$releaseSize = [math]::Round((Get-Item $releaseZip).Length / 1MB, 1)
Write-Host ""
Write-Host "==> Done."
Write-Host "    dist/release.zip ($releaseSize MB)"
Write-Host "    dist/deploy.php"
Write-Host ""
Write-Host "    Upload BOTH files to your /home/<user>/public_html directory."
Write-Host "    Then visit https://<domain>/deploy.php to auto-extract and configure!"
