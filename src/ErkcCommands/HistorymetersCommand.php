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
            file_put_contents('historiesMeterCallback.log', 'callback_query:' . $user_id . "\n", FILE_APPEND);
        }

        //Request::emptyResponse();
        $meters = Api::getMeterHistories(0, $user_id);
        if (empty($meters)) {
            return Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => 'Сервер не доступен, попробуйте позже!'
            ]);
        }
        $inline_keyboard = new InlineKeyboard([
            [
                'text' => '1/' . $meters['pageCount'], 'callback_data' => 'cb_loading_meter'
            ],
            [
                'text' => 'Вперед  ➡',
                'callback_data' => 'cb_meter_page_12_' . $meters['total'] . '_' . $meters['pageCount'] . '_2_' . $message_id
            ],
        ]);

        return Request::sendMessage([
            'chat_id' => $chat_id,
            'text' => implode($meters['meters']),
            'parse_mode' => 'html',
            'reply_markup' => $inline_keyboard
        ]);
    }
}
