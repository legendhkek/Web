# Ngrok Setup Script for Windows
Write-Host "Setting up ngrok for LEGEND CHECKER..." -ForegroundColor Green

# Download ngrok
$ngrokUrl = "https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-windows-amd64.zip"
$downloadPath = "$env:TEMP\ngrok.zip"
$extractPath = "D:\legend\ngrok"

Write-Host "Downloading ngrok..." -ForegroundColor Yellow
Invoke-WebRequest -Uri $ngrokUrl -OutFile $downloadPath

Write-Host "Extracting ngrok..." -ForegroundColor Yellow
if (Test-Path $extractPath) {
    Remove-Item $extractPath -Recurse -Force
}
New-Item -ItemType Directory -Path $extractPath -Force
Expand-Archive -Path $downloadPath -DestinationPath $extractPath

Write-Host "Ngrok downloaded to: $extractPath" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "1. Sign up at https://ngrok.com if you haven't already"
Write-Host "2. Get your auth token from https://dashboard.ngrok.com/get-started/your-authtoken"
Write-Host "3. Run: .\ngrok\ngrok.exe config add-authtoken YOUR_TOKEN_HERE"
Write-Host "4. Run: .\ngrok\ngrok.exe http 8000"
Write-Host ""
Write-Host "Example commands to run after getting your token:" -ForegroundColor Yellow
Write-Host "cd D:\legend"
Write-Host ".\ngrok\ngrok.exe config add-authtoken YOUR_TOKEN_HERE"
Write-Host ".\ngrok\ngrok.exe http 8000"

# Clean up
Remove-Item $downloadPath -Force