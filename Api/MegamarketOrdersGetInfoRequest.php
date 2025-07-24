<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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
use Exception;
use stdClass;

final class MegamarketOrdersGetInfoRequest extends Megamarket
{
    /**
     * Возвращает информацию о заказе
     *
     * https://partner-wiki.megamarket.ru/merchant-api/2-opisanie-api-fbs/2-1-rabota-s-api-vyzovami/order-get-standart
     * https://partner-wiki.megamarket.ru/merchant-api/2-opisanie-api-fbs/order-get-standart
     *
     */
    public function find(int|string $order): false|array
    {
        /** Если передан системны идентификатор заказа */
        $order = (string) $order;
        $order = str_replace('M-', '', $order);

        // 	https://api.megamarket.tech/api/market/v1/orderService/order/get

        try
        {
            $response = $this->TokenHttpClient()
                ->request(
                    'POST',
                    '/api/market/v1/orderService/order/get',
                    ['json' =>
                        [
                            'meta' => new stdClass(),
                            'data' => [
                                "token" => $this->getToken(),
                                "shipments" => [$order]
                            ]
                        ]
                    ],
                );

            $content = $response->toArray(false);

        }
        catch(Exception $exception)
        {
            $this->logger->critical(
                sprintf('megamarket-orders: Ошибка при получении информации о заказе %s', $order),
                [self::class, $exception->getMessage()]
            );

            return false;
        }


        if((isset($content['success']) && $content['success'] !== 1) || $response->getStatusCode() !== 200)
        {
            $this->logger->critical($order.': '.$content['error']['message'], [self::class.':'.__LINE__]);

            return false;
        }


        return empty($content['data']['shipments']) ? false : current($content['data']['shipments']);
    }

}
