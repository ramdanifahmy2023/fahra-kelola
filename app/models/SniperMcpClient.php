<?php

class SniperMcpClient {
    private $url;
    private $token;
    private $projectId;

    public function __construct() {
        $this->url = rtrim((string)(defined('SNIPER_MCP_URL') ? SNIPER_MCP_URL : ''), '/');
        $this->token = (string)(defined('SNIPER_MCP_TOKEN') ? SNIPER_MCP_TOKEN : '');
        $this->projectId = (int)(defined('SNIPER_MCP_PROJECT_ID') ? SNIPER_MCP_PROJECT_ID : 1);
    }

    public function isConfigured() {
        return $this->url !== '' && $this->token !== '' && $this->projectId > 0;
    }

    private function call($id, $name, array $arguments) {
        if (!$this->isConfigured()) return null;
        $body = json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments]
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
                'Accept: application/json, text/event-stream',
                'MCP-Protocol-Version: 2025-11-25'
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        $response = json_decode((string)$raw, true);
        return is_array($response) ? ($response['result']['structuredContent'] ?? null) : null;
    }

    public function weeklyReports(array $period) {
        if (!$this->isConfigured()) return ['available' => false, 'error' => 'XYZ Sniper MCP belum dikonfigurasi.'];
        $start = strtotime(($period['from'] ?? '') . ' 00:00:00 Asia/Jakarta');
        $end = strtotime(($period['to'] ?? '') . ' 23:59:59 Asia/Jakarta');
        if (!$start || !$end) return ['available' => false, 'error' => 'Periode report tidak valid.'];
        $endpoints = $this->call(1, 'get_endpoints', ['project_id' => $this->projectId, 'query' => 'get_time_graph', 'limit' => 20, 'offset' => 0]);
        $endpointId = 0;
        foreach ((array)$endpoints as $endpoint) {
            if (strpos((string)($endpoint['path'] ?? ''), '/api/pas/v1/report/get_time_graph/') !== false) {
                $endpointId = (int)($endpoint['id'] ?? 0);
                break;
            }
        }
        if ($endpointId < 1) return ['available' => false, 'error' => 'Endpoint report Sniper tidak ditemukan.'];
        $payloads = $this->call(2, 'get_payloads', ['endpoint_id' => $endpointId, 'limit' => 200, 'offset' => 0]);
        $latest = [];
        foreach ((array)$payloads as $payload) {
            $body = is_array($payload['request_body'] ?? null) ? $payload['request_body'] : [];
            $response = is_array($payload['response_body'] ?? null) ? $payload['response_body'] : [];
            $aggregate = $response['data']['report_aggregate'] ?? null;
            if (!is_array($aggregate) || (int)($response['code'] ?? -1) !== 0) continue;
            if ((int)($body['start_time'] ?? 0) !== $start || (int)($body['end_time'] ?? 0) !== $end) continue;
            $type = (string)($body['campaign_type'] ?? '');
            if ($type === '') continue;
            if (!isset($latest[$type]) || strcmp((string)($payload['created_at'] ?? ''), (string)($latest[$type]['created_at'] ?? '')) > 0) {
                $latest[$type] = ['aggregate' => $aggregate, 'created_at' => $payload['created_at'] ?? null];
            }
        }
        return ['available' => !empty($latest), 'reports' => $latest, 'source' => 'xyz_sniper_mcp'];
    }
}
