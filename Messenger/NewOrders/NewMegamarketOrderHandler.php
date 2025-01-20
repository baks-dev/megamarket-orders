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

namespace BaksDev\Megamarket\Orders\Messenger\NewOrders;

use BaksDev\Core\Type\Field\InputField;
use BaksDev\Delivery\Repository\CurrentDeliveryEvent\CurrentDeliveryEventInterface;
use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\Megamarket\Orders\Api\MegamarketOrdersGetInfoRequest;
use BaksDev\Megamarket\Orders\Type\DeliveryType\TypeDeliveryDbsMegamarket;
use BaksDev\Megamarket\Orders\Type\DeliveryType\TypeDeliveryFbsMegamarket;
use BaksDev\Megamarket\Orders\Type\PaymentType\TypePaymentDbsMegamarket;
use BaksDev\Megamarket\Orders\Type\PaymentType\TypePaymentFbsMegamarket;
use BaksDev\Megamarket\Orders\Type\ProfileType\TypeProfileDbsMegamarket;
use BaksDev\Megamarket\Orders\Type\ProfileType\TypeProfileFbsMegamarket;
use BaksDev\Megamarket\Orders\UseCase\New\MegamarketOrderDTO;
use BaksDev\Megamarket\Orders\UseCase\New\MegamarketOrderHandler;
use BaksDev\Megamarket\Orders\UseCase\New\Products\NewOrderProductDTO;
use BaksDev\Megamarket\Orders\UseCase\New\User\Delivery\Field\OrderDeliveryFieldDTO;
use BaksDev\Megamarket\Orders\UseCase\New\User\UserProfile\Value\ValueDTO;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Repository\ExistsOrderNumber\ExistsOrderNumberInterface;
use BaksDev\Orders\Order\Repository\FieldByDeliveryChoice\FieldByDeliveryChoiceInterface;
use BaksDev\Payment\Type\Id\Choice\TypePaymentCache;
use BaksDev\Payment\Type\Id\PaymentUid;
use BaksDev\Products\Product\Repository\CurrentProductByArticle\ProductConstByArticleInterface;
use BaksDev\Reference\Money\Type\Money;
use BaksDev\Users\Address\Services\GeocodeAddressParser;
use BaksDev\Users\Profile\TypeProfile\Type\Id\TypeProfileUid;
use BaksDev\Users\Profile\UserProfile\Repository\FieldValueForm\FieldValueFormDTO;
use BaksDev\Users\Profile\UserProfile\Repository\FieldValueForm\FieldValueFormInterface;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 100)]
final readonly class NewMegamarketOrderHandler
{
    public function __construct(
        #[Target('megamarketOrdersLogger')] private LoggerInterface $logger,
        private ExistsOrderNumberInterface $existsOrderNumber,
        private MegamarketOrderHandler $megamarketOrderHandler,
        private MegamarketOrdersGetInfoRequest $megamarketOrderRequest,
        private FieldValueFormInterface $fieldValue,
        private GeocodeAddressParser $geocodeAddressParser,
        private FieldByDeliveryChoiceInterface $deliveryFields,
        private CurrentDeliveryEventInterface $currentDeliveryEvent,
        private ProductConstByArticleInterface $productConstByArticle,
        private UserByUserProfileInterface $userByUserProfile,
    ) {}

    public function __invoke(NewMegamarketOrderMessage $message): bool
    {

        /** Делаем проверку, что заказа с таким номером не существует */
        if($this->existsOrderNumber->isExists('M-'.$message->getShipment()))
        {
            $this->logger->info(sprintf('Заказ Megamarket #%s уже добавлен в систему', $message->getShipment()), [self::class.':'.__LINE__]);
            return false;
        }

        /** Получаем информацию о заказе */
        $MegamarketOrderRequest = $this->megamarketOrderRequest
            ->profile($message->getProfile())
            ->find($message->getShipment());


        if($MegamarketOrderRequest === false)
        {
            $this->logger->critical(sprintf('Ошибка при добавлении заказа Megamarket #%s', $message->getShipment()), [self::class.':'.__LINE__]);
            return false;
        }

        $MegamarketOrderDTO = new MegamarketOrderDTO();
        $MegamarketOrderDTO->setNumber('M-'.$MegamarketOrderRequest['shipmentId']); // номер
        $MegamarketOrderDTO->setCreated(new DateTimeImmutable($MegamarketOrderRequest['creationDate'])); // дата создания заказа

        /** Присваиваем постоянную величину */
        $User = $this->userByUserProfile->forProfile($message->getProfile())->findUser();
        $MegamarketOrderInvariableDTO = $MegamarketOrderDTO->getInvariable();
        $MegamarketOrderInvariableDTO->setNumber('M-'.$MegamarketOrderRequest['shipmentId']); // номер заказа
        $MegamarketOrderInvariableDTO->setUsr($User);
        $MegamarketOrderInvariableDTO->setProfile($message->getProfile());


        $OrderDeliveryDTO = $MegamarketOrderDTO->getUsr()->getDelivery();
        $OrderDeliveryDTO->setDeliveryDate(new DateTimeImmutable($MegamarketOrderRequest['deliveryDateFrom']));
        $OrderDeliveryDTO->setAddress($MegamarketOrderRequest['customerAddress']);


        $OrderPaymentDTO = $MegamarketOrderDTO->getUsr()->getPayment();
        $OrderProfileDTO = $MegamarketOrderDTO->getUsr()->getUserProfile();


        /** Доставка DBS */
        if($MegamarketOrderRequest['serviceScheme'] === 'DELIVERY_BY_MERCHANT')
        {
            /** Тип профиля DBS Megamarket */
            $Profile = new TypeProfileUid(TypeProfileDbsMegamarket::class);
            $OrderProfileDTO?->setType($Profile);

            /** Способ доставки Магазином (DBS Megamarket) */
            $Delivery = new DeliveryUid(TypeDeliveryDbsMegamarket::class);
            $OrderDeliveryDTO->setDelivery($Delivery);

            if($MegamarketOrderRequest['depositedAmount'] === 0)
            {
                /** Оплата при получении  */
                $Payment = new PaymentUid(TypePaymentCache::class);
            }
            else
            {
                /** Способ оплаты DBS Megamarket */
                $Payment = new PaymentUid(TypePaymentDbsMegamarket::class);
            }


            $email = $MegamarketOrderRequest['customer']['email'];
            $phone = $MegamarketOrderRequest['customer']['phone'];

            $OrderPaymentDTO->setPayment($Payment);

        }
        else
        {
            /** Тип профиля DBS Megamarket */
            $Profile = new TypeProfileUid(TypeProfileFbsMegamarket::class);
            $OrderProfileDTO?->setType($Profile);

            /** Способ доставки Магазином (DBS Megamarket) */
            $Delivery = new DeliveryUid(TypeDeliveryFbsMegamarket::class);
            $OrderDeliveryDTO->setDelivery($Delivery);

            /** Способ оплаты DBS Megamarket  */
            $Payment = new PaymentUid(TypePaymentFbsMegamarket::class);
            $OrderPaymentDTO->setPayment($Payment);

            $email = $MegamarketOrderRequest['customer']['email'];
            $phone = $MegamarketOrderRequest['customer']['phone'];
        }


        $MegamarketOrderDTO->setComment($MegamarketOrderRequest['customer']['comment']);

        if($OrderProfileDTO)
        {
            /** Определяем свойства клиента при доставке DBS */
            $profileFields = $this->fieldValue->get($OrderProfileDTO->getType());

            /** @var FieldValueFormDTO $profileField */
            foreach($profileFields as $profileField)
            {
                if(!empty($email) && $profileField->getType()->getType() === 'account_email')
                {
                    $UserProfileValueDTO = new ValueDTO();
                    $UserProfileValueDTO->setField($profileField->getField());
                    $UserProfileValueDTO->setValue($MegamarketOrderRequest['customer']['email']);
                    $OrderProfileDTO?->addValue($UserProfileValueDTO);
                    continue;
                }

                if($profileField->getType()->getType() === 'contact_field')
                {
                    $UserProfileValueDTO = new ValueDTO();
                    $UserProfileValueDTO->setField($profileField->getField());
                    $UserProfileValueDTO->setValue($MegamarketOrderRequest['customerFullName']);
                    $OrderProfileDTO?->addValue($UserProfileValueDTO);

                    continue;
                }

                if(!empty($phone) && $profileField->getType()->getType() === 'phone_field')
                {
                    $UserProfileValueDTO = new ValueDTO();
                    $UserProfileValueDTO->setField($profileField->getField());
                    $UserProfileValueDTO->setValue($MegamarketOrderRequest['customer']['phone']);
                    $OrderProfileDTO?->addValue($UserProfileValueDTO);

                    continue;
                }
            }
        }

        /** Определяем геолокацию */
        $GeocodeAddress = $this
            ->geocodeAddressParser
            ->getGeocode($OrderDeliveryDTO->getAddress());


        if(!empty($GeocodeAddress))
        {
            $OrderDeliveryDTO->setAddress($GeocodeAddress->getAddress());
            $OrderDeliveryDTO->setLatitude($GeocodeAddress->getLatitude());
            $OrderDeliveryDTO->setLongitude($GeocodeAddress->getLongitude());
        }

        /**
         * Определяем свойства доставки и присваиваем адрес
         */

        $fields = $this->deliveryFields->fetchDeliveryFields($OrderDeliveryDTO->getDelivery());

        $address_field = array_filter($fields, function($v) {
            /** @var InputField $InputField */
            return $v->getType()->getType() === 'address_field';
        });

        $address_field = current($address_field);

        if($address_field)
        {
            $OrderDeliveryFieldDTO = new OrderDeliveryFieldDTO();
            $OrderDeliveryFieldDTO->setField($address_field);
            $OrderDeliveryFieldDTO->setValue($OrderDeliveryDTO->getAddress());
            $OrderDeliveryDTO->addField($OrderDeliveryFieldDTO);
        }

        /** Присваиваем активное событие доставки */
        $DeliveryEvent = $this->currentDeliveryEvent->get($OrderDeliveryDTO->getDelivery());
        $OrderDeliveryDTO->setEvent($DeliveryEvent?->getId());

        $products = [];

        /** Продукция */
        foreach($MegamarketOrderRequest['items'] as $product)
        {
            /** Если доставка - присваиваем стоимость */
            if($product['offerId'] === 'delivery')
            {
                $OrderDeliveryDTO
                    ->getPrice()
                    ->setPrice(new Money($product['price']));
                continue;
            }

            if(!isset($products[$product['offerId']]))
            {
                $ProductData = $this->productConstByArticle->find($product['offerId']);

                if(!$ProductData)
                {
                    $error = sprintf('Артикул товара %s не найден', $product->getArticle());
                    throw new InvalidArgumentException($error);
                }

                /* Создаем объект и присваиваем стоимость */
                $NewOrderProductDTO = new NewOrderProductDTO($product['offerId']);
                $NewOrderProductDTO->getPrice()->setPrice(new Money($product['price']));
                $NewOrderProductDTO
                    ->setProduct($ProductData->getEvent())
                    ->setOffer($ProductData->getOffer())
                    ->setVariation($ProductData->getVariation())
                    ->setModification($ProductData->getModification());

                $MegamarketOrderDTO->addProduct($NewOrderProductDTO);

                $products[$product['offerId']] = $NewOrderProductDTO;
            }
            else
            {
                $NewOrderProductDTO = $products[$product['offerId']];
            }

            /** Увеличиваем количество */
            $NewOrderProductDTO->getPrice()->addTotal($product['quantity']);
        }

        $Order = $this->megamarketOrderHandler->handle($MegamarketOrderDTO);

        if($Order instanceof Order)
        {
            /** Отправляем сообщение о принятом в обработку заказе */
            $this->logger->info(sprintf('Megamarket: Добавили новый заказа #%s', $message->getShipment()));
            return true;
        }

        $this->logger->critical(
            sprintf('Megamarket: Ошибка %s при добавлении заказа #%s', $Order, $message->getShipment()),
            [self::class.':'.__LINE__]
        );
        return false;

    }
}
