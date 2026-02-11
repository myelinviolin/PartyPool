Param(
  [string]$ScriptRoot = ''
)

if (-not $ScriptRoot) { $ScriptRoot = Split-Path -Parent $PSScriptRoot }
$pidFile = Join-Path $ScriptRoot '.php-server.pid'
if (-not (Test-Path $pidFile)) {
  Write-Output "PID file not found: $pidFile. Is the server running?"
  exit 0
}

$serverPid = (Get-Content $pidFile) -as [int]
if (-not $serverPid) {
  Write-Error "Invalid PID in $pidFile"
  Remove-Item $pidFile -ErrorAction SilentlyContinue
  exit 1
}

try {
  Stop-Process -Id $serverPid -Force -ErrorAction Stop
  Write-Output "Stopped PHP process (PID $serverPid)."
  Remove-Item $pidFile -ErrorAction SilentlyContinue
} catch {
  Write-Error ("Failed to stop process {0}: {1}" -f $serverPid, $_)
  exit 1
}