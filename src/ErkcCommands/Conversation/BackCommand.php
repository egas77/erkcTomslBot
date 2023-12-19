<?php

/**
 * This file is part of the PHP Telegram Bot example-bot package.
 * https://github.com/php-telegram-bot/example-bot/
 * (c) PHP Telegram Bot Team
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * User "/cancel" command
 * This command cancels the currently active conversation and
 * returns a message to let the user know which conversation it was.
 * If no conversation is active, the returned message says so.
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Services\Erkc\ErkcKeyboards;

class BackCommand extends UserCommand
{
    /**
     * @var string
     */
    protected $name = 'back';

    /**
     * @var string
     */
    protected $description = 'Назад';

    /**
     * @var string
     */
    protected $usage = '/back';

    /**
     * @var string
     */
    protected $version = '0.0.1';

    /**
     * @var bool
     */
    protected $need_mysql = true;

    /**
     * Main command execution if no DB connection is available
     * @throws TelegramException
     */
    public function executeNoDb(): ServerResponse
    {
        return $this->removeKeyboard('Нечего отменять.');
    }

    /**
     * Main command execution
     * @return ServerResponse
     * @throws TelegramException
     */
    public function execute(): ServerResponse
    {
        $text = 'Нет активного диалога!';
        // Cancel current conversation if any
        $conversation = new Conversation(
            $this->getMessage()->getFrom()->getId(),
            $this->getMessage()->getChat()->getId()
        );

        if ($conversation_command = $conversation->getCommand()) {
            $conversation->cancel();
            $text = 'Вы завершили диалог!';
        }
        //$this->removeKeyboard($text);
        return $this->getTelegram()->executeCommand('start_basic');
    }

    /**
     * Remove the keyboard and output a text.
     * @param string $text
     * @return ServerResponse
     * @throws TelegramException
     */
    private function removeKeyboard(string $text): ServerResponse
    {
        return $this->replyToChat($text, [
            'reply_markup' => Keyboard::remove(['selective' => true]),
        ]);
    }

}
