# Bump the deployed app version (minor) baked into the footer span of index.html.
# Run this at CHECKPOINT time (before deploy/commit), not on every deploy.
# Usage: .\bump-version.ps1            (bump minor: v1.4 -> v1.5)
#        .\bump-version.ps1 -Major     (bump major, reset minor: v1.5 -> v2.0)

param([switch]$Major)

$indexPath = Join-Path $PSScriptRoot "index.html"
$content   = [System.IO.File]::ReadAllText($indexPath, [System.Text.Encoding]::UTF8)

if ($content -match 'id="app-version">v(\d+)\.(\d+)<') {
    $vMajor = [int]$Matches[1]
    $vMinor = [int]$Matches[2]
    if ($Major) { $vMajor += 1; $vMinor = 0 }
    else        { $vMinor += 1 }
    $newVer  = "v$vMajor.$vMinor"
    $content = $content -replace '(?<=id="app-version">)[^<]*', $newVer
    [System.IO.File]::WriteAllText($indexPath, $content, [System.Text.Encoding]::UTF8)
    Write-Host "Version bumped to $newVer" -ForegroundColor Green
} else {
    Write-Host "Could not find app-version span in index.html" -ForegroundColor Red
    exit 1
}
