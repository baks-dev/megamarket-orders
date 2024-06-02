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

namespace BaksDev\Megamarket\Orders\Controller\Megamarket;

use BaksDev\Core\Controller\AbstractController;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
final class NewController extends AbstractController
{
    /**
     * Метод принимает запросы на создание заказа мегамаркет
     */
    #[Route('/megamarket/order/new', name: 'megamarket.order.new', methods: ['GET', 'POST'])]
    public function index(
        Request $request
    ): Response
    {

        $data = json_decode($request->getContent());


        return new JsonResponse(['data' => new stdClass(), 'meta' => new stdClass(), 'success' => 1]);

    }

    public function test()
    {

        $json = '{
  "data": {
    "merchantId": 5046,
    "shipments": [
      {
        "shipmentId": "946032218",
        "shipmentDate": "2021-02-17T09:34:03+03:00",
        "items": [
          {
            "itemIndex": "1",
            "goodsId": "100023763738",
            "offerId": "3951",
            "itemName": "Вертикальный пылесос Kitfort  KT-535-2 White",
            "price": 11990,
            "finalPrice": 5995,
            "discounts": [
              {
                "discountType": "BPG20",
                "discountDescription": "BPG20",
                "discountAmount": 1424
              },
              {
                "discountType": "LOY",
                "discountDescription": "Скидка БР",
                "discountAmount": 4571
              }
            ],
            "quantity": 1,
            "taxRate": "20",
            "reservationPerformed": true,
            "isDigitalMarkRequired": false
          }
        ],
        "label": {
          "deliveryId": "933021193",
          "region": "Воронежская",
          "city": "Воронеж",
          "address": "Россия, Воронежская обл.,  Воронеж, Проспект Губкина, 9",
          "fullName": "Петров Пётр",
          "merchantName": "ООО \"АЭРО-ТРЕЙД\"",
          "merchantId": 5046,
          "shipmentId": "956032218",
          "shippingDate": "2021-02-21T17:00:00+03:00",
          "deliveryType": "Самовывоз из пункта выдачи",
          "labelText": "<!DOCTYPE html>\n<html>\n<head>\n    <meta charset=\"UTF-8\"/>\t\n    <title>Маркировочный лист</title>    ..."
        },
        "shipping": {
          "shippingDate": "2021-02-21T17:00:00+03:00",
          "shippingPoint": 132378
        },
        "fulfillmentMethod": "FULFILLMENT_BY_MERCHANT"
      }
    ]
  },
  "meta": {
    "source": "OMS"
  }
}';

        return json_decode($json);

    }
}
