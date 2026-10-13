param(
    [ValidateSet('serve', 'test')]
    [string]$Action = 'serve'
)

$ErrorActionPreference = 'Stop'
$modulePhp = Join-Path $PSScriptRoot '..\..\.tools\php\php.exe'
if (-not (Test-Path -LiteralPath $modulePhp)) {
    $modulePhp = (Get-Command php -ErrorAction Stop).Source
}
Push-Location $PSScriptRoot
try {
    if ($Action -eq 'test') {
        & $modulePhp artisan test --compact
    } else {
        & $modulePhp artisan serve --host=127.0.0.1 --port=8000
    }
    if ($LASTEXITCODE -ne 0) { throw "Laravel command failed with exit code $LASTEXITCODE" }
} finally {
    Pop-Location
}
