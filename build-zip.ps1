# Build a clean distribution zip for WordPress upload.
# Run from the plugin root: powershell -ExecutionPolicy Bypass -File build-zip.ps1

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$pluginSlug = "smoketree-plugin"
$desktopDir = [Environment]::GetFolderPath('Desktop')
$zipFile    = Join-Path $desktopDir "${pluginSlug}.zip"

$excludeDirs = @('.git', 'prompts', 'docs', 'dev')
$excludeFiles = @(
    'build-zip.ps1',
    '.gitignore',
    '.cursorignore',
    'tailwind.config.js',
    'tailwind.plugin.config.js',
    'CHANGELOG.md',
    'README.md',
    'README.txt',
    'LICENSE.txt',
    'languages/plugin-name.pot',
    'vendor/stripe/stripe-php/CHANGELOG.md',
    'vendor/stripe/stripe-php/README.md',
    'vendor/stripe/stripe-php/CONTRIBUTING.md',
    'vendor/stripe/stripe-php/justfile',
    'vendor/stripe/stripe-php/.gitignore',
    'vendor/stripe/stripe-php/API_VERSION',
    'vendor/stripe/stripe-php/OPENAPI_VERSION',
    'vendor/stripe/stripe-php/VERSION'
)

$sourceDir = $PSScriptRoot
if (-not $sourceDir) { $sourceDir = (Get-Location).Path }
$sourceDir = $sourceDir.TrimEnd('\')

if (Test-Path $zipFile) { Remove-Item $zipFile -Force }

$zip = [System.IO.Compression.ZipFile]::Open($zipFile, 'Create')

$allFiles = Get-ChildItem -Path $sourceDir -Recurse -File -Force
foreach ($file in $allFiles) {
    $relativePath = $file.FullName.Substring($sourceDir.Length + 1)
    $relativeUnix = $relativePath.Replace('\', '/')

    $skip = $false
    foreach ($exDir in $excludeDirs) {
        if ($relativeUnix -eq $exDir -or $relativeUnix.StartsWith("${exDir}/")) {
            $skip = $true; break
        }
    }
    if ($skip) { continue }

    foreach ($exFile in $excludeFiles) {
        if ($relativeUnix -eq $exFile) {
            $skip = $true; break
        }
    }
    if ($skip) { continue }

    $entryName = "${pluginSlug}/${relativeUnix}"
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
        $zip, $file.FullName, $entryName, 'Optimal'
    ) | Out-Null
}

$zip.Dispose()

$size = (Get-Item $zipFile).Length
$sizeMB = [math]::Round($size / 1MB, 2)
Write-Host "Built $zipFile ($sizeMB MB)"
