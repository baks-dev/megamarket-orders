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
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
final class CancelController extends AbstractController
{
    /**
     * Метод принимает запросы на отмену заказа мегамаркет
     */
    #[Route('/megamarket/order/cancel/{profile}', name: 'megamarket.order.cancel', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        MessageDispatchInterface $messageDispatch,
        LoggerInterface $megamarketOrdersLogger,
        #[ParamConverter(UserProfileUid::class)] $profile = null,
    ): Response {

        if(empty($profile))
        {
            $megamarketOrdersLogger->warning('Идентификатор профиля не найден');
            return new JsonResponse(['data' => new stdClass(), 'meta' => new stdClass(), 'success' => 0]);
        }

        $megamarketOrdersLogger->warning($request->getContent());
        $data = json_decode($request->getContent(), false);


        return new JsonResponse(['data' => new stdClass(), 'meta' => new stdClass(), 'success' => 1]);


        /**
         * Делаем отмену заказа
         * @see https://partner-wiki.megamarket.ru/merchant-api/2-opisanie-api-fbs/2-1-rabota-s-api-vyzovami/order-cancel-standart
         */

        //        $MegamarketOrderDTO = new MegamarketOrderDTO($data);
        //        $Order = $megamarketOrderHandler->handle($MegamarketOrderDTO);
        //
        //        if($Order instanceof Order)
        //        {
        //            return new JsonResponse(['data' => new stdClass(), 'meta' => new stdClass(), 'success' => 1]);
        //        }
        //
        //        return new JsonResponse(['data' => new stdClass(), 'meta' => new stdClass(), 'success' => 0]);


    }
}
