<?php
header('Content-Type: application/json');
if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer fixture-only-key') { http_response_code(401); echo '{"error":"wrong fixture credential"}'; return; }
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (str_starts_with($path,'/redirect/')) { header('Location: /followed',true,302); return; }
if (str_starts_with($path,'/large/')) { echo str_repeat('x',1048577); return; }
if (str_ends_with($path,'/models')) { echo '{"object":"list","data":[{"id":"fixture-combo","owned_by":"combo"}]}'; return; }
$body=json_decode(file_get_contents('php://input'),true);
if ($_SERVER['REQUEST_METHOD']!=='POST' || ($body['model'] ?? '')!=='fixture-combo' || ($body['stream'] ?? null)!==false) { http_response_code(400); echo '{}'; return; }
echo '{"choices":[{"message":{"role":"assistant","content":"OK"}}],"usage":{"total_tokens":5}}';
