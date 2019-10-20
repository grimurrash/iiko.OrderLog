<?php

namespace OrderLog;

trait OrderLogTemplate
{

    protected $cookDelay = 0;

    protected $courierDelay = 0;

    /**
     * Получение текстового представления разницы
     *
     * @param int $time Время разницы с ожидаемым временем в сукундах
     * @return string Текст описания разницы с ожидаемым временем
     */
    private function getDelayText($time)
    {
        if ($time < 600) {
            return 'Незначительная проблема';
        } else if ($time < 1800) {
            return 'Существенная проблема';
        } else {
            return 'Серьезная проблема';
        }
    }

    private function getCookDelayText($delay)
    {
        $cookDelayText = '';
        if ($delay > $this->cookDelay) {
            $cookDelayText = "\n<b>Отклонение приготовления:</b> " . date("H:i:s", mktime(0, 0, $delay - $this->cookDelay));
        }
        return $cookDelayText;
    }

    private function getCourierDelayText($delay)
    {
        $courierDelayText = '';
        if ($delay > $this->courierDelay) {
            $courierDelayText = "\n<b>Отклонение в ожидании курьера:</b> " . date("H:i:s", mktime(0, 0, $delay - $this->courierDelay));
        }
        return $courierDelayText;
    }

    protected function getOneAbbreviatedTemplate($res)
    {
        $delayStatus = $this->getDelayText($res['delay']);
        return "‼️<b>$delayStatus</b> ‼️" .
            "\n\n<b>Номер заказа:</b>" . $res['number'] .
            "\n<b>Ресторан:</b> " . $res['delivery_terminal'] .
            "\n\n📊 <b>Отчет</b>" .
            "\n<b>Готовили:</b> " . date("H:i:s", mktime(0, 0, ($res['ready_time'] - $res['cook_start_time']) > 0 ? ($res['ready_time'] - $res['cook_start_time']) : 0)) .
            $this->getCookDelayText($res['ready_time'] - $res['cook_start_time']) .
            "\n<b>Одновременно готовилось:</b> " . $res['cook_count'] .
            "\n<b>Ждали курьера:</b> " . date("H:i:s", mktime(0, 0, ($res['send_start_time'] - $res['ready_time']) > 0 ? ($res['send_start_time'] - $res['ready_time']) : 0)) .
            $this->getCourierDelayText($res['send_start_time'] - $res['ready_time']) .
            "\n<b>Длительность доставки:</b> " . date("H:i:s", mktime(0, 0, ($res['close_time'] - $res['send_start_time']) > 0 ? ($res['close_time'] - $res['send_start_time']) : 0)) .
            "\n<b>Отклонение:</b> " . date("H:i:s", mktime(0, 0, $res['delay'])) .
            "\n\n🧾 <b>Сумма заказа</b> " .
            "\n\n<b>Итого:</b> " . $res['amount'] . "шт., " . $res['sum'] . " ₽.";
    }

    protected function getOneDetailTemplate($res)
    {
        $delayStatus = $this->getDelayText($res['delay']);
        return "‼️<b>$delayStatus</b> ‼️" .
            "\n\n<b>Номер заказа:</b> " . $res['number'] .
            "\n<b>Ресторан:</b> " . $res['delivery_terminal'] .
            "\n\n<b>Адрес:</b> " . $res['address'] .
            "\n\n⏰ <b>Timeline</b>" .
            "\n<b>Дата:</b> " . date("d.m.Y", $res['create_time']) .
            "\n<b>Принят:</b> " . date("H:i", $res['create_time']) .
            "\n<b>Подтверждён:</b> " . date("H:i", $res['confirm_time']) .
            "\n<b>Отправили на кухню:</b> " . date("H:i", $res['cook_start_time']) .
            "\n<b>Приготовили:</b> " . date("H:i", $res['ready_time']) .
            "\n<b>Отправили курьером:</b> " . date("H:i", $res['send_start_time']) .
            "\n<b>Время завершения заказа:</b> " . date("H:i", $res['close_time']) .
            "\n<b>Статус заказа:</b> " . $res['status'] .
            "\n\n📊 <b>Отчет</b>" .
            "\n<b>Готовили:</b> " . date("H:i:s", mktime(0, 0, ($res['ready_time'] - $res['cook_start_time']) > 0 ? ($res['ready_time'] - $res['cook_start_time']) : 0)) .
            "\n<b>Одновременно готовилось:</b> " . $res['cook_count'] .
            "\n<b>Ждали курьера:</b> " . date("H:i:s", mktime(0, 0, ($res['send_start_time'] - $res['ready_time']) > 0 ? ($res['send_start_time'] - $res['ready_time']) : 0)) .
            "\n<b>Длительность доставки:</b> " . date("H:i:s", mktime(0, 0, ($res['close_time'] - $res['send_start_time']) > 0 ? ($res['close_time'] - $res['send_start_time']) : 0)) .
            "\n<b>Отклонение:</b> " . date("H:i:s", mktime(0, 0, $res['delay'])) .
            "\n\n🧸 <b>Клиент</b>" .
            $res['customer'] .
            "\n\n🗣 <b>Оператор</b>" .
            "\n<b>Имя:</b> " . $res['operator'] .
            "\n\n🛵 <b>Курьер</b>" .
            "\n<b>Имя:</b> " . $res['courier'] .
            "\n\n🧾 <b>Состав заказа</b> " .
            $res['items'] .
            "\n\n<b>Итого:</b> " . $res['amount'] . "шт., " . $res['sum'] . " ₽.";
    }

    protected function getCancelTemplate($res)
    {
        return '🚫🚫🚫 <b>Отмененная доставка</b> 🚫🚫🚫 ' .
        "\n🛒 <b>Номер заказа:</b> " . $res['number'] .
        "\n🏰 <b>Ресторан:</b> " . $res['delivery_terminal'] .
        "\n⚠ <b>Статус:</b> " . $res['status'] .
        "\n⏰ <b>Время отмены:</b> " . date("d.m.Y H:i", $res['cancel_time']) .
        "\n📝 <b>Причина отмены:</b> " . $res['delivery_cancel_cause'];
    }
}