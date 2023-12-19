<?php

namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use Services\Erkc\Api;

class RemovereceiptCommand extends UserCommand
{

    protected $name = 'remove_receipt';
    protected $description = 'A command for test';
    protected $usage = '/remove_receipt';
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
            'reply_markup' => Keyboard::remove(['selective' => true])
        ];

        if ($message->getText() === 'Назад') {
            return $this->getTelegram()->executeCommand('cancel');
        }

        $conversation = new Conversation($user_id, $chat_id, $this->getName());

        $notes = &$conversation->notes;
        !is_array($notes) && $notes = [];
        $state = $notes['state'] ?? 0;
        $result = Request::emptyResponse();

        switch ($state) {
            case 0:
                $notes['state'] = 1;
                $payload = Api::getUserBarcodesByUserId($user_id);
                $notes['barcodes'] = $this->parseBarcodes($payload);
                $conversation->update();
                return $this->buildBarcodesKeyboard($notes['barcodes'], $data);

            case 1:
                $barcode = trim($message->getText());
                if (!in_array($barcode, $notes['barcodes'], true)) {
                    return Request::sendMessage(
                        ['chat_id' => $chat_id,
                            'text' => 'Неверный штрих-код. Попробуйте еще раз.']);
                }
                $notes['barcode'] = $barcode;
                $notes['state'] = 2;
                $conversation->update();
                return $this->getYesNoKeyboard($data);

            case 2:
                $confirmation = mb_strtolower(trim($message->getText()));

                $notes['answer'] = $confirmation;
                $conversation->update();

                if ($confirmation === 'да') {
                    if (Api::removeBarcode($user_id, $notes['barcode'])) {
                        Request::sendMessage(['chat_id' => $chat_id, 'text' => 'Квитанция удалена.']);
                        $result = $this->getTelegram()->executeCommand('start_basic');
                        $conversation->stop();
                    } else {
                        Request::sendMessage(['chat_id' => $chat_id, 'text' => 'При удалении произошла ошибка.']);
                    }
                } elseif ($confirmation === 'нет') {
                    $text = 'Удаление отменено.';
                    Request::sendMessage(['chat_id' => $chat_id, 'text' => $text]);
                    $conversation->stop();
                    $this->getTelegram()->executeCommand('cancel');
                } else {
                    $text = 'Выберите: Да или Нет !';
                    $result = Request::sendMessage(['chat_id' => $chat_id, 'text' => $text]);
                }

                return $result;

            default:
                $conversation->stop();
                return Request::sendMessage(['chat_id' => $chat_id, 'text' => 'Произошла ошибка.']);
        }
    }

    private function buildBarcodesKeyboard(array $barcodes, array $data): ServerResponse
    {
        $kb = new Keyboard([]);
        foreach ($barcodes as $barcode) {
            $kb->addRow($barcode);
        }
        $kb->addRow('Назад');
        $data['text'] = 'Выберите какую квитанцию удалить из меню или напишите штрих-код:';
        $data['reply_markup'] = $kb;
        return Request::sendMessage($data);
    }

    private function parseBarcodes(array $payloads): array
    {
        foreach ($payloads as $payload) {
            $barcodes[] = json_decode($payload['payload'], true)['barcode'];
        }
        return $barcodes;
    }

    private function getYesNoKeyboard(array $data): ServerResponse
    {
        $kb = new Keyboard([]);
        $kb->addRow('Да');
        $kb->addRow('Нет');
        $kb->addRow('Назад');
        $data['text'] = 'Вы уверены, что хотите удалить квитанцию?';
        $data['reply_markup'] = $kb;
        return Request::sendMessage($data);
    }
}