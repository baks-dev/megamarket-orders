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

namespace BaksDev\Megamarket\Orders\Messenger\MegamarketOrderStatus;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Megamarket\Orders\Api\MegamarketOrdersGetInfoRequest;
use BaksDev\Megamarket\Orders\Api\MegamarketOrdersPostCloseRequest;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\OrderEvent\OrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusCompleted;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusCompleted;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class CloseMegamarketOrderHandler
{
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $megamarketOrdersLogger,
        private OrderEventInterface $orderEvent,
        private DeduplicatorInterface $deduplicator,
        private MegamarketOrdersGetInfoRequest $megamarketOrderRequest,
        private MegamarketOrdersPostCloseRequest $MegamarketOrdersCloseRequest,
    ) {
        $this->logger = $megamarketOrdersLogger;
    }

    /**
     * Метод отправляет уведомление Megamarket о выполненном заказе
     * если Completed «Выдан по месту назначения»
     */
    public function __invoke(OrderMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('megamarket-orders')
            ->deduplication([
                (string) $message->getId(),
                ProductStockStatusCompleted::STATUS,
                md5(self::class)
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        /** @var OrderEvent $OrderEvent */
        $OrderEvent = $this->orderEvent->find($message->getEvent());

        if(!$OrderEvent)
        {
            $this->logger->critical(
                'products-sign: Не найдено событие OrderEvent',
                [self::class.':'.__LINE__, 'OrderEventUid' => (string) $message->getEvent()]
            );

            return;
        }


        if($OrderEvent->isStatusEquals(OrderStatusCompleted::class) === false)
        {
            return;
        }


        if($OrderEvent->getOrderNumber() === null)
        {

            $this->logger->critical(
                'products-sign: Невозможно определить номер заказа',
                [self::class.':'.__LINE__, 'OrderEventUid' => (string) $message->getEvent()]
            );

            return;
        }


        /** Проверяем, что номер заявки начинается с M- (Megamarket) */
        if(false === str_starts_with($OrderEvent->getOrderNumber(), 'M-'))
        {
            return;
        }

        /** Получаем информацию о заказе */

        $UserProfileUid = $OrderEvent->getOrderProfile();

        $MegamarketOrder = $this->megamarketOrderRequest
            ->profile($UserProfileUid)
            ->find($OrderEvent->getOrderNumber());

        if($MegamarketOrder === false)
        {
            $this->logger->critical(
                sprintf('megamarket-orders: Заказ Megamarket %s не найден', $OrderEvent->getOrderNumber()),
                [self::class.':'.__LINE__]
            );

            return;
        }

        /** Формируем список продукции в заказе */

        $items = null;

        foreach($MegamarketOrder['items'] as $key => $product)
        {
            /** Пропускаем элемент с доставкой */
            if($product['offerId'] === 'delivery')
            {
                continue;
            }

            $items[$key]['itemIndex'] = $product['itemIndex'];
            $items[$key]['handoverResult'] = true;
        }

        /**
         * Отправляем уведомление о комплектации заказа
         */
        $package = $this->MegamarketOrdersCloseRequest
            ->profile($UserProfileUid)
            ->items($items)
            ->close($OrderEvent->getOrderNumber());

        if($package === true)
        {
            $this->logger->info(
                sprintf('%s: Обновили статус «Выдан по месту назначения»', $OrderEvent->getOrderNumber()),
                [self::class.':'.__LINE__]
            );

            $Deduplicator->save();

            return;
        }

        throw new InvalidArgumentException(
            sprintf(
                'megamarket-orders: Ошибка при обновлении заказа %s в статус «Выдан по месту назначения»',
                $OrderEvent->getOrderNumber()
            )
        );
    }
}
