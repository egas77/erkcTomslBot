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
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Services\Erkc\Api;
use Services\Erkc\ErkcKeyboards;

class StartCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'start';

    /**
     * @var string
     */
    protected $description = 'Start command';

    /**
     * @var string
     */
    protected $usage = '/start';

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
        $result = Request::emptyResponse();
        if (false) {
            $greetingMessage = 'Здравствуйте, ' . $getFirstName . '! 👋';

            $result = Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => $greetingMessage,
                'parse_mode' => 'html',
                'reply_markup' => ErkcKeyboards::getBasicKb()->setResizeKeyboard(true)->setSelective(true)
            ]);
        } else {
            $greetingMessage = 'Здравствуйте! 👋' . "\n" .
                'Вас приветствует чат-бот <b>УМП "ЕРКЦ г.Томска"</b>' . "\n\n" .
                '<b>С помощью нашего бота Вы сможете:</b>' . "\n" .
                '  ✔ Просматривать информацию по лицевым счетам' . "\n" .
                '  ✔ Получать электронную квитанцию' . "\n" .
                '  ✔ Передавать показания счетчиков' . "\n" .
                '  ✔ Оплачивать услуги' . "\n" .
                '  ✔ Просматривать историю платежей и показаний счетчиков' . "\n\n" .
                'Также вы можете воспользоваться личным кабинетом на нашем сайте'
                . ' (<a href="https://vc.tom.ru/uinfo/lkspravka/">https://vc.tom.ru/uinfo/lkspravka</a>).' . "\n\n" .
                'Перечисленные выше возможности актуальны для квитанций напечатанных УМП "ЕРКЦ г. Томска"'
                . '(<a href="https://vc.tom.ru/upload/stats/images/tg-bot1.jpg">как узнать?</a>),' . "\n" .
                'Рег. Фондом Кап. Ремонта Томской области (взносы на капремонт на общий счет),' . "\n" .
                'ООО "ТРЦ" (услуги водоснабжения и обращения с ТКО),' . "\n" .
                'ООО "УК "Жилище".';

            Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => $greetingMessage,
                'parse_mode' => 'html',
                'disable_web_page_preview' => true,
                'reply_markup' => Keyboard::remove(['selective' => true]),
            ]);

            $greetingMessage_2 = 'Полный набор услуг данного сервиса доступен при регистрации личного кабинета на нашем сайте.' . "\n\n"
                . 'Зарегистрировать личный кабинет на сайте 👉️ <a href="https://vc.tom.ru/users/?action=reg">ссылка</a>.';
            Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => $greetingMessage_2,
                'parse_mode' => 'html',
                'disable_web_page_preview' => true

            ]);

            $greetingMessage_3 = 'Воспользоваться услугами данного сервиса по оплате ЖКУ и передачей показаний счетчиков можно без регистрации.' . "\n" .
                'Для этого Вам необходимо добавить ниже информацию о своих квитанциях на оплату услуг.' . "\n" .
                '<b>Для этого Вам необходимо указать:</b>' . "\n" .
                '  ✔ Штрихкод квитанции ' . "\n" .
                '  ✔ Выбрать улицу' . "\n" .
                '  ✔ Номер дома' . "\n" .
                '  ✔ Номер квартиры' . "\n" .
                '‼ Важно, написание адреса, должно быть точно как в квитанции, но без сокращений типа: ул., пер., пр-т и т.д. ‼' . "\n\n" .
                'Либо просто прикрепить фотографию со штрих-кодом или qr-кодом квитанции (<a href="https://vc.tom.ru/upload/stats/images/tg-bot2.jpg">пример</a>) .' . "\n\n" .
                'Для начала добавьте квитанцию ';
            if (Api::is_registered($user_id)) {
                $keyboard = ErkcKeyboards::keyboardByRegisteredUser()
                    ->setResizeKeyboard(true)
                    ->setSelective(false);
            } else {
                $keyboard = ErkcKeyboards::getBasicKb()->setResizeKeyboard(true)->setSelective(false);
            }
            $result = Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => $greetingMessage_3,
                'reply_markup' => $keyboard,
                'parse_mode' => 'html',
                'disable_web_page_preview' => true
            ]);
        }
        return $result;
    }
}
