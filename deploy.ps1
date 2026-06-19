# Deploy SAM to Hostinger via FTP
# Usage: .\deploy.ps1 index.html    (deploy single file)
#        .\deploy.ps1               (deploy all files)

$creds = @{}
Get-Content "$PSScriptRoot\.ftp-credentials" | ForEach-Object {
    if ($_ -match "^(\w+)=(.+)$") { $creds[$Matches[1]] = $Matches[2] }
}
$ftpHost   = $creds["FTP_HOST"]
$ftpUser   = $creds["FTP_USER"]
$ftpPass   = $creds["FTP_PASS"]
$ftpPort   = $creds["FTP_PORT"]
$remotePath = $creds["FTP_REMOTE_PATH"]
$local     = $PSScriptRoot

$exclude = @(".git", ".ftp-credentials", "deploy.ps1", "CLAUDE.md", "README.md")

function Should-Exclude($path) {
    foreach ($ex in $exclude) {
        if ((Split-Path $path -Leaf) -like $ex) { return $true }
        if ($path -like "*\$ex\*") { return $true }
    }
    return $false
}

function Deploy-File($rel) {
    $localPath  = Join-Path $local $rel
    $relForward = $rel -replace "\\", "/"
    $url = "ftp://${ftpHost}:${ftpPort}/${remotePath}/${relForward}"
    Write-Host "Uploading $rel ..." -ForegroundColor Cyan
    $out = & curl.exe --ftp-create-dirs -u "${ftpUser}:${ftpPass}" -T $localPath $url 2>&1
    if ($LASTEXITCODE -eq 0) { Write-Host "  OK" -ForegroundColor Green }
    else { Write-Host "  FAILED: $out" -ForegroundColor Red }
}

# Single file mode
if ($args.Count -gt 0) {
    Deploy-File $args[0]
    exit
}

# Full deploy
Write-Host "Deploying all SAM files to $ftpHost/$remotePath ..." -ForegroundColor Yellow
$files = Get-ChildItem -Path $local -Recurse -File | Where-Object { -not (Should-Exclude $_.FullName) }
$i = 0
foreach ($file in $files) {
    $i++
    $rel = $file.FullName.Substring($local.Length + 1)
    Write-Progress -Activity "Deploying" -Status "$i of $($files.Count): $rel" -PercentComplete (($i / $files.Count) * 100)
    Deploy-File $rel
}
Write-Host "Deploy complete." -ForegroundColor Green
