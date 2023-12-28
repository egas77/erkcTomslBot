<?php

namespace Services\Erkc;

use Longman\TelegramBot\DB;
use Longman\TelegramBot\Exception\TelegramException;
use PDOException;

class Api
{
    const API_TOKEN = 'iuHHAnFo3U87A4Kvl173yX1ausYd7EGusf0IkVdkaWiRJeOk47d2U4NJV4mFrBf2';
    const APP_ID = 'VtqDyaekN';
    const PRIVATE_KEY = '21ba15f3410d6fe7fd00b83d18025358';
    const API_URL = 'https://api.vc.tom.ru';
    const API_IPU_GET_METHOD = '&method=ipu.getbyreceipt';
    const API_IPU_SEND_METERS_METHOD = '&method=ipu.sendmeters';

    public function checkBarcodeByText(string $value): array
    {
        return $this->checkCodeApi(json_decode($this->fetchByApi($value), true));
    }

    public function checkBarcodeByImage($path): array
    {
        return $this->_parsePhotoBarcode($path);
        //return $this->parsePhotoBarcode($path);

    }

    public function fetchMetersData(string $barcode): array
    {
        try {
            $url = self::API_URL . '/apps/?token='
                . self::API_TOKEN
                . '&app_id=' . self::APP_ID
                . self::API_IPU_GET_METHOD
                . '&code=' . $barcode;
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $response = curl_exec($curl);
            curl_close($curl);
            $response_code = json_decode($response, true)['result']['code'];
            if ($response_code === "0") {
                return ["status" => true, "data" => json_decode($response, true)['data']]; // счётчики
            } elseif ($response_code === "201") {
                return ["status" => false, "desc" => json_decode($response, true)['result']['desc']];
            }
        } catch (\Exception $exception) {
            file_put_contents('curl_error.log', $exception->getMessage());
            return ["status" => false, "desc" => 'Ошибка не сервере. Обратитесь к администратору сервиса.'];
        }
        return [];
    }

    public static function getMetersFromApi($meters): array
    {
//        $meter_response = [];
//        foreach ($meters['data'] as $meter) {
//            $meter_response[] = $meter['usluga_name'] == 'Горячее водоснабжение'
//                ? '🩸 ГВС: ' . $meter['nomer']
//                : '💧 ХВС: ' . $meter['nomer'];
//        }
//        return $meter_response;

        $meter_response = [];
        foreach ($meters['data'] as $meter) {
            $meter_item = [
                'id' => $meter['id'], // ID счетчика для использования в API
                'nomer' => $meter['nomer'], // номер счетчика для отображения пользователю
                'usluga_name' => $meter['usluga_name'] == 'Горячее водоснабжение'
                    ? '🩸 ГВС: ' . $meter['nomer']
                    : '💧 ХВС: ' . $meter['nomer']
            ];

            $meter_response[] = $meter_item;
        }
        return $meter_response;
    }

    public static function sendMetersData($barcode, $meters_value)
    {
        $curl = curl_init();
        $url = self::API_URL . '/apps/?token='
            . self::API_TOKEN
            . '&app_id=' . self::APP_ID
            . '&method=ipu.sendmeters&'
            . 'code=' . $barcode;
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $meters_value,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        $response_code = json_decode($response, true)['result']['code'];
        if ($response_code === "0") {
            return ["status" => true];
        } elseif ($response_code === "325") {
            return ["status" => false, "desc" => json_decode($response, true)['result']['desc']];
        } elseif ($response_code === "322") {
            return ["status" => false, "desc" => json_decode($response, true)['result']['desc']];
        } elseif ($response_code === "201") {
            return ["status" => false, "desc" => json_decode($response, true)['result']['desc']];
        }
    }

    public function _parsePhotoBarcode(string $barcodeImagePath)
    {
        $result = [
            'status' => false,
            'text' => 'Код не распознан, либо не найден. Попробуйте более лучше сделать фото.'
        ];
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'http://decode/decode-barcode',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'Content-Type: image'
            ],
        ]);
        curl_setopt($curl, CURLOPT_POSTFIELDS, file_get_contents($barcodeImagePath)); # set image data
        $response = curl_exec($curl);

        curl_close($curl);
        $parse_result = json_decode($response, true);

        if ($parse_result['status']) {
            if ($parse_result['type'] === 'qrcode' || $parse_result['type'] === 'qr_code') {
                if (array_key_exists('PERSACC', $parse_result['data'])) {
                    $barcode = $parse_result['data']['PERSACC'];
                }
                if (array_key_exists('PersAcc', $parse_result['data'])) {
                    $barcode = $parse_result['data']['PersAcc'];
                }

            }
            if ($parse_result['type'] === 'ean13') {
                $barcode = $parse_result['data'];
            }
            $response_by_api = json_decode($this->fetchByApi($barcode), true);
            $result = $this->checkCodeApi($response_by_api);
            $result['barcode'] = $barcode;
        }
        return $result;
    }

    public function parsePhotoBarcode(string $barcodeImagePath): array
    {
        $result = [
            'status' => false,
            'text' => 'Код не распознан, либо не найден. Попробуйте более лучше сделать фото.'
        ];
        $pythonScriptPath = 'Services/Barcode/decode_barcode.py';
        $output = shell_exec(
            'python3'
            . ' ' . $pythonScriptPath
            . ' ' . $barcodeImagePath
        );
        file_put_contents('python.log', ' python3'
            . ' ' . $pythonScriptPath
            . ' ' . $barcodeImagePath);
        $output = iconv('windows-1251', 'utf-8', $output);
        $parse_result = json_decode($output, true);
        if ($parse_result['status']) {
            if ($parse_result['type'] === 'qrcode') {
                if (array_key_exists('PERSACC', $parse_result['data'])) {
                    $barcode = $parse_result['data']['PERSACC'];
                }
                if (array_key_exists('PersAcc', $parse_result['data'])) {
                    $barcode = $parse_result['data']['PersAcc'];
                }

            }
            if ($parse_result['type'] === 'ean13') {
                $barcode = $parse_result['data'];
            }
            $response_by_api = json_decode($this->fetchByApi($barcode), true);
            $result = $this->checkCodeApi($response_by_api);
            $result['barcode'] = $barcode;
        }
        return $result; //json_encode($enc_output,JSON_UNESC1981APED_UNICODE);
    }

    protected function checkCodeApi(array $response): array
    {
        $result = [];
        /* проверка введенного штрихкода */
        if ($response['result']['code'] === '100') {
            $result = [
                'status' => false,
                'text' => 'Извините, в нашей системе пока нет информации о Вашей квитанции.' . "\n"
                    . ' Мы не сможем предложить Вам услуг по ней.'
            ];
        } elseif ($response['result']['code'] === '0') {
            $result['status'] = true;
            $result['text'] = $response['data']['text'];
            $result['ls'] = $response['data']['account'];
            $result['supplier_name'] = $response['data']['supplier_name'];
            $result['service_name'] = $response['data']['service_name'];
            $result['service_code'] = $response['data']['service_code'];
            $result['address'] = $response['data']['address'];
            $result['barcode'] = $response['data']['barcode'];
            $result['street'] = $response['data']['street'];
            $result['house'] = $response['data']['house'];
            $result['flat'] = $response['data']['apart'];
            $result['percent_q'] = $response['data']['percent_q'];
            $result['amount'] = $response['data']['amount'];
            $result['meters'] = $this->fetchMetersData($result['barcode']);
        } else {
            $result = [
                'status' => false,
                'text' => 'Вы не верно указали штрих код.'
            ];
        }
        return $result;
    }

    protected function gen_query($barcode): string
    {
        return self::API_URL . '/apps/?token='
            . self::API_TOKEN
            . '&app_id=' . self::APP_ID
            . '&method=receipts.get&code=' . $barcode;
    }

    public function fetchByApi($barcode): false|string
    {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->gen_query($barcode),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $response = curl_exec($curl);
            curl_close($curl);
        } catch (\Exception $exception) {
            file_put_contents('curl_error.log', $exception->getMessage());
        }
        return $response;
    }

    public static function gen_payment_link($summa, $barcode, $email)
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => self::API_URL . '/apps/?token='
                . self::API_TOKEN . '&app_id='
                . self::APP_ID . '&method=payments.init&code='
                . $barcode . '&method_id=0&summ='
                . $summa
                . '&email=' . $email,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        $response_code = json_decode($response, true)['result']['code'];
        if ($response_code === "0") {
            return ["status" => true, "url" => json_decode($response, true)['data']['url']];
        } elseif ($response_code === "325") {
            return ["status" => false, "desc" => json_decode($response, true)['result']['desc']];
        } elseif ($response_code === "322") {
            return ["status" => false, "desc" => json_decode($response, true)['result']['desc']];
        }
    }

    public static function getIconByOpcode(int $opcode)
    {
        $url = self::API_URL . '/apps/?token=' . self::API_TOKEN
            . '&app_id=' . self::APP_ID
            . '&method=dictionary.opcodes&opcode_code=' . $opcode;

        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $response = curl_exec($curl);
            curl_close($curl);
        } catch (\Exception $exception) {
            return null;
        }
        return json_decode($response, true)['data'][0]['icon_small'];
    }

    public static function is_registered($id_user): bool
    {
        try {
            $pdo = DB::getPdo();
            $sql = 'SELECT is_registered FROM `user` WHERE id=' . $id_user;
            return boolval($pdo->query($sql)->fetchColumn());
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    public static function update_user_registered($id_user, $flag = 0): bool
    {
        try {
            $pdo = DB::getPdo();
            $sql = 'UPDATE user SET is_registered=' . $flag . ' WHERE id=' . $id_user;
            return $pdo->query($sql)->execute();
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    public static function update_user_email($id_user, $email)
    {
        try {
            $pdo = DB::getPdo();
            $updateSql = 'UPDATE user SET site_email = :email WHERE id=' . $id_user;
            $updateStm = $pdo->prepare($updateSql);
            $updateStm->bindValue('email', $email);
            return $updateStm->execute();
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    public static function addBarcode(array $barcode_payload, int $user_id): bool
    {
        try {
            $pdo = DB::getPdo();
            $barcode = $barcode_payload['barcode'];
            $payload = json_encode($barcode_payload, JSON_UNESCAPED_UNICODE);
            $existingBarcodeQuery = $pdo->prepare('SELECT barcode FROM user_barcode WHERE barcode = :barcode and user_id = :user_id');
            $existingBarcodeQuery->bindValue('barcode', $barcode);
            $existingBarcodeQuery->bindValue('user_id', $user_id);
            $result = $existingBarcodeQuery->execute();
            if ($existingBarcodeQuery->rowCount() > 0) {
                $updateSql = 'UPDATE user_barcode SET payload = :payload, updated_at = CURRENT_TIMESTAMP WHERE barcode = :barcode and user_id = :user_id';
                $updateStm = $pdo->prepare($updateSql);
                $updateStm->bindValue('barcode', $barcode);
                $updateStm->bindValue('payload', $payload);
                $updateStm->bindValue('user_id', $user_id);
                $result = $updateStm->execute();
            } else {
                if (!self::hasActiveBarcodes($user_id)) {
                    $insertSql = 'INSERT INTO user_barcode (barcode, user_id, payload,is_active) VALUES (:barcode, :user_id, :payload, 1)';
                } else {
                    $insertSql = 'INSERT INTO user_barcode (barcode, user_id, payload,is_active) VALUES (:barcode, :user_id, :payload, 0)';
                }
                $insertStm = $pdo->prepare($insertSql);
                $insertStm->bindValue('barcode', $barcode);
                $insertStm->bindValue('user_id', $user_id);
                $insertStm->bindValue('payload', $payload);
                $result = $insertStm->execute();
            }

            return $result;
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    /**
     * @throws TelegramException
     */
    private static function hasActiveBarcodes(int $userId): bool
    {
        try {
            $pdo = DB::getPdo();
            $sql = 'SELECT COUNT(is_active) FROM user_barcode WHERE is_active = 1 and user_id = ' . $userId;
            $stm = $pdo->query($sql);

            return $stm->fetchColumn() > 0;
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    public static function hasBarcode(int $userId, int $barcode): bool
    {
        try {
            $pdo = DB::getPdo();
            $sql = 'SELECT barcode FROM user_barcode WHERE barcode = :barcode and user_id = :user_id';
            $stm = $pdo->prepare($sql);
            $stm->bindValue('user_id', $userId);
            $stm->bindValue('barcode', $barcode);
            $stm->execute();
            return $stm->fetchColumn() > 0;
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    public static function getEmailByUserId($user_id)
    {
        try {
            $pdo = DB::getPdo();
            $sql = 'SELECT site_email FROM user WHERE id = :userId';
            $stm = $pdo->prepare($sql);
            $stm->bindValue('userId', $user_id, \PDO::PARAM_INT);
            $stm->execute();
            return $stm->fetchColumn();
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    public static function getActiveBarcode(int $user_id): array
    {
        try {
            $pdo = DB::getPdo();
            $sql = 'SELECT payload FROM user_barcode WHERE is_active = 1 and user_id = :user_id';
            $stm = $pdo->prepare($sql);
            $stm->bindValue('user_id', $user_id, \PDO::PARAM_INT);
            $stm->execute();
            return json_decode($stm->fetchAll(\PDO::FETCH_ASSOC)[0]['payload'], true);
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    public static function getPayloadByBarcode(int $barcode): array
    {
        try {
            $pdo = DB::getPdo();
            $sql = 'SELECT payload FROM user_barcode WHERE barcode = :barcode';
            $stm = $pdo->prepare($sql);
            $stm->bindValue('barcode', $barcode, \PDO::PARAM_INT);
            $stm->execute();
            return json_decode($stm->fetchAll(\PDO::FETCH_ASSOC)[0]['payload'], true);
        } catch (PDOException $e) {
            throw new TelegramException($e->getMessage());
        }
    }

    public static function removeBarcode(int $userId, int $barcode): bool
    {
        try {
            $pdo = DB::getPdo();
//            if (self::hasActiveBarcodes($userId)) {
//                $selectBarcode = $pdo->prepare('SELECT barcode FROM user_barcode WHERE user_id = :user_id
//                                   and barcode !=:barcode LIMIT 1');
//                $selectBarcode->bindValue('user_id', $userId);
//                $selectBarcode->bindValue('barcode', $barcode);
//                $selectBarcode->execute();
//                $new_barcode = $selectBarcode->fetchColumn();
//
//                $sqlSetActive = 'UPDATE user_barcode SET is_active = 1 WHERE barcode = :barcode and user_id = :userId';
//                $stmSetActive = $pdo->prepare($sqlSetActive);
//                $stmSetActive->bindValue('barcode', $new_barcode);
//                $stmSetActive->bindValue('userId', $userId);
//                $stmSetActive->execute();
//            }

            $sqlDelete = 'DELETE FROM user_barcode WHERE barcode = :barcode and user_id = :userId';
            $stmDelete = $pdo->prepare($sqlDelete);
            $stmDelete->bindValue('barcode', $barcode);
            $stmDelete->bindValue('userId', $userId);
            return $stmDelete->execute();
        } catch (PDOException $e) {
            file_put_contents('API_removeBarcode.log', $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    public static function setActiveBarcode(int $userId, int $barcode): bool
    {
        try {
            $pdo = DB::getPdo();
            $sqlDeactivate = 'UPDATE user_barcode SET is_active = 0 WHERE user_id = :userId';
            $stmDeactivate = $pdo->prepare($sqlDeactivate);
            $stmDeactivate->bindValue('userId', $userId);
            $result = $stmDeactivate->execute();
            if ($result) {
                $sqlActivate = 'UPDATE user_barcode SET is_active = 1 WHERE user_id = :userId AND barcode = :barcode';
                $stmActivate = $pdo->prepare($sqlActivate);
                $stmActivate->bindValue('userId', $userId, \PDO::PARAM_INT);
                $stmActivate->bindValue('barcode', $barcode, \PDO::PARAM_INT);
                $result = $stmActivate->execute();
            }
            return $result;

        } catch (PDOException $e) {
            file_put_contents('API_setActiveBarcode.log', $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    public static function getUserBarcodesByUserId(int $user_id): array
    {
        try {
            $pdo = DB::getPdo();
            $sql = 'SELECT * FROM user_barcode WHERE user_id = :user_id';
            $stm = $pdo->prepare($sql);
            $stm->bindValue('user_id', $user_id, \PDO::PARAM_INT);
            $stm->execute();
            return $stm->fetchAll(\PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            //throw new TelegramException($e->getMessage());
            file_put_contents('ListReceiptCommand.log', $e->getMessage() . "\n", FILE_APPEND);
            return [];
        }
    }

    public static function remove_emoji($string)
    {
        $emoji_pattern = '/[\x{1F600}-\x{1F64F}]/u' // смайлики
            . '|[\x{1F300}-\x{1F5FF}]/u' // различные символы и пиктограммы
            . '|[\x{1F680}-\x{1F6FF}]/u' // транспорт и карты
            . '|[\x{1F1E0}-\x{1F1FF}]/u' // флаги
            . '|[\x{2600}-\x{26FF}]/u'   // разные символы
            . '|[\x{2700}-\x{27BF}]/u'   // дингбаты
            . '|[\x{1F900}-\x{1F9FF}]/u' // дополнительные смайлики и символы
            . '|[\x{1FA70}-\x{1FAFF}]/u'; // более символов

//
//        // Match Enclosed Alphanumeric Supplement
//        $regex_alphanumeric = '/[\x{1F100}-\x{1F1FF}]/u';
//        $clear_string = preg_replace($regex_alphanumeric, '', $string);
//
//        // Match Miscellaneous Symbols and Pictographs
//        $regex_symbols = '/[\x{1F300}-\x{1F5FF}]/u';
//        $clear_string = preg_replace($regex_symbols, '', $clear_string);
//
//        // Match Emoticons
//        $regex_emoticons = '/[\x{1F600}-\x{1F64F}]/u';
//        $clear_string = preg_replace($regex_emoticons, '', $clear_string);
//
//        // Match Transport And Map Symbols
//        $regex_transport = '/[\x{1F680}-\x{1F6FF}]/u';
//        $clear_string = preg_replace($regex_transport, '', $clear_string);
//
//        // Match Supplemental Symbols and Pictographs
//        $regex_supplemental = '/[\x{1F900}-\x{1F9FF}]/u';
//        $clear_string = preg_replace($regex_supplemental, '', $clear_string);
//
//        // Match Miscellaneous Symbols
//        $regex_misc = '/[\x{2600}-\x{26FF}]/u';
//        $clear_string = preg_replace($regex_misc, '', $clear_string);
//
//        // Match Dingbats
//        $regex_dingbats = '/[\x{2700}-\x{27BF}]/u';
        //$clear_string = preg_replace($regex_dingbats, '', $clear_string);
        $clear_string = preg_replace($emoji_pattern, '', $emoji_pattern);
        $clear_string = preg_replace('/\s+/', ' ', $clear_string);
        $clear_string = trim(preg_replace("/\n|\r/", " ", $clear_string));
        return $clear_string;
    }

    public static function setMobile(int $userId, string $mobile): bool
    {
        try {
            $pdo = DB::getPdo();
            $sqlSetActive = 'UPDATE user SET phone_number = :mobile WHERE id = :userId';
            $stmSetActive = $pdo->prepare($sqlSetActive);
            $stmSetActive->bindValue('mobile', $mobile);
            $stmSetActive->bindValue('userId', $userId);
            return $stmSetActive->execute();
        } catch (PDOException $e) {
            //throw new TelegramException($e->getMessage());
            return false;
        }
    }

    public static function authByPhone(int $phone = 0)
    {
        $url = self::API_URL . '/apps/?token=' . self::API_TOKEN
            . '&app_id=' . self::APP_ID
            . '&method=users.authorizationphone&phone=' . $phone;
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            if ($httpCode !== 200) {
                return null;
            }
        } catch (\Exception $exception) {
            return null;
        }
        return json_decode($response, true)['data'];
    }

    public static function genSig($accessToken, $method, $options = null): string
    {
        $hash = $accessToken
            . 'method=' . $method
            . 'app_id=' . self::APP_ID
            //. 'session_token=' . $accessToken
            . 'token=' . $accessToken//. self::API_TOKEN
            . $options
            . self::PRIVATE_KEY;
        /**
         * 7Zcb0m5SbaRmhJj5Y6cfb6vYdfh1B1RX
         * method=receipts.getbyuser
         * app_id=VtqDyaekN
         * session_token=7Zcb0m5SbaRmhJj5Y6cfb6vYdfh1B1RX
         * token=iuHHAnFo3U87A4Kvl173yX1ausYd7EGusf0IkVdkaWiRJeOk47d2U4NJV4mFrBf2
         * 21ba15f3410d6fe7fd00b83d18025358
         * */
        return md5($hash);
    }

    public static function setAuthTokens(int $userId, $refresh, $access): bool
    {
        try {
            $pdo = DB::getPdo();
            $sqlSetActive = 'UPDATE user SET access_token = :access_token, refresh_token = :refresh_token  WHERE id = :userId';
            $stmSetActive = $pdo->prepare($sqlSetActive);
            $stmSetActive->bindValue('userId', $userId);
            $stmSetActive->bindValue('access_token', $access);
            $stmSetActive->bindValue('refresh_token', $refresh);
            return $stmSetActive->execute();
        } catch (PDOException $e) {
            file_put_contents('Api.log', $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    private function getAccessToken(int $user_id, string $phone = null)
    {
        try {
            $pdo = DB::getPdo();
            if (!empty($phone)) {
                $sqlSetActive = 'SELECT access_token FROM user WHERE phone_number = :phone_number';
            } else {
                $sqlSetActive = 'SELECT access_token FROM user WHERE id = :userId';
            }

            $stm = $pdo->prepare($sqlSetActive);
            if (!empty($phone)) {
                $stm->bindValue('phone_number', $phone);
            } else {
                $stm->bindValue('userId', $user_id, \PDO::PARAM_INT);

            }
            $stm->execute();
            return $stm->fetchColumn(0);
        } catch (PDOException $e) {
            file_put_contents('Api.log', $e->getMessage() . "\n", FILE_APPEND);
            return null;
        }
    }

    public static function getUserInfo($userId)
    {
        $access_token = (new Api())->getAccessToken($userId);
        $method = 'users.get';
        $sig = self::genSig($access_token, $method);
        return self::fetchAuthUrl($method, $access_token, $sig);
    }

    public static function getPaymentHistoriesByBarcode($offset, $userId, $barcode): array
    {
        $access_token = (new Api())->getAccessToken($userId);
        $method = 'payments.getbyreceipt';
        $sig = self::genSig($access_token, $method, 'code=' . $barcode);
        $receipts = self::fetchAuthUrl($method, $access_token, $sig, 'code=' . $barcode);
        if (!empty($receipts)) {
            $pageCount = (int)ceil(count($receipts) / 12);
            $totalPayments = count($receipts);
            $paymentHistories = self::parsePaymentHistories($offset, $receipts);
        } else {
            return [];
        }
        return [
            'payments' => $paymentHistories,
            'pageCount' => $pageCount,
            'total' => $totalPayments
        ];
    }

    public static function getPaymentHistories($offset, $userId, $list_barcodes, $phone = null): array
    {
        $access_token = (new Api())->getAccessToken($userId, $phone);
        $method = 'payments.getbyuser';
        $sig = self::genSig($access_token, $method);
        $receipts = self::fetchAuthUrl($method, $access_token, $sig);
        // Преобразование массива
        $barcodes = array_map(function ($receipt) {
            return $receipt['barcode'];
        }, $list_barcodes);
        // Фильтрация массива
        $filteredReceipts = array_filter($receipts, function ($receipt) use ($barcodes) {
            return in_array($receipt['barcode'], $barcodes);
        });
        if (!empty($filteredReceipts)) {
            $pageCount = (int)ceil(count($filteredReceipts) / 12);
            $totalPayments = count($filteredReceipts);
            $paymentHistories = self::parsePaymentHistories($offset, $filteredReceipts);
        } else {
            return [];
        }
        return [
            'payments' => $paymentHistories,
            'pageCount' => $pageCount,
            'total' => $totalPayments
        ];
    }

    public static function getLinkPayment($offset, $userId, $phone = null): array
    {
        $access_token = (new Api())->getAccessToken($userId, $phone);
        return [];
    }

    public static function getMeterHistories($offset, $userId, $barcode, $phone = null): array
    {
        $access_token = (new Api())->getAccessToken($userId, $phone);
        $method = 'ipu.gethistorybyreceipt';
        $sig = self::genSig($access_token, $method, $barcode);
        $response = self::fetchAuthUrl($method, $access_token, $sig, $barcode);

        if (!empty($response)) {
            $pageCount = (int)ceil(count($response) / 12);
            $totalMeters = count($response);
            $meterHistories = self::parseMeterHistories($offset, $response);
        } else {
            return [];
        }

        return [
            'meters' => $meterHistories,
            'pageCount' => $pageCount,
            'total' => $totalMeters
        ];
    }

    private static function parseMeterHistories($offset, $json_response): array
    {
        $itemsPerPage = 12;
        $items = array_slice($json_response, $offset, $itemsPerPage);
        foreach ($items as $meter) {
            $p[] = "\n" . '<b>Дата:</b> ' . $meter['datecreate'] . "\n"
                . '<b>Услуга:</b>' . $meter['usluga_name'] . "\n"
                . '<b>Номер:</b> ' . $meter['nomer'] . "\n"
                . '<b>Показание:</b> ' . $meter['ipu_pokaz'] . "\n";
        }
        return $p;
    }

    private static function parsePaymentHistories($offset, $json_response): array
    {
        $itemsPerPage = 12;
        $items = array_slice($json_response, $offset, $itemsPerPage);
        foreach ($items as $payment) {
            $p[] = "\n\n" . '<b>Дата:</b> ' . $payment['date_pay'] . "\n"
                . '<b>Поставщик:</b>' . $payment['supplier_name'] . "\n"
                . '<b>Услуга:</b> ' . $payment['service_name'] . "\n"
                . '<b>Штрих-код:</b> ' . $payment['barcode'] . "\n"
                . '<b>Адрес:</b> ' . $payment['address'] . "\n"
                . '<b>Сумма:</b> ' . $payment['summ'] . ' руб.' . "";
            //. '<b>Чек:</b> (https://vc.tom.ru/get-history-invoices/' . $payment['id'] . ').';
        }
        return $p;
    }

    private static function fetchAuthUrl($method, $access_token, $sig, $options = null)
    {
        //$method = 'payments.getbyreceipt';
        $url = self::API_URL . '/apps/?token=' . $access_token
            . '&app_id=' . self::APP_ID
            . '&method=' . $method
            . '&' . $options
            . '&sig=' . $sig;
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($httpCode !== 200) {
            return [];
        }
        return json_decode($response, true)['data'];
    }


    public static function getHashLinkInvoice($userId, $barcode, $period): string
    {
        $access_token = (new Api())->getAccessToken($userId);
        $method = 'receipts.getfromreports';
        $sig = self::genSig($access_token, $method, 'code=' . $barcode . 'period=' . $period);
        $response = self::fetchAuthUrl($method, $access_token, $sig, 'code=' . $barcode . '&period=' . $period);
        if ($response['status']) {
            return 'https://vc.tom.ru/invoices/' . $response['hash_link'];
        } else {
            return '';
        }
    }
}
