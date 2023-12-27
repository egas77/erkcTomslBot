<?php

/**
 * This file is part of the PHP Telegram Bot example-bot package.
 * https://github.com/php-telegram-bot/example-bot/
 * (c) PHP Telegram Bot Team
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Start command
 * Gets executed when a user first starts using the bot.
 * When using deep-linking, the parameter can be accessed by getting the command text.
 * @see https://core.telegram.org/bots#deep-linking
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Services\Erkc\Api;
use Services\Erkc\ErkcKeyboards;

class SendmeterCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'send_meter';

    /**
     * @var string
     */
    protected $description = 'send_meter command';

    /**
     * @var string
     */
    protected $usage = '/send_meter';

    /**
     * @var string
     */
    protected $version = '1.2.0';

    /**
     * @var bool
     */
    protected $private_only = true;

    /**
     * Main command execution
     * @return ServerResponse
     * @throws TelegramException
     */
    public function execute(): ServerResponse
    {
        $text = null;
        $user_id = null;
        if ($message = $this->getMessage()) {
            $user_id = $message->getFrom()->getId();
            $text = trim($message->getText(true));
            $chat_id = $message->getChat()->getId();

        } elseif ($callback_query = $this->getCallbackQuery()) {
            $user_id = $callback_query->getFrom()->getId();
            $chat_id = $callback_query->getMessage()->getChat()->getId();
        }
        $result = Request::emptyResponse();
        //if (Api::is_registered($user_id)) {
            $this->conversation = new Conversation($user_id, $chat_id, $this->getName());
            $notes = &$this->conversation->notes;
            !is_array($notes) && $notes = [];
            $state = $notes['state'] ?? 0;
            switch ($state) {
                case 0:
                    if ($text === 'Передать показания 🔍') {
                        $notes['state'] = 1;
                        $this->conversation->update();
                        $data['text'] = 'Для передачи показаний выберите квитанцию:';
                        $data['chat_id'] = $chat_id;
                        $this->getSelectReceiptKeyboard($user_id, $data);
                        break;
                    }
                case 1:
                    if (preg_match('/^(\d{12,13}) - /', $text, $matches)) {
                        $payload = Api::getPayloadByBarcode((int)$matches[1]);
                        if (!$payload['meters']['status']) {
                            Request::sendMessage([
                                'chat_id' => $chat_id,
                                'text' => 'По данной квитанции не предусмотрена передача данных по показаниям ИПУ, выберите другую:',
                            ]);
                            $notes['barcode'] = (int)$matches[1];
                            $notes['status'] = $payload['meters']['desc'];
                            $this->conversation->update();
                            return Request::emptyResponse();
                        }

                        // Выводим информацию перед началом ввода показаний
                        $result = Request::sendMessage([
                            'chat_id' => $chat_id,
                            'text' => 'Вы собираетесь передать показания по:<b>' . $payload['supplier_name']
                                . '</b> за <b>' . $payload['service_name'] . '</b>.' . "\n"
                                . 'По адресу: <b>' . $payload['address'] . '</b>' . "\n",
                            'parse_mode' => 'html',
                            'reply_markup' => ErkcKeyboards::getBackKb()->setResizeKeyboard(true)
                        ]);
                        unset($notes['status']);
                        $notes['barcode'] = (int)$matches[0];
                        $this->conversation->update();

                        $all_meters = Api::getMetersFromApi($payload['meters']);
                        $current_meter_index = 0;

                        if (isset($all_meters[$current_meter_index])) {
                            $current_meter = $all_meters[$current_meter_index];
                            $result = Request::sendMessage([
                                'chat_id' => $chat_id,
                                'text' => "Внесите показание для счетчика {$current_meter['usluga_name']}"
                            ]);

                            $notes['current_meter_index'] = 0;
                            $notes['all_meters'] = $all_meters;
                            $notes['state'] = 2;
                            $this->conversation->update();
                        }
                    }
                    break;
                case 2:
                    if (is_numeric($text)) {
                        $current_meter = $notes['all_meters'][$notes['current_meter_index']];
                        $meter_id = $current_meter['id'];
                        $meter_number = $current_meter['nomer'];
                        $meter_name = "ipu_" . $meter_id;
                        $notes['meters'][] = [
                            'name' => $meter_name,
                            'user_input' => $text,
                            'ipu_nomer' => $meter_number
                        ];
                        $notes['current_meter_index'] = $notes['current_meter_index'] + 1;
                        $this->conversation->update();
                    } else {
                        $result = Request::sendMessage([
                            'chat_id' => $chat_id,
                            'text' => "Введенное значение не является числом. Пожалуйста, введите число."
                        ]);
                        break;
                    }
                    if (isset($notes['current_meter_index']) && $notes['current_meter_index'] > 0) {
                        $current_meter_index = $notes['current_meter_index'];
                        if (isset($notes['all_meters'][$current_meter_index])) {
                            $current_meter = $notes['all_meters'][$current_meter_index];
                            $result = Request::sendMessage([
                                'chat_id' => $chat_id,
                                'text' => "Внесите показание для счетчика {$current_meter['usluga_name']}"
                            ]);
                        } else {
                            $api_parameters = [];
                            foreach ($notes['meters'] as $meter) {
                                $api_parameters[] = "{$meter['name']}={$meter['user_input']}";
                            }
                            $api_parameters_string = implode('&', $api_parameters);
                            unset($notes['current_meter_index']);
                            $this->conversation->update();
                            $barcode = $notes['barcode'];
                            $this->conversation->stop();
                            $responseApi = Api::sendMetersData($barcode, $api_parameters_string);
                            if ($responseApi['status']) {
                                Request::sendMessage([
                                    'chat_id' => $chat_id,
                                    'text' => 'Спасибо, мы сохранили Ваши показания. Но они могут быть переданы поставщику, только после совершения оплаты его услуг!'
                                ]);
                                $result = $this->telegram->executeCommand('start_basic');
                                break;
                            } else {
                                Request::sendMessage([
                                    'chat_id' => $chat_id,
                                    'text' => '‼ ' . $responseApi['desc'] . ' ‼',
                                    'parse_mode' => 'html'
                                ]);
                                $result = $this->telegram->executeCommand('start_basic');
                            }
                        }
                    }
            }
//        } else {
//            Request::sendMessage([
//                'chat_id' => $chat_id,
//                'text' => 'Вы не зарегистрированы!',
//                'parse_mode' => 'html'
//            ]);
//            return $this->telegram->executeCommand('start');
//        }
        return $result;
    }

    private function getSelectReceiptKeyboard(int $user_id, $data): ServerResponse
    {
        $list_barcodes = Api::getUserBarcodesByUserId($user_id);
        $kb = new Keyboard([]);
        foreach ($list_barcodes as $barcode_info) {
            $barcode = json_decode($barcode_info['payload'], true)['barcode'];
            $barcode_service_name = json_decode($barcode_info['payload'], true)['service_name'];
            $barcode_address = json_decode($barcode_info['payload'], true)['address'];
            if ($barcode_info['is_active'] === 1) {
                $kb->addRow($barcode . " - " . $barcode_service_name . "\nАдрес: " . $barcode_address);
            } else {
                $kb->addRow($barcode . " - " . $barcode_service_name . "\nАдрес: " . $barcode_address);
            }
        }
        $kb->addRow('Назад');
        $data['parse_mode'] = 'html';
        $data['reply_markup'] = $kb;
        return Request::sendMessage($data);
    }


}
