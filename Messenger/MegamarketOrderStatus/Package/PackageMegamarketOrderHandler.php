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


use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Megamarket\Orders\Api\MegamarketOrdersGetInfoRequest;
use BaksDev\Megamarket\Orders\Api\MegamarketOrdersPostPackageRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class PackageMegamarketOrderHandler
{
    public function __construct(
        #[Target('megamarketOrdersLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
        private MegamarketOrdersGetInfoRequest $megamarketOrderRequest,
        private MegamarketOrdersPostPackageRequest $megamarketOrdersPackageRequest,
    ) {}

    /**
     * Метод отправляет уведомление Megamarket
     * об комплектации заказа (принят в обработку)
     */
    public function __invoke(PackageMegamarketOrderMessage $message): void
    {

        /** Получаем информацию о заказе */

        $UserProfileUid = $message->getProfile();
        $number = $message->getNumber();

        $MegamarketOrder = $this->megamarketOrderRequest
            ->profile($UserProfileUid)
            ->find($number);

        if($MegamarketOrder === false)
        {
            // Пробуем упаковать заказ через минуту
            $this->retry($message);

            return;
        }

        if($MegamarketOrder['status'] !== 'NEW')
        {
            $this->logger->critical(
                sprintf('megamarket-orders: Невозможно обновить статус не нового заказа %s', $message->getNumber()),
                [$MegamarketOrder, self::class.':'.__LINE__]
            );

            return;
        }

        /** Формируем список продукции в заказе */

        $items = null;

        foreach($MegamarketOrder['items'] as $key => $product)
        {
            /** При упаковке пропускаем элемент с доставкой */
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
            ->package($number);

        if($package === true)
        {
            $this->logger->info(
                sprintf('%s: Обновили статус «Укомплектована, готова к выдаче»', $number),
                [$message, self::class.':'.__LINE__]
            );

            return;
        }

        // Пробуем упаковать заказ через минуту
        $this->retry($message);
    }

    private function retry(PackageMegamarketOrderMessage $message): void
    {

        $this->logger->critical(
            sprintf('megamarket-orders: Пробуем упаковать заказ %s через 1 минуту', $message->getNumber()),
            [$message, self::class.':'.__LINE__]
        );

        $this->messageDispatch->dispatch(
            message: $message,
            stamps: [new MessageDelay('1 minutes')],
            transport: 'megamarket-orders-low'
        );
    }
}
