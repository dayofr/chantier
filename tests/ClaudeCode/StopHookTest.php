<?php

namespace App\Tests\ClaudeCode;

use PHPUnit\Framework\TestCase;

/** Exécute public/claude-code/chantier-stop.sh comme le ferait Claude Code. */
final class StopHookTest extends TestCase
{
    private string $dir;
    private string $transcript;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/chantier-hook-test-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/tmp', 0o777, true);
        $this->transcript = $this->dir.'/transcript.jsonl';
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dir));
    }

    public function testRemindsOnlyWhenChantierIsUsedAndConversationGrew(): void
    {
        file_put_contents($this->transcript, str_repeat('x', 2000));
        self::assertSame(0, $this->runHook()[0], 'Séance sans Chantier : silencieux.');

        file_put_contents($this->transcript, '{"name":"mcp__chantier__get_ticket"}'."\n", \FILE_APPEND);
        [$code, $stderr] = $this->runHook();
        self::assertSame(2, $code);
        self::assertStringContainsString('save_session_summary', $stderr);

        self::assertSame(0, $this->runHook(stopHookActive: true)[0], 'Relance par le hook : pas de boucle.');
        self::assertSame(0, $this->runHook()[0], 'Juste après un rappel : silencieux.');

        file_put_contents($this->transcript, str_repeat('y', 1500).'{"name":"mcp__chantier__save_session_summary"}', \FILE_APPEND);
        self::assertSame(0, $this->runHook()[0], 'Résumé envoyé depuis le rappel : silencieux.');

        file_put_contents($this->transcript, str_repeat('z', 1500), \FILE_APPEND);
        self::assertSame(2, $this->runHook()[0], 'La conversation a encore avancé : nouveau rappel.');
    }

    public function testMalformedInputNeverBlocks(): void
    {
        self::assertSame(0, $this->runHook(raw: 'pas du json')[0]);
        self::assertSame(0, $this->runHook(raw: '{"session_id":"x","transcript_path":"/nexiste/pas"}')[0]);
    }

    /** @return array{0: int, 1: string} */
    private function runHook(bool $stopHookActive = false, ?string $raw = null): array
    {
        $input = $raw ?? json_encode(['session_id' => 'sess', 'transcript_path' => $this->transcript, 'stop_hook_active' => $stopHookActive], \JSON_PRETTY_PRINT);
        $process = proc_open(
            ['sh', __DIR__.'/../../public/claude-code/chantier-stop.sh'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['TMPDIR' => $this->dir.'/tmp', 'CHANTIER_SUMMARY_EVERY_BYTES' => '1000', 'PATH' => getenv('PATH')],
        );
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        return [proc_close($process), $stderr];
    }
}
