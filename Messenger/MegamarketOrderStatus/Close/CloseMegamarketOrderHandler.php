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

namespace BaksDev\Megamarket\Orders\Messenger\MegamarketOrderStatus\Close;


use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Megamarket\Orders\Api\MegamarketOrdersGetInfoRequest;
use BaksDev\Megamarket\Orders\Api\MegamarketOrdersPostCloseRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class CloseMegamarketOrderHandler
{
    public function __construct(
        #[Target('megamarketOrdersLogger')] private readonly LoggerInterface $logger,
        private MegamarketOrdersGetInfoRequest $megamarketOrderRequest,
        private MegamarketOrdersPostCloseRequest $MegamarketOrdersCloseRequest,
        private MessageDispatchInterface $messageDispatch,
    ) {}

    /**
     * Метод отправляет уведомление Megamarket о выполненном заказе
     * если Completed «Выдан по месту назначения»
     */
    public function __invoke(CloseMegamarketOrderMessage $message): void
    {
        $UserProfileUid = $message->getProfile();
        $number = $message->getNumber();

        /** Получаем информацию о заказе */

        $MegamarketOrder = $this->megamarketOrderRequest
            ->profile($UserProfileUid)
            ->find($number);

        if($MegamarketOrder === false)
        {
            // Пробуем закрыть заказ через минуту
            $this->retry($message);

            return;
        }

        /** Формируем список продукции в заказе */

        $items = null;

        foreach($MegamarketOrder['items'] as $key => $product)
        {
            $items[$key]['itemIndex'] = $product['itemIndex'];
            $items[$key]['handoverResult'] = true;
        }

        /**
         * Отправляем уведомление о комплектации заказа
         */
        $package = $this->MegamarketOrdersCloseRequest
            ->profile($UserProfileUid)
            ->items($items)
            ->close($number);

        if($package === true)
        {
            $this->logger->info(
                sprintf('%s: Обновили статус «Выдан по месту назначения»', $number),
                [self::class.':'.__LINE__]
            );

            return;
        }

        // Пробуем закрыть заказ через минуту
        $this->retry($message);

    }

    /**
     * Метод добавляет отложенное на 1 минуту сообщение в очередь
     */
    private function retry(CloseMegamarketOrderMessage $message): void
    {
        $this->logger->critical(
            sprintf('megamarket-orders: Пробуем закрыть заказ %s через 1 минуту', $message->getNumber()),
            [self::class.':'.__LINE__]
        );

        $this->messageDispatch->dispatch(
            message: $message,
            stamps: [new MessageDelay('1 minutes')],
            transport: 'megamarket-orders'
        );
    }
}
