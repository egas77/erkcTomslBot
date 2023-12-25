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

class ListreceiptCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'list_receipt';

    /**
     * @var string
     */
    protected $description = 'list_receipt command';

    /**
     * @var string
     */
    protected $usage = '/list_receipt';

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
        $data['chat_id'] = $chat_id;
        $data['parse_mode'] = 'html';
        $result = Request::emptyResponse();
        //if (Api::is_registered($user_id)) {
            $list_barcodes = Api::getUserBarcodesByUserId($user_id);
            if (!empty($list_barcodes)) {
                foreach ($list_barcodes as $barcode) {
                    $payload = json_decode($barcode['payload'], true);
                    $img_service = Api::getIconByOpcode(intval($payload['service_code']));
                    if ($img_service !== null) {
                        $bin_img = base64_decode($img_service);
                        $im = imageCreateFromString($bin_img);
                        $path = $this->telegram->getUploadPath() . '/icon_' . $payload['service_code'] . '.png';
                        imagepng($im, $path, 0);
                    }
                    $msg = 'Квитанция <b>' . $payload['supplier_name'] . '</b> за <b>' . $payload['service_name'] . '</b>' . "\n"
                        . 'По адресу:<b>' . $payload['address'] . '</b>.' . "\n"
                        . 'Штрих-код:<b>' . $payload['barcode'] . '</b>'
                        . "\n\n";
                    $data['text'] = $msg;
                    if ($img_service !== null) {
                        $data['photo'] = $path;
                        $data['caption'] = $msg;
                        $result = Request::sendPhoto($data);
                    } else {
                        $result = Request::sendMessage($data);
                    }
                }
                //квитанция за ЖКУ по адресу: Иркутский тракт д. 94 кв. 52

            } else {
                $data['text'] = 'у Вас нет добавленных квитанций.';
                $result = Request::sendMessage($data);
            }
//        } else {
//            $data['text'] = 'Вы не зарегистрированы!';
//            Request::sendMessage($data);
//            return $this->telegram->executeCommand('start');
//        }
        return $result;
    }
}
