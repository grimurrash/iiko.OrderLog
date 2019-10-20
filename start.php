<?php
require_once __DIR__ . '/vendor/autoload.php';

use OrderLog\OrderLog;

// Задаём информацию о организации
$orderLog = new OrderLog('SRmobile', 'PytEdmefkiwoyf9');

// Задаём БД
$orderLog->setDB('root', '', 'tbot');

// Задаём Телеграмм бота
$orderLog->setTelegram('817256891:AAFMD0DCbpA20qnusYd6vxzc2JNSeNFstsI', 'okinavaproblemsbot');

// Задаём Телеграмм каналы
$telegramChannelIds = [
    'general' => [
        'mode' => 'general',
        'id' => '-1001347851318'
    ],
    'cooks' => [
        'mode' => 'general',
        'id' => ''
    ],
];

$orderLog->setTelegramChannelIds($telegramChannelIds);

// Задаём Отклонения
$orderLog->setDelay([
    'cook' => 26 * 60,
    'courier' => 15 * 60,
]);
