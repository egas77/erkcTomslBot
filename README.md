# erkc_bot

## 0. Клонирование этого репозитория

Для начала вы можете клонировать этот репозиторий с помощью git:

```bash
$ git clone https://github.com/barkovskii/erkc_bot.git
```
Прежде всего, вам необходимо переименовать config.example.php в config.php, а затем заменить все необходимые значения значениями вашего проекта.

Описание файлов:

- `composer.json` (Описывает ваш проект и его зависимости)
- `set.php` (Используется для установки вебхука)
- `unset.php` (Используется для отключения веб-перехватчика)
- `hook.php` (Используется для метода веб-перехватчик)
- `getUpdatesCLI.php` (Используется для метода getUpdates)
- `cron.php` (Используется для выполнения команд через cron)