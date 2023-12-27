<?php

/**
 * This file is part of the PHP Telegram Bot example-bot package.
 * https://github.com/php-telegram-bot/example-bot/
 * (c) PHP Telegram Bot Team
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Callback query command
 * This command handles all callback queries sent via inline keyboard buttons.
 * @see InlinekeyboardCommand.php
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use Services\Erkc\Api;

class CallbackqueryCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'callbackquery';

    /**
     * @var string
     */
    protected $description = 'Handle the callback query';

    /**
     * @var string
     */
    protected $version = '1.2.0';

    /**
     * Main command execution
     * @return ServerResponse
     * @throws \Exception
     */
    public function execute(): ServerResponse
    {
        $callback_query = $this->getCallbackQuery();
        $callback_data = $callback_query->getData();
        $user_id = $callback_query->getFrom()->getId();
        $chat_id = $callback_query->getMessage()->getChat()->getId();
        $message_id = $callback_query->getMessage()->getMessageId();

        if (strpos($callback_data, 'delete_msg_') === 0) {
            $msgIdToRemove = (int)explode('_', $callback_data)[2];
            $msgIdToRemove_first = (int)explode('_', $callback_data)[3];


            Request::deleteMessage([
                'chat_id' => $chat_id,
                'message_id' => $msgIdToRemove_first,
            ]);
            Request::deleteMessage([
                'chat_id' => $chat_id,
                'message_id' => $msgIdToRemove,
            ]);
            return Request::deleteMessage([
                'chat_id' => $chat_id,
                'message_id' => (string)$message_id,
            ]);
        }
        if (strpos($callback_data, 'cb_meter_barcode_choice') === 0) {
            $first_message_id = (int)explode('_', $callback_data)[5];
            $message_id = (int)explode('_', $callback_data)[4];
            Request::deleteMessage([
                'chat_id' => $chat_id,
                'message_id' => $first_message_id,
            ]);
            Request::deleteMessage([
                'chat_id' => $chat_id,
                'message_id' => $message_id,
            ]);
            return $this->getTelegram()->executeCommand('history_meters');
        }
        if (strpos($callback_data, 'cb_meter_barcode_') === 0) {
            $barcode = explode('cb_meter_barcode_', $callback_data)[1];
            $meterHistory = Api::getMeterHistories(0, $user_id, 'code=' . $barcode);
            if (empty($meterHistory)) {
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Сервер не доступен, попробуйте позже!'
                ]);
            }
            $inline_keyboard = new InlineKeyboard([
                [
                    'text' => '1/' . $meterHistory['pageCount'],
                    'callback_data' => 'cb_loading_meter'
                ],
                [
                    'text' => 'Вперед  ➡',
                    'callback_data' => 'cb_meter_page_12_' . $meterHistory['total'] . '_' . $meterHistory['pageCount'] . '_2_' . $message_id . '_' . $barcode
                ],
            ], [['text' => 'Выбрать квитанцию', 'callback_data' => 'cb_meter_barcode_choice_' . $message_id]],
                [['text' => 'Скрыть', 'callback_data' => 'delete_msg_' . $message_id]],
            );

            return Request::editMessageText([
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => implode($meterHistory['meters']),
                'parse_mode' => 'html',
                'reply_markup' => $inline_keyboard,
            ]);
        }

        if (strpos($callback_data, 'cb_meter_page_') === 0) {

            $offset = (int)explode('_', $callback_data)[3];
            $paymentsTotal = (int)explode('_', $callback_data)[4];
            $pageCount = (int)explode('_', $callback_data)[5];
            $page = (int)explode('_', $callback_data)[6];
            $first_message_id = (int)explode('_', $callback_data)[7];
            $barcode = (int)explode('_', $callback_data)[8];

            $meterHistory = Api::getMeterHistories($offset, $user_id, 'code=' . $barcode);
            $keyboards = [];
            if ($page == 1) {
                $ikb_loaded =
                    ['text' => $page . '/' . $pageCount, 'callback_data' => 'page_counter'];
                $ikb_forward =
                    ['text' => 'Вперёд ➡', 'callback_data' => 'cb_meter_page_' . ($offset + 12) . '_' . $paymentsTotal . '_' . $pageCount . '_' . ($page + 1) . '_' . $first_message_id . '_' . $barcode];
                $keyboards[] = $ikb_loaded;
                $keyboards[] = $ikb_forward;
            } else if ($page == $pageCount) {
                $ikb_back =
                    ['text' => '⬅ Назад', 'callback_data' => 'cb_meter_page_' . ($offset - 12) . '_' . $paymentsTotal . '_' . $pageCount . '_' . ($page - 1) . '_' . $first_message_id . '_' . $barcode];
                $ikb_loaded =
                    ['text' => $page . '/' . $pageCount, 'callback_data' => 'page_counter'];
                $keyboards[] = $ikb_back;
                $keyboards[] = $ikb_loaded;
            } else {
                $ikb_back =
                    ['text' => '⬅ Назад', 'callback_data' => 'cb_meter_page_' . ($offset - 12) . '_' . $paymentsTotal . '_' . $pageCount . '_' . ($page - 1) . '_' . $first_message_id . '_' . $barcode];
                $ikb_loaded =
                    ['text' => $page . '/' . $pageCount, 'callback_data' => 'page_counter'];
                $ikb_forward =
                    ['text' => 'Вперёд ➡', 'callback_data' => 'cb_meter_page_' . ($offset + 12) . '_' . $paymentsTotal . '_' . $pageCount . '_' . ($page + 1) . '_' . $first_message_id . '_' . $barcode];
                $keyboards[] = $ikb_back;
                $keyboards[] = $ikb_loaded;
                $keyboards[] = $ikb_forward;
            }
            $ikb_choice = [['text' => 'Выбрать квитанцию', 'callback_data' => 'cb_meter_barcode_choice_' . $message_id . '_' . $first_message_id]];
            $ikb_delete = [['text' => 'Скрыть', 'callback_data' => 'delete_msg_' . $message_id . '_' . $first_message_id]];
            $inline_keyboard = new InlineKeyboard($keyboards, $ikb_choice, $ikb_delete);
            return Request::editMessageText([
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => implode($meterHistory['meters']),
                'parse_mode' => 'html',
                'reply_markup' => $inline_keyboard,
            ]);
        }

        if (strpos($callback_data, 'cb_payment_page_') === 0) {
            $offset = (int)explode('_', $callback_data)[3];
            $paymentsTotal = (int)explode('_', $callback_data)[4];
            $pageCount = (int)explode('_', $callback_data)[5];
            $page = (int)explode('_', $callback_data)[6];
            $first_message_id = (int)explode('_', $callback_data)[7];
            $list_barcodes = Api::getUserBarcodesByUserId($user_id);
            // Здесь получаем историю оплат для данного пользователя и смещения $offset
            $paymentHistory = Api::getPaymentHistories($offset, $user_id, $list_barcodes);
            $keyboards = [];
            if ($page === 1) {
                $offset_forward = $offset + 12;
                $ikb_loaded =
                    ['text' => $page . '/' . $pageCount, 'callback_data' => 'page_counter'];
                $ikb_forward =
                    ['text' => 'Вперёд ➡', 'callback_data' => 'cb_payment_page_'
                        . $offset_forward . '_' . $paymentsTotal . '_' . $pageCount . '_' . ($page + 1) . '_' . $first_message_id];
                $keyboards[] = $ikb_loaded;
                $keyboards[] = $ikb_forward;
            } else if ($page === $pageCount) {
                $offset_back = $offset - 12;
                $ikb_back =
                    ['text' => '⬅ Назад', 'callback_data' => 'cb_payment_page_'
                        . $offset_back . '_' . $paymentsTotal . '_' . $pageCount . '_' . ($page - 1) . '_' . $first_message_id];
                $ikb_loaded =
                    ['text' => $page . '/' . $pageCount, 'callback_data' => 'page_counter'];
                $keyboards[] = $ikb_back;
                $keyboards[] = $ikb_loaded;
            } else {
                $offset_forward = $offset + 12;
                $offset_back = $offset - 12;
                $ikb_back =
                    ['text' => '⬅ Назад', 'callback_data' => 'cb_payment_page_'
                        . $offset_back . '_' . $paymentsTotal . '_' . $pageCount . '_' . ($page - 1) . '_' . $first_message_id];
                $ikb_loaded =
                    ['text' => $page . '/' . $pageCount, 'callback_data' => 'page_counter'];
                $ikb_forward =
                    ['text' => 'Вперёд ➡', 'callback_data' => 'cb_payment_page_'
                        . $offset_forward . '_' . $paymentsTotal . '_' . $pageCount . '_' . ($page + 1) . '_' . $first_message_id];
                $keyboards[] = $ikb_back;
                $keyboards[] = $ikb_loaded;
                $keyboards[] = $ikb_forward;
            }
            $ikb_delete = ['text' => 'Скрыть', 'callback_data' => 'delete_msg_' . $message_id . '_' . $first_message_id];
            $inline_keyboard = new InlineKeyboard(
                $keyboards,
                [$ikb_delete]);


            return Request::editMessageText([
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => implode($paymentHistory['payments']),
                'parse_mode' => 'html',
                'reply_markup' => $inline_keyboard,
            ]);
            //   return $callback_query->answer();
        }

        if ($callback_data === 'menu_pay_overpayments') {
            $callback_query->answer([
                'text' => 'У вас переплата.',
                'show_alert' => 1,
                'cache_time' => 5,
            ]);
        }
        if ($callback_data === 'menu_pay') {
            return $this->telegram->executeCommand('payment');
        }
        if ($callback_data === 'menu_ipu_send') {
            return $this->telegram->executeCommand('send_meter');
        }
        if ($callback_data === 'menu_add_invoice') {
            return $this->telegram->executeCommand('add_receipt');
        }
        return $callback_query->answer();
    }

    public function changeKb($inline_keyboards, $selected_kb)
    {
        foreach ($inline_keyboards as &$street) {
            if ($street[0]['text'] . '_false' === $selected_kb) {
                $street[0]['text'] = '❌ ' . $street[0]['text'];
                break;
            } elseif ($street[0]['text'] === $selected_kb) {
                $street[0]['text'] = '✅ ' . $street[0]['text'];
                break;
            }
        }
        return $inline_keyboards;
    }
}
