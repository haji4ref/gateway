<?php

namespace Larabookir\Gateway\Efarda;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Request;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;
use Larabookir\Gateway\Restable;

class Efarda extends PortAbstract implements PortInterface, Restable
{

    protected $mobileNumber;

    protected $baseApi = 'https://mpg.ba24.ir/mpg/api/';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    protected $gateUrl = '';

    protected $gateParams = [];

    /**
     * Makes an api client and configs it and returns the client
     *
     * @return \GuzzleHttp\Client
     */
    private function getApiClient()
    {
        $config = [];
        if (env('PROXY_ENABLE')) {
            $config['proxy'] = 'http://' .
                env('PROXY_USER') .
                ':' .
                env('PROXY_PASS') .
                '@' .
                env('PROXY_HOST') .
                ':' .
                env('PROXY_PORT');
        }

        return new Client($config);
    }

    /**
     * Verifies payment
     *
     * @param object $transaction
     *
     * @return $this|\Larabookir\Gateway\PortAbstract|\Larabookir\Gateway\PortInterface
     * @throws \Larabookir\Gateway\Efarda\EfardaErrorException
     */
    public function verify($transaction)
    {
        parent::verify($transaction);

        $this->verifyPayment($transaction);

        return $this;
    }

    /**
     * Sets amount
     *
     * {@inheritdoc}
     */
    public function set($amount)
    {
        $this->amount = intval($amount);

        return $this;
    }

    /**
     * Makes request to api and get gateway
     *
     * @inheritDoc
     * @throws \Larabookir\Gateway\EFarda\EfardaErrorException
     */
    public function ready()
    {
        $this->sendPayRequest();

        return $this;
    }

    /**
     * Sets callback url
     *
     * @inheritDoc
     */
    public function setCallback($url)
    {
        $this->callbackUrl = $url;

        return $this;
    }

    /**
     * Returns default callback url or configured url
     *
     * @inheritDoc
     */
    public function getCallback()
    {
        if (!$this->callbackUrl)
            $this->callbackUrl = $this->config->get('gateway.efarda.callback-url');

        return env('APP_URL') . $this->callbackUrl;
    }

    /**
     * Return redirect to view of gateway
     *
     * @inheritDoc
     */
    public function redirect()
    {

        $url = $this->gateUrl . $this->refId();

        return \View::make('gateway::parsian-redirector')->with(compact('url'));
    }

    /**
     * Send Request to get gateway
     *
     * @throws \Larabookir\Gateway\EFarda\EfardaErrorException
     */
    protected function sendPayRequest()
    {
        $this->newTransaction();

        $traceNumber = $this->getTraceId();

        $params = $this->getGatewayPage($traceNumber);

        $this->gateUrl = $params['url'];

        $this->gateParams['Token'] = $params['Token'];
    }

    /**
     * Sets Mobile number for get gateway
     *
     * @param $number
     *
     * @return $this
     */
    public function setMobileNumber($number)
    {
        $this->mobileNumber = $number;

        return $this;
    }

    /**
     * Make request to get traceid from bank
     *
     * @return mixed
     * @throws \Larabookir\Gateway\Efarda\EfardaErrorException
     */
    private function getTraceId()
    {
        $client = $this->getApiClient();

        $params = [
            RequestOptions::JSON => [
                'username'          => $this->config->get('gateway.efarda.username'),
                'password'          => $this->config->get('gateway.efarda.password'),
                'additionalData'    => "",
                'callBackUrl'       => $this->getCallback(),
                'amount'            => $this->amount . "",
                'serviceAmountList' => [
                    [
                        'serviceId' => $this->config->get('gateway.efarda.serviceId'),
                        'amount'    => $this->amount . ""
                    ]
                ],
                'Mobile'            => $this->mobileNumber ? $this->mobileNumber : $this->config->get('gateway.efarda.mobile', ''),
            ]
        ];
        try {
            $res = $client->post($this->baseApi . 'ipgGetTraceId', $params);

            $body = json_decode($res->getBody()->getContents(), true);

            if ($body['result'] !== "0") {
                $errorMessage = EfardaResult::errorMessage(intval($body['result']));

                $this->transactionFailed();

                $this->newLog(intval($body['result']), $body['description']);

                throw new EfardaErrorException($errorMessage, intval($body['result']));
            } else {
                $traceNumber = $body['traceNumber'];

                $status = intval($body['result']);

                if ($traceNumber && $status == 0) {
                    $this->refId = $traceNumber;

                    $this->transactionSetRefId();

                    return $traceNumber;
                }
            }
        } catch (ClientException $exception) {
            $this->transactionFailed();

            $this->newLog($exception->getCode(), $exception->getMessage());

            throw new EfardaErrorException('مشکلی در ارتباط با درگاه پیش آمده لطفا از درگاه دیگری استفاده کنید', $exception->getCode());
        }
    }

    /**
     * Gets the gateway url and its parameters
     *
     * @param $traceNumber
     *
     * @return array
     */
    private function getGatewayPage($traceNumber)
    {

        $client = $this->getApiClient();

        $params = [
            'form_params' => [
                'username'    => $this->config->get('gateway.efarda.username'),
                'traceNumber' => intval($traceNumber)
            ],
            'headers'     => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ];

        $body = $client->post($this->baseApi . 'ipgPurchase', $params)->getBody()->getContents();

        return [
            'url'   => $this->findURL($body),
            'Token' => $this->findToken($body)
        ];
    }

    /**
     * Makes the verify request to the bank and executes it
     *
     * @param $traceNumber
     *
     * @throws \Larabookir\Gateway\Efarda\EfardaErrorException
     */
    private function doVerify($traceNumber)
    {
        $client = $this->getApiClient();

        $params = [
            RequestOptions::JSON => [
                'username'    => $this->config->get('gateway.efarda.username'),
                'password'    => $this->config->get('gateway.efarda.password'),
                'traceNumber' => $traceNumber
            ],
            'headers'            => [
                'Content-Type' => 'application/json'
            ]
        ];

        try {
            $res = $client->post($this->baseApi . 'ipgPurchaseVerify', $params);

            $body = json_decode($res->getBody()->getContents(), true);
        } catch (\Exception $e) {
            throw new EfardaErrorException($e->getMessage(), -1);
        }

        if (!$body)
            throw new EfardaErrorException('پاسخ دریافتی از بانک نامعتبر است.', -1);
        if ($body['result'] != 0) {
            $errorMessage = Request::input('desc');;
            $this->transactionFailed();
            $this->newLog($body['result'], $errorMessage);
            throw new EfardaErrorException($errorMessage, $body['result']);
        }

        $this->trackingCode = $body['rrn'];
        //$this->cardNumber = $result->ConfirmPaymentResult->CardNumberMasked;
        $this->transactionSucceed();
        $this->newLog($body['result'], EfardaResult::errorMessage($body['result']));
    }

    /**
     * Finds url of the bank in html that returned
     *
     * @param $string
     *
     * @return false|string
     */
    private function findURL($string)
    {
        $start = strpos($string, "action=") + 8;

        $end = strpos($string, '"', $start);

        return substr($string, $start, $end - $start);
    }

    /**
     * Finds token of the gateway in html that returned
     *
     * @param $string
     *
     * @return false|string
     */
    private function findToken($string)
    {
        $start = strpos($string, "value=") + 7;

        $end = strpos($string, '"', $start);

        return substr($string, $start, $end - $start);
    }

    /**
     * Return gateway url of the Efarda
     *
     * @return string
     */
    public function getGatewayUrl()
    {
        return $this->gateUrl;
    }

    /**
     * Returns parameters that will be needed in the gateway redirection
     *
     * @return array
     */
    public function redirectParameters()
    {
        return $this->gateParams;
    }

    /**
     * Verifies payment first by checking status and some other inputs
     *
     * @param $transaction
     *
     * @throws \Larabookir\Gateway\Efarda\EfardaErrorException
     */
    private function verifyPayment($transaction)
    {
        if (!Request::has('traceNumber') && !Request::has('result'))
            throw new EfardaErrorException('درخواست غیر معتبر', -1);

        $traceNumber = Request::input('traceNumber');
        $result = intval(Request::input('result'));

        if ($result != 0) {
            $errorMessage = Request::input('desc');
            $this->newLog($result, $errorMessage);
            throw new EfardaErrorException($errorMessage, $result);
        }

        if ($this->refId != $traceNumber)
            throw new EfardaErrorException('تراکنشی یافت نشد', -1);

        $this->doVerify($traceNumber);
    }
}
