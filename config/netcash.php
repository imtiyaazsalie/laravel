<?php

return [
    'api' => [
        'url' => env('NETCASH_SOAP_CLIENT', 'https://ws.netcash.co.za/NIWS/niws_nif.svc?wsdl'),
        'software_vendor_key' => env('NETCASH_SOFTWARE_VENDOR_KEY', 'testCred'),
    ],

    'directories' => [
        'batches' => 'sage/octiv-batches',
    ],
];
