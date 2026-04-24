# heratio-scan.ps1 — upload a scanned file to Heratio via the Scan API.
#
# Designed for VueScan / NAPS2 "After save" hooks on Windows.
#
# Environment variables (or config file at $env:USERPROFILE\.heratio-scan.conf):
#   HERATIO_URL           e.g. https://heratio.theahg.co.za
#   HERATIO_API_KEY       API key with scan:write scope
#   HERATIO_PARENT_ID     existing IO id (or use HERATIO_PARENT_SLUG)
#   HERATIO_PARENT_SLUG   URL slug of the parent IO
#   HERATIO_SECTOR        archive (default) | library | gallery | museum
#   HERATIO_STANDARD      isadg (default) | marc21 | lido | spectrum | ...
#
# Usage:
#   .\heratio-scan.ps1 -File "C:\scans\page001.tiff" `
#                      [-Identifier "ARC-2026-0001"] `
#                      [-Title "Letter from Smith"] `
#                      [-Sidecar "C:\scans\page001.xml"]
#
# Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
# Licensed under the GNU AGPL v3.

param(
    [Parameter(Mandatory = $true)] [string] $File,
    [string] $Identifier = '',
    [string] $Title = '',
    [string] $Sidecar = ''
)

# --- Load config ---
$configFile = Join-Path $env:USERPROFILE '.heratio-scan.conf'
if (Test-Path $configFile) {
    Get-Content $configFile | ForEach-Object {
        if ($_ -match '^\s*([A-Z_]+)\s*=\s*(.+?)\s*$') {
            [Environment]::SetEnvironmentVariable($matches[1], $matches[2].Trim('"'), 'Process')
        }
    }
}

$Url = $env:HERATIO_URL
$Key = $env:HERATIO_API_KEY
if (-not $Url -or -not $Key) {
    Write-Error "HERATIO_URL and HERATIO_API_KEY must be set (env or $configFile)"
    exit 1
}
$Sector = if ($env:HERATIO_SECTOR) { $env:HERATIO_SECTOR } else { 'archive' }
$Standard = if ($env:HERATIO_STANDARD) { $env:HERATIO_STANDARD } else { 'isadg' }

if (-not (Test-Path $File)) {
    Write-Error "File not found: $File"
    exit 1
}

$Headers = @{ 'X-API-Key' = $Key }

# --- Resolve parent slug → id if needed ---
$ParentId = $null
if ($env:HERATIO_PARENT_ID) {
    $ParentId = [int]$env:HERATIO_PARENT_ID
} elseif ($env:HERATIO_PARENT_SLUG) {
    $slug = $env:HERATIO_PARENT_SLUG
    $resp = Invoke-RestMethod -Uri "$Url/api/v2/scan/destinations?q=$slug" -Headers $Headers
    $match = $resp.data | Where-Object { $_.slug -eq $slug } | Select-Object -First 1
    if (-not $match) {
        Write-Error "Could not resolve parent slug '$slug'"
        exit 1
    }
    $ParentId = $match.id
}

# --- Create session ---
$SessionBody = @{
    sector = $Sector
    standard = $Standard
    auto_commit = $true
}
if ($ParentId) { $SessionBody.parent_id = $ParentId }

$SessionResp = Invoke-RestMethod -Uri "$Url/api/v2/scan/sessions" -Headers $Headers `
    -Method Post -Body ($SessionBody | ConvertTo-Json) -ContentType 'application/json'
$Token = $SessionResp.data.token
if (-not $Token) {
    Write-Error "Failed to create session: $($SessionResp | ConvertTo-Json -Compress)"
    exit 1
}

# --- Build metadata ---
$Meta = @{}
if ($Identifier) { $Meta.identifier = $Identifier }
if ($Title)      { $Meta.title = $Title }

# --- Multipart upload ---
# PowerShell's Invoke-RestMethod -Form needs 7.0+. Fall back to curl.exe when present.
$uploadUrl = "$Url/api/v2/scan/sessions/$Token/files"
$Form = @{
    file = Get-Item $File
    metadata = ($Meta | ConvertTo-Json -Compress)
}
if ($Sidecar -and (Test-Path $Sidecar)) {
    $Form.sidecar = Get-Item $Sidecar
}

try {
    $UploadResp = Invoke-RestMethod -Uri $uploadUrl -Headers $Headers -Method Post -Form $Form
} catch {
    Write-Error "Upload failed: $_"
    exit 1
}

if (-not $UploadResp.success) {
    Write-Error "Upload rejected: $($UploadResp | ConvertTo-Json -Compress)"
    exit 1
}

Write-Host "Uploaded to Heratio. Session: $Token"
Write-Host "Status: $Url/api/v2/scan/sessions/$Token"
