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

use BaksDev\Megamarket\Api\Megamarket;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use stdClass;

final class MegamarketOrdersGetNewsRequest extends Megamarket
{
    private ?DateTimeImmutable $fromDate = null;

    /**
     * Возвращает информацию о заказах за сутки
     *
     * https://partner-wiki.megamarket.ru/merchant-api/2-opisanie-api-fbs/order-search-standart
     *
     */
    public function findAll(?DateInterval $interval = null): false|array
    {
        if(!$this->fromDate)
        {
            // заказы за последние 5 минут (планировщик на каждую минуту)
            $dateTime = new DateTimeImmutable();
            $this->fromDate = $dateTime->sub($interval ?? DateInterval::createFromDateString('1 day'));
        }

        $response = $this->TokenHttpClient()
            ->request(
                'GET',
                '/api/market/v1/orderService/order/search',
                ['json' =>
                    [
                        'meta' => new stdClass(),
                        'data' => [
                            "token" => $this->getToken(),
                            "dateFrom" => $this->fromDate->format(DateTimeInterface::W3C),
                            "dateTo" => $dateTime->format(DateTimeInterface::W3C),
                            "statuses" => ["NEW"]
                        ]
                    ]
                ],
            );

        $content = $response->toArray(false);

        if($response->getStatusCode() !== 200 || $content['success'] !== 1)
        {
            return false;
        }

        return empty($content['data']['shipments']) ? false : $content['data']['shipments'];

    }
}
