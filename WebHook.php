<?php
include('Base.php');

/**
 * Web hook is receiver data from outside, processing and sending it to Bitrix24
 */
class WebHook extends Base
{

    /**
     * Api url for Bitrix24
     * @var string
     */
    public $apiUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68';

    /**
     * Api url for Triggers
     * @var
     */
    protected $triggerApiUrl = 'https://a-nevski.bitrix24.ru/rest/1/yjonnh47ijv34jjh';

    /**
     * Business process id for Bitrix24
     * @var int
     */
    public $businessProcessId = 346;

    /**
     * Base function for processing data and logical actions
     */
    public function processing(){

    }

}
