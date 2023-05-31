<?php

namespace ACME\Mpesa\Payment;

use Webkul\Payment\Payment\Payment;

class Mpesa extends Payment
{
    /**
     * Payment method code
     *
     * @var string
     */
    protected $code  = 'mpesa';

    public function getRedirectUrl()
    {
        
    }
}