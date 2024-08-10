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

namespace BaksDev\Megamarket\Orders\UseCase\New;

use BaksDev\Core\Type\Gps\GpsLatitude;
use BaksDev\Core\Type\Gps\GpsLongitude;
use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\Megamarket\Orders\Type\DeliveryType\TypeDeliveryDbsMegamarket;
use BaksDev\Megamarket\Orders\Type\PaymentType\TypePaymentDbsMegamarket;
use BaksDev\Megamarket\Orders\Type\ProfileType\TypeProfileDbsMegamarket;
use BaksDev\Orders\Order\Entity\Event\OrderEventInterface;
use BaksDev\Orders\Order\Type\Event\OrderEventUid;
use BaksDev\Payment\Type\Id\PaymentUid;
use BaksDev\Reference\Money\Type\Money;
use BaksDev\Users\Profile\TypeProfile\Type\Id\TypeProfileUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use stdClass;
use Symfony\Component\Validator\Constraints as Assert;

/** @see OrderEvent */
final class MegamarketOrderDTO implements OrderEventInterface
{
    /** Идентификатор события */
    #[Assert\Uuid]
    private ?OrderEventUid $id = null;

    /** Идентификатор отправления Мегамаркета */
    private string $number;

    /** Дата создания отправления */
    #[Assert\NotBlank]
    private DateTimeImmutable $created;


    /** Коллекция продукции в заказе */
    #[Assert\Valid]
    private ArrayCollection $product;

    /** Пользователь */
    #[Assert\Valid]
    private User\OrderUserDTO $usr;

    /** Ответственный */
    private ?UserProfileUid $profile = null;

    /** Комментарий к заказу */
    private ?string $comment = null;

    /** Информация о покупателе */
    private ?stdClass $customer = null;


    public function __construct() {

        $this->product = new ArrayCollection();
        $this->usr = new User\OrderUserDTO();
    }

    public function construct(stdClass $order)
    {

        //$data = $order->data;
        //$shipments = current($data->shipments);

        //$this->number = (string) 'M-'.$shipments->shipmentId; // Номер заказа
        //$this->created = new DateTimeImmutable($shipments->shipmentDate); // Дата заказа


        //$this->product = new ArrayCollection();
        //$this->usr = new User\OrderUserDTO();

        $OrderDeliveryDTO = $this->usr->getDelivery();
        $OrderPaymentDTO = $this->usr->getPayment();
        $OrderProfileDTO = $this->usr->getUserProfile();

        /** Дата доставки */
        $delivery = $shipments->handover;
        $deliveryDate = new DateTimeImmutable($delivery->deliveryInterval->dateFrom);
        $OrderDeliveryDTO->setDeliveryDate($deliveryDate);


        $address = $shipments->customer->address;

        /** Геолокация клиента */
        $OrderDeliveryDTO->setLatitude(new GpsLatitude($address->geo->lat));
        $OrderDeliveryDTO->setLongitude(new GpsLongitude($address->geo->lon));
        $OrderDeliveryDTO->setAddress($address->source);


        /** Доставка DBS */
        if($delivery->serviceScheme === 'DELIVERY_BY_MERCHANT')
        {
            /** Тип профиля DBS Megamarket */
            $Profile = new TypeProfileUid(TypeProfileDbsMegamarket::class);
            $OrderProfileDTO?->setType($Profile);

            /** Способ доставки Магазином (DBS Megamarket) */
            $Delivery = new DeliveryUid(TypeDeliveryDbsMegamarket::class);
            $OrderDeliveryDTO->setDelivery($Delivery);

            /** Способ оплаты DBS Megamarket  */
            $Payment = new PaymentUid(TypePaymentDbsMegamarket::class);
            $OrderPaymentDTO->setPayment($Payment);
        }

        foreach($address->access as $key => $access)
        {
            if(empty($access))
            {
                continue;
            }

            $deliveryComment[] = match ($key)
            {
                'detachedHouse' => 'частный дом',
                'entrance' => 'вход '.$access,
                'intercom' => 'домофон '.$access,
                'cargoElevator' => 'гр. лифт',
                'floor' => 'этаж '.$access,
                'apartment' => 'кв. '.$access,
                default => $access,
            };
        }

        /** Комментарий покупателя */
        $this->comment = implode(', ', $deliveryComment);


        /** Информация о покупателе (для заполнения свойств профиля) */
        $this->customer = $shipments->customer;


        $products = [];

        /** Продукция */
        foreach($shipments->items as $item)
        {
            /** Если доставка - присваиваем стоимость */
            if($item->offerId === 'delivery')
            {
                $OrderDeliveryDTO
                    ->getPrice()
                    ->setPrice(new Money($item->finalPrice));
                continue;
            }

            if(!isset($products[$item->offerId]))
            {
                /* Создаем объект и присваиваем стоимость */
                $NewOrderProductDTO = new Products\NewOrderProductDTO($item->offerId);
                $NewOrderProductDTO->getPrice()->setPrice(new Money($item->finalPrice));
                $this->addProduct($NewOrderProductDTO);

                $products[$item->offerId] = $NewOrderProductDTO;
            }
            else
            {
                $NewOrderProductDTO = $products[$item->offerId];
            }

            /** Увеличиваем количество */
            $NewOrderProductDTO->getPrice()->addTotal($item->quantity);
        }
    }





    /** @see OrderEvent */
    public function getEvent(): ?OrderEventUid
    {
        return $this->id;
    }

    /**
     * Number
     */
    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;
        return $this;
    }



    /** Коллекция продукции в заказе */

    public function getProduct(): ArrayCollection
    {
        return $this->product;
    }

    public function setProduct(ArrayCollection $product): void
    {
        $this->product = $product;
    }

    public function addProduct(Products\NewOrderProductDTO $product): void
    {
        $filter = $this->product->filter(function (Products\NewOrderProductDTO $element) use ($product) {
            return $element->getArticle() === $product->getArticle();
        });

        if($filter->isEmpty())
        {
            $this->product->add($product);
        }
    }

    public function removeProduct(Products\NewOrderProductDTO $product): void
    {
        $this->product->removeElement($product);
    }

    /**
     * Usr
     */
    public function getUsr(): User\OrderUserDTO
    {
        return $this->usr;
    }

    /**
     * Buyer
     */
    public function getCustomer(): ?stdClass
    {

        return $this->customer;
    }



    /**
     * Created
     */
    public function getCreated(): DateTimeImmutable
    {
        return $this->created;
    }

    public function setCreated(DateTimeImmutable $created): self
    {
        $this->created = $created;
        return $this;
    }



    /**
     * Comment
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }





}
