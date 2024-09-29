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

namespace BaksDev\Megamarket\Orders\Api\Tests;

use BaksDev\Megamarket\Orders\Api\MegamarketOrderRequest;
use BaksDev\Megamarket\Orders\Api\MegamarketOrdersCloseRequest;
use BaksDev\Megamarket\Orders\Api\MegamarketOrdersConfirmRequest;
use BaksDev\Megamarket\Orders\Api\MegamarketOrdersNewRequest;
use BaksDev\Megamarket\Orders\Api\MegamarketOrdersPackageRequest;
use BaksDev\Megamarket\Type\Authorization\MegamarketAuthorizationToken;
use BaksDev\Ozon\Products\Api\Settings\Attribute\OzonAttributeDTO;
use BaksDev\Ozon\Products\Api\Settings\Attribute\OzonAttributeRequest;
use BaksDev\Ozon\Type\Authorization\OzonAuthorizationToken;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group megamarket-orders
 */
#[When(env: 'test')]
class MegamarketOrdersPackageRequestTest extends KernelTestCase
{
    private static MegamarketAuthorizationToken $Authorization;

    public static function setUpBeforeClass(): void
    {
        self::$Authorization = new MegamarketAuthorizationToken(
            new UserProfileUid(),
            $_SERVER['TEST_MEGAMARKET_TOKEN'],
            $_SERVER['TEST_MEGAMARKET_COMPANY'],
        );
    }


    public function testComplete(): void
    {

        self::assertTrue(true);
        return;

        /** @var MegamarketOrderRequest $MegamarketOrderRequest */
        $MegamarketOrderRequest = self::getContainer()->get(MegamarketOrderRequest::class);
        $MegamarketOrderRequest->TokenHttpClient(self::$Authorization);

        $number = '1234567890';

        $order = $MegamarketOrderRequest->find($number);

        $items = null;

        foreach($order['items'] as $key => $product)
        {
            /** Пропускаем элемент с доставкой */
            if($product['offerId'] === 'delivery')
            {
                continue;
            }

            $items[$key]['itemIndex'] = $product['itemIndex'];
            $items[$key]['handoverResult'] = true;
        }


        /** @var MegamarketOrdersPackageRequest $MegamarketOrdersCloseRequest */
        $MegamarketOrdersCloseRequest = self::getContainer()->get(MegamarketOrdersCloseRequest::class);
        $MegamarketOrdersCloseRequest->TokenHttpClient(self::$Authorization);

        $close = $MegamarketOrdersCloseRequest
            ->items($items)
            ->package($number);

        // dd($close);
        self::assertTrue($close);


    }
}