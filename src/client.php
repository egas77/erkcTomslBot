<?php

$curlDecodeImage = curl_init();

curl_setopt_array($curlDecodeImage, array(
  CURLOPT_URL => 'http://decode/decode-barcode',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_HTTPHEADER => array(
    'Content-Type: image'
  ),
));
curl_setopt($curlDecodeImage, CURLOPT_POSTFIELDS, file_get_contents($argv[1])); # set image data
$response = curl_exec($curlDecodeImage);
curl_close($curlDecodeImage);
echo $response;
