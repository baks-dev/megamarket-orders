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

namespace BaksDev\Megamarket\Orders\Messenger\MegamarketOrderStatus\Package;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Megamarket\Orders\Type\DeliveryType\TypeDeliveryDbsMegamarket;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Repository\OrderEvent\OrderEventInterface;
use BaksDev\Orders\Order\Repository\OrderNumber\NumberByOrder\NumberByOrderInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusNew;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Если поступает новый заказ Megamarket - отправляем уведомление о статусе «Принят в обработку»
 */
#[AsMessageHandler(priority: 9)]
final readonly class UpdatePackageOrderMegamarketDispatcher
{
    public function __construct(
        #[Target('megamarketOrdersLogger')] private LoggerInterface $logger,
        private OrderEventInterface $OrderEventRepository,
        private CurrentOrderEventInterface $CurrentOrderEvent,
        private DeduplicatorInterface $deduplicator,
        private MessageDispatchInterface $messageDispatch
    ) {}


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
                self::class
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        /** @var OrderEvent $OrderEvent */
        $OrderEvent = $this->OrderEventRepository
            ->find($message->getEvent());

        if(false === ($OrderEvent instanceof OrderEvent))
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

        /** Если тип заказа не DBS Megamarket «Доставка собственной службой» */
        $DeliveryUid = $OrderEvent->getDelivery()?->getDeliveryType();

        if(is_null($DeliveryUid) || false === $DeliveryUid->equals(TypeDeliveryDbsMegamarket::class))
        {
            return;
        }


        /** Получаем активное событие заказа в случае если статус заказа изменился */
        if(false === ($OrderEvent->getOrderProfile() instanceof UserProfileUid))
        {
            $OrderEvent = $this->CurrentOrderEvent
                ->forOrder($message->getId())
                ->find();

            if(false === ($OrderEvent instanceof OrderEvent))
            {
                $this->logger->critical(
                    'megamarket-orders: Не найдено событие OrderEvent',
                    [self::class.':'.__LINE__, var_export($message, true)]
                );

                return;
            }
        }

        $PackageMegamarketOrderMessage = new UpdatePackageOrderMegamarketMessage(
            $OrderEvent->getOrderNumber(),
            $OrderEvent->getOrderProfile()
        );

        $this->messageDispatch->dispatch(
            message: $PackageMegamarketOrderMessage,
            transport: 'megamarket-orders'
        );

        $Deduplicator->save();
    }
}
