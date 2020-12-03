<?php
require_once 'vendor/autoload.php';

use OrderLog\OrderLog;
use OrderLog\TelegramChannel;

//https://docs.google.com/document/d/1pRQNIn46GH1LVqzBUY5TdIIUuSCOl-A_xeCBbogd2bE/edit#heading=h.m1n1hoethjnt
//Документация по API

// Задаём информацию о организации
$orderLog = new OrderLog('', '');

// Задаём БД
$orderLog->setDB('', '', '');

// Задаём Телеграмм бота
$orderLog->setTelegram('', '');

// Задаём Телеграмм каналы
$orderLog->addTelegramChannelId('general', '-');
$orderLog->addTelegramChannelId('cooks', '-');

//Каналы рестаранов
//Окинава Бигичева 3 (f71f52cf-c3dc-11e7-80ca-d8d385655247)
$orderLog->addTelegramChannelId('', '-');
//Окинава Г17 (ddafede7-c53f-11e7-80ca-d8d385655247)
$orderLog->addTelegramChannelId('', '-');
//Окинава Декабристов 85 (fc949d53-c3db-11e7-80ca-d8d385655247)
$orderLog->addTelegramChannelId('', '-');
//Копылова 14 (842e1ede-c3db-11e7-80ca-d8d385655247)
$orderLog->addTelegramChannelId('', '-');
//Окинава У10 (64aed661-c3dc-11e7-80ca-d8d385655247)
$orderLog->addTelegramChannelId('', '-');
//Окинава Ф88: Фучика (подменный терминал) (c77c0104-c3d9-11e7-80ca-d8d385655247)
$orderLog->addTelegramChannelId('', '-');
//Окинава Ямашева 43 (ea0c2bd4-c3dc-11e7-80ca-d8d385655247)
$orderLog->addTelegramChannelId('', '-');
//Окинава Ямашева 82 (a6817b35-c3dc-11e7-80ca-d8d385655247)
$orderLog->addTelegramChannelId('', '-');
//$orderLog->addTelegramChannelId('50322c02-141c-11e5-80d2-d8d38565926f', new TelegramChannel('-1001232407452', 'general'));
//$orderLog->addTelegramChannelId('38e6c9c5-c3da-11e7-80ca-d8d385655247', new TelegramChannel('-1001464285134', 'general'));


// Задаем Телеграмм канал для дебага
$orderLog->setDebugTelegramChannel('-');

// Задаём Отклонения
$orderLog->setDelay(26 * 60, 15 * 60, 30 * 60, 5 * 60);

//Удалять сообщения после 
$orderLog->setDeleteMode(true);
