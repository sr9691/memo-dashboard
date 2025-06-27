# Deployment Script for Memo Dashboard
# Run this to prepare the plugin for deployment

Write-Host "Preparing Memo Dashboard for deployment..." -ForegroundColor Green

# Create plugin ZIP file
$zipPath = "visitor-dashboard.zip"
if (Test-Path $zipPath) {
    Remove-Item $zipPath
}

# Compress plugin directory
Compress-Archive -Path "visitor-dashboard\*" -DestinationPath $zipPath

Write-Host "[SUCCESS] Plugin packaged as: $zipPath" -ForegroundColor Green
Write-Host "[INFO] Ready for WordPress upload!" -ForegroundColor Cyan
