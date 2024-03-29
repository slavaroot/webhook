<?php
require __DIR__ . '/Base.php';
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

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
     * Logger component of notice information
     * Source is https://github.com/Seldaek/monolog
     * @var array of Logger
     */
    public $loggerInfo;

    /**
     * Logger component of errors
     * Source is https://github.com/Seldaek/monolog
     * @var array of Logger
     */
    public $loggerErrors;

    /**
     * WebHook constructor.
     * Create logger object
     */
    public function __construct(){
        $this->loggerInfo = new Logger('info');
        $this->loggerInfo->pushHandler(new StreamHandler('webhook_logs/info2.log', Logger::INFO));

        $this->loggerErrors = new Logger('errors');
        $this->loggerErrors->pushHandler(new StreamHandler('webhook_logs/errors2.log', Logger::DEBUG));
    }

    /**
     * Base function for processing data and logical actions
     */
    public function processing(){
        $request = $_POST;

        if (isset($request["tel"])) {

            /**
             * Processing data for update lead
             */
            $this->processingOfUpdateLead();
        } elseif (isset($request["pay"])) {

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
            $this->isPercent = False;
            $this->discount = $request["payment"]["discountvalue"];
            $this->findAndSavePromo();
        } else {
            $this->promoCode = NULL;
            $this->isPercent = True;
            $this->discount = 1;
        }
    }

    /**
     * Calculating discount by the promo code and save it in variable
     */
    public function findAndSavePromo(){

		$promoList = $this->findPromoByName($this->promoCode);

		$this->loggerInfo->info('Result of searching promo by name', [
            'response' => $promoList,
            'promocode' => $this->promoCode
        ]);

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

        if (($rPromoCode == $this->promoCode) && ((bool)$this->isPercent === (bool)$rPercent) && ($r_discount == intval($this->discount))) {
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
        $this->loggerInfo->info('Result of calculating discount', [
            'discount' => $this->discount,
            'promocode' => $this->promoCode,
            'isPercent' => $this->isPercent
        ]);
    }

    /**
     * First scenario of logic
     */
    public function processingOfUpdateLead(){

        $request = $_POST;
        $phone = $request["tel"];
        $name = $request["Name"];
        $email = $request["Email"];

        $this->loggerInfo->info('Attempt to update lead', [
            'phone' => $phone,
            'name' => $name,
            'email' => $email,
            'request' => $request
        ]);

        $normalizedPhone = $this->normalizePhoneNumber($phone);
        $this->loggerInfo->info('Result of normalize phone', [
            'normalizedPhone' => $normalizedPhone,
        ]);

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

        $this->loggerInfo->info('Result of searching by phone and email', [
            'json' => $json,
        ]);

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

            $this->loggerInfo->info('Result of creation lead (scenario 2, updating lead)', [
                'json' => json_encode($json),
                'systranid' => $systranid,
                'amount' => $amount,
                'discount' => $this->discount,
                'isPercent' => $this->isPercent,
                'promoCode' => $this->promoCode,
                'request' => $request
            ]);

            $this->startTrigger("target=LEAD_" . $json->result . "&code=oncpa");
            $result = $this->startBusinessProcess($json->result);
            $this->loggerInfo->info('Result of starting BP (scenario 2, updating lead)', [
                'result' => json_encode($result),
                'leadId' => $json->result[0]->ID
            ]);
        } else {
            $this->updateLead(
                $json,
                $this->discount,
				$this->isPercent,
                $email,
                $this->promoCode,
                $request
            );

            $this->loggerInfo->info('Result of update lead (scenario 2, updating lead)', [
                'json' => json_encode($json),
                'discount' => $this->discount,
                'isPercent' => $this->isPercent,
                'promoCode' => $this->promoCode,
                'request' => $request
            ]);

            $result = $this->startBusinessProcess($json->result[0]->ID);

            $this->loggerInfo->info('Result of starting BP (scenario 2, updating lead)', [
                'result' => json_encode($result),
                'leadId' => $json->result[0]->ID
            ]);

            $this->startTrigger("target=LEAD_" . $json->result[0]->ID . "&code=oncpa");
        }
    }

    /**
     * Second scenario of logic
     * Processing of payment
     */
    public function processingOfPayment() {

		$request = $_POST;
		$dealId = $request["deal_id"];
        $tranId = $request["payment"]["systranid"];
        $newAmount = $request["payment"]["products"][0]["amount"];
        $pay = $request["pay"];

        $this->loggerInfo->info('Processing of payment', [
            'request' => $request,
            'dealId' => $dealId,
            'tranId' => $tranId,
            'newAmount' => $newAmount,
            'pay' => $pay
        ]);

        $dealResult = $this->findDealByTransactionId($tranId);

        if ($dealResult->total > 0) {
            $this->loggerErrors->error('Transaction is exist in bitrix (payment scenario)', [
                'transaction id' => $tranId,
                'deal' => json_encode($dealResult),

            ]);
            return;
        } else {
            $this->loggerInfo->info('Result of searching deal (payment scenario)', [
                'deal' => json_encode($dealResult),
                'tranId' => $tranId,
            ]);
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

		$this->loggerInfo->info('$dAmount', ['$dAmount' => $dAmount]);

        $deal = $this->getDealByID($dealId);


        $this->loggerInfo->info('Result of getting deal', [
            'deal' => json_encode($deal),
            'dealId' => $dealId,
        ]);

        $productName = $request["payment"]["products"][0]["name"];
        $assigner = $deal->result->ASSIGNED_BY_ID;
        $prolongation = $request["payment"]["products"][0]["options"][0]["variant"];

        $result = $this->updateDeal($deal, $dAmount, $request, $this->promoCode);

        $this->loggerInfo->info('Result of updating deal (payment scenario)', [
        	'result' => $result,
            '$pay' => $pay,
        ]);

		/**
		 * Триггеры
		 * Запрос такого типа двигает стадии сделки. Они должны выполняться после заполнения полей сделки (ВАЖНО!)
		 */

        if ($pay == "partial") {
			/**
			 * перевод сделки в стадию "Забронировано" при оплате брони
			 */
			$this->loggerInfo->info('partial', ['$dealId' => $dealId]);
			$trigger = $this->startTrigger("target=DEAL_" . $dealId . "&code=5utbM");
			if ($trigger->result) {
				$this->loggerInfo->info('Success trigger "partial"', [
					'$trigger' => $trigger
				]);
			} else {
				$this->loggerInfo->info('Error trigger "partial"', [
					'$trigger' => $trigger
				]);
			}
//            print $this->startTrigger("target=DEAL_" . $dealId . "&code=5utbM");
        } elseif ($pay == 'full') {
			/**
			 * перевод сделки по основному курсу в стадию "Оплатил полностью"
			 */
        	$this->loggerInfo->info('full', ['$dealId' => $dealId]);
			$trigger = $this->startTrigger("target=DEAL_" . $dealId . "&code=HpWIx");
			if ($trigger->result) {
				$this->loggerInfo->info('Success trigger "full"', [
					'$trigger' => $trigger
				]);
			} else {
				$this->loggerInfo->info('Error trigger "full"', [
					'$trigger' => $trigger
				]);
			}
//				print $this->startTrigger("target=DEAL_" . $dealId . "&code=HpWIx");

        } elseif ($pay == 'prolongation') {
			/**
			 * перевод сделки по пролонгации в стадию "Продлила"
			 */
			$this->loggerInfo->info('prolongation', ['$dealId' => $dealId]);
			$trigger = $this->startTrigger("target=DEAL_" . $dealId . "&code=ZKX6s");
			if ($trigger->result) {
				$this->loggerInfo->info('Success trigger "prolongation"', [
					'$trigger' => $trigger
				]);
			} else {
				$this->loggerInfo->info('Error trigger "prolongation"', [
					'$trigger' => $trigger
				]);
			}
//            print $this->startTrigger("target=DEAL_" . $dealId . "&code=ZKX6s");
        } elseif ($pay == 'credit') {
			/**
			 * перевод сделки "Рассрочка платежа" при внесении очередного платежа
			 */
			$this->loggerInfo->info('credit', ['$dealId' => $dealId]);
			$trigger = $this->startTrigger("target=DEAL_" . $dealId . "&code=ePvAO");
			if ($trigger->result) {
				$this->loggerInfo->info('Success trigger "credit"', [
					'$trigger' => $trigger
				]);
			} else {
				$this->loggerInfo->info('Error trigger "credit"', [
					'$trigger' => $trigger
				]);
			}
        }

        if ($pay == 'prolongation') {
			$this->loggerInfo->info('Writing comment (prolongation)');
			print $this->writeDealComment(
				"Произведён платёж",
				"Произведен платеж на сумму " . $dAmount . " за " . $productName .
				". Продолжительность пролонгации " . $prolongation . ". Номер транзакции " . $tranId . " от " .
				date("d.m.Y"),
				$dealId,
				$assigner
			);
		} else {
			$this->loggerInfo->info('Writing comment (not prolongation)');
			print $this->writeDealComment(
				"Произведён платёж",
				"Произведен платеж на сумму " . $dAmount . " за " . $productName .
				". Номер транзакции " . $tranId . " от " . date("d.m.Y"),
				$dealId,
				$assigner
			);
		}

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

            $this->loggerInfo->info('Attempt to create lead (scenario 3)', [
                'phone' => $phone,
                'name' => $name,
                'email' => $email,
            ]);

            $normalizedPhone = $this->normalizePhoneNumber($phone);
            if (empty($normalizedPhone) && empty($email)) {
                return;
            }
            $this->loggerInfo->info('Searching by phone', [
                'normalizedPhone' => $normalizedPhone,
            ]);

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
            $this->loggerInfo->info('Response from bitrix ', [
                'response' => json_encode($json),
            ]);
            if (!empty($json->total)) {
                $this->loggerInfo->info('Lead found');
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
                $this->loggerInfo->info('Result of writing to lead\'s feed', [
                    'response' => json_encode($result),
                ]);

                $result = $this->postToChat($assignedByID, "Повторное заполнение формы: \"$formname\", лид [url=https://a-nevski.bitrix24.ru/crm/lead/details/$leadID/]$leadName" . "[/url]");

                $this->loggerInfo->info('Result of posting to chat', [
                    'response' => json_encode($result),
                ]);

                $result = $this->updateLeadForRepeat(
                    $normalizedPhone,
                    $json,
                    $request
                );
                $this->loggerInfo->info('Result of updating lead (double lead)', [
                    'response' => json_encode($result),
                ]);

                $result = $this->startBusinessProcess($leadID);
                $this->loggerInfo->info('Result of starting BP (scenario 3, updating lead)', [
                    'result' => json_encode($result),
                    'leadId' => $leadID
                ]);
            } else {

				$this->loggerInfo->info('Lead not found. Creating lead');

                /**
                 * If lead is not exists - to create lead
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

                $this->loggerInfo->info('Result of creation lead', [
                    'response' => json_encode($result),
                ]);
                if (isset($result->result)) {
                    $leadId = $result->result;
                    $result = $this->startBusinessProcess($result->result);
                    $this->loggerInfo->info('Result of starting BP (scenario 3, create lead)', [
                        'result' => $result,
                        'leadId' => $leadId
                    ]);
                } else {
                    $this->loggerErrors->error('Error of creation lead', [
                        'response' => $result,
                        'request' => $request
                    ]);
                }
            }
        }
    }
}
