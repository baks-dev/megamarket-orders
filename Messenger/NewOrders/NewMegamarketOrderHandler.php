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

namespace BaksDev\Megamarket\Orders\Messenger\NewOrders;

use BaksDev\Megamarket\Orders\Api\MegamarketOrderRequest;
use BaksDev\Megamarket\Orders\UseCase\New\MegamarketOrderDTO;
use BaksDev\Megamarket\Orders\UseCase\New\MegamarketOrderHandler;
use BaksDev\Megamarket\Repository\ProfileByCompany\ProfileByCompanyInterface;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Repository\ExistsOrderNumber\ExistsOrderNumberInterface;
use BaksDev\Yandex\Market\Orders\Api\YandexMarketNewOrdersRequest;
use BaksDev\Yandex\Market\Orders\Messenger\NewOrders\NewYandexOrdersMessage;
use BaksDev\Yandex\Market\Orders\UseCase\New\YandexMarketOrderDTO;
use BaksDev\Yandex\Market\Orders\UseCase\New\YandexMarketOrderHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class NewMegamarketOrderHandler
{

    private LoggerInterface $logger;
    private MegamarketOrderHandler $megamarketOrderHandler;
    private ExistsOrderNumberInterface $existsOrderNumber;
    private MegamarketOrderRequest $megamarketOrderRequest;
    private ProfileByCompanyInterface $profileByCompany;


    public function __construct(
        LoggerInterface $megamarketOrdersLogger,
        ExistsOrderNumberInterface $existsOrderNumber,
        MegamarketOrderHandler $megamarketOrderHandler,
        MegamarketOrderRequest $megamarketOrderRequest,
        ProfileByCompanyInterface $profileByCompany

    )
    {
        $this->logger = $megamarketOrdersLogger;
        $this->megamarketOrderHandler = $megamarketOrderHandler;
        $this->existsOrderNumber = $existsOrderNumber;
        $this->megamarketOrderRequest = $megamarketOrderRequest;
        $this->profileByCompany = $profileByCompany;
    }

    public function __invoke(NewMegamarketOrderMessage $message): void
    {
        /** Делаем проверку, что заказа с таким номером не существует */
        if($this->existsOrderNumber->isExists($message->getShipment()))
        {
            return;
        }

        /** Получаем активный профиль пользователя по идентификатору компании */
        $UserProfileUid = $this->profileByCompany->find($message->getCompany());

        if(!$UserProfileUid)
        {
            return;
        }

        /** Получаем информацию о заказе */
        $MegamarketOrderDTO = $this->megamarketOrderRequest
            ->profile($UserProfileUid)
            ->find($message->getShipment());


        if(false === $MegamarketOrderDTO->valid())
        {
            return;
        }


        dd($MegamarketOrderDTO);

        //            /**
        //             * Создаем системный заказ
        //             */
        //            $handle = $this->yandexMarketOrderHandler->handle($order);


        //        /** @var YandexMarketOrderDTO $order */
        //        foreach($orders as $order)
        //        {
        //            if($this->existsOrderNumber->isExists($order->getNumber()))
        //            {
        //                continue;
        //            }
        //
        //            /**
        //             * Создаем системный заказ
        //             */
        //            $handle = $this->yandexMarketOrderHandler->handle($order);
        //
        //            if($handle instanceof Order)
        //            {
        //                $this->logger->info(
        //                    sprintf('Добавили новый заказ %s', $order->getNumber()),
        //                    [
        //                        __FILE__.':'.__LINE__,
        //                        'attr' => (string) $message->getProfile()->getAttr(),
        //                        'profile' => (string) $message->getProfile(),
        //                    ]
        //                );
        //
        //                continue;
        //            }
        //
        //
        //            $this->logger->critical(
        //                sprintf('%s: Ошибка при добавлении заказа %s', $handle, $order->getNumber()),
        //                [
        //                    __FILE__.':'.__LINE__,
        //                    'attr' => (string) $message->getProfile()->getAttr(),
        //                    'profile' => (string) $message->getProfile(),
        //                ]
        //            );
        //        }
    }

}