<?php

namespace App\Services;

use SoapClient;
use Exception;

class SmsService
{
    public function send(string $mobile, string $code): mixed
    {
        try {

    ini_set('soap.wsdl_cache_enabled', 0);

    $client = new SoapClient(
        config('sms.wsdl'),
        [
            'encoding' => 'UTF-8',
        ]
    );

    $data = [
        'username' => config('sms.username'),
        'password' => config('sms.password'),
        'text'     => [$code],
        'to'       => $mobile,
        'bodyId'   => config('sms.body_id'),
    ];
    return $client
        ->SendByBaseNumber($data)
        ->SendByBaseNumberResult;

    } catch (Exception $exception) {

        report($exception);

        return false;
    }

    }

}
