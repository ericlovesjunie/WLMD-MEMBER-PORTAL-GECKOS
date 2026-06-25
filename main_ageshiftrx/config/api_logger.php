<?php

return [

    // false = sirf selected APIs
    // true  = ALL APIs
    'log_all' => false,

    // jab log_all = false
    'only_urls' => [
        'api/user_login',
     'api/phone_verification',
        'api/verify_verification_code',
    ],

];
