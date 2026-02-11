Param(
  [int]$Port = 8000,
  [string]$HostName = 'localhost',
  [string]$Root = '',
  [string]$PhpPath = ''
)

$scriptRoot = Split-Path -Parent $PSScriptRoot
if (-not $Root) { $Root = Join-Path $scriptRoot 'public' }

if (-not (Test-Path $Root)) {
  Write-Error "Web root not found: $Root"
  exit 1
}

if (-not $PhpPath) {
  $phpCmd = Get-Command php -ErrorAction SilentlyContinue
  if ($phpCmd) { $PhpPath = $phpCmd.Source }
  elseif (Test-Path 'C:\tools\php85\php.exe') { $PhpPath = 'C:\tools\php85\php.exe' }
  elseif (Test-Path 'C:\Program Files\php\php.exe') { $PhpPath = 'C:\Program Files\php\php.exe' }
}

if (-not (Test-Path $PhpPath)) {
  Write-Error "PHP executable not found. Provide -PhpPath or ensure php is in PATH."
  exit 1
}

$pidFile = Join-Path $scriptRoot '.php-server.pid'
if (Test-Path $pidFile) {
  $oldServerPid = (Get-Content $pidFile) -as [int]
  if ($oldServerPid -and (Get-Process -Id $oldServerPid -ErrorAction SilentlyContinue)) {
    Write-Output "PID file exists at $pidFile. Server may already be running (PID $oldServerPid)."
    exit 1
  } else {
    Write-Output "Cleaning up stale PID file."
    Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
  }
}

$arguments = @(
  '-S',
  "$HostName`:$Port",
  '-t',
  $Root
)

$proc = Start-Process -FilePath $PhpPath -ArgumentList $arguments -NoNewWindow -PassThru
Start-Sleep -Milliseconds 200
if ($proc -and $proc.Id) {
  $proc.Id | Out-File -FilePath $pidFile -Encoding ascii
  Write-Output ("Started PHP server at http://{0}:{1} (PID {2}). PID file: {3}" -f $HostName, $Port, $proc.Id, $pidFile)
  Write-Output "To stop: .\scripts\stop-php.ps1"
} else {
  Write-Error "Failed to start PHP process."
  exit 1
}