# Builds level-up-embed.zip, ready to upload via Plugins -> Add New -> Upload Plugin.
#
#   pwsh ./wordpress/build-zip.ps1
#
# The ZIP must contain a single top-level folder named after the plugin, which is
# why this stages a copy rather than zipping the folder's contents directly.

$ErrorActionPreference = 'Stop'

$here    = Split-Path -Parent $MyInvocation.MyCommand.Path
$source  = Join-Path $here 'level-up-embed'
$dist    = Join-Path $here 'dist'
$staging = Join-Path $dist 'level-up-embed'
$zip     = Join-Path $dist 'level-up-embed.zip'

if (-not (Test-Path $source)) { throw "Plugin folder not found: $source" }

if (Test-Path $dist) { Remove-Item -Recurse -Force $dist }
New-Item -ItemType Directory -Force -Path $staging | Out-Null

Copy-Item -Recurse -Force -Path (Join-Path $source '*') -Destination $staging

# Anything that should not ship to a live site.
Get-ChildItem -Path $staging -Recurse -Force -Include '.DS_Store', 'Thumbs.db', '*.map' |
	Remove-Item -Force -ErrorAction SilentlyContinue

Compress-Archive -Path $staging -DestinationPath $zip -CompressionLevel Optimal
Remove-Item -Recurse -Force $staging

$size = [math]::Round((Get-Item $zip).Length / 1KB, 1)
Write-Host "Built $zip ($size KB)"
