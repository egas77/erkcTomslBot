<?php

/**
 * This file is part of the PHP Telegram Bot example-bot package.
 * https://github.com/php-telegram-bot/example-bot/
 * (c) PHP Telegram Bot Team
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Generic message command
 * Gets executed when any type of message is sent.
 * In this conversation-related context, we must ensure that active conversations get executed correctly.
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Services\Erkc\Api;


class GenericmessageCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'genericmessage';

    /**
     * @var string
     */
    protected $description = 'Handle generic message';

    /**
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * @var bool
     */
    protected $need_mysql = true;

    /**
     * Command execute method if MySQL is required but not available
     * @return ServerResponse
     */
    public function executeNoDb(): ServerResponse
    {
        // Do nothing
        return Request::emptyResponse();
    }

    /**
     * Main command execution
     * @return ServerResponse
     * @throws TelegramException
     */
    public function execute(): ServerResponse
    {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $user_id = $message->getFrom()->getId();

        $conversation = new Conversation(
            $message->getFrom()->getId(),
            $message->getChat()->getId()
        );

        $text = $message->getText();

        if (str_starts_with($text, 'Я ознакомлен и согласен с условиями оплаты')) {
            $conversation->notes['agree_payment_with_percent'] = true;
            $conversation->notes['state'] = 3;
            $conversation->update();
        } else if (str_starts_with($text, 'Отправить замечания или предложение по работе бота. ✍')) {
            new Conversation($user_id, $chat_id, 'send_suggestion');
            return $this->getTelegram()->executeCommand('send_suggestion');
        } else if ($text === 'Что может бот ❓'){
            return $this->getTelegram()->executeCommand('start');
        } else if ($text === 'История платежей 📚') {
            return $this->getTelegram()->executeCommand('histories');
        } else if ($text === 'История показаний 📈') {
            return $this->getTelegram()->executeCommand('history_meters');
        } else if ($text === 'Добавить квитанцию 📥') {
            return $this->getTelegram()->executeCommand('signup');
        } else if ($text === 'Оплатить квитанцию 💳') {
            new Conversation($user_id, $chat_id, 'payment');
            return $this->getTelegram()->executeCommand('payment');
        } else if ($text === 'Список квитанций 📋') {
            return $this->getTelegram()->executeCommand('list_receipt');
        } else if ($text === 'Сформировать квитанцию 🖨️') {
            new Conversation($user_id, $chat_id, 'gen_invoice');
            return $this->getTelegram()->executeCommand('gen_invoice');
        } else if ($text === 'Удалить квитанцию 🗑️') {
            new Conversation($user_id, $chat_id, 'remove_receipt');
            //return $this->getTelegram()->executeCommand('remove_receipt');
            return $this->getTelegram()->executeCommand('remove_receipt');
        } else if ($text === 'Передать показания 🔍') {
            return $this->getTelegram()->executeCommand('send_meter');
        } else if ($text === 'Выбрать квитанцию') {
            new Conversation($user_id, $chat_id, 'select_receipt');
            return $this->getTelegram()->executeCommand('select_receipt');
        } else if ($message->getType() === 'contact') {
            //проверка мобильного на vc.tom.ru
            $response = Api::authByPhone($message->getContact()->getPhoneNumber());
            if (!empty($response)) {
                //сохраняем номер
                if (Api::setMobile($user_id, $message->getContact()->getPhoneNumber())) {
                    //устанавливаем что зарегистрирован пользователь
                    if (Api::update_user_registered($user_id, 1)) {
                        //TODO Берем данные о пользователе из апи
                        $info = Api::getUserInfo($user_id);
                        try {
                            Api::update_user_email($user_id, $info['email']);
                        } catch (\Exception $exception) {
                            Request::sendMessage(
                                [
                                    'chat_id' => $chat_id,
                                    'text' => $exception->getMessage()
                                ]
                            );
                        }
                        Api::setAuthTokens($user_id, $response['refresh_token'], $response['access_token']);
                        return $this->getTelegram()->executeCommand('link_mobile');
                    }
                } else {
                    return Request::sendMessage(
                        [
                            'chat_id' => $message->getChat()->getId(),
                            'text' => 'Возникла ошибка при привязке номера. Попробуйте позже.'
                        ]
                    );
                }
            } else {
                return Request::sendMessage(
                    [
                        'chat_id' => $message->getChat()->getId(),
                        'text' => 'Ваш номер не найден.'
                    ]
                );
            }
        } else if ($text === 'Назад') {
            if ($conversation_command = $conversation->getCommand()) {
                $conversation->cancel();
            }
            return $this->getTelegram()->executeCommand('start_basic');
        }

        // return $this->getTelegram()->executeCommand('start_basic');
        if ($conversation->exists() && $command = $conversation->getCommand()) {
            return $this->telegram->executeCommand($command);
        } else {
            if ($text === 'Отмена ❌') {
                return $this->telegram->executeCommand('start');
            }
        }
        return Request::emptyResponse();
    }
}
