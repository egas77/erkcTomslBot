<?php

/**
 * This file is part of the PHP Telegram Bot example-bot package.
 * https://github.com/php-telegram-bot/example-bot/
 * (c) PHP Telegram Bot Team
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

/**
 * Generic command
 * Gets executed for generic commands, when no other appropriate one is found.
 */
class GenericCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'generic';

    /**
     * @var string
     */
    protected $description = 'Handles generic commands or is executed by default when a command is not found';

    /**
     * @var string
     */
    protected $version = '1.1.0';

    /**
     * Main command execution
     * @return ServerResponse
     * @throws TelegramException
     */
    public function execute(): ServerResponse
    {
        $message = $this->getMessage();
        $user_id = $message->getFrom()->getId();
        $command = $message->getCommand();
        if ($command === 'signup') {
            return Request::emptyResponse();
        }

        if (stripos($command, 'whois') === 0 && $this->telegram->isAdmin($user_id)) {
            return $this->telegram->executeCommand('whois');
        }
        return $this->replyToChat("Извините я незнаю такую комманду: /{$command} 😞");
    }

    public function checkSignup($userId): bool
    {
        //TODO check user
        return true;
    }
}
