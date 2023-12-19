<?php

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;

class SendsuggestionCommand extends UserCommand
{
    protected $name = 'send_suggestion';
    protected $description = 'A command for test';
    protected $usage = '/send_suggestion';
    protected $version = '1.0.0';

    public function execute(): ServerResponse
    {
        $chat_id = null;
        $user_id = null;
        if ($message = $this->getMessage()) {
            $user_id = $message->getFrom()->getId();
            $chat_id = $message->getChat()->getId();
        } elseif ($callback_query = $this->getCallbackQuery()) {
            $user_id = $callback_query->getFrom()->getId();

            $chat_id = $callback_query->getMessage()->getChat()->getId();
        }
        $data = [
            'chat_id' => $chat_id,
            'parse_mode' => 'html',
            'reply_markup' => new Keyboard(['Назад'])
        ];

        if ($message->getText() === 'Назад') {
            return $this->getTelegram()->executeCommand('cancel');
        }

        $conversation = new Conversation($user_id, $chat_id, $this->getName());

        $notes = &$conversation->notes;
        !is_array($notes) && $notes = [];
        $state = $notes['state'] ?? 0;

        switch ($state) {
            case 0:
                $notes['state'] = 1;
                $data['text'] = 'Укажите Ваше имя:';
                $conversation->update();
                return Request::sendMessage($data);
            case 1:
                $notes['name'] = trim($message->getText());
                $notes['state'] = 2;
                $conversation->update();
                $data['text'] = 'Опишите ваши предложения или замечания:';
                return Request::sendMessage($data);
            case 2:
                $notes['text'] = trim($message->getText());
                $conversation->update();

                $data['text'] = 'Мы рады получить Ваш отзыв или предложение о работе бота, чтобы сделать его еще удобнее для Вас.';
                $msg_from_client = "<b>Поступила информация от клиента по работе с ботом:</b>\n"
                    . "<b>Указанное имя:</b> " . $notes['name'] . "\n"
                    . "<b>TelegramId:</b> " . $message->getFrom()->getId() . "\n"
                    . "<b>FirstName:</b> " . $message->getFrom()->getFirstName() . "\n"
                    . "<b>UserName:</b> @" . $message->getFrom()->getUsername() . "\n"
                    . "Текст: " . $notes['text'];
                $conversation->stop();
                Request::sendMessage(['chat_id' => -887608214, 'text' => $msg_from_client, 'parse_mode' => 'html']);
                Request::sendMessage($data);
                return $this->telegram->executeCommand('start_basic');
            default:
                $conversation->stop();
                return Request::sendMessage(['chat_id' => $chat_id, 'text' => 'Произошла ошибка.']);
        }
    }
}