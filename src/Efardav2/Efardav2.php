<?php

namespace Larabookir\Gateway\Efardav2;

use Larabookir\Gateway\Efarda\Efarda;

class Efardav2 extends Efarda
{

    protected $mobileNumber;

    protected $baseApi = 'https://pf.efarda.ir/pf/api/';

    protected $traceApi = 'ipg/getTraceId';

    protected $purchaseApi = 'ipg/purchase';

    protected $verifyApi = 'ipg/verify';
}
