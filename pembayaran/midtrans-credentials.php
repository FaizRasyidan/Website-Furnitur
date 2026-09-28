<?php
return [
    'serverKey' => getenv('MIDTRANS_SERVER_KEY') ?: '',
    'clientKey' => getenv('MIDTRANS_CLIENT_KEY') ?: '',
    'isProduction' => false,
];
