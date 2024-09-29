<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

declare(strict_types=1);

namespace BaksDev\Megamarket\Orders\Api;

use BaksDev\Delivery\Type\Field\DeliveryFieldUid;
use BaksDev\Megamarket\Api\Megamarket;
use BaksDev\Megamarket\Orders\UseCase\New\MegamarketOrderDTO;
use BaksDev\Yandex\Market\Api\YandexMarket;
use BaksDev\Yandex\Market\Orders\UseCase\New\YandexMarketOrderDTO;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DomainException;
use InvalidArgumentException;
use stdClass;

final class MegamarketOrdersCloseRequest extends Megamarket
{
    private int $retry = 0;

    private array|false $items = false;

    /**
     * Данные о лотах
     *
     * DBS: @see https://partner-wiki.megamarket.ru/merchant-api/4-opisanie-api-dbs/4-1-dbs-s-tsentral-nogo-sklada/4-1-1-opisanie-metodov/4-3-4-order-packing
     * {
     * "itemIndex": 1,
     * "quantity": 1
     * }
     *
     * FBS: @see https://partner-wiki.megamarket.ru/merchant-api/2-opisanie-api-fbs/order-packing-standart
     *
     * {
     * "itemIndex": 1,
     * "boxes": [{
     * "boxIndex": 1,
     * "boxCode": "797*145056*1"
     * }
     * ],
     * "digitalMark": "Это_код_маркировки"
     * }
     *
     */
    public function items(array $items): self
    {
        if(empty($items))
        {
            throw new InvalidArgumentException('Invalid Argument items');
        }

        /** Проверяем, что передан ключ handoverResult и он равен TRUE */
        foreach($items as $item)
        {
            if(array_key_exists('handoverResult', $item) || $item['handoverResult'] !== true)
            {
                throw new InvalidArgumentException('Invalid Argument handoverResult');
            }
        }

        $this->items = $items;

        return $this;
    }

    /**
     * Сообщает о выдаче товара
     *
     * https://partner-wiki.megamarket.ru/merchant-api/4-opisanie-api-dbs/4-1-dbs-s-tsentral-nogo-sklada/4-1-1-opisanie-metodov/4-3-5-order-close
     *
     */
    public function close(int|string $order): bool
    {
        /**
         * Выполнять операции запроса ТОЛЬКО в PROD окружении
         */
        if($this->isExecuteEnvironment() === false)
        {
            return true;
        }

        /** Если передан системны идентификатор заказа */
        $order = (string) $order;
        $order = str_replace('M-', '', $order);

        if($this->items === false)
        {
            throw new InvalidArgumentException('Invalid Argument items');
        }

        /**
         * Дата выдачи заказа === дата выполнения запроса
         */
        $closeDate = new DateTimeImmutable();
        $closeDate = $closeDate->format(DateTimeInterface::ATOM);

        $response = $this->TokenHttpClient()
            ->request(
                'GET',
                '/api/market/v1/orderService/order/close',
                ['json' =>
                    [
                        'meta' => new stdClass(),
                        'data' => [
                            "token" => $this->getToken(),
                            "shipments" => [[
                                'shipmentId' => $order,
                                'closeDate' => $closeDate,
                                'items' => $this->items
                            ]]
                        ]
                    ]
                ],
            );


        $content = $response->toArray(false);

        /** Статус всегда возвращает 200, делаем ретрай сами */
        if(isset($content['error']))
        {
            // Если истрачено 5 попыток с задержкой в прогрессии
            if($this->retry > 32)
            {
                $content['error'][0] = self::class.':'.__LINE__;

                $this->logger->critical(sprintf('Megamarket: Ошибка при подтверждении в обработку нового заказа %s', $order), $content['error']);

                return false;
            }

            sleep($this->retry);

            $this->retry *= 2;
            $this->close($order);
        }

        return true;
    }
}
