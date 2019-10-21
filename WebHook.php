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
     * Promo code for lead
     * @var string
     */
    private $promoCode = '';

    /**
     * Is discount percent or int?
     * True - float
     * False - integer
     * @var bool
     */
    private $isPercent;

    /**
     * Discount value (0.1 - 10%)
     * @var float
     */
    private $discount;

    /**
     * Base function for processing data and logical actions
     */
    public function processing(){
        $request = $_POST;
        if (isset($request["tel"])) {
            /**
             * Processing data for update lead
             */
            $this->processingUpdateLead();
        } else if (isset($request["pay"])) {
            /**
             * Processing request for pushing lead
             */
            $this->processingOfPayment();
        } else {
            /**
             * Processing request for creating lead
             */
            $this->processingOfCreationLead();
        }
    }

    /**
     * Logic for promo code
     * @param $request
     */
    public function promoCodeProcessing($request){
        if (isset($request["payment"]["promocode"])) {
            $this->promoCode = $request["payment"]["promocode"];
            $this->discount = $request["payment"]["discountvalue"];
            $this->findAndSavePromo();
        } else {
            $this->promoCode = NULL;
            $this->isPercent = True;
            $this->discount = 1;
        };
    }
    
    /**
     * Calculating discount by the promo code and save it in variable
     */
    public function findAndSavePromo(){
        $promoList = $this->findPromoByName($this->promoCode);
        $rPromoCode = $promoList->result[0]->NAME;
        $rPercent = (array)$promoList->result[0]->PROPERTY_158;
        $rPercent = array_shift($rPercent);

        if ($rPercent == 108) {
            $rPercent = true;
        } else {
            $rPercent = false;
        }
        
        $r_discount = (array)$promoList->result[0]->PROPERTY_160;
        $r_discount = (int)(array_shift($r_discount));
        
        $matches = null;
        $this->isPercent = preg_match(
            '/([0-9]{1,2}|100)%/',
            $this->discount,
            $matches,
            PREG_OFFSET_CAPTURE,
            0
        );
        
        if (($rPromoCode == $this->promoCode) && ($this->isPercent == $rPercent) && ($r_discount == intval($this->discount))) {
            if ((bool)$this->isPercent) {
                $this->discount = 1 - intval($this->discount) / 100;
            } else {
                $this->discount = intval($this->discount);
            }
        } else {
            $this->promoCode = NULL;
            $this->isPercent = True;
            $this->discount = 1;
        }
    }

    /**
     * First scenario of logic
     */
    public function processingUpdateLead(){
        $request = $_POST;
        $phone = $request["tel"];
        $name = $request["Name"];
        $email = $request["Email"];
        $normalizedPhone = $this->normalizePhoneNumber($phone);

        /**
         * Processing of promo code
         * Checking existence promo in Tilda
         * If code found - save it, else - NULL
         */
        $this->promoCodeProcessing($request);

        if (empty($normalizedPhone) && empty($email)) {
            return;
        }

        /**
         * Checking lead in Bitrix
         */
        $json = NULL;

        if (!empty($normalizedPhone)) {
            $json = $this->findLeadByPhone($phone);
        }

        if (!empty($email) && (empty($normalizedPhone) || $json->total == 0)) {
            $json = $this->findLeadByEmail($email);
        }

        /**
         * Update or create new lead
         */
        if (isset($json->error) || (isset($json->result) && empty($json->result))) {
            if (empty($name)) {
                $name = $phone;
                if (isset($email)) $name = $email;
            }
            $systranid = $request["payment"]["systranid"];
            $amount = $request["payment"]["products"][0]["amount"];

            $json = $this->createLead(
                $name,
                $normalizedPhone,
                $email,
                $systranid,
                $amount,
                $this->discount,
                $this->isPercent,
                $this->promoCode,
                $request
            );

            $this->startTrigger("target=LEAD_" . $json->result . "&code=oncpa");
            $this->startBusinessProcess($json->result);
        } else {
            $this->updateLead(
                $json,
                $this->discount,
                $email,
                $this->promoCode,
                $request
            );

            $this->startBusinessProcess($json->result[0]->ID);
            $this->startTrigger("target=LEAD_" . $json->result[0]->ID . "&code=oncpa");
        }
    }

    /**
     * Second scenario of logic
     * Processing of payment
     */
    public function processingOfPayment(){
        $request = $_POST;
        $dealId = $request["deal_id"];
        $tranId = $request["payment"]["systranid"];
        $newAmount = $request["payment"]["products"][0]["amount"];
        $pay = $request["pay"];

        if ($this->findDealByTransactionId($tranId)) {
            return;
        }

        /**
         * Processing of promo code
         * Checking existence promo in Tilda
         * If code found - save it, else - NULL
         */
        $this->promoCodeProcessing($request);

        if ((bool)$this->isPercent) {
            $dAmount = $newAmount * $this->discount;
        } else {
            $dAmount = $newAmount - $this->discount;
            $dAmount = max(0, $dAmount);
        }

        $deal = $this->getDealByID($dealId);

        $productName = $request["payment"]["products"][0]["name"];
        $assigner = $deal->result->ASSIGNED_BY_ID;
        $prolongation = $request["payment"]["products"][0]["options"][0]["variant"];

        $result = $this->updateDeal($deal, $dAmount, $request);

        if ($pay == "partial") {
            print $this->startTrigger("target=DEAL_" . $dealId . "&code=5utbM");
        } elseif ($pay == 'full') {
            print $this->startTrigger("target=DEAL_" . $dealId . "&code=HpWIx");
        } elseif ($pay == 'prolongation') {
            print $this->startTrigger("target=DEAL_" . $dealId . "&code=ZKX6s");
        } elseif ($pay == 'credit') {
            print $this->startTrigger("target=DEAL_" . $dealId . "&code=ePvAO");
        }

        print $result;
        if ($pay == 'prolongation')
            print $this->writeDealComment(
                "Произведён платёж",
                "Произведен платеж на сумму " . $newAmount . " за " . $productName .
                ". Продолжительность пролонгации " . $prolongation . ". Номер транзакции " . $tranId . " от " .
                date("d.m.Y"),
                $dealId,
                $assigner
            );
        else
            print $this->writeDealComment(
                "Произведён платёж",
                "Произведен платеж на сумму " . $newAmount . " за " . $productName .
                ". Номер транзакции " . $tranId . " от " . date("d.m.Y"),
                $dealId,
                $assigner
            );
    }

    /**
     * Third scenario of logic
     * Creation lead 
     */
    public function processingOfCreationLead(){
        $request = $_POST;
        if (!isset($request["Phone"]) || !isset($request["Name"])) {
            print "Error: wrong parameters";
        } else {
            $phone = $request["Phone"];
            $name = $request["Name"];
            $email = $request["Email"];

            $normalizedPhone = $this->normalizePhoneNumber($phone);
            if (empty($normalizedPhone) && empty($email)) {
                return;
            }

            /**
             * Checking contact by phone and email
             */
            $json = NULL;
            if (isset($request["IDWEB"])) {
                if (!empty($normalizedPhone)) {
                    $json = $this->findContactByPhone($phone);
                }
                if (!empty($email) && (empty($normalizedPhone) || $json->total == 0)) {
                    $json = $this->findContactByEmail($email);
                }
                if (!empty($json->total)) {
                    $result = $this->updateContact($json, $request);
                    print $result;
                }
            }
            if (!empty($normalizedPhone)) {
                $json = $this->findLeadByPhone($phone);
            }
            if (!empty($email) && (empty($normalizedPhone) || $json->total == 0)) {
                $json = $this->findLeadByEmail($email);
                if ($json->total) {
                    $leadID = $json->result[0]->ID;
                    $this->startTrigger("target=LEAD_$leadID&code=dCyTf");
                }
            }
            if (!empty($json->total)) {
                /**
                 * If message exist - send it to Bitrix
                 */
                $leadID = $json->result[0]->ID;
                $leadName = $json->result[0]->TITLE;
                $assignedByID = $json->result[0]->ASSIGNED_BY_ID;
                $formname = $request["formname"];

                $result = $this->writeLeadComment(
                    "CRM-форма",
                    "Повторное заполнение формы \"$formname\"",
                    $leadID,
                    $assignedByID
                );

                $result = $this->postToChat($assignedByID, "Повторное заполнение формы: \"$formname\", лид [url=https://a-nevski.bitrix24.ru/crm/lead/details/$leadID/]$leadName" . "[/url]");
                print $result;


                $result = $this->updateLeadForRepeat(
                    $normalizedPhone,
                    $json,
                    $request
                );
                print $result;

                $this->startBusinessProcess($leadID);
            } else {
                /**
                 * If lead is not exist - to create lead
                 */
                $result = $this->createLead(
                    $name,
                    $normalizedPhone,
                    $email,
                    NULL,
                    NULL,
                    1,
                    true,
                    NULL,
                    $request
                );
                print $result;

                if (isset($result->result)) {
                    $this->startBusinessProcess($result->result);
                }
            }
        }
    }
}
