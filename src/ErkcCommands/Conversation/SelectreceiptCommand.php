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
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Services\Erkc\Api;

class SelectreceiptCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'select_receipt';

    /**
     * @var string
     */
    protected $description = 'select_receipt command';

    /**
     * @var string
     */
    protected $usage = '/select_receipt';

    /**
     * @var string
     */
    protected $version = '1.2.0';

    /**
     * @var bool
     */
    protected $private_only = true;

    protected Conversation $conversation;

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
            $chat = $callback_query->getMessage()->getChat();
            $getFirstName = $callback_query->getFrom()->getFirstName();
            $getLastName = $callback_query->getFrom()->getLastName();
            $chat_id = $callback_query->getMessage()->getChat()->getId();
        }
        $data = ['chat_id' => $chat_id, 'reply_markup' => Keyboard::remove(['selective' => true])];
        if ($chat->isGroupChat() || $chat->isSuperGroup()) {
            $data['reply_markup'] = Keyboard::forceReply(['selective' => true]);
        }
        $result = Request::emptyResponse();
        if (Api::is_registered($user_id)) {
            $this->conversation = new Conversation($user_id, $chat_id, $this->getName());
            $notes = &$this->conversation->notes;
            !is_array($notes) && $notes = [];
            $state = $notes['state'] ?? 0;
            // State machine
            switch ($state) {
                case 0:
                    $message_type = $message->getType();
                    if (($message_type === 'text' && $text === 'Выбрать квитанцию')) {
                        $notes['state'] = 0;
                        $this->conversation->update();
                        $data['text'] = 'Введите штрих-код квитанции или выберите из списка предложенных:';
                        $result = $this->getSelectReceiptKeyboard($user_id, $data);
                        break;
                    } elseif ($message_type === 'text' && !is_numeric($text)) {
                        $result = Request::sendMessage(
                            [
                                'chat_id' => $message->getChat()->getId(),
                                'text' => 'Штрихкод должен состоять только из цифр, проверьте позжайлуста.'
                            ]
                        );
                        break;
                    }

                    if (Api::setActiveBarcode($user_id, $text)) {
                        $notes['setIsActiveBarcode'] = $text;
                        $this->conversation->update();
                        unset($notes['state']);
                        $this->conversation->stop();
                        $result = $this->telegram->executeCommand('start_basic');
                    } else {
                        $result = Request::sendMessage(
                            [
                                'chat_id' => $message->getChat()->getId(),
                                'text' => 'Не сработало'
                            ]
                        );
                    }
            }
        } else {
            Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => 'Вы не добавили ни одной квитанции.',
                'parse_mode' => 'html'
            ]);
            return $this->telegram->executeCommand('start');
        }
        return $result;
    }

    private function getSelectReceiptKeyboard(int $user_id, $data): ServerResponse
    {
        $list_barcodes = Api::getUserBarcodesByUserId($user_id);
        $kb = new Keyboard([]);
        foreach ($list_barcodes as $barcode_info) {
            $barcode = json_decode($barcode_info['payload'], true)['barcode'];
            $kb->addRow($barcode);
        }
        $kb->addRow('Назад');
        $data['parse_mode'] = 'html';
        $data['reply_markup'] = $kb;
        return Request::sendMessage($data);
    }
}
