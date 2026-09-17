# Scénarios des hooks PowerShell (mêmes cas que StopHookTest.php pour la version sh).
# Sans PowerShell installé, depuis la racine du dépôt :
#   docker run --rm --platform linux/amd64 -v "$PWD/public/claude-code:/hooks:ro" -v "$PWD/tests/ClaudeCode:/work" \
#     mcr.microsoft.com/powershell:latest pwsh -NoProfile -File /work/powershell-hooks.ps1
# Sortie non nulle si un cas échoue.

$ErrorActionPreference = 'Stop'
$hooks = if (Test-Path '/hooks') { '/hooks' } else { Join-Path $PSScriptRoot '../../public/claude-code' }
$work = Join-Path ([IO.Path]::GetTempPath()) ('chantier-pwsh-' + [guid]::NewGuid())
New-Item -ItemType Directory -Force $work | Out-Null
$env:CHANTIER_SUMMARY_EVERY_BYTES = '1000'
$t = Join-Path $work 'transcript.jsonl'
Remove-Item -Force -ErrorAction SilentlyContinue $t
Get-ChildItem -Path ([IO.Path]::GetTempPath()) -Filter 'chantier-hooks' -ErrorAction SilentlyContinue | Remove-Item -Recurse -Force
function Run([string]$json) {
    $psi = New-Object Diagnostics.ProcessStartInfo 'pwsh', ('-NoProfile -File ' + (Join-Path $hooks 'chantier-stop.ps1'))
    $psi.RedirectStandardInput = $true; $psi.RedirectStandardError = $true; $psi.RedirectStandardOutput = $true; $psi.UseShellExecute = $false
    $p = [Diagnostics.Process]::Start($psi); $p.StandardInput.Write($json); $p.StandardInput.Close()
    $err = $p.StandardError.ReadToEnd(); [void]$p.StandardOutput.ReadToEnd(); $p.WaitForExit()
    return @{ code = $p.ExitCode; err = $err }
}
function Payload([bool]$active) { @{ session_id = 'sess'; transcript_path = $t; stop_hook_active = $active } | ConvertTo-Json -Compress }
$script:failures = 0
function Check([string]$label, $result, [int]$expected) {
    $ok = $result.code -eq $expected
    if (-not $ok) { $script:failures++ }
    "{0,-45} exit={1} {2}" -f $label, $result.code, ($(if ($ok) { 'OK' } else { "ATTENDU $expected" }))
}

Set-Content -NoNewline -LiteralPath $t -Value ('x' * 2000)
Check 'sans Chantier' (Run (Payload $false)) 0
Add-Content -NoNewline -LiteralPath $t -Value '{"name":"mcp__chantier__get_ticket"}'
$r = Run (Payload $false); Check 'seuil dépassé' $r 2
"  message : " + ($r.err -split "`n")[0]
Check 'relancé par le hook' (Run (Payload $true)) 0
Check 'juste après rappel' (Run (Payload $false)) 0
Add-Content -NoNewline -LiteralPath $t -Value (('y' * 1500) + '{"name":"mcp__chantier__save_session_summary"}')
Check 'résumé déjà envoyé' (Run (Payload $false)) 0
Add-Content -NoNewline -LiteralPath $t -Value ('z' * 1500)
Check 'conversation encore avancée' (Run (Payload $false)) 2
Check 'entrée invalide' (Run 'pas du json') 0
Check 'transcript absent' (Run '{"session_id":"x","transcript_path":"/nexiste/pas"}') 0
$t2 = Join-Path $work 't2.jsonl'; Set-Content -NoNewline -LiteralPath $t2 -Value ('{"name":"mcp__chantier__list_projects"}' + ('w' * 2000))
Check 'JSON multiligne, sans résumé' (Run ((@{ session_id = 'multi'; transcript_path = $t2; stop_hook_active = $false } | ConvertTo-Json))) 2

$psi = New-Object Diagnostics.ProcessStartInfo 'pwsh', ('-NoProfile -File ' + (Join-Path $hooks 'chantier-session-start.ps1'))
$psi.RedirectStandardInput = $true; $psi.RedirectStandardOutput = $true; $psi.UseShellExecute = $false
$p = [Diagnostics.Process]::Start($psi); $p.StandardInput.Write('{"source":"compact"}'); $p.StandardInput.Close(); $out = $p.StandardOutput.ReadToEnd(); $p.WaitForExit()
"session-start compact : " + ($out -split "`n")[0]

Remove-Item -Recurse -Force $work
exit $script:failures
