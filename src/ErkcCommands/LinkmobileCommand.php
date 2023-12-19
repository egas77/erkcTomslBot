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
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Services\Erkc\Api;
use Services\Erkc\ErkcKeyboards;

class LinkmobileCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'link_mobile';

    /**
     * @var string
     */
    protected $description = 'link_mobile command';

    /**
     * @var string
     */
    protected $usage = '/link_mobile';

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
        if ($message = $this->getMessage()) {
            $user_id = $message->getFrom()->getId();
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

        if (Api::is_registered($user_id)) {
            $result = Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => 'Ваш номер успешно привязан.',
                'parse_mode' => 'html',
                'reply_markup' => ErkcKeyboards::keyboardByRegisteredUser()
                    ->setResizeKeyboard(true)
                    ->setSelective(false)

            ]);
        } else {
            Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => 'Мы не нашли Ваш номер телефона, возможно вы его не указали в профиле.',
                'parse_mode' => 'html'
            ]);
            $result = $this->telegram->executeCommand('start');
        }
        return $result;
    }
}
