<?php

declare(strict_types=1);

namespace App\Services\Rms;

/**
 * Non-invasive network preflight for a controller registry form.
 *
 * This service never sends a YMConnect command and never writes to the robot.
 * ICMP reachability proves that a device answers at the address from the
 * Laravel host. The real Yaskawa identity is still confirmed by the C++
 * YMConnect collector when it establishes the first connected event.
 */
final class RobotReachabilityProbe
{
    /** @return array{ok: bool, status: string, message: string, method: string, checked_at: string, latency_ms: float|null} */
    public function probe(string $ipAddress): array
    {
        $checkedAt = now()->toIso8601String();
        $timeout = (int) config('rms.robot_probe.timeout_seconds', 2);

        if (! filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->result(false, 'invalid', 'Alamat harus berupa IPv4 yang valid.', 'validation', $checkedAt);
        }

        if (! config('rms.robot_probe.enabled', true)) {
            return $this->result(false, 'disabled', 'Pemeriksaan IP dinonaktifkan oleh konfigurasi server.', 'configuration', $checkedAt);
        }

        $binary = (string) config('rms.robot_probe.ping_binary', '');
        if ($binary === '') {
            foreach (['/usr/bin/ping', '/bin/ping', 'ping'] as $candidate) {
                if ($candidate === 'ping' || is_executable($candidate)) {
                    $binary = $candidate;
                    break;
                }
            }
        }

        if ($binary === '') {
            return $this->result(false, 'unavailable', 'Perintah ping tidak tersedia di container Laravel. Pasang iputils-ping terlebih dahulu.', 'icmp', $checkedAt);
        }

        $started = microtime(true);
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = @proc_open(
            [$binary, '-n', '-c', '1', '-W', (string) $timeout, $ipAddress],
            $descriptors,
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );

        if (! is_resource($process)) {
            return $this->result(false, 'unavailable', 'Laravel tidak dapat menjalankan pemeriksaan jaringan.', 'icmp', $checkedAt);
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $latency = round((microtime(true) - $started) * 1000, 1);

        if ($exitCode === 0) {
            $ports = implode(', ', config('rms.robot_probe.udp_ports', [10040, 10041]));
            return [
                'ok' => true,
                'status' => 'reachable',
                'message' => "Perangkat pada {$ipAddress} merespons ping ({$latency} ms). Port YMConnect yang dipakai collector: UDP {$ports}.",
                'method' => 'icmp',
                'checked_at' => $checkedAt,
                'latency_ms' => $latency,
            ];
        }

        $detail = trim((string) ($stderr !== '' ? $stderr : $stdout));
        return $this->result(false, 'unreachable', "IP {$ipAddress} tidak merespons dari server Laravel. {$detail}", 'icmp', $checkedAt, $latency);
    }

    /** @return array{ok: bool, status: string, message: string, method: string, checked_at: string, latency_ms: float|null} */
    private function result(bool $ok, string $status, string $message, string $method, string $checkedAt, ?float $latency = null): array
    {
        return compact('ok', 'status', 'message', 'method', 'checked_at', 'latency_ms');
    }
}
