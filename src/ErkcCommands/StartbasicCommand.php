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

use JakubOnderka\PhpParallelLint\Exception;
use Longman\TelegramBot\ChatAction;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\BotCommand;
use Longman\TelegramBot\Entities\BotCommandScope\BotCommandScopeAllPrivateChats;
use Longman\TelegramBot\Entities\BotCommandScope\BotCommandScopeDefault;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\KeyboardButton;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Entities\WebAppInfo;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\TelegramLog;
use PHP_CodeSniffer\Config;
use Services\Erkc\Api;
use Services\Erkc\ErkcKeyboards;

class StartbasicCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'start_basic';

    /**
     * @var string
     */
    protected $description = 'Start_basic command';

    /**
     * @var string
     */
    protected $usage = '/start_basic';

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

        // If you use deep-linking, get the parameter like this:
        // $deep_linking_parameter = $this->getMessage()->getText(true);
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
        $result = Request::emptyResponse();
        $greetingMessage = 'Выберите желаемое действие:';
        if (Api::is_registered($user_id)) {
            $keyboard = ErkcKeyboards::keyboardByRegisteredUser()->setResizeKeyboard(true)->setSelective(false);
        } else {
            $keyboard = ErkcKeyboards::getBasicKb()->setResizeKeyboard(true)->setSelective(false);
        }

        $result = Request::sendMessage([
            'chat_id' => $chat_id,
            'text' => $greetingMessage,
            'parse_mode' => 'html',
            'reply_markup' => $keyboard
        ]);

        return $result;

//        $message = $this->getMessage();
//        $chat_id = $message->getChat()->getId();


//        $data = [
//            'chat_id'      => $chat_id,
//            'text'=>'!',
//            // Remove any keyboard by default
//            'reply_markup' => Keyboard::remove(['selective' => true]),
//        ];
//        Request::sendMessage($data);
//        $keyboardWebApp = new Keyboard([
//            new InlineKeyboardButton([
//                'text' => "УМП ЕРКЦ",
//                'web_app' => new WebAppInfo([
//                    'url' => 'https://a8a3-217-18-136-10.ngrok-free.app/webapps/Erkc_v01/index.html'
//                ]),
//            ]),
//        ]);
//        $keyboardWebApp->setResizeKeyboard(TRUE);
//        Request::sendChatAction([
//            'chat_id' => $chat_id,
//            'action'  => ChatAction::TYPING,
//        ]);
//        $kbPersonalArea = new InlineKeyboard([
//            new InlineKeyboardButton([
//                'text' => "Справка по использованию Личного кабинета 👉️",
//                'url' => "http://vc.tom.ru/uinfo/lkspravka"
//            ]),
//        ]);


//        return $this->replyToUser(
//            'Воспользоваться услугами данного сервиса по оплате ЖКУ и передачей показаний счетчиков можно без регистрации.' . "\n" .
//            'Для этого Вам необходимо добавить ниже информацию о своих квитанциях на оплату услуг.' . "\n" .
//            '<b>Для этого Вам необходимо указать:</b>' . "\n" .
//            '  ✔ Штрихкод квитанции ' . "\n" .
//            '  ✔ Выбрать улицу' . "\n" .
//            '  ✔ Номер дома' . "\n" .
//            '  ✔ Номер квартиры' . "\n" .
//            '‼ Важно, написание адреса, должно быть точно как в квитанции, но без сокращений типа: ул., пер., пр-т и т.д. ‼' . "\n\n" .
//            'Либо просто прикрепить фотографию со штрих-кодом или qr-кодом квитанции (пример) ссылка на фотографии штрих-кодов и qr-кодов квитанций.' . "\n\n" .
//            'Для начала регистрации введите команду: /signup ',
//            [
//                'reply_markup' => Keyboard::remove(['selective' => true]),
//                'parse_mode' => 'html'
//            ]
//        );
    }
}
