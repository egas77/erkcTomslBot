<?php

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Services\Erkc\Api;

class HistorymetersCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'history_meters';

    /**
     * @var string
     */
    protected $description = 'history meter command';

    /**
     * @var string
     */
    protected $usage = '/history_meters';

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
        $chat_id = null;
        $user_id = null;
        if ($message = $this->getMessage()) {
            $user_id = $message->getFrom()->getId();
            $message_id = $message->getMessageId();
            $chat_id = $message->getChat()->getId();
        } elseif ($callback_query = $this->getCallbackQuery()) {
            $user_id = $callback_query->getFrom()->getId();
            $message_id = $callback_query->getMessage()->getMessageId();
            $chat_id = $callback_query->getMessage()->getChat()->getId();
        }
        return $this->buildInvoiceAccountSelectionKeyboard($chat_id, $user_id, $message_id);
    }

    private function buildInvoiceAccountSelectionKeyboard($chat_id, $user_id, $message_id): ServerResponse
    {
        $list_barcodes = Api::getUserBarcodesByUserId($user_id);
        if (empty($list_barcodes)) {
            return Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => 'у Вас нет добавленных квитанций.'
            ]);
        }
        // Преобразование массива
        foreach ($list_barcodes as $item) {
            $payload = json_decode($item['payload'], true);
            // Проверяем есть ли данные о счётчиках
            if (isset($payload['meters'])) {
                if ($payload['meters']['status']) {
                    if ($payload && isset($payload['service_name']) && isset($payload['address'])) {
                        $barcodes[] = [
                            'barcode' => $item['barcode'],
                            'service_name' => $payload['service_name'],
                            'address' => $payload['address']
                        ];
                    }
                }
            }
        }

        $keyboard = [];
        foreach ($barcodes as $barcode) {
            $keyboard[] = [
                'text' => (string)$barcode['barcode'],
                'callback_data' => 'cb_meter_barcode_' . $barcode['barcode']
            ];
        }
        $first_message_id = 0;
        $ikb_delete = ['text' => 'Скрыть', 'callback_data' => 'delete_msg_' . $message_id . '_' . $first_message_id];
        return Request::sendMessage([
            'chat_id' => $chat_id,
            'text' => 'Выберите штрихкод:',
            'parse_mode' => 'html',
            'reply_markup' => new InlineKeyboard($keyboard, [$ikb_delete])
        ]);
    }
}
