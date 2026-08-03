<?php

namespace CoaProxy;

class RadiusClient
{
    private array $config;
    private Logger $logger;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Send RADIUS disconnect packet to NAS
     */
    public function disconnect(string $username, ?string $sessionId, string $nasIp): array
    {
        $attributes = [
            'User-Name' => $username,
        ];
        if ($sessionId !== null && $sessionId !== '') {
            $attributes['Acct-Session-Id'] = $sessionId;
        }

        return $this->sendPacket($nasIp, 'disconnect', $attributes);
    }

    /**
     * Send RADIUS CoA (Change of Authorization) packet to NAS
     */
    public function coa(string $username, ?string $sessionId, string $nasIp, array $attributes): array
    {
        $payloadAttributes = array_merge([
            'User-Name' => $username,
        ], $attributes);

        if ($sessionId !== null && $sessionId !== '') {
            $payloadAttributes['Acct-Session-Id'] = $sessionId;
        }

        return $this->sendPacket($nasIp, 'coa', $payloadAttributes);
    }

    /**
     * Send RADIUS packet to NAS via proc_open() call to radclient
     */
    public function sendPacket(string $nasIp, string $action, array $attributes): array
    {
        $radclientPath = $this->config['radclient_path'];
        $port = $this->config['coa_port'];
        $secret = $this->config['secret'];
        $timeout = $this->config['timeout'];

        if (!file_exists($radclientPath) || !is_executable($radclientPath)) {
            // Check system PATH fallback
            $whichOutput = @shell_exec('which radclient 2>/dev/null');
            if ($whichOutput) {
                $radclientPath = trim($whichOutput);
            } else {
                return [
                    'success' => false,
                    'error_code' => 'RADCLIENT_NOT_FOUND',
                    'message' => "radclient binary not found or not executable at [{$radclientPath}]",
                    'exit_code' => -1,
                    'stdout' => '',
                    'stderr' => '',
                    'duration_ms' => 0,
                    'timed_out' => false,
                ];
            }
        }

        // Format RADIUS attribute payload for STDIN
        $stdinData = '';
        foreach ($attributes as $key => $value) {
            $stdinData .= sprintf("%s = \"%s\"\n", $key, addcslashes($value, '"\\'));
        }

        // Target string e.g. 10.10.10.1:3799
        $target = sprintf('%s:%d', $nasIp, $port);

        // Command array passed directly to proc_open to prevent command injection completely
        $cmd = [
            $radclientPath,
            '-x',
            $target,
            $action,
            $secret
        ];

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $startTime = microtime(true);

        $process = proc_open($cmd, $descriptors, $pipes, null, null, ['suppress_errors' => true]);

        if (!is_resource($process)) {
            return [
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Failed to spawn radclient process using proc_open',
                'exit_code' => -1,
                'stdout' => '',
                'stderr' => '',
                'duration_ms' => 0,
                'timed_out' => false,
            ];
        }

        // Write STDIN payload and close STDIN stream immediately
        fwrite($pipes[0], $stdinData);
        fclose($pipes[0]);

        // Set non-blocking stream mode on stdout and stderr
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $timedOut = false;
        $endTime = $startTime + $timeout;

        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;

            $remainingTime = max(0, $endTime - microtime(true));
            if ($remainingTime <= 0) {
                $timedOut = true;
                proc_terminate($process, 9); // Send SIGKILL
                break;
            }

            $numChanged = stream_select($read, $write, $except, (int)floor($remainingTime), (int)(($remainingTime - floor($remainingTime)) * 1000000));
            if ($numChanged === false) {
                break;
            }

            if ($numChanged > 0) {
                foreach ($read as $r) {
                    if ($r === $pipes[1]) {
                        $stdout .= stream_get_contents($pipes[1]);
                    } elseif ($r === $pipes[2]) {
                        $stderr .= stream_get_contents($pipes[2]);
                    }
                }
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                // Read any remaining unread bytes from pipes
                $stdout .= stream_get_contents($pipes[1]);
                $stderr .= stream_get_contents($pipes[2]);
                break;
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        if ($timedOut) {
            $this->logger->error('radius_timeout', [
                'nas_ip' => $nasIp,
                'action' => $action,
                'duration_ms' => $durationMs,
            ]);
            return [
                'success' => false,
                'error_code' => 'RADIUS_TIMEOUT',
                'message' => "RADIUS packet delivery timed out after {$timeout} seconds",
                'exit_code' => -1,
                'stdout' => trim($stdout),
                'stderr' => trim($stderr),
                'duration_ms' => $durationMs,
                'timed_out' => true,
            ];
        }

        // Check if stdout contains RADIUS ACK response (e.g. Received response code 2 / CoA-ACK / Disconnect-ACK)
        $isAck = ($exitCode === 0) && (
            str_contains($stdout, 'Received response code 2') || // Disconnect-ACK / CoA-ACK
            str_contains($stdout, 'Received response code 4') ||
            str_contains($stdout, 'ACK') ||
            str_contains($stdout, 'code 2')
        );

        // If radclient returned 0 exit code, treat as successful RADIUS execution
        $isSuccess = ($exitCode === 0);

        $this->logger->info('radius_executed', [
            'nas_ip' => $nasIp,
            'action' => $action,
            'exit_code' => $exitCode,
            'success' => $isSuccess,
            'duration_ms' => $durationMs,
        ]);

        return [
            'success' => $isSuccess,
            'error_code' => $isSuccess ? null : 'RADIUS_REQUEST_FAILED',
            'message' => $isSuccess ? 'RADIUS request processed successfully' : 'RADIUS request failed or NAK returned by NAS',
            'exit_code' => $exitCode,
            'stdout' => trim($stdout),
            'stderr' => trim($stderr),
            'duration_ms' => $durationMs,
            'timed_out' => false,
        ];
    }
}
