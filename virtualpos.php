<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "https://gateway.futbolitico.com/api/virtualpos",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS =>"{\n\t\"platformId\" : \"soyfreelancer\",\n\t\"productId\" : \"Membership\",\n\t\"clientName\" : \"lombardo@gmail.com\",\n\t\"cardName\" : \"Erick Lombardo\",\n\t\"cardNumber\" : \"4111111111111\",\n\t\"cardExpirationYear\": 2022,\n\t\"cardExpirationMonth\" : 10,\n\t\"cardVerificationValue\" : \"1234\",\n\t\"cardType\" : 1,\n\t\"amount\" : 12\n}",
  CURLOPT_HTTPHEADER => array(
    "Content-Type: application/json",
    "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1bmlxdWVfbmFtZSI6InJvb3QiLCJuYmYiOjE1NzkwOTc3NjUsImV4cCI6MTU3OTA5OTU2NSwiaWF0IjoxNTc5MDk3NzY1fQ.wD_1l-kEQTUFUAqTpFQWzxiFR8YMhSfKy4WUM_TX320"
  ),
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;
