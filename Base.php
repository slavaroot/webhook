<?php

/**
 * Base class contains api methods for Bitrix24 and other common functions
 */
class Base
{
    /**
     * Api url for Bitrix24
     * @var string
     */
    protected $apiUrl;

    /**
     * Api url for Triggers
     * @var
     */
    protected $triggerApiUrl;

    /**
     * Business process id for Bitrix24
     * @var int
     */
    protected $businessProcessId;

    /**
     * Updating contact in Bitrix24
     * @param $result
     * @param $request
     * @return mixed
     */
    public function updateContact($result, $request){
        $userParameters = array();
        $userParameters["UF_CRM_5D67B01F896D7"] = $request["web_date"];
        $userParameters["UF_CRM_5D67B01F49CD8"] = $request["web_link"];
        $userParameters["UF_CRM_5D67B01D77970"] = $request["IDWEB"];
        $data = array(
            'id' => $result->result[0]->ID,
            'fields' => $userParameters,
            'params' => array("REGISTER_SONET_EVENT" => "Y"),
        );
        $parameters = http_build_query($data);

        return $this->sendRequest('crm.contact.update.json', $parameters);
    }

    /**
     * Updating deal in Bitrix24
     * @param $deal
     * @param $dAmount
     * @param $request
     * @return mixed
     */
    public function updateDeal(
        $deal,
        $dAmount,
        $request
    ){
        $oldAmount = $deal->result->UF_CRM_1529156721;
        $dealId = $request["deal_id"];
        $tranId = $request["payment"]["systranid"];
        $pay = $request["pay"];
        $prolongation = $request["payment"]["products"][0]["options"][0]["variant"];

        $data = array(
            'id' => $dealId,
            'fields' => array(
                "UF_CRM_1529156721" => $oldAmount + $dAmount,
                "UF_CRM_5C44C164E80CC" => $tranId,
            ),
            'params' => array("REGISTER_SONET_EVENT" => "Y"),
        );

        if (isset($promocode)) {
            $data['fields']['UF_CRM_5CEFFABAC23ED'] = $promocode;
        }
        if ($pay == 'prolongation') {
            if ($prolongation == "1 месяц")
                $data['fields']['UF_CRM_1527763052'] = 470;
            elseif ($prolongation == "2 месяца") {
                $data['fields']['UF_CRM_1527763052'] = 472;
            } elseif ($prolongation == "4 месяца") {
                $data['fields']['UF_CRM_1527763052'] = 1440;
            } else {
                $data['fields']['UF_CRM_1527763052'] = '';
            }
        } else if ($pay == 'credit') {
            $data['fields']['UF_CRM_1560926337'] = $request["num"];
            $data['fields']['UF_CRM_1560925916'] = $request["fnum"];
        }

        $parameters = http_build_query($data);
        return $this->sendRequest('crm.deal.update.json', $parameters);
    }

    /**
     * Update lead if repeat exists
     * @param $normalizedPhone
     * @param $result
     * @param $request
     * @return mixed
     */
    public function updateLeadForRepeat(
        $normalizedPhone,
        $result,
        $request
    ){
        $userParameters = array();
        if (!empty($normalizedPhone) && !empty($email)) {
            $userParameters["PHONE"] = array(array("VALUE" => $normalizedPhone, "VALUE_TYPE" => "WORK"));
            $userParameters["EMAIL"] = array(array("VALUE" => $email, "VALUE_TYPE" => "WORK"));
        }
        if (isset($request["type"]))
            $userParameters["UF_CRM_TYPE"] = $request["type"];
        if (isset($request["Height"]))
            $userParameters["UF_CRM_5B16C0A186F1B"] = $request["Height"];
        if (isset($request["Mass"]))
            $userParameters["UF_CRM_5B16C0A192A33"] = $request["Mass"];
        if (isset($request["Age"]))
            $userParameters["UF_CRM_5B16C0A21AB47"] = $request["Age"];
        if (isset($request['IDWEB'])) {
            $userParameters['UF_CRM_1567072808032'] = $request['IDWEB'];
        }

        $data = array(
            'id' => $result->result[0]->ID,
            'fields' => $userParameters,
            'params' => array("REGISTER_SONET_EVENT" => "Y"),
        );
        $parameters = http_build_query($data);

        return $this->sendRequest('crm.lead.update.json', $parameters);
    }

    /**
     * Update lead in Bitrix24
     * @param $result
     * @param $discount
     * @param $email
     * @param $promoCode
     * @param $request
     * @return bool|string
     */
    public function updateLead(
        $result,
        $discount,
		$is_percent,
        $email,
        $promoCode,
        $request
    ){
        $amount = $request["payment"]["products"][0]["amount"];

        if ((bool)$is_percent) {
            $dAmount = $amount * $discount;
        } else {
            $dAmount = $amount - $discount;
            $dAmount = max(0, $dAmount);
        }

        $userParameters = array(
            "UF_CRM_1547493000073" => $request["payment"]["systranid"],
            "UF_CRM_1547492931256" => $dAmount,
        );
        if (isset($promoCode) && !empty($promoCode))
			$userParameters["UF_CRM_1559231074"] = $promoCode;
        if (isset($request["rtype"]))
            $userParameters["UF_CRM_1553250302"] = $request["rtype"];
        if (isset($request['IDWEB']))
            $userParameters["UF_CRM_5D67B01D77970"] = $request["IDWEB"];
        if (!empty($normalizedPhone) && !empty($email)) {
            $userParameters["EMAIL"] = array(array("VALUE" => $email, "VALUE_TYPE" => "WORK"));
        }

        $data = array(
            'id' => $result->result[0]->ID,
            'fields' => $userParameters,
            'params' => array("REGISTER_SONET_EVENT" => "Y"),
        );
        $parameters = http_build_query($data);

        return $this->sendRequest('crm.lead.update.json', $parameters);
    }

    /**
     * Creating lead in Bitrix24
     * @param $name
     * @param $normalizedPhone
     * @param $email
     * @param $systranid
     * @param $amount
     * @param $discount
     * @param $isPercent
     * @param $promoCode
     * @param $request
     * @return mixed
     */
    public function createLead(
        $name,
        $normalizedPhone,
        $email,
        $systranid,
        $amount,
        $discount,
        $isPercent,
        $promoCode,
        $request
    ){
        $userParameters = array(
            "NAME" => $name,
            "STATUS_ID" => "NEW",
            "OPENED" => "Y",
            "PHONE" => array(array("VALUE" => $normalizedPhone, "VALUE_TYPE" => "WORK")),
        );

        if (isset($email))
            $userParameters["EMAIL"] = array(array("VALUE" => $email, "VALUE_TYPE" => "WORK"));
        if (isset($systranid))
            $userParameters["UF_CRM_1547493000073"] = $systranid;
        if (isset($amount)) {
            if ((bool)$isPercent) {
                $dAmount = $amount * $discount;
            } else {
                $dAmount = $amount - $discount;
                $dAmount = max(0, $dAmount);
            }
            $userParameters["UF_CRM_1547492931256"] = $dAmount;
        }
        if (isset($request["type"]))
            $userParameters["UF_CRM_TYPE"] = $request["type"];
        if (isset($request["utm_campaign"]))
            $userParameters["UTM_CAMPAIGN"] = $request["utm_campaign"];
        if (isset($request["UTM_CONTENT"]))
            $userParameters["UTM_CONTENT"] = $request["UTM_CONTENT"];
        if (isset($request["utm_medium"]))
            $userParameters["UTM_MEDIUM"] = $request["utm_medium"];
        if (isset($request["utm_source"]))
            $userParameters["UTM_SOURCE"] = $request["utm_source"];
        if (isset($request["utm_term"]))
            $userParameters["UTM_TERM"] = $request["utm_term"];
        if (isset($request["tranid"]))
            $userParameters["UF_CRM_TRANID"] = $request["tranid"];
        if (isset($request["formname"]))
            $userParameters["UF_CRM_FORMNAME"] = $request["formname"];
        if (isset($request["payment"]["systranid"]))
            $userParameters["UF_CRM_1547493000073"] = $request["payment"]["systranid"];
        if (isset($request["payment"]["products"][0]["amount"])) {
            if ((bool)$isPercent) {
                $dAmount = $request["payment"]["products"][0]["amount"] * $discount;
            } else {
                $dAmount = $request["payment"]["products"][0]["amount"] - $discount;
                $dAmount = max(0, $dAmount);
            }
            $userParameters["UF_CRM_1547492931256"] = $dAmount;
        }
        if (isset($request["Height"]))
            $userParameters["UF_CRM_5B16C0A186F1B"] = $request["Height"];
        if (isset($request["Mass"]))
            $userParameters["UF_CRM_5B16C0A192A33"] = $request["Mass"];
        if (isset($request["Age"]))
            $userParameters["UF_CRM_5B16C0A21AB47"] = $request["Age"];
        if (isset($request["rtype"]))
            $userParameters["UF_CRM_1553250302"] = $request["rtype"];
        if (isset($request['IDWEB']))
            $userParameters['IDWEB'] = $request['IDWEB'];
        if (isset($promoCode))
            $userParameters["UF_CRM_1559231074"] = $promoCode;
        if (isset($request['IDWEB']))
            $userParameters['UF_CRM_1567072808032'] = $request['IDWEB'];

        $parameters = http_build_query(array(
            'fields' => $userParameters,
            'params' => array("REGISTER_SONET_EVENT" => "Y")
        ));

        return $this->sendRequest('crm.lead.add.json', $parameters);
    }

    /**
     * Start business process for lead by id
     * @param $leadId
     * @return mixed
     */
    public function startBusinessProcess($leadId)
    {
        $parameters = http_build_query([
            'TEMPLATE_ID' => $this->businessProcessId,
            'DOCUMENT_ID' => ['crm', 'CCrmDocumentLead', $leadId],
        ]);
        return $this->sendRequest('bizproc.workflow.start', $parameters, 'GET');
    }

    /**
     * Normalize phone number to one format
     * @param $phone
     * @return string|string[]|null
     */
    public function normalizePhoneNumber($phone){
        $normalizedPhone = preg_replace("/[^+0-9]/", "", $phone);
        $plus = (substr($normalizedPhone, 0, 1) === "+");
        $normalizedPhone = preg_replace("/[^0-9]/", "", $phone);
        if ($plus) $normalizedPhone = "+" . $normalizedPhone;
        return $normalizedPhone;
    }

    /**
     * Find lead by transaction id
     * @param $transactionId
     * @return mixed
     */
    public function findLeadByTransactionID($transactionId){
        $parameters = http_build_query(array(
            'filter' => array("UF_CRM_1547493000073" => $transactionId),
            'select' => array("ID", "TITLE", "ASSIGNED_BY_ID", "UF_CRM_1547492931256")
        ));
        return $this->sendRequest('crm.lead.list.json', $parameters);
    }

    /**
     * Find deal by id
     * @param $dealId
     * @return mixed
     */
    public function getDealByID($dealId){
        $parameters = http_build_query(array(
            'id' => $dealId
        ));
        return $this->sendRequest('crm.deal.get.json', $parameters);
    }

    /**
     * Find deal by transaction id
     * @param $transactionId
     * @return mixed
     */
    public function findDealByTransactionID($transactionId){
        $parameters = http_build_query(array(
            'filter' => array("UF_CRM_5C44C164E80CC" => $transactionId),
            'select' => array("ID")
        ));
        return $this->sendRequest('crm.deal.list.json', $parameters);
    }

    /**
     * Find lead by phone
     * @param $phone
     * @return mixed
     */
    public function findLeadByPhone($phone){
        $parameters = http_build_query(array(
            'filter' => array("PHONE" => $phone),
            'select' => array("ID", "TITLE", "ASSIGNED_BY_ID")
        ));
        return $this->sendRequest('crm.lead.list.json', $parameters);
    }

    /**
     * Find contact by phone
     * @param $phone
     * @return mixed
     */
    public function findContactByPhone($phone){
        $parameters = http_build_query(array(
            'filter' => array("PHONE" => $phone),
            'select' => array("*")
        ));
        return $this->sendRequest('crm.contact.list.json', $parameters);
    }

    /**
     * Find lead by email in Bitrix24
     * @param $email
     * @return mixed
     */
    public function findLeadByEmail($email){
        $parameters = http_build_query(array(
            'filter' => array("EMAIL" => $email),
            'select' => array("ID", "TITLE", "ASSIGNED_BY_ID")
        ));
        return $this->sendRequest('crm.lead.list.json', $parameters);
    }

    /**
     * Find contact by email in Bitrix24
     * @param $email
     * @return mixed
     */
    public function findContactByEmail($email){
        $parameters = http_build_query(array(
            'filter' => array("EMAIL" => $email),
            'select' => array("*")
        ));
        return $this->sendRequest('crm.contact.list.json', $parameters);
    }

    /**
     * Start automation trigger in Bitrix24
     * @param $parameters
     * @return mixed
     */
    public function startTrigger($parameters){
        return $this->sendRequest('crm.automation.trigger', $parameters, 'GET');
    }

    /**
     * Write comment in feed for lead in Bitrix24
     * @param $header
     * @param $text
     * @param $leadId
     * @param $assignee
     * @return mixed
     */
    public function writeLeadComment($header, $text, $leadId, $assignee){
        $parameters = http_build_query(array(
            'fields' => array(
                "POST_TITLE" => $header,
                "MESSAGE" => $text,
                "SPERM" => array(
                    "U" => array("U" . $assignee)
                ),
                "ENTITYTYPEID" => 1,
                "ENTITYID" => $leadId,
            )
        , 'params' => array("REGISTER_SONET_EVENT" => "Y")
        ));
        return $this->sendRequest('crm.livefeedmessage.add.json', $parameters);
    }

    /**
     * Write comment in feed for deal in Bitrix24
     * @param $header
     * @param $text
     * @param $dealId
     * @param $assignee
     * @return mixed
     */
    public function writeDealComment($header, $text, $dealId, $assignee){
        $parameters = http_build_query(array(
            'fields' => array(
                "POST_TITLE" => $header,
                "MESSAGE" => $text,
                "SPERM" => array(
                    "U" => array("U" . $assignee)
                ),
                "ENTITYTYPEID" => 2,
                "ENTITYID" => $dealId,
            )
        , 'params' => array("REGISTER_SONET_EVENT" => "Y")
        ));
        return $this->sendRequest('crm.livefeedmessage.add.json', $parameters);
    }

    /**
     * Post message to chat in Bitrix24
     * @param $userId
     * @param $message
     * @return mixed
     */
    public function postToChat($userId, $message){
        $parameters = http_build_query(
            array(
                "USER_ID" => $userId,
                "MESSAGE" => $message,
            )
        );
        return $this->sendRequest('im.message.add.json', $parameters);
    }

    /**
     * Find promo by name from Bitrix24
     * @param $name
     * @return mixed
     */
    public function findPromoByName($name){
        $parameters = http_build_query(array(
            'IBLOCK_TYPE_ID' => 'lists',
            'IBLOCK_ID' => '44',
            'FILTER' => array("NAME" => $name)
        ));
        return $this->sendRequest('lists.element.get.json', $parameters);
    }

    /**
     * Base function for send HTTP request with Curl
     * @param $method
     * @param $parameters
     * @param string $type
     * @return mixed
     */
    private function sendRequest($method, $parameters = '', $type = 'POST') {

        $curl = curl_init();

        $options = [
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
        ];

        if ($type == 'POST') {
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_URL] = $this->apiUrl . '/' . $method;
            $options[CURLOPT_POSTFIELDS] = $parameters;
        } else {
            $options[CURLOPT_URL] = $this->apiUrl . '/' . $method . '?' . $parameters;
        }

        curl_setopt_array($curl, $options);

        $result = curl_exec($curl);
        $json = json_decode($result);

        curl_close($curl);

        return $json;
    }
}
