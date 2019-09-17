<?php
file_put_contents(getcwd() . '/hook_new.log', printArray($_POST) . "\n", FILE_APPEND);

$request = $_POST;
if (isset($request["tel"])) {
    //обрабатываем запрос на обновление лида
    $tel = $request["tel"];
    $name = $request["Name"];
    $email = $request["Email"];
    writeLog("Course paid request $tel $email");
    $normalizedTel = normalizePhoneNumber($tel);

    /////////////////////////////////////////
    // Обработка промокода.
    // Проерить наличие промокода в данных из Тильды.
    /////////////////////////////////////////
    if (isset($request["payment"]["promocode"])) {
        // Сохраняем промокод
        $promocode = $request["payment"]["promocode"];

        // Математика, шестой класс.
        //
        // Переводим строку процентов скидки в число.
        // Делим на 100, чтобы получить дробь и вычитаем из 1, чтобы вычислить процент(в дроби), от изначальной суммы.
        // Далее нужно умножать $discount на цену и получившийся результат отправлять в Битрикс.
        // Еси промокода нет, по $discount будет равна еденице, что при умножении не изменит значение.
        $promolist = findPromoBuName($promocode);
        $r_promocode = $promolist->result[0]->NAME;

        $r_precent = (array)$promolist->result[0]->PROPERTY_158;
        $r_precent = array_shift($r_precent);
        if ($r_precent == 108) {
            $r_precent = true;
        } else {
            $r_precent = false;
        }

        $r_discount = (array)$promolist->result[0]->PROPERTY_160;
        $r_discount = (int)(array_shift($r_discount));
        var_dump($r_discount);

        $matches = null;
        $is_percent = preg_match('/([0-9]{1,2}|100)%/', $request["payment"]["discountvalue"], $matches, PREG_OFFSET_CAPTURE, 0);

        if (($r_promocode == $promocode) && ($is_percent == $r_precent) && ($r_discount == intval($request["payment"]["discountvalue"]))) {
            writeLog("Promocode $r_promocode found");

            if ((bool)$is_percent) {
                $discount = 1 - (intval($request["payment"]["discountvalue"]) / 100);
            } else {
                $discount = intval($request["payment"]["discountvalue"]);
            }
        } else {

            writeLog("Promocode $r_promocode not found");

            $promocode = NULL;
            $is_percent = True;
            $discount = 1;

        }

    } else {
        // Елси нет промокода - обнулим данные.
        writeLog("No promocode");

        $promocode = NULL;
        $is_percent = True;
        $discount = 1;
    };
    //////////////////////////////////////////


    if (empty($normalizedTel) && empty($email)) {
        writeLog("No contact data Phone: $tel Email: $email");
        return;
    }

    $json = NULL;

    if (!empty($normalizedTel)) {
        writeLog("Searching by phone $normalizedTel");
        $json = findLeadByPhone($tel);
    }

    if (!empty($email) && (empty($normalizedTel) || $json->total == 0)) {
        writeLog("Searching by email $email");
        $json = findLeadByEmail($email);
    }

    if (isset($json->error) || (isset($json->result) && empty($json->result))) {
        writeLog("No lead found. Creating new Lead");

        if (empty($name)) {
            $name = $tel;
            if (isset($email))
                $name = $email;
        }

        $systranid = $request["payment"]["systranid"];
        $amount = $request["payment"]["products"][0]["amount"];
        $result = createLead($name, $normalizedTel, $email, $systranid, $amount, $discount, $is_percent, $promocode, $request);
        print $result;
        $json = json_decode($result);
        writeLog("Lead created " . $result);
        $result = startTrigger("https://a-nevski.bitrix24.ru/rest/1/yjonnh47ijv34jjh/crm.automation.trigger/?target=LEAD_" . $json->result[0]->ID . "&code=oncpa");
        writeLog("Move lead " . $result);
    } else {
        writeLog("Lead found");

        //TODO определить $result для лога или удалить
//			writeLog("Move lead ".$result);

        //обновляем лида
        $queryUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/crm.lead.update.json';

        $amount = $request["payment"]["products"][0]["amount"];

        if ((bool)$is_percent) {
            $d_amount = $amount * $discount;
        } else {
            $d_amount = $amount - $discount;
            $d_amount = max(0, $d_amount);
        }

        $userParameters = array(
            "UF_CRM_1547493000073" => $request["payment"]["systranid"],
            "UF_CRM_1547492931256" => $d_amount,
        );

        writeLog("Price " . $d_amount);

        if (isset($request["rtype"]))
            $userParameters["UF_CRM_1553250302"] = $request["rtype"];

        if (isset($request['IDWEB'])) {
            $userParameters['IDWEB'] = $request['IDWEB'];
        }

        if (isset($promocode))
            $userParameters["UF_CRM_1559231074"] = $promocode;

        if (!empty($normalizedTel) && !empty($email)) {
            writeLog("Updating email " . $email);
            $userParameters["EMAIL"] = array(array("VALUE" => $email, "VALUE_TYPE" => "WORK"));
        }

        $data = array(
            'id' => $json->result[0]->ID,
            'fields' => $userParameters,
            'params' => array("REGISTER_SONET_EVENT" => "Y"),
        );
        $queryData = http_build_query($data);
        writeLog("Update data " . printArray($data));
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $queryUrl,
            CURLOPT_POSTFIELDS => $queryData,
        ));

        $result = curl_exec($curl);


        curl_close($curl);
        print $result;
        writeLog("Update order " . $result);

        $result = startTrigger("https://a-nevski.bitrix24.ru/rest/1/yjonnh47ijv34jjh/crm.automation.trigger/?target=LEAD_" . $json->result[0]->ID . "&code=oncpa");
    }
} elseif (isset($request["pay"])) {

    //обрабатываем запрос на продвижение сделки
    $dealID = $request["deal_id"];
    $tranID = $request["payment"]["systranid"];
    $newAmount = $request["payment"]["products"][0]["amount"];
    $pay = $request["pay"];

    if (findDealByTranID($tranID)) {
        writeLog("TranID " . $tranID . " already exists");
        return;
    }

    if (isset($request["payment"]["promocode"])) {
        // Сохраняем промокод
        $promocode = $request["payment"]["promocode"];

        // Математика, шестой класс.
        //
        // Переводим строку процентов скидки в число.
        // Делим на 100, чтобы получить дробь и вычитаем из 1, чтобы вычислить процент(в дроби), от изначальной суммы.
        // Далее нужно умножать $discount на цену и получившийся результат отправлять в Битрикс.
        // Еси промокода нет, по $discount будет равна еденице, что при умножении не изменит значение.
        $promolist = findPromoBuName($promocode);
        $r_promocode = $promolist->result[0]->NAME;

        $r_precent = (array)$promolist->result[0]->PROPERTY_158;
        $r_precent = array_shift($r_precent);
        if ($r_precent == 108) {
            $r_precent = true;
        } else {
            $r_precent = false;
        }

        $r_discount = (array)$promolist->result[0]->PROPERTY_160;
        $r_discount = (int)(array_shift($r_discount));
        var_dump($r_discount);

        $matches = null;
        $is_percent = preg_match('/([0-9]{1,2}|100)%/', $request["payment"]["discountvalue"], $matches, PREG_OFFSET_CAPTURE, 0);

        if (($r_promocode == $promocode) && ($is_percent == $r_precent) && ($r_discount == intval($request["payment"]["discountvalue"]))) {
            writeLog("Promocode $r_promocode found");
            if ((bool)$is_percent) {
                $discount = 1 - (intval($request["payment"]["discountvalue"]) / 100);
            } else {
                $discount = intval($request["payment"]["discountvalue"]);
            }
        } else {
            writeLog("Promocode $r_promocode NOT found");

            $promocode = NULL;
            $is_percent = True;
            $discount = 1;

        }

    } else {
        // Елси нет промокода - обнулим данные.
        writeLog("No promocode");
        $promocode = NULL;
        $is_percent = True;
        $discount = 1;
    };
    //////////////////////////////////////////

    if ((bool)$is_percent) {
        $d_amount = $newAmount * $discount;
    } else {
        $d_amount = $newAmount - $discount;
        $d_amount = max(0, $d_amount);
    }

    //получаем сделку
    $deal = getDealByID($dealID);
    print_r($deal);
    $oldAmount = $deal->result->UF_CRM_1529156721;
    $productName = $request["payment"]["products"][0]["name"];
    $assigner = $deal->result->ASSIGNED_BY_ID;
    $prolongation = $request["payment"]["products"][0]["options"][0]["variant"];


    //обновляем сделку
    $queryUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/crm.deal.update.json';

    $data = array(
        'id' => $dealID,
        'fields' => array(
            "UF_CRM_1529156721" => $oldAmount + $d_amount,
            "UF_CRM_5C44C164E80CC" => $tranID,
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
    } elseif ($pay == 'credit') {
        $data['fields']['UF_CRM_1560926337'] = $request["num"];
        $data['fields']['UF_CRM_1560925916'] = $request["fnum"];
    }


    $queryData = http_build_query($data);
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));

    $result = curl_exec($curl);
    curl_close($curl);

    //дёргаем триггер
    if ($pay == "partial") {
        print startTrigger("https://a-nevski.bitrix24.ru/rest/1/yjonnh47ijv34jjh/crm.automation.trigger/?target=DEAL_" . $dealID . "&code=5utbM");
    } elseif ($pay == 'full') {
        print startTrigger("https://a-nevski.bitrix24.ru/rest/1/yjonnh47ijv34jjh/crm.automation.trigger/?target=DEAL_" . $dealID . "&code=HpWIx");
    } elseif ($pay == 'prolongation') {
        print startTrigger("https://a-nevski.bitrix24.ru/rest/1/yjonnh47ijv34jjh/crm.automation.trigger/?target=DEAL_" . $dealID . "&code=ZKX6s");
    } elseif ($pay == 'credit') {
        print startTrigger("https://a-nevski.bitrix24.ru/rest/1/yjonnh47ijv34jjh/crm.automation.trigger/?target=DEAL_" . $dealID . "&code=ePvAO");
    }

    //добавляем сообщение о платеже
    print $result;
    if ($pay == 'prolongation')
        print writeDealComment("Произведён платёж", "Произведен платеж на сумму " . $newAmount . " за " . $productName . ". Продолжительность пролонгации " . $prolongation . ". Номер транзакции " . $tranID . " от " . date("d.m.Y"), $dealID, $assigner);
    else
        print writeDealComment("Произведён платёж", "Произведен платеж на сумму " . $newAmount . " за " . $productName . ". Номер транзакции " . $tranID . " от " . date("d.m.Y"), $dealID, $assigner);

} else {
    //обрабатываем запрос на создание лида
    if (!isset($request["Phone"]) || !isset($request["Name"])) {
        print "Error: wrong parameters";
    } else {
        $tel = $request["Phone"];
        $name = $request["Name"];
        $email = $request["Email"];
        writeLog("Create lead request $tel $name");

        //нормализуем телефонный номер
        $normalizedTel = normalizePhoneNumber($tel);

        if (empty($normalizedTel) && empty($email)) {
            writeLog("No contact data Phone: $tel Email: $email");
            return;
        }

        //проверяем есть ли уже лид с таким телефоном
        $json = NULL;

        if (isset($request["web_theme"])) {

            if (!empty($normalizedTel)) {
                writeLog("Searching contacts by phone $normalizedTel");
                $json = findContactByPhone($tel);
            }

            if (!empty($email) && (empty($normalizedTel) || $json->total == 0)) {
                writeLog("Searching contacts by email $email");
                $json = findContactByEmail($email);
            }

            if (!empty($json->total)) {
                $leadID = $json->result[0]->ID;

                $queryUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/crm.contact.update.json';
                $userParameters = array();

                $userParameters["UF_CRM_5D67B01F896D7"] = $request["web_date"];
                $userParameters["UF_CRM_5D67B01F49CD8"] = $request["web_link"];
//                $userParameters["UF_CRM_5D67B01D77970"] = $request["web_theme"];
                $userParameters["UF_CRM_5D67B01D77970"] = $request["IDWEB"];

                $data = array(
                    'id' => $json->result[0]->ID,
                    'fields' => $userParameters,
                    'params' => array("REGISTER_SONET_EVENT" => "Y"),
                );

                $queryData = http_build_query($data);
                writeLog("Update contact " . printArray($data));
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_SSL_VERIFYPEER => 0,
                    CURLOPT_POST => 1,
                    CURLOPT_HEADER => 0,
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_URL => $queryUrl,
                    CURLOPT_POSTFIELDS => $queryData,
                ));
                $result = curl_exec($curl);
                curl_close($curl);

                print $result;
                writeLog($result);
            }
        }


        if (!empty($normalizedTel)) {
            writeLog("Searching by phone $normalizedTel");
            $json = findLeadByPhone($tel);
        }

        if (!empty($email) && (empty($normalizedTel) || $json->total == 0)) {
            writeLog("Searching by email $email");
            $json = findLeadByEmail($email);
            if ($json->total > 0) {
                $leadID = $json->result[0]->ID;
                startTrigger("https://a-nevski.bitrix24.ru/rest/1/yjonnh47ijv34jjh/crm.automation.trigger/?target=LEAD_$leadID&code=dCyTf");
            }
        }

        if (!empty($json->total)) {
            writeLog("Lead found");
            //если есть, то отправляем сообщение
            $leadID = $json->result[0]->ID;
            $leadName = $json->result[0]->TITLE;
            $assignedByID = $json->result[0]->ASSIGNED_BY_ID;
            $formname = $request["formname"];
            $result = writeComment("CRM-форма", "Повторное заполнение формы \"$formname\"", $leadID, $assignedByID);
            $result = postToChat($assignedByID, "Повторное заполнение формы: \"$formname\", лид [url=https://a-nevski.bitrix24.ru/crm/lead/details/$leadID/]$leadName" . "[/url]");

            print $result;

            $queryUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/crm.lead.update.json';
            $userParameters = array();
            if (!empty($normalizedTel) && !empty($email)) {
                writeLog("Updating email " . $email);
                $userParameters["PHONE"] = array(array("VALUE" => $normalizedTel, "VALUE_TYPE" => "WORK"));
                $userParameters["EMAIL"] = array(array("VALUE" => $email, "VALUE_TYPE" => "WORK"));
            }
            if (isset($request["type"]))
                $userParameters["UF_CRM_TYPE"] = $request["type"];
            if (isset($request['IDWEB'])) {
                $userParameters['IDWEB'] = $request['IDWEB'];
            }
            if (isset($request["Height"]))
                $userParameters["UF_CRM_5B16C0A186F1B"] = $request["Height"];
            if (isset($request["Mass"]))
                $userParameters["UF_CRM_5B16C0A192A33"] = $request["Mass"];
            if (isset($request["Age"]))
                $userParameters["UF_CRM_5B16C0A21AB47"] = $request["Age"];

//            if (isset($request["web_theme"]))
//                $userParameters["UF_CRM_1567072808032"] = $request["web_theme"];
            if (isset($request['IDWEB'])) {
                $userParameters['UF_CRM_1567072808032'] = $request['IDWEB'];
            }
            if (isset($request["web_link"]))
                $userParameters["UF_CRM_1567072855342"] = $request["web_link"];
            if (isset($request["web_date"]))
                $userParameters["UF_CRM_1567073106"] = $request["web_date"];

            $data = array(
                'id' => $json->result[0]->ID,
                'fields' => $userParameters,
                'params' => array("REGISTER_SONET_EVENT" => "Y"),
            );
            $queryData = http_build_query($data);
            writeLog("Update data " . printArray($data));
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_POST => 1,
                CURLOPT_HEADER => 0,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $queryUrl,
                CURLOPT_POSTFIELDS => $queryData,
            ));
            $result = curl_exec($curl);
            curl_close($curl);


            print $result;
            writeLog($result);

            startBusinessProcess($leadID);
        } else {
            writeLog("Lead not found");
            //если не то создаём лида
            $result = createLead($name, $normalizedTel, $email, NULL, NULL, 1, true, NULL, $request);
            print $result;
            writeLog($result);


            $decodedResult = json_decode($result);

            if (isset($decodedResult->result[0]->ID)) {
                $leadID = $decodedResult->result[0]->ID;
            }

            startBusinessProcess($leadID);
        }
    }
}

function createLead($name, $normalizedTel, $email, $systranid, $amount, $discount, $is_percent, $promocode, $request)
{
    $queryUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/crm.lead.add.json';
    $userParameters = array(
        "NAME" => $name,
        "STATUS_ID" => "NEW",
        "OPENED" => "Y",
        "PHONE" => array(array("VALUE" => $normalizedTel, "VALUE_TYPE" => "WORK")),
    );
    if (isset($email))
        $userParameters["EMAIL"] = array(array("VALUE" => $email, "VALUE_TYPE" => "WORK"));
    if (isset($systranid))
        $userParameters["UF_CRM_1547493000073"] = $systranid;
    if (isset($amount)) {

        if ((bool)$is_percent) {
            $d_amount = $amount * $discount;
        } else {
            $d_amount = $amount - $discount;
            $d_amount = max(0, $d_amount);
        }

        $userParameters["UF_CRM_1547492931256"] = $d_amount;
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

        if ((bool)$is_percent) {
            $d_amount = $request["payment"]["products"][0]["amount"] * $discount;
        } else {
            $d_amount = $request["payment"]["products"][0]["amount"] - $discount;
            $d_amount = max(0, $d_amount);
        }
        $userParameters["UF_CRM_1547492931256"] = $d_amount;
    }
    if (isset($request["Height"]))
        $userParameters["UF_CRM_5B16C0A186F1B"] = $request["Height"];
    if (isset($request["Mass"]))
        $userParameters["UF_CRM_5B16C0A192A33"] = $request["Mass"];
    if (isset($request["Age"]))
        $userParameters["UF_CRM_5B16C0A21AB47"] = $request["Age"];
    if (isset($request["rtype"]))
        $userParameters["UF_CRM_1553250302"] = $request["rtype"];
    if (isset($request['IDWEB'])) {
        $userParameters['IDWEB'] = $request['IDWEB'];
    }
    if (isset($promocode))
        $userParameters["UF_CRM_1559231074"] = $promocode;

//    if (isset($request["web_theme"]))
//        $userParameters["UF_CRM_1567072808032"] = $request["web_theme"];
    if (isset($request['IDWEB'])) {
        $userParameters['UF_CRM_1567072808032'] = $request['IDWEB'];
    }
    if (isset($request["web_link"]))
        $userParameters["UF_CRM_1567072855342"] = $request["web_link"];
    if (isset($request["web_date"]))
        $userParameters["UF_CRM_1567073106"] = $request["web_date"];

    $queryData = http_build_query(array(
        'fields' => $userParameters
    , 'params' => array("REGISTER_SONET_EVENT" => "Y")
    ));

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));

    $result = curl_exec($curl);


    curl_close($curl);
    return $result;
}

/**
 * @param $leadId
 * @param $businessProcessId
 *
 * Запуск бизнес-процесса для лида
 *
 * !id бизнес-процесса захардкожен!
 */
function startBusinessProcess($leadId, $businessProcessId = 346)
{
    $parameters = [
        'TEMPLATE_ID' => $businessProcessId,
        'DOCUMENT_ID' => ['crm', 'CCrmDocumentLead', $leadId],
    ];

    $url = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/bizproc.workflow.start';

    sendRequest($url, $parameters);
}

/**
 * @param string $queryUrl
 * @param array $parameters
 */
function sendRequest($queryUrl, $parameters)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $parameters,
    ));

    curl_exec($curl);

    curl_close($curl);
}

function normalizePhoneNumber($tel)
{
    $normalizedTel = preg_replace("/[^+0-9]/", "", $tel);
    $plus = (substr($normalizedTel, 0, 1) === "+");
    $normalizedTel = preg_replace("/[^0-9]/", "", $tel);
    if ($plus)
        $normalizedTel = "+" . $normalizedTel;
    return $normalizedTel;
}

function printArray($array)
{
    $result = "";
    foreach ($array as $key => $value) {
        $result = $result . " $key => $value";
        if (is_array($value)) { //If $value is an array, print it as well!
            $result = $result . " " . printArray($value);
        }
    }
    return $result;
}

function findLeadByTranID($tranid)
{
    $queryUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/crm.lead.list.json';
    $queryData = http_build_query(array(
        'filter' => array("UF_CRM_1547493000073" => $tranid)
    , 'select' => array("ID", "TITLE", "ASSIGNED_BY_ID", "UF_CRM_1547492931256")
    ));

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));

    $result = curl_exec($curl);
    $json = json_decode($result);

    curl_close($curl);
    return $json;
}

function getDealByID($dealID)
{
    $queryUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/crm.deal.get.json';
    $queryData = http_build_query(array(
        'id' => $dealID
    ));

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));

    $result = curl_exec($curl);
    $json = json_decode($result);

    curl_close($curl);
    return $json;
}

function findDealByTranID($tranID)
{
    $queryUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/crm.deal.list.json';
    $queryData = http_build_query(array(
        'filter' => array("UF_CRM_5C44C164E80CC" => $tranID)
    , 'select' => array("ID")
    ));

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));

    $result = curl_exec($curl);
    $json = json_decode($result);

    curl_close($curl);
    return $json->total > 0;
}

function findLeadByPhone($tel)
{
    $queryUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/crm.lead.list.json';
    $queryData = http_build_query(array(
        'filter' => array("PHONE" => $tel)
    , 'select' => array("ID", "TITLE", "ASSIGNED_BY_ID")
    ));

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));

    $result = curl_exec($curl);
    $json = json_decode($result);

    curl_close($curl);
    return $json;
}

function findContactByPhone($tel)
{
    $queryUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/crm.contact.list.json';
    $queryData = http_build_query(array(
        'filter' => array("PHONE" => $tel)
    , 'select' => array("*")
    ));

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));

    $result = curl_exec($curl);
    return json_decode($result);
}

function findLeadByEmail($email)
{
    $queryUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/crm.lead.list.json';
    $queryData = http_build_query(array(
        'filter' => array("EMAIL" => $email)
    , 'select' => array("ID", "TITLE", "ASSIGNED_BY_ID")
    ));

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));

    $result = curl_exec($curl);
    $json = json_decode($result);

    curl_close($curl);
    return $json;
}

function findContactByEmail($email)
{
    $queryUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/crm.contact.list.json';
    $queryData = http_build_query(array(
        'filter' => array("EMAIL" => $email)
    , 'select' => array("*")
    ));

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));

    $result = curl_exec($curl);
    return json_decode($result);
}

function startTrigger($queryUrl)
{

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
    ));

    $result = curl_exec($curl);

    curl_close($curl);
    return $result;
}

function writeComment($header, $text, $leadID, $assignee)
{
    $queryUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/crm.livefeedmessage.add.json';
    $queryData = http_build_query(array(
        'fields' => array(
            "POST_TITLE" => $header,
            "MESSAGE" => $text,
            "SPERM" => array(
                "U" => array("U" . $assignee)
            ),
            "ENTITYTYPEID" => 1,
            "ENTITYID" => $leadID,
        )
    , 'params' => array("REGISTER_SONET_EVENT" => "Y")
    ));
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));

    $result = curl_exec($curl);
    curl_close($curl);

    return $result;
}

function writeDealComment($header, $text, $dealID, $assignee)
{
    $queryUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/crm.livefeedmessage.add.json';
    $queryData = http_build_query(array(
        'fields' => array(
            "POST_TITLE" => $header,
            "MESSAGE" => $text,
            "SPERM" => array(
                "U" => array("U" . $assignee)
            ),
            "ENTITYTYPEID" => 2,
            "ENTITYID" => $dealID,
        )
    , 'params' => array("REGISTER_SONET_EVENT" => "Y")
    ));
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));

    $result = curl_exec($curl);
    curl_close($curl);

    return $result;
}

function postToChat($userID, $message)
{

    $queryUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/im.message.add.json';
    $queryData = http_build_query(
        array(
            "USER_ID" => $userID,
            "MESSAGE" => $message,
        )
    );

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));

    $result = curl_exec($curl);
    curl_close($curl);

    return $result;

}

function findPromoBuName($name)
{

    $queryUrl = 'https://a-nevski.bitrix24.ru/rest/1/1o88wyq5m0yizz68/lists.element.get.json';
    $queryData = http_build_query(array(
        'IBLOCK_TYPE_ID' => 'lists',
        'IBLOCK_ID' => '44',
        'FILTER' => array("NAME" => $name)
    ));

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));

    $result = curl_exec($curl);
    $json = json_decode($result);

    curl_close($curl);
    return $json;
}

function writeLog($data)
{
    file_put_contents(getcwd() . '/hook_new.log', $data . "\n", FILE_APPEND);
}

?>