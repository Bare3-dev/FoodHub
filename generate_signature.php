<?php

$payload = '{"amount":100,"status":"success","transaction_id":"TXN_TEST_1754491531","order_id":11}';
$secret = 'test_webhook_secret_123';

$signature = hash_hmac('sha256', $payload, $secret);

echo "Payload: " . $payload . "\n";
echo "Secret: " . $secret . "\n";
echo "Signature: " . $signature . "\n"; 