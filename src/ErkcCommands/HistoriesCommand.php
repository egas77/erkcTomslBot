<?php

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Services\Erkc\Api;

class HistoriesCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'histories';

    /**
     * @var string
     */
    protected $description = 'histories command';

    /**
     * @var string
     */
    protected $usage = '/histories';

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
            if ($payload && isset($payload['service_name']) && isset($payload['address'])) {
                $barcodes[] = [
                    'barcode' => $item['barcode'],
                    'service_name' => $payload['service_name'],
                    'address' => $payload['address']
                ];
            }

        }
        $keyboard = [];
        foreach ($barcodes as $barcode) {
            $keyboard[] = [[
                'text' => (string)$barcode['barcode'],
                'callback_data' => 'cb_payment_barcode_' . $barcode['barcode']
            ]];
        }
        $keyboard[] = [['text' => 'Скрыть', 'callback_data' => 'delete_msg_' . $message_id . '_0']];
        $inline_keyboard = new InlineKeyboard(...$keyboard);

        return Request::sendMessage([
            'chat_id' => $chat_id,
            'text' => 'Выберите штрихкод:',
            'parse_mode' => 'html',
            'reply_markup' => $inline_keyboard
        ]);
    }
}
