<?php

namespace Msads\AgentSdk;

/**
 * MS Ads Agent Exchange server SDK for PHP 7.4+ (single file, curl + json). Keep keys server-side only.
 *   $ax = new AgentExchange('agt_...', getenv('MSADS_KEY'), getenv('MSADS_SIGNING_SECRET') ?: null);
 *   $r  = $ax->getOffers(['unit' => 'msads_...', 'test' => true, 'intent' => [...]]);
 */
class AgentExchange
{
    public const VERSION = '0.1.0';
    private $agentId; private $key; private $secret; private $base; private $timeout;

    public function __construct(string $agentId, string $key, ?string $secret = null, string $baseUrl = 'https://msadsnetwork.com', float $timeout = 2.0)
    {
        $this->agentId = $agentId; $this->key = $key; $this->secret = $secret ?: null; $this->base = rtrim($baseUrl, '/'); $this->timeout = $timeout;
    }

    public static function sign(string $secret, string $ts, string $nonce, string $method, string $path, string $body): string
    {
        $s = $ts . "\n" . $nonce . "\n" . strtoupper($method) . "\n" . '/' . ltrim($path, '/') . "\n" . hash('sha256', $body);
        return 'v1=' . hash_hmac('sha256', $s, $secret);
    }

    /** Intent summary only: no raw chat text, no personal data. */
    public function getOffers(array $request): array
    {
        return $this->post('/api/v1/agent-exchange/offers', $request);
    }

    public function sendEvent(string $offerRef, string $event, string $idempotencyKey, ?string $proofHash = null): array
    {
        return $this->post('/api/v1/agent-exchange/events', array_filter(['offer_ref' => $offerRef, 'event' => $event, 'idempotency_key' => $idempotencyKey, 'proof_hash' => $proofHash]));
    }

    public static function proofHash(string $orderRef): string
    {
        return hash('sha256', $orderRef);
    }

    public static function sponsoredText(array $o): string
    {
        $label = $o['disclosure']['label'] ?? 'Sponsored';
        return sprintf("[%s] %s - %s\n%s %s\n%s", $label, $o['title'] ?? '', $o['merchant']['name'] ?? '', $o['price']['currency'] ?? '', $o['price']['amount'] ?? '', $o['checkout']['url'] ?? '');
    }

    private function post(string $path, array $payload): array
    {
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $h = ['Content-Type: application/json', 'Accept: application/json', 'X-MSADS-Key: ' . $this->key, 'User-Agent: msads-agent-php/' . self::VERSION];
        if ($this->secret) {
            $ts = (string) time(); $nonce = bin2hex(random_bytes(12));
            array_push($h, 'X-MSADS-Agent: ' . $this->agentId, 'X-MSADS-Timestamp: ' . $ts, 'X-MSADS-Nonce: ' . $nonce, 'X-MSADS-Signature: ' . self::sign($this->secret, $ts, $nonce, 'POST', $path, $raw));
        }
        $c = curl_init($this->base . $path);
        curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $raw, CURLOPT_HTTPHEADER => $h, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => (int) ($this->timeout * 1000), CURLOPT_CONNECTTIMEOUT_MS => 800]);
        $out = curl_exec($c); $code = (int) curl_getinfo($c, CURLINFO_HTTP_CODE); $err = curl_error($c); curl_close($c);
        if ($out === false) {
            throw new \RuntimeException('MS Ads request failed: ' . $err);
        }
        $body = json_decode((string) $out, true) ?? [];
        if ($code >= 400) {
            throw new \RuntimeException("MS Ads HTTP {$code}: " . $out, $code);
        }
        return $body;
    }
}
