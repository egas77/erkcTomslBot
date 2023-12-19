<?php

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use Services\Erkc\Api;
use Services\Erkc\ErkcKeyboards;

class SignupCommand extends UserCommand
{
    protected $name = 'signup';                      // Your command's name
    protected $description = 'A command for test'; // Your command description
    protected $usage = '/signup';                    // Usage of your command
    protected $version = '1.0.0';                  // Version of your command
    /**
     * @var bool
     */
    protected $need_mysql = true;
    /**
     * @var bool
     */
    protected $private_only = true;
    /**
     * Conversation Object
     * @var Conversation
     */
    protected $conversation;

    public function execute(): ServerResponse
    {
        $message = $this->getMessage();
        $chat = $message->getChat();
        $user = $message->getFrom();
        $text = trim($message->getText(true));
        $chat_id = $chat->getId();
        $user_id = $user->getId();
        if ($message->getText() === 'Отмена ❌') {
            return $this->getTelegram()->executeCommand('cancel');
        }
        $data = [
            'chat_id' => $chat_id,
            'reply_markup' => ErkcKeyboards::getCancelKb()->setResizeKeyboard(true)//Keyboard::remove(['selective' => true]),
        ];

        if ($chat->isGroupChat() || $chat->isSuperGroup()) {
            $data['reply_markup'] = Keyboard::forceReply(['selective' => true]);
        }

        $this->conversation = new Conversation($user_id, $chat_id, $this->getName());
        $attempts = &$this->conversation->attempts;
        if ($attempts >= 3) {
            Request::sendMessage([
                'text' => '‼ Вы слишком много сделали не верных попыток.' . "\n" . 'Свяжитесь с нами, тут наши <a href="https://vc.tom.ru/about/contacts/">контакты</a>',
                'chat_id' => $chat_id,
                'parse_mode' => 'html'
            ]);
            $this->conversation->stop();
            return $this->telegram->executeCommand('start_basic');
        }
        $notes = &$this->conversation->notes;
        !is_array($notes) && $notes = [];

        $receipt_details = &$this->conversation->receipt_details;
        !is_array($receipt_details) && $receipt_details = [];


        $kb = &$this->conversation->keyboards;
        !is_array($kb) && $kb = [];

        $state = $notes['state'] ?? 0;
        $result = Request::emptyResponse();

        $erkcApi = new Api();

        switch ($state) {
            case 0:
                $message_type = $message->getType();
                if ($message_type !== 'photo' && $text === 'Добавить квитанцию 📥') {
                    $notes['state'] = 0;
                    $this->conversation->update();
                    $data['text'] = 'Введите штрих-код квитанции либо прикрепите фото со штрих-кодом или qr-кодом квитанции:';

                    $result = Request::sendMessage($data);
                    break;
                }
                $barcode = null;

                /* если пришло фото */

                if ($message_type === 'photo') {
                    $doc = $message->{'get' . ucfirst($message_type)}();
                    $download_path = $this->telegram->getDownloadPath();
                    if (!is_dir($download_path)) {
                        return $this->replyToChat('Не могу скачать ваш файл.');
                    }
                    ($message_type === 'photo') && $doc = end($doc);
                    $file_id = $doc->getFileId();
                    $file = Request::getFile(['file_id' => $file_id]);
                    if ($file->isOk() && Request::downloadFile($file->getResult())) {
                        $photo = $message->getPhoto()[0];
                        $notes['photo_id'] = $photo->getFileId();
                        $receipt_details = $erkcApi->checkBarcodeByImage($download_path . '/' . $file->getResult()->getFilePath());

                        if ((isset($receipt_details['status']) && !$receipt_details['status']) || (empty($receipt_details))) {
                            $this->conversation->updateAttempts();
                            $result = Request::sendMessage([
                                'chat_id' => $message->getChat()->getId(),
                                'text' => $receipt_details['text']]);
                            break;
                        } elseif ($receipt_details['status']) {
                            $notes['state'] = 4;
                            $notes['barcode'] = $receipt_details['barcode'];
                            $data['text'] = '<b>Вы указали штрихкод :</b>' . $receipt_details['barcode'];
                            $data['reply_markup'] = 'html';
                            $result = Request::sendMessage($data);

                            $this->conversation->update();
                            $this->getLS($chat_id, $receipt_details);
                            $this->getDebt($chat_id, $receipt_details, $erkcApi);

                            if (!Api::addBarcode($receipt_details, $user_id)) {
                                $result = Request::sendMessage(
                                    [
                                        'chat_id' => $message->getChat()->getId(),
                                        'text' => 'Не сработало'
                                    ]
                                );
                            }

                            $this->conversation->stop();
                            break;
                        }
                    }
                } elseif ($message_type === 'text') { /*если пришёл текст */
                    $text = trim($message->getText(true));
                    if (!is_numeric($text)) {
                        $result = Request::sendMessage(
                            [
                                'chat_id' => $message->getChat()->getId(),
                                'text' => 'Штрихкод должен состоять только из цифр, проверьте позжайлуста.'
                            ]
                        );
                        break;
                    } else {
                        $receipt_details = $erkcApi->checkBarcodeByText($text);
                        if (!$receipt_details['status']) {
                            $result = Request::sendMessage([
                                'chat_id' => $message->getChat()->getId(),
                                'text' => $receipt_details['text']]);
                            break;
                        } else {
                            $barcode = $text;

                        }
                    }
                }

                $notes['barcode'] = $barcode;
                $text = '';

            // No break!
            case 1:
                if ($text === '') {
                    $notes['state'] = 1;
                    $data['text'] = 'Введите название улицы, как указано в квитанции, но без ул., пер., пр-т. и т.д.:';
                    $this->conversation->update();
                    $result = Request::sendMessage($data);
                    break;
                }
                // Проверка улицы
                if (!$this->checkStreetMatch($receipt_details['street'], $text)) {

                    $notes['error'] = true;
//                    Request::sendMessage([
//                        'chat_id' => $message->getChat()->getId(),
//                        'text' => 'Улица указана не верно.']);
//                    break;
                }

                $notes['street'] = $text;
                $text = '';
            // No break!
            case 2:
                if ($text === '') {
                    $notes['state'] = 2;
                    $this->conversation->update();
                    $data['text'] = 'Введите номер дома:';
                    $result = Request::sendMessage($data);
                    break;
                }
                if ($receipt_details['house'] !== $text) {
                    $notes['error'] = true;
//                    Request::sendMessage([
//                        'chat_id' => $message->getChat()->getId(),
//                        'text' => 'Дом указан не верно.']);
//                    break;
                }

                $notes['house'] = $text;
                $text = '';

            // No break!
            case 3:
                if ($text === '' || !is_numeric($text)) {
                    $notes['state'] = 3;
                    $this->conversation->update();
                    $data['text'] = 'Введите номер квартиры:';
                    $result = Request::sendMessage($data);
                    break;
                }
                // проверка квартиры
                if ($receipt_details['flat'] !== $text) {
                    $notes['error'] = true;
//                    Request::sendMessage([
//                        'chat_id' => $message->getChat()->getId(),
//                        'text' => 'Квартира указана не верно.']);
//                    break;
                }

                $notes['kv'] = $text;
                $text = '';

            // No break!
            case 4:
                $this->conversation->update();
                if ($notes['error']) {
                    $attempts++;
                    $notes['state'] = 0;
                    $notes['error'] = false;
                    $this->conversation->update();
                    Request::sendMessage([
                        'chat_id' => $message->getChat()->getId(),
                        'text' => 'Вы указали не верные данные, попробуйте ещё раз.']);
                    return Request::sendMessage([
                        'chat_id' => $message->getChat()->getId(),
                        'text' => 'Введите штрих-код квитанции либо прикрепите фото со штрих-кодом или qr-кодом квитанции:'
                    ]);
                }
                $out_text = '<b>Вы указали следующие данные:</b>' . PHP_EOL;
                unset($notes['state']);
                foreach ($notes as $k => $v) {
                    switch ($k) {
                        case 'barcode':
                            $out_text .= PHP_EOL . '<b>Штрихкод:</b> ' . $v;
                            break;
                        case 'street':
                            $out_text .= PHP_EOL . '<b>Улица:</b> ' . $v;
                            break;
                        case 'house':
                            $out_text .= PHP_EOL . '<b>Дом:</b> ' . $v;
                            break;
                        case 'kv':
                            $out_text .= PHP_EOL . '<b>Квартира:</b> ' . $v;
                            break;
                    }
                }

                $data['text'] = $out_text;
                $data['barcode'] = $notes['barcode'];
                $data['street'] = $notes['street'];
                $data['house'] = $notes['house'];
                $data['kv'] = $notes['kv'];
                $data['parse_mode'] = 'html';
                $result = Request::sendMessage($data);
                $this->getLS($chat_id, $receipt_details);
                $this->getDebt($chat_id, $receipt_details, $erkcApi);
                //Api::update_user_registered($user_id, 1);
                Api::addBarcode($receipt_details, $user_id);
                $this->conversation->stop();
                break;
        }

        return $result;
    }


    private function sanitizeString($str)
    {
        // Заменить все знаки препинания на пробелы
        $str = preg_replace('/[.,:;!?()\-]/', ' ', $str);
        // Заменить двойные пробелы на одинарные
        $str = preg_replace('/\s+/', ' ', $str);
        return trim($str);
    }

    private function checkStreetMatch($dbString, $userInput)
    {
        if (!is_string($dbString) || !is_string($userInput)) {
            return false;
        }
        if ($userInput != 'С.Лазо') {
            $sanitizedDbString = explode(' ', $this->sanitizeString($dbString));
        } else {
            $sanitizedDbString = explode(' ', $dbString);
        }
        return in_array($userInput, $sanitizedDbString, true);
    }

    public function getLS($chat_id, $receipt_details): void
    {
        $data['chat_id'] = $chat_id;
        $data['text'] =
            'Найдена квитанция ' . $receipt_details['supplier_name'] . ' за <b>' . $receipt_details['service_name']
            . '</b> по адресу: <b>' . $receipt_details['address'] . '</b>';
        $data['parse_mode'] = 'html';
        Request::sendMessage($data);
    }

    public function getDebt($chat_id, $receipt_details, Api $erkcApi): ServerResponse
    {
        $data['chat_id'] = $chat_id;
        $data['parse_mode'] = 'html';
        //$date = date("d.m.Y");
        /* к оплате */
        $img_service = Api::getIconByOpcode(intval($receipt_details['service_code']));
        if ($img_service !== null) {
            $bin_img = base64_decode($img_service);
            $im = imageCreateFromString($bin_img);
            $path = $this->telegram->getUploadPath() . '/icon_' . $receipt_details['service_code'] . '.png';
            imagepng($im, $path, 0);
        }
        $to_pay = $receipt_details['amount'] >= 0
            ? $receipt_details['amount'] . ' ₽'
            : $receipt_details['amount'] . ' ₽ (переплата)';
        $data['text'] = '<b>' . $receipt_details['address'] . '</b>' . "\n"
            . '<b>' . $receipt_details['service_name'] . '</b>' . "\n"
            . 'Штрих-код: <b>' . $receipt_details['barcode'] . '</b>' . "\n"
            . 'К оплате: <b>' . $to_pay . '</b>';

        //$data['reply_markup'] = ErkcKeyboards::getBasicKb()->setResizeKeyboard(true)->setSelective(true);

        if ($img_service !== null) {
            $data['photo'] = $path;
            $data['caption'] = $data['text'];
            Request::sendPhoto($data);
        } else {
            Request::sendMessage($data);
        }
        return $this->telegram->executeCommand('start_basic');
    }
}
