<?php

namespace Larabookir\Gateway\Efarda;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;

class Efarda extends PortAbstract implements PortInterface {

    protected $mobileNumber;

    protected $baseApi = 'https://mpg.ba24.ir/mpg/api/';

    private function getApiClient()
    {
        $config = [];
        if(env('APP_ENV') === 'dev') {
            $config['proxy'] = env("PROXY");
        }

        return new Client($config);
    }

    /**
     * {@inheritdoc}
     */
    public function set($amount)
    {
        $this->amount = intval($amount);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function ready()
    {
        $this->sendPayRequest();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setCallback($url)
    {
        $this->callbackUrl = $url;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getCallback()
    {
        if( ! $this->callbackUrl)
            $this->callbackUrl = $this->config->get('gateway.efarda.callback-url');

        return $this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]);
    }

    /**
     * @inheritDoc
     */
    public function redirect()
    {
        // TODO: Implement redirect() method.
    }

    /**
     * @throws \Larabookir\Gateway\EFarda\EfardaErrorException
     */
    protected function sendPayRequest()
    {
        $this->newTransaction();

        $traceNumber = $this->getTraceId();

        //$this->getGatewayPage($traceNumber);
        dd($traceNumber);
        //$res = $client->request('POST', $this->getTokenApi, [
        //    'form_params' => $params,
        //    'headers'     => [
        //        'Content-Type' => 'application/json'
        //    ]
        //]);

        //if( ! isset($response->SalePaymentRequestResult)
        //    || isset($response->SalePaymentRequestResult)
        //    && ! isset($response->SalePaymentRequestResult->Token)
        //    || isset($response->SalePaymentRequestResult->Token)
        //    && $response->SalePaymentRequestResult->Token == '') {
        //    $errorMessage = ParsianResult::errorMessage($response->SalePaymentRequestResult->Status);
        //    $this->transactionFailed();
        //    $this->newLog($response->SalePaymentRequestResult->Status, $errorMessage);
        //    throw new ParsianErrorException($errorMessage, $response->SalePaymentRequestResult->Status);
        //}
        //if($response !== false) {
        //    $authority = $response->SalePaymentRequestResult->Token;
        //    $status = $response->SalePaymentRequestResult->Status;
        //
        //    if($authority && $status == 0) {
        //        $this->refId = $authority;
        //        $this->transactionSetRefId();
        //
        //        return true;
        //    }
        //
        //    $errorMessage = ParsianResult::errorMessage($status);
        //    $this->transactionFailed();
        //    $this->newLog($status, $errorMessage);
        //    throw new ParsianErrorException($errorMessage, $status);
        //
        //} else {
        //    $this->transactionFailed();
        //    $this->newLog(- 1, 'خطا در اتصال به درگاه پارسیان');
        //    throw new ParsianErrorException('خطا در اتصال به درگاه پارسیان', - 1);
        //}
    }

    public function setMobileNumber($number)
    {
        $this->mobileNumber = $number;

        return $this;
    }

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

        $res = $client->post($this->baseApi . 'ipgGetTraceId', $params);

        $body = json_decode($res->getBody()->getContents(), true);

        if($body['result'] !== "0") {
            $errorMessage = EfardaResult::errorMessage(intval($body['result']));
            $this->transactionFailed();
            $this->newLog(intval($body['result']), $body['description']);
            throw new EfardaErrorException($errorMessage, intval($body['result']));
        } else {
            $traceNumber = $body['traceNumber'];
            $status = intval($body['result']);

            if($traceNumber && $status == 0) {
                $this->refId = $traceNumber;
                $this->transactionSetRefId();

                return $traceNumber;
            }
        }

    }

    private function getGatewayPage($traceNumber)
    {
        $client = $this->getApiClient();

        $params = [
            RequestOptions::JSON => [
                'username'    => $this->config->get('gateway.efarda.username'),
                'traceNumber' => "asda" . ""
            ]
        ];

        $res = $client->post($this->baseApi . 'ipgPurchase', $params);

        $body = json_decode($res->getBody()->getContents(), true);

        dd($res);
    }

}
