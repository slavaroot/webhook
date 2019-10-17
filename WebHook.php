<?php
/**
 * Web hook is receiver data from outside, processing and sending it to BitrixCRM
 */

class WebHook extends Base
{

    /**
     * Api url for BitrixCRM
     * @var string
     */
    public $apiUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/';

    /**
     * Business process id for BitrixCRM
     * @var int
     */
    public $businessProcessId = 346;

    /**
     * Base function for processing data and logical actions
     */
    public function processing(){

    }

}