<?php

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
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
        if (!empty($list_barcodes)) {
            $payments = Api::getPaymentHistories(0, $user_id, $list_barcodes);
            if (empty($payments)) {
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Платежи не найдены.'
                ]);
            }
            $inline_keyboard = new InlineKeyboard([
                [
                    'text' => '1/' . $payments['pageCount'], 'callback_data' => 'cb_loading'
                ],
                [
                    'text' => 'Вперед  ➡',
                    'callback_data' => 'cb_payment_page_12_' . $payments['total'] . '_' . $payments['pageCount'] . '_2_' . $message_id
                ],
            ]);

            return Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => implode($payments['payments']),
                'parse_mode' => 'html',
                'reply_markup' => $inline_keyboard
            ]);
        } else {
            return Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => 'у Вас нет добавленных квитанций.'
            ]);
        }
    }

    function displayPayments($chat_id, $offset = 0)
    {
        // Получение данных оплат (возможно, из базы данных)
        $payments = getPaymentsFromDatabase($offset); // Вернёт массив из, например, 12 записей

        // Формирование текста сообщения
        $messageText = "История оплат:\n";
        foreach ($payments as $payment) {
            $messageText .= date('Y-m-d', $payment['date']) . " - " . $payment['amount'] . " руб.\n";
        }

        // Формирование inline-клавиатуры
        $inlineKeyboard = [
            'inline_keyboard' => []
        ];
        if ($offset > 0) {
            $inlineKeyboard['inline_keyboard'][] = [
                [
                    'text' => 'Назад',
                    'callback_data' => 'prev_' . ($offset - 12)
                ]
            ];
        }
        $inlineKeyboard['inline_keyboard'][] = [
            [
                'text' => 'Следующий',
                'callback_data' => 'next_' . ($offset + 12)
            ]
        ];

        // Обновляем предыдущее сообщение
        editMessageText($chat_id, $message_id, $messageText); // Фиктивная функция
        editMessageReplyMarkup($chat_id, $message_id, $inlineKeyboard); // Фиктивная функция
    }

// Обработка callback-кнопок
    function handleCallback($callbackData, $chat_id, $message_id)
    {
        $offset = 0;
        if (strpos($callbackData, 'next_') === 0) {
            $offset = (int)substr($callbackData, 5);
        } elseif (strpos($callbackData, 'prev_') === 0) {
            $offset = max(0, (int)substr($callbackData, 5));
        }
        displayPayments($chat_id, $offset);
    }
}
