<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Phone Validation Country
    |--------------------------------------------------------------------------
    |
    | This value determines which country's phone number format to validate.
    | Supported values: 'IN' (India) or 'AE' (UAE)
    | Default: 'IN' (India)
    |
    | Set this in your ".env" file:
    | PHONE_VALIDATION_COUNTRY=IN
    | or
    | PHONE_VALIDATION_COUNTRY=AE
    |
    */

    'validation_country' => env('PHONE_VALIDATION_COUNTRY', 'AE'),

    /*
    |--------------------------------------------------------------------------
    | Phone Validation Rules
    |--------------------------------------------------------------------------
    |
    | Define validation rules for each supported country.
    |
    */

    'rules' => [
        // 'IN' => [
        //     'regex' => '/^\+91[6-9]\d{9}$/',
        //     'pattern' => '+91XXXXXXXXXX',
        //     'error_message' => 'Please enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9',
        //     'country_code' => '+91',
        //     'length' => 10, // digits after country code
        //     'starts_with' => ['6', '7', '8', '9'],
        // ],
        'AE' => [
            'regex' => '/^\+971(2|3|4|6|7|9|50|52|54|55|56|58)\d{7}$/',
            'pattern' => '+971XXXXXXXXX',
            'error_message' => 'Please enter a valid UAE mobile number',
            'country_code' => '+971',
            'length' => 9, // digits after country code
            'starts_with' => ['2', '3', '4', '6', '7', '9', '50', '52', '54', '55', '56', '58'],
        ],
    ],


];

