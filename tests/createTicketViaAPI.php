<?php
// User Params
$glpiUrl = 'https://your.glpi.url';                         // Do not include closing slash '/'
$appToken = 'FOUND-IN-SETUP-GENERAL-API-ADD-API-CLIENT';
$userToken = 'REMOTE-ACCESS-KEY-GENERATED-IN-GLPI-USERTAB';
$ticketBody = json_encode([
    "input" => [
        "name"                  => "[TEST TICKET] CREATE TEST TICKET!",             // Title
        "content"               => "Awsom ticket content!",                         // Content body
        "_users_id_requester"   => 523,                                             // Match your user
        "urgency"               => 3,
        "itilcategories_id"     => 1,                                               // Match your category
        "type"                  => 2,                                               // Request
        "status"                => 1                                                // Open.
    ],
]);

// Init Curl library;
// I just assume its present, else an fatal error will occur.
$ch = curl_init();

// Define HTTP headers to be send by Curl.
$initHeaders = ['Content-Type: application/json',
                "Authorization: user_token $userToken",
                "App-Token: $appToken",
               ];

// Initialize CURL params.
// See: https://www.php.net/manual/en/function.curl-setopt-array.php
$options = [
    CURLOPT_URL             => $glpiUrl.'/apirest.php/initSession',                             // What URL to open
    CURLOPT_HEADER          => false,                                                           // Do not return header information in response
    CURLOPT_POST            => true,                                                            // Use HTTP POST in the request
    CURLOPT_TIMEOUT         => 4,                                                               // Timeout after 4 attempts
    CURLOPT_HTTPHEADER      => $initHeaders,                                                    // Include de Init headers
    CURLOPT_RETURNTRANSFER  => true,                                                            // Put response in the returnvalue of curl_exec() for further processing
];

// perform Curl to receive valid session Token.
try {
    curl_setopt_array($ch, $options);
    if( ! $result = curl_exec($ch)) {
        trigger_error(curl_error($ch));
    }
}catch(Exception $e){           // Might also be ValueError :S
    echo 'Caught exception: ',  $e->getMessage(), "\n";
    exit;
}

var_dump($result);

// A nice thing to do would be to catch the HTTP 200 (OK) here and validate the response.
// I assume the request is succesfull, else an error will be shown by var_dump;
// But im lazy in debugging scripts.

// Lets create our ticket;
$ticketHeaders = ['Content-Type: application/json',
                  'App-Token: $appToken',
                  "Session-Token: ".json_decode($result)->session_token,
               ];

// ReInitialize CURL params.
$options = [
    CURLOPT_URL             => $glpiUrl.'/apirest.php/Ticket',
    CURLOPT_HEADER          => false,
    CURLOPT_POST            => true,
    CURLOPT_TIMEOUT         => 4,
    CURLOPT_HTTPHEADER      => $ticketHeaders,
    CURLOPT_POSTFIELDS      => $ticketBody,
    CURLOPT_RETURNTRANSFER  => true,
];

// perform Curl to receive valid session Token.
try {
    curl_setopt_array($ch, $options);
    if( ! $result = curl_exec($ch)) {
        trigger_error(curl_error($ch));
    }
}catch(ValueError $e){
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

var_dump($result);

curl_close($ch);

