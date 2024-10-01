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
use BaksDev\Megamarket\Orders\Api\MegamarketOrderRequest;
use BaksDev\Megamarket\Orders\Api\MegamarketOrdersPackageRequest;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\OrderEvent\OrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusNew;
use HttpRequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class PackageMegamarketOrderHandler
{
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $megamarketOrdersLogger,
        private OrderEventInterface $orderEvent,
        private DeduplicatorInterface $deduplicator,
        private MegamarketOrderRequest $megamarketOrderRequest,
        private MegamarketOrdersPackageRequest $megamarketOrdersPackageRequest,
    ) {
        $this->logger = $megamarketOrdersLogger;
    }

    /**
     * Метод отправляет уведомление Megamarket
     * об комплектации заказа (принят в обработку)
     */
    public function __invoke(OrderMessage $message): void
    {
        /** Новый заказ не имеет предыдущего события!!! */
        if($message->getLast())
        {
            return;
        }

        $Deduplicator = $this->deduplicator
            ->namespace('megamarket-orders')
            ->deduplication([
                (string) $message->getId(),
                OrderStatusNew::STATUS,
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


        if($OrderEvent->isStatusEquals(OrderStatusNew::class) === false)
        {
            return;
        }

        if($OrderEvent->getOrderNumber() === null)
        {
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
            $items[$key]['offerId'] = $product['offerId'];
            $items[$key]['quantity'] = $product['quantity'];
        }

        /**
         * Отправляем уведомление о комплектации заказа
         */
        $package = $this->megamarketOrdersPackageRequest
            ->profile($UserProfileUid)
            ->items($items)
            ->package($OrderEvent->getOrderNumber());

        if($package === true)
        {
            $this->logger->info(
                sprintf('%s: Обновили статус «Укомплектована, готова к выдаче»', $OrderEvent->getOrderNumber()),
                [self::class.':'.__LINE__]
            );

            $Deduplicator->save();

            return;
        }

        throw new HttpRequestException(
            sprintf(
                'megamarket-orders: Ошибка при обновлении заказа %s в статус «Укомплектована, готова к выдаче»',
                $OrderEvent->getOrderNumber()
            )
        );
    }
}
