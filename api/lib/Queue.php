<?php
/**
 * Queue - wrapper simples para Redis lists (RPUSH / BLPOP).
 * Usa phpredis quando disponivel e fallback RESP puro quando a extensao nao existe.
 */
class Queue {
    private $redis;
    private $native = true;

    public function __construct() {
        if (class_exists('Redis')) {
            $r = new Redis();
            $connected = $r->connect(REDIS_HOST, (int)REDIS_PORT);
            if (!$connected) throw new Exception('Nao foi possivel conectar ao Redis em '.REDIS_HOST.':'.REDIS_PORT);
            if (REDIS_PASSWORD !== '') $r->auth(REDIS_PASSWORD);
            if (REDIS_DB !== '') $r->select((int)REDIS_DB);
            $this->redis = $r;
            return;
        }

        $this->native = false;
        $this->redis = new SimpleRedisClient(REDIS_HOST, (int)REDIS_PORT, REDIS_PASSWORD, (int)REDIS_DB);
    }

    public function push(string $queue, array $payload): bool {
        $data = json_encode($payload, JSON_UNESCAPED_UNICODE);
        return $this->redis->rPush($queue, $data) > 0;
    }

    /**
     * Bloqueante com timeout em segundos. Retorna null se timeout.
     */
    public function pop(string $queue, int $timeout = 5): ?array {
        if (!$this->native) {
            $json = $this->redis->blPop($queue, $timeout);
            $payload = json_decode($json ?? '', true);
            return is_array($payload) ? $payload : null;
        }

        $res = $this->redis->blPop([$queue], $timeout);
        if (!$res || !is_array($res) || count($res) < 2) return null;
        $json = $res[1];
        $payload = json_decode($json, true);
        return is_array($payload) ? $payload : null;
    }
}

class SimpleRedisClient {
    private $socket;

    public function __construct(string $host, int $port, string $password = '', int $db = 0) {
        $this->socket = @fsockopen($host, $port, $errno, $errstr, 5);
        if (!$this->socket) throw new Exception("Nao foi possivel conectar ao Redis em {$host}:{$port}: {$errstr}");
        stream_set_timeout($this->socket, 0, 500000);
        if ($password !== '') $this->command('AUTH', $password);
        if ($db > 0) $this->command('SELECT', (string)$db);
    }

    public function rPush(string $queue, string $data): int {
        $res = $this->command('RPUSH', $queue, $data);
        return is_int($res) ? $res : 0;
    }

    public function blPop(string $queue, int $timeout): ?string {
        $res = $this->command('BLPOP', $queue, (string)$timeout);
        if (!is_array($res) || count($res) < 2) return null;
        return (string)$res[1];
    }

    private function command(string ...$parts) {
        $payload = '*' . count($parts) . "\r\n";
        foreach ($parts as $part) {
            $payload .= '$' . strlen($part) . "\r\n" . $part . "\r\n";
        }
        fwrite($this->socket, $payload);
        return $this->readResponse();
    }

    private function readResponse() {
        $line = fgets($this->socket);
        if ($line === false) throw new Exception('Resposta vazia do Redis.');
        $prefix = $line[0];
        $value = rtrim(substr($line, 1), "\r\n");

        if ($prefix === '+') return $value;
        if ($prefix === ':') return (int)$value;
        if ($prefix === '-') throw new Exception('Redis: ' . $value);

        if ($prefix === '$') {
            $len = (int)$value;
            if ($len < 0) return null;
            $data = '';
            while (strlen($data) < $len) {
                $chunk = fread($this->socket, $len - strlen($data));
                if ($chunk === false || $chunk === '') break;
                $data .= $chunk;
            }
            fread($this->socket, 2);
            return $data;
        }

        if ($prefix === '*') {
            $count = (int)$value;
            if ($count < 0) return null;
            $items = [];
            for ($i = 0; $i < $count; $i++) {
                $items[] = $this->readResponse();
            }
            return $items;
        }

        throw new Exception('Resposta Redis desconhecida.');
    }
}
