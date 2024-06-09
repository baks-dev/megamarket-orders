# BaksDev Orders Megamarket

[![Version](https://img.shields.io/badge/version-7.1.2-blue)](https://github.com/baks-dev/megamarket-orders/releases)
![php 8.3+](https://img.shields.io/badge/php-min%208.3-red.svg)

Модуль заказов Megamarket

## Установка

``` bash
$ composer require baks-dev/megamarket-orders
```

## Дополнительно

Установка конфигурации и файловых ресурсов:

``` bash
$ php bin/console baks:assets:install
```

Добавить тип профиля и доставку Megamarket

``` bash
$ php bin/console baks:users-profile-type:megamarket
$ php bin/console baks:payment:megamarket
$ php bin/console baks:delivery:megamarket
```

Изменения в схеме базы данных с помощью миграции

``` bash
$ php bin/console doctrine:migrations:diff

$ php bin/console doctrine:migrations:migrate
```

## Тестирование

``` bash
$ php bin/phpunit --group=megamarket-orders
```

## Лицензия ![License](https://img.shields.io/badge/MIT-green)

The MIT License (MIT). Обратитесь к [Файлу лицензии](LICENSE.md) за дополнительной информацией.

