<?php

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Services\Erkc\Api;
use Services\Erkc\ErkcKeyboards;

class GeninvoiceCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'gen_invoice';

    /**
     * @var string
     */
    protected $description = 'gen_invoice command';

    /**
     * @var string
     */
    protected $usage = '/gen_invoice';

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
        $user_id = null;
        $chat_id = null;
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

        $conversation = new Conversation($user_id, $chat_id, $this->getName());
        $notes = &$conversation->notes;
        !is_array($notes) && $notes = [];
        $state = $notes['state'] ?? 0;
        $text = str_replace(' руб.', '', $text);
        switch ($state) {
            case 0:
                if ($text === 'Сформировать квитанцию 🖨️') {
                    $notes['state'] = 1;
                    $conversation->update();
                    $data['text'] = 'Выберите квитанцию:';
                    $data['chat_id'] = $chat_id;
                    $this->getSelectReceiptKeyboard($user_id, $data);
                    break;
                }
            case 1:
                if (preg_match('/^\d{12,13}/', $text, $matches) !== false) {
                    $notes['state'] = 2;
                    $notes['barcode'] = (int)$matches[0];
                    $conversation->update();
                    $payload = Api::getPayloadByBarcode((int)$matches[0]);
                    $result = Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Выбрана квитанция:<b>' . $payload['supplier_name'] . '</b> за <b>' . $payload['service_name'] . '</b>.' . "\n"
                            . 'По адресу: <b>' . $payload['address'] . '</b>' . "\n"
                            . 'Укажите период в формате годмесяц слитно (202303):  ',
                        'parse_mode' => 'html',
                        'reply_markup' => new Keyboard(['Назад'])
                    ]);
                    break;
                } else {
                    $notes['state'] = 0;
                    $notes['text'] = $text;
                    $conversation->update();
                    Request::sendMessage(
                        [
                            'chat_id' => $message->getChat()->getId(),
                            'text' => 'Указан не верный штрихкод'
                        ]
                    );
                    $data['text'] = 'Выберите квитанцию:';
                    $data['chat_id'] = $chat_id;
                    $result = $this->getSelectReceiptKeyboard($user_id, $data);
                    break;
                }

            case 2:
                if ($text === '' || !is_numeric($text) || strlen($text) !== 6) {
                    $notes['state'] = 2;
                    $conversation->update();
                    $result = Request::sendMessage(
                        [
                            'chat_id' => $message->getChat()->getId(),
                            'text' => 'Перид указан не корректо. Пример: 202309'
                        ]
                    );
                    break;
                }
                $notes['period'] = $text;
                $text = '';
            case 3:
                $notes['state'] = 3;
                Request::sendMessage(
                    [
                        'chat_id' => $message->getChat()->getId(),
                        'text' => 'Запускаю формирование квитанции..'
                    ]
                );
                $conversation->update();
                $url = Api::getHashLinkInvoice($user_id, $notes['barcode'], $notes['period']);
                if (empty($url)) {
                    Request::sendMessage(
                        [
                            'chat_id' => $message->getChat()->getId(),
                            'text' => 'По вашей квитанции не возможно сформировать печатный вариант!',
                            'reply_markup' => ErkcKeyboards::keyboardByRegisteredUser()
                                ->setResizeKeyboard(true)
                                ->setSelective(false)
                        ]
                    );
                } else {
                    $notes['url'] = $url;
                    $conversation->update();
                    Request::sendMessage(
                        [
                            'chat_id' => $message->getChat()->getId(),
                            'text' => 'Ваша ссылка на квитанцию: ' . $url,
                            'reply_markup' => ErkcKeyboards::keyboardByRegisteredUser()
                                ->setResizeKeyboard(true)
                                ->setSelective(false)
                        ]
                    );
                }
                unset($notes['state']);
                $conversation->stop();
                $result = $this->telegram->executeCommand('start_basic');
                break;
        }
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
