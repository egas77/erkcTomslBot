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

class PaymentCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'payment';

    /**
     * @var string
     */
    protected $description = 'payment command';

    /**
     * @var string
     */
    protected $usage = '/payment';

    /**
     * @var string
     */
    protected $version = '1.2.0';

    /**
     * @var bool
     */
    protected $private_only = true;
    private Conversation $conversation;

    /**
     * Main command execution
     * @return ServerResponse
     * @throws TelegramException
     */
    public function execute(): ServerResponse
    {
        if ($message = $this->getMessage()) {
            $user_id = $message->getFrom()->getId();
            $chat = $message->getChat();
            $text = trim($message->getText(true));
            $message_id = $message->getMessageId();
            $getFirstName = $message->getFrom()->getFirstName();
            $getLastName = $message->getFrom()->getLastName();
            $chat_id = $message->getChat()->getId();
        } elseif ($callback_query = $this->getCallbackQuery()) {
            $user_id = $callback_query->getFrom()->getId();
            $message_id = $callback_query->getMessage()->getMessageId();
            $getFirstName = $callback_query->getFrom()->getFirstName();
            $getLastName = $callback_query->getFrom()->getLastName();
            $chat_id = $callback_query->getMessage()->getChat()->getId();
        }
        $result = Request::emptyResponse();

       // if (Api::is_registered($user_id)) {
            $this->conversation = new Conversation($user_id, $chat_id, $this->getName());
            $notes = &$this->conversation->notes;
            !is_array($notes) && $notes = [];
            $state = $notes['state'] ?? 0;
            $text = str_replace(' руб.', '', $text);

            switch ($state) {
                case 0:
                    if ($text === 'Оплатить квитанцию 💳') {
                        $notes['state'] = 1;
                        $this->conversation->update();
                        $data['text'] = 'Для оплаты выберите квитанцию:';
                        $data['chat_id'] = $chat_id;
                        $this->getSelectReceiptKeyboard($user_id, $data);
                        break;
                    }
                case 1:
                    if (preg_match('/^(\d{12,13})/', $text, $matches)) {
                        $notes['state'] = 2;
                        $notes['barcode'] = (int)$matches[0];
                        $api = new Api();
                        if (!Api::hasBarcode($user_id, $notes['barcode'])) {
                            return Request::sendMessage(
                                [
                                    'chat_id' => $message->getChat()->getId(),
                                    'text' => 'У Вас нет такого штрихкода.'
                                ]
                            );
                        }
                        $payload = $api->checkBarcodeByText((int)$matches[1]);
                        $text_percent_q = 'Введите сумму к зачислению или выберите из меню:';
                        $amount = round($payload['amount'], 2);
                        $amount_without_percent = round($payload['amount'], 2);
                        if ($payload['percent_q'] > 0) {
                            $notes['percent_q'] = $payload['percent_q'];
                            $notes['summa_percent'] =
                                round($payload['amount'] * $payload['percent_q'] / 100, 2);
                            $notes['agree_payment_with_percent'] = false;
                        }
                        $result = Request::sendMessage([
                            'chat_id' => $chat_id,
                            'text' => 'Вы собираетесь оплатить:<b>' . $payload['supplier_name'] . '</b> за <b>'
                                . $payload['service_name'] . '</b>.' . "\n"
                                . 'По адресу: <b>' . $payload['address'] . '</b>' . "\n"
                                . 'Текущая задолженность: <b>' . $amount_without_percent . ' руб.</b>' . "\n"
                                . $text_percent_q
                            ,
                            'parse_mode' => 'html',
                            'reply_markup' => new Keyboard([$amount . ' руб.'], ['Назад'])
                        ]);
                        $this->conversation->update();
                        break;
                    } else {
                        break;
                    }
                case 2:
                    if ($text === '' || !is_numeric($text)) {
                        $notes['state'] = 2;
                        $this->conversation->update();
                        $result = Request::sendMessage(
                            [
                                'chat_id' => $message->getChat()->getId(),
                                'text' => 'Не корректная сумма. Пример: 102 или 102.33'
                            ]
                        );
                        break;
                    }
                    $notes['state'] = 3;
                    $notes['summa'] = round($text, 2);
                    $notes['summa_percent'] =
                        round($notes['summa'] * $notes['percent_q'] / 100, 2);
                    $text = '';
                case 3:
                    if ($notes['state'] === 3 && $notes['percent_q'] > 0 && !$notes['agree_payment_with_percent']) {
                        $this->conversation->update();
                        $agree = 'Необходимо дать согласие с <a href="https://vc.tom.ru/pay/conditions/">условиями</a>'
                            . ' оплаты и на оплату комиссии при её наличии!';
                        $total_summa = round($notes['summa'] + $notes['summa_percent'], 2);
                        $result = Request::sendMessage([
                            'chat_id' => $chat_id,
                            'text' => $agree,
                            'parse_mode' => 'html',
                            'reply_markup' => new Keyboard([
                                'Я ознакомлен и согласен с условиями оплаты и согласен оплатить комиссию в размере 1.52% ('
                                . $notes['summa_percent'] . ' руб.) Итого к оплате ' . $total_summa . ' руб.'], ['Назад'])
                        ]);
                        break;
                    }
                    $text = '';
                case 4:
                    if ($text === '') {
                        $notes['state'] = 4;
                        $this->conversation->update();
                        $email = Api::getEmailByUserId($user_id);
                        if ($email) {
                            $keyboardLayout[] = [$email];
                        }
                        $keyboardLayout[] = ['Назад'];
                        $result = Request::sendMessage(
                            [
                                'chat_id' => $message->getChat()->getId(),
                                'text' => 'Укажите почтовый адрес для отправки электронного чека:',
                                'reply_markup' => new Keyboard(...$keyboardLayout)
                            ]
                        );
                        break;
                    }
                    if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
                        $result = Request::sendMessage(
                            [
                                'chat_id' => $message->getChat()->getId(),
                                'text' => $text . ' не является действительным адресом электронной почты.'
                            ]
                        );
                        break;
                    }
                    $notes['email'] = $text;
                    $this->conversation->update();
                    $summa_for_link_to_pay = $notes['summa'] + $notes['summa_percent'];
                    $responseApi = Api::gen_payment_link($summa_for_link_to_pay, $notes['barcode'], $notes['email']);
                    if ($responseApi['status']) {
                        $notes['paymentUrl'] = $responseApi['url'];
                        $inline_keyboard = new InlineKeyboard([
                            ['text' => 'Оплатить: ' . $summa_for_link_to_pay . ' руб.', 'url' => $responseApi['url']]
                        ]);
                        Request::sendMessage([
                            'chat_id' => $chat_id,
                            'text' => 'Ваша ссылка на оплату 👇',
                            'reply_markup' => $inline_keyboard
                        ]);
                    } else {
                        $notes['paymentUrl'] = $responseApi['url'];
                        Request::sendMessage([
                            'chat_id' => $chat_id,
                            'text' => '‼ ' . $responseApi['desc'] . ' ‼',
                            'parse_mode' => 'html'
                        ]);
                    }
                    $this->conversation->update();
                    unset($notes['state']);
                    $this->conversation->stop();
                    $result = $this->telegram->executeCommand('start_basic');
                    break;
            }
//        } else {
//            Request::sendMessage([
//                'chat_id' => $chat_id,
//                'text' => 'Вы не добавили ни одной квитанции.',
//                'parse_mode' => 'html'
//            ]);
//            return $this->telegram->executeCommand('start_basic');
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
