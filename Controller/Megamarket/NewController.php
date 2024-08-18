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
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Megamarket\Orders\Messenger\NewOrders\NewMegamarketOrderMessage;
use BaksDev\Megamarket\Orders\UseCase\New\MegamarketOrderDTO;
use BaksDev\Megamarket\Orders\UseCase\New\MegamarketOrderHandler;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class NewController extends AbstractController
{
    /**
     * Метод принимает запросы на создание заказа мегамаркет
     */
    #[Route('/megamarket/order/new/{profile}', name: 'megamarket.order.new', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        LoggerInterface $megamarketOrdersLogger,
        MessageDispatchInterface $messageDispatch,
        #[ParamConverter(UserProfileUid::class)] $profile = null,
    ): Response {

        if(empty($profile))
        {
            $megamarketOrdersLogger->warning('Идентификатор профиля не найден');
            return new JsonResponse(['data' => new stdClass(), 'meta' => new stdClass(), 'success' => 0]);
        }

        $megamarketOrdersLogger->debug($request->getContent());
        $data = json_decode($request->getContent(), false);

        /** _TODO: тестовые данные */
        // $data = $this->test();

        /**
         * Создаем новый заказ
         * @see https://partner-wiki.megamarket.ru/merchant-api/2-opisanie-api-fbs/2-1-rabota-s-api-vyzovami/order-new-standart
         */

        $shipments = current($data->data->shipments);
        $NewMegamarketOrderMessage = new NewMegamarketOrderMessage($shipments->shipmentId, $profile);
        $messageDispatch->dispatch($NewMegamarketOrderMessage, transport: 'megamarket-orders');

        return new JsonResponse(['data' => new stdClass(), 'meta' => new stdClass(), 'success' => 1]);
    }

    public function test()
    {
        /** DBS */
        $json = '{"meta":{},"data":{"shipments":[{"shipmentId":"9324005526611","shipmentDate":"2024-08-09T16:12:28+03:00","handover":{"packingDate":"2024-08-09T19:00:00+03:00","reserveExpirationDate":"2024-08-29T00:00:00+03:00","outletId":"","serviceScheme":"DELIVERY_BY_MERCHANT","depositedAmount":30584,"deliveryInterval":{"dateFrom":"2024-08-13T10:00:00+03:00","dateTo":"2024-08-15T20:00:00+03:00"},"deliveryId":9385695846770},"customer":{"customerFullName":"Абдуллин Галим","phone":"79153693033","email":"","address":{"source":"Москва, улица Вавилова, 70 к3","postalCode":"119261","fias":{"regionId":"0c5b2444-70a0-4932-980c-b4dc0d3f02b5","destinationId":"0455c6b3-793f-457d-8b42-78b979b947e2"},"geo":{"lat":"55.683041","lon":"37.546646"},"access":{"detachedHouse":false,"entrance":null,"floor":null,"intercom":null,"cargoElevator":false,"comment":"предварительно позвоните за 3-4 часа до доставки.","apartment":""},"regionKladrId":"77","house":"70 к3","block":null,"flat":null,"regionWithType":"Москва","cityWithType":"Москва","cityArea":null,"streetWithType":"улица Вавилова"}},"flags":[],"items":[{"itemIndex":"1","goodsId":"100030313902","offerId":"PL02-19-235-40-96W","itemName":"Шины Triangle SnowLink PL02 235/40 R19 96W","price":7446,"finalPrice":7446,"discounts":[],"quantity":1,"taxRate":"20","reservationPerformed":true,"isDigitalMarkRequired":true},{"itemIndex":"2","goodsId":"100030313902","offerId":"PL02-19-235-40-96W","itemName":"Шины Triangle SnowLink PL02 235/40 R19 96W","price":7446,"finalPrice":7446,"discounts":[],"quantity":1,"taxRate":"20","reservationPerformed":true,"isDigitalMarkRequired":true},{"itemIndex":"3","goodsId":"100030313902","offerId":"PL02-19-235-40-96W","itemName":"Шины Triangle SnowLink PL02 235/40 R19 96W","price":7446,"finalPrice":7446,"discounts":[],"quantity":1,"taxRate":"20","reservationPerformed":true,"isDigitalMarkRequired":true},{"itemIndex":"4","goodsId":"100030313902","offerId":"PL02-19-235-40-96W","itemName":"Шины Triangle SnowLink PL02 235/40 R19 96W","price":7446,"finalPrice":7446,"discounts":[],"quantity":1,"taxRate":"20","reservationPerformed":true,"isDigitalMarkRequired":true},{"itemIndex":"5","goodsId":"100029275905","offerId":"delivery","itemName":"Доставка","price":800,"finalPrice":800,"discounts":[],"quantity":1,"taxRate":null,"reservationPerformed":true,"isDigitalMarkRequired":false}]}],"merchantId":175306}}';

        return json_decode($json, );
    }
}
