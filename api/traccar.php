<?php
header('Content-Type: application/json');

$endpoint = $_GET['endpoint'] ?? '';

$traccarUrl = "http://YOUR_TRACCAR/api/" . $endpoint;

$ch = curl_init($traccarUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "user:pass");

$response = curl_exec($ch);
echo $response;
