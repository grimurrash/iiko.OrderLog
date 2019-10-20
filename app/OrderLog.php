<?php

namespace OrderLog;

use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use SafeMySQL;

/**
 * Class OrderLog
 * @package OrderLog
 */
class OrderLog
{
    use OrderLogTemplate;

    /** @var SafeMySQL */
    private $db;

    /** @var string Токен iiko */
    private $token;

    /** @var int Id организаии iiko */
    private $orgId;

    /** @var string Пользователь iiko */
    private $user;

    /** @var string Пароль iiko */
    private $password;

    /** @var int Разница во времени с МСК */
    private $timeDifference = 0;

    /** @var Telegram */
    private $telegram;

    /** @var int Главный канал */
    private $telegramChannelIds;

    /** @var int Время начала скрипта */
    private $startTime;

    /** @var int Сегоднешний день */
    private $currentDate;

    /** @var array Список незавершенных заказов в бд */
    private $orderList;

    private $update;

    /**
     * OrderLog constructor.
     *
     * @param string $user Пользователь iiko
     * @param string $password Пароль iiko
     */
    public function __construct($user, $password)
    {
        $this->user = $user;
        $this->password = $password;
        $this->token = $this->getContents("https://iiko.biz:9900/api/0/auth/access_token?user_id=$user&user_secret=$password");
        $this->orgId = $this->getContents("https://iiko.biz:9900/api/0/organization/list?access_token={$this->token}")[0]->id;
    }

    public function setDB($user, $password, $db)
    {
        $this->db = new SafeMySQL([
            'user' => $user,
            'pass' => $password,
            'db' => $db
        ]);
    }

    public function setDelay($delay)
    {
        $this->cookDelay = isset($delay['cook']) ? $delay['cook'] : 0;
        $this->courierDelay = isset($delay['courier']) ? $delay['courier'] : 0;
    }

    /**
     * Получение контента
     *
     * @param string $path Путь контента
     * @return mixed
     */
    protected function getContents($path)
    {
        return json_decode(file_get_contents($path));
    }

    /**
     * Задать телеграмм бота
     *
     * @param string $botToken Бот Токен
     * @param string $username Имя бота
     * @throws TelegramException
     */
    public function setTelegram($botToken, $username)
    {
        $this->telegram = new Telegram($botToken, $username);
    }

    public function getTelegram()
    {
        return $this->telegram;
    }

    /**
     * Задать Id главного телеграм канала
     *
     * @param array $Ids Id телеграмм каналы
     */
    public function setTelegramChannelIds($Ids)
    {
        $this->telegramChannelIds = $Ids;
    }

    /**
     * Задать разницу во времени с МСК
     *
     * @param int $time Разница с МСК в секундах
     */
    public function setTimeDifference($time = 0)
    {
        $this->timeDifference = $time;
    }

    /**
     * Логгирование
     *
     * @param string $message Текст сообщения
     */
    protected function log($message)
    {
        echo "$message\n";
    }

    /**
     * Получить все незавершенные заказы из бд
     */
    protected function getAllOrders()
    {
        $this->orderList = $this->db->getAll("SELECT * FROM `ordersLog` WHERE `status` NOT IN ('Отменена','Закрыта')");
        $this->log('Получениме из DB: ' . time());
        $this->log('Длительность: ' . (time() - $this->startTime));
        $this->log('Count db: ' . count($this->orderList));
    }

    /**
     * Главный скрипт
     *
     * @throws TelegramException
     */
    public function runScript()
    {
        $this->currentDate = date('Y-m-d', time());

        $this->startTime = time();
        $this->log('Начало: ' . $this->startTime);

        //Получение заказов из бд
        $this->getAllOrders();

        //Новое, Готовится, Готово
        $this->runNewCoolReadyStatus();
        $this->log('Время на новые заказы: ' . time());
        $this->log('Длительность: ' . (time() - $this->startTime));

        //В пути
        $this->runOnWayStatus();
        $this->log('Время на заказы со статусом "В пути": ' . time());
        $this->log('Длительность: ' . (time() - $this->startTime));

        //Завершение заказа
        $this->runCompletedStatus();
        $this->log('Время завершения: ' . time());
        $this->log('Длительность: ' . (time() - $this->startTime));

        //Отмененные заказы
        $this->runCancelStatus();
        $this->log('Конец: ' . time());
        $this->log('Длительность: ' . (time() - $this->startTime));
    }

    /**
     * Проверка новых, готовящих и готовых заказов
     */
    private function runNewCoolReadyStatus()
    {
        $db = $this->db;
        $token = $this->token;
        $orgId = $this->orgId;
        $date = $this->currentDate;
        $orderList = $this->orderList;

        $orders = $this->getContents("https://iiko.biz:9900/api/0/orders/deliveryOrders?access_token={$token}&organization={$orgId}&dateFrom={$date}&dateTo={$date}&deliveryStatus[]=new");
        $orders = $orders->deliveryOrders;

        $cookCount = 0;
        foreach ($orders as $order) {
            if ($order->status == 'Готовится') {
                $cookCount++;
            }
        }
        $this->log('Кол-во новых заказов: ' . count($orders));
        foreach ($orders as $order) {
            $key = array_search($order->orderId, array_column($orderList, 'orderId'));
            $time = time() + $this->timeDifference;
            if ($key !== false) {
                $dbOrder = $orderList[$key];
                if ($order->status === 'Готовится' && $order->status != $dbOrder['status']) {
                    $sql = "UPDATE `ordersLog` 
                                SET `cook_count`=?s, 
                                    `status` = ?s, 
                                    `cook_start_time` = ?s 
                                WHERE `orderId` = ?s";
                    $db->query($sql, $cookCount, $order->status, $time, $order->orderId);
                } else if ($order->status === 'Готово' && $order->status != $dbOrder['status']) {
                    $sql = "UPDATE `ordersLog` 
                                SET `status` = ?s, 
                                    `ready_time` = ?s 
                                WHERE `orderId` = ?s";
                    $db->query($sql, $order->status, $time, $order->orderId);
                }
            } else {
                $createTime = strtotime($order->createdTime);
                $confirmTime = strtotime($order->confirmTime);
                $deliveryDate = strtotime($order->deliveryDate);
                $deliveryTerminalId = $order->deliveryTerminal->deliveryTerminalId;
                $deliveryTerminal = $order->deliveryTerminal->restaurantName;
                if ($order->status == 'Новая') {
                    $cookTime = null;
                    $readyTime = null;
                } else if ($order->status == 'Готовится') {
                    $cookTime = $confirmTime;
                    $readyTime = null;
                } else {
                    $cookTime = $confirmTime;
                    $readyTime = $time;
                }
                $sql = "INSERT INTO `ordersLog` 
                            (`orderId`, `number`, `status`, `create_time`, `confirm_time`, `cook_start_time`, 
                                `ready_time`, `delivery_date`, `delivery_terminal`, `cook_count`) 
                            VALUES (?s, ?s, ?s, ?s, ?s, ?s, ?s, ?s, ?s, ?s)";
                $db->query($sql, $order->orderId, $order->number, $order->status, $createTime, $confirmTime, $cookTime,
                    $readyTime, $deliveryDate, $deliveryTerminal, $cookCount);
            }
        }
    }

    /**
     * Проверка заказов находящихся в пути
     */
    private function runOnWayStatus()
    {
        $db = $this->db;
        $token = $this->token;
        $orgId = $this->orgId;
        $date = $this->currentDate;
        $orderList = $this->orderList;

        $orders = $this->getContents("https://iiko.biz:9900/api/0/orders/deliveryOrders?access_token={$token}&organization={$orgId}&dateFrom={$date}&dateTo={$date}&deliveryStatus[]=ON_WAY");
        $orders = $orders->deliveryOrders;
        $onWayCount = count($orders);
        $this->log('Кол-во заказов со статусом В пути: ' . $onWayCount);
        foreach ($orders as $order) {
            $key = array_search($order->orderId, array_column($orderList, 'orderId'));
            if ($key !== false) {
                $dbOrder = $orderList[$key];
                if ($order->status == 'В пути' && $dbOrder['status'] != $order->status) {
                    $sendTime = strtotime($order->sendTime);
                    $sql = "UPDATE `ordersLog` 
                                SET `status` = ?s, 
                                    `send_start_time` = ?s 
                                WHERE `orderId` = ?s";
                    $db->query($sql, $order->status, $sendTime, $order->orderId);
                }
            } else {
                $createTime = strtotime($order->createdTime);
                $confirmTime = strtotime($order->confirmTime);
                $sendTime = strtotime($order->sendTime);
                $deliveryDate = strtotime($order->deliveryDate);
                $deliveryTerminal = $order->deliveryTerminal->restaurantName;
                $sql = "INSERT INTO `ordersLog` 
                            (`number`, `orderId`, `status`, `create_time`, `confirm_time`,
                                `send_start_time`, `delivery_date`, `delivery_terminal`) 
                        VALUES (?s, ?s, ?s, ?s, ?s, ?s, ?s, ?s)";
                $db->query($sql, $order->number, $order->orderId, $order->status, $createTime, $confirmTime,
                    $sendTime, $deliveryDate, $deliveryTerminal);
            }
        }
    }

    /**
     * Проверка завершенных заказов
     */
    private function runCompletedStatus()
    {
        $db = $this->db;
        $token = $this->token;
        $orgId = $this->orgId;
        $date = $this->currentDate;
        $orderList = $this->orderList;
        $orders = $this->getContents("https://iiko.biz:9900/api/0/orders/deliveryOrders?access_token={$token}&organization={$orgId}&dateFrom={$date}&dateTo={$date}&deliveryStatus[]=closed");
        $orders = $orders->deliveryOrders;
        $this->log('Кол-во завершенных заказов: ' . count($orders));
        $this->log("https://iiko.biz:9900/api/0/orders/deliveryOrders?access_token={$token}&organization={$orgId}&dateFrom={$date}&dateTo={$date}&deliveryStatus[]=closed");
        $this->log('Длительность: ' . (time() - $this->startTime));
        exit(0);
        foreach ($orders as $order) {
            $key = array_search($order->orderId, array_column($orderList, 'orderId'));
            if ($key !== false) {
                $closeTime = strtotime($order->actualTime);
                $dbOrder = $orderList[$key];

                if (!is_null($dbOrder['cook_start_time']) && $dbOrder['cook_start_time'] != '') {
                    $cookTime = $dbOrder['cook_start_time'];
                } else {
                    $cookTime = $dbOrder['confirm_time'];
                }
                if (!is_null($dbOrder['ready_time']) && $dbOrder['ready_time'] != '') {
                    $readyTime = $dbOrder['ready_time'];
                    if (!is_null($dbOrder['send_start_time']) && $dbOrder['send_start_time'] != '') {
                        $sendTime = $dbOrder['send_start_time'];
                    } else {
                        $sendTime = $readyTime;
                    }
                } else {
                    if (!is_null($dbOrder['send_start_time']) && $dbOrder['send_start_time'] != '') {
                        $readyTime = $dbOrder['send_start_time'];
                        $sendTime = $dbOrder['send_start_time'];
                    } else {
                        $sendTime = $closeTime;
                        $readyTime = $closeTime;
                    }
                }
                if ($sendTime < $readyTime) {
                    $readyTime = $sendTime;
                }
                $address = $order->address->city . " " . $order->address->street . " " . $order->address->home . " " .
                    $order->address->apartment;
                $customer = "\n<b>Имя: </b>" . $order->customer->name .
                    "\n<b>Телефон: </b>" . $order->customer->phone .
                    (!is_null($order->customer->email) ? ("\n<b>Email: </b>" . $order->customer->email) : "");

                $operator = $order->operator->displayName;
                $items = "";
                $amount = 0;

                if (is_null($dbOrder['cook_count'])) {
                    $cookCount = 0;
                } else {
                    $cookCount = $dbOrder['cook_count'];
                }
                foreach ($order->items as $item) {
                    $items = $items . "\n<b>" . $item->name . "</b>: " . $item->sum / $item->amount . " ₽ * " .
                        $item->amount . 'шт = ' . $item->sum . " ₽.";
                    $amount += $item->amount;
                }

                $courier = "";
                if (!is_null($order->courierInfo->courierId)) {
                    $couriers = $this->getContents("https://iiko.biz:9900/api/0/rmsSettings/getCouriers?access_token={$token}&organization={$orgId}");
                    $couriers = $couriers->users;
                    foreach ($couriers as $cour) {
                        if ($cour->id == $order->courierInfo->courierId) {
                            $courier = $cour->displayName;
                        }
                    }
                }
                $number = $dbOrder['number'] != null ? $dbOrder['number'] : null;

                $number = $number != null ? $number : $order->number;

                $delay = ($closeTime - strtotime($order->deliveryDate)) > 0 ? ($closeTime - strtotime($order->deliveryDate)) : 0;

                $sql = "UPDATE `ordersLog`
                            SET `cook_count`=?s, 
                                `courier` = ?s, 
                                `address`=?s, 
                                `customer`=?s,  
                                `operator`=?s, 
                                `items`=?s, 
                                `amount`=?i, 
                                `number`=?s, 
                                `status` = ?s, 
                                `close_time` = ?s,
                                `sum`=?s, 
                                `delay`=?s,
                                `send_start_time` = ?s,
                                `ready_time` = ?s,
                                `cook_start_time`=?s,
                                `number` =?s
                            WHERE `orderId` = ?s";
                $db->query($sql, $cookCount, $courier, $address, $customer, $operator, $items,
                    $amount, $order->number, $order->status, $closeTime, $order->sum, $delay,
                    $sendTime, $readyTime, $cookTime, $number, $order->orderId);

                if ($readyTime - $cookTime > $this->cookDelay && isset($this->telegramChannelIds['cooks'])) {
                    $this->notificationAbbreviated($order->orderId, $this->telegramChannelIds['cooks']);
                }

                if (($delay > 0) && ($order->orderType->orderServiceType == 'DELIVERY_BY_COURIER')) {
                    $this->notificationAbbreviated($order->orderId, $this->telegramChannelIds['general']);

                    $deliveryTerminalId = $order->deliveryTerminal->deliveryTerminalId;
                    if (isset($this->telegramChannelIds[$deliveryTerminalId])) {
                        $this->notificationAbbreviated($order->orderId, $this->telegramChannelIds[$deliveryTerminalId]);
                    }
                }
            }
        }
    }

    /**
     * Провека отменнных заказов
     *
     * @throws TelegramException
     */
    private function runCancelStatus()
    {
        $db = $this->db;
        $token = $this->token;
        $orgId = $this->orgId;
        $date = $this->currentDate;
        $orderList = $this->orderList;

        $orders = $this->getContents("https://iiko.biz:9900/api/0/orders/deliveryOrders?access_token={$token}&organization={$orgId}&dateFrom={$date}&dateTo={$date}&deliveryStatus[]=CANCELLED");
        $orders = $orders->deliveryOrders;
        $this->log('Кол-во отменных заказов: ' . count($orders));
        foreach ($orders as $order) {
            $key = array_search($order->orderId, array_column($orderList, 'orderId'));
            if ($key !== false) {
                $cancelTime = strtotime($order->cancelTime);
                $cancelName = $order->deliveryCancelCause->name;

                $sql = "UPDATE `ordersLog` 
                            SET `status` = ?s,
                                `close_time` = ?s,
                                `cancel_time` = ?s,
                                `delivery_cancel_cause` = ?s 
                            WHERE `orderId` = ?s";
                $db->query($sql, $order->status, $cancelTime, $cancelTime, $cancelName, $order->orderId);
                $this->notificationCancel($order->orderId, $this->telegramChannelIds['general']);

                $deliveryTerminalId = $order->deliveryTerminal->deliveryTerminalId;
                if (isset($this->telegramChannelIds[$deliveryTerminalId])) {
                    $this->notificationAbbreviated($order->orderId, $this->telegramChannelIds[$deliveryTerminalId]);
                }
            }
        }
    }


    /**
     * Сокращенные вид уведомления об завершении заказа
     *
     * @param string $orderId Номер заказа
     * @param $telegramChannel
     * @param int|null $messageId Id сообщения для редактирования
     */
    private function notificationAbbreviated($orderId, $telegramChannel ,$messageId = null)
    {
        $this->log('Сокращенное уведомление по заказу ' . $orderId);
        $res = $this->db->getRow("SELECT * FROM `ordersLog` WHERE `orderId` = ?s", $orderId);

        $telegramText = $this->getOneAbbreviatedTemplate($res);

        if (is_null($messageId)) {
            $messageType = 'sendMessage';
        } else {
            $messageType = 'editMessageText';
        }

        Request::$messageType([
            'chat_id' => $telegramChannel['id'],
            'message_id' => $messageId,
            'text' => $telegramText,
            'parse_mode' => 'html',
            'reply_markup' => [
                'one_time_keyboard' => true,
                'resize_keyboard' => true,
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Подробно 🔖,',
                            'callback_data' => "detail_{$orderId}_{$telegramChannel['mode']}"
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * Полные вид уведомления об завершении заказа
     *
     * @param string $orderId Номер заказа
     * @param $telegramChannel
     * @param int|null $messageId Id сообщения для редактирования
     */
    private function notificationInDetail($orderId, $telegramChannel, $messageId)
    {
        $this->log('Полное уведомление по заказу ' . $orderId);
        $res = $this->db->getRow("SELECT * FROM `ordersLog` WHERE `orderId` = ?s", $orderId);

        $telegramText = $this->getOneDetailTemplate($res);

        Request::editMessageText([
            'chat_id' => $telegramChannel['id'],
            'message_id' => $messageId,
            'text' => $telegramText,
            'parse_mode' => 'html',
            'reply_markup' => [
                'one_time_keyboard' => true,
                'resize_keyboard' => true,
                'inline_keyboard' => [
                    [
                        [
                            'text' => 'Скрыть 🔖',
                            'callback_data' => 'abbreviated_' . $orderId
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * Уведомление при отмене заказа
     *
     * @param $orderId
     * @param $telegramChannel
     * @throws TelegramException
     */
    private function notificationCancel($orderId, $telegramChannel)
    {
        $this->log('Уведомление о отменнном заказе ' . $orderId);
        $res = $this->db->getRow("SELECT * FROM `ordersLog` WHERE `orderId` = ?s", $orderId);

        $telegramText = $this->getCancelTemplate($res);

        Request::sendMessage([
            'chat_id' => $telegramChannel['id'],
            'text' => $telegramText,
            'parse_mode' => 'html'
        ]);
    }

    /**
     * @throws TelegramException
     */
    public function webHook()
    {
        $this->telegram->setWebhook('https://logistic.smart-resto.ru/api/v1/hook.php');

        $input = Request::getInput();
        if (empty($input)) {
            throw new TelegramException('Input is empty!');
        }

        $post = json_decode($input, true);
        if (empty($post)) {
            throw new TelegramException('Invalid JSON!');
        }

        $this->processUpdate(new Update($post, $this->telegram->getBotUsername()));
    }

    /**
     * @param Update $update
     * @throws TelegramException
     */
    public function processUpdate(Update $update)
    {
        $this->update = $update;

        Request::sendMessage([
            'chat_id' => $this->telegramChannelIds['general'],
            'text' => var_export($update),
            'parse_mode' => 'html'
        ]);
    }
}
