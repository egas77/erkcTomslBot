<?php

namespace Services\Erkc;

use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\KeyboardButton;

class ErkcKeyboards
{
    public static function getBasicKb(): Keyboard
    {
        return new Keyboard(
            ['Что может бот ❓'],
            ['Добавить квитанцию 📥'],
            ['Удалить квитанцию 🗑️'],
            //['Выбрать квитанцию'],
            ['Список квитанций 📋'],
            ['Оплатить квитанцию 💳'],
            ['Передать показания 🔍'],
            [(new KeyboardButton('Привязать номер телефона 📱'))->setRequestContact(true)]
        );
    }

    public static function keyboardByRegisteredUser(): Keyboard
    {
        return new Keyboard(
            ['Что может бот ❓'],
            ['Добавить квитанцию 📥'],
            ['Удалить квитанцию 🗑️'],
            ['Список квитанций 📋'],
            ['Оплатить квитанцию 💳'],
            ['Сформировать квитанцию 🖨️'],
            ['Передать показания 🔍'],
            ['История платежей 📚'],
            ['История показаний 📈'],
//            ['Создать обращение ✉️'],
//            ['Статусы обращений 🔄'],
//            ['История обращений 📖'],
            ['Отправить замечания или предложение по работе бота. ✍']
        );
    }

    public static function getBackKb(): Keyboard
    {
        return new Keyboard(
            ['Назад']
        );
    }

    public static function getCancelKb(): Keyboard
    {
        return new Keyboard(
            ['Отмена ❌']
        );
    }
}