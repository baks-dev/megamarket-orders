<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Core\Type\Field\InputField;
use BaksDev\Core\Validator\ValidatorCollectionInterface;
use BaksDev\Delivery\Repository\CurrentDeliveryEvent\CurrentDeliveryEventInterface;
use BaksDev\Files\Resources\Upload\File\FileUploadInterface;
use BaksDev\Files\Resources\Upload\Image\ImageUploadInterface;
use BaksDev\Megamarket\Orders\UseCase\New\User\UserProfile\Value\ValueDTO;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\ExistsOrderNumber\ExistsOrderNumberInterface;
use BaksDev\Orders\Order\Repository\FieldByDeliveryChoice\FieldByDeliveryChoiceInterface;
use BaksDev\Products\Product\Repository\CurrentProductByArticle\ProductConstByArticleInterface;
use BaksDev\Users\Address\Services\GeocodeAddressParser;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\FieldValueForm\FieldValueFormDTO;
use BaksDev\Users\Profile\UserProfile\Repository\FieldValueForm\FieldValueFormInterface;
use BaksDev\Users\Profile\UserProfile\UseCase\User\NewEdit\UserProfileHandler;
use Doctrine\ORM\EntityManagerInterface;

final class MegamarketOrderHandler extends AbstractHandler
{
    public function __construct(
        EntityManagerInterface $entityManager,
        MessageDispatchInterface $messageDispatch,
        ValidatorCollectionInterface $validatorCollection,
        ImageUploadInterface $imageUpload,
        FileUploadInterface $fileUpload,
        private readonly UserProfileHandler $profileHandler,
        //        private readonly ProductConstByArticleInterface $productConstByArticle,
        //        private readonly FieldByDeliveryChoiceInterface $deliveryFields,
        //        private readonly CurrentDeliveryEventInterface $currentDeliveryEvent,
        //        private readonly GeocodeAddressParser $geocodeAddressParser,
        //        private readonly FieldValueFormInterface $fieldValue,
        //        private readonly ExistsOrderNumberInterface $existsOrderNumber,
    ) {
        parent::__construct($entityManager, $messageDispatch, $validatorCollection, $imageUpload, $fileUpload);
    }

    public function handle(MegamarketOrderDTO $command): string|Order
    {
        //        $exist = $this->existsOrderNumber->isExists($command->getNumber());
        //
        //        if($exist)
        //        {
        //            return '';
        //        }

        //        /**
        //         * Получаем события продукции
        //         * @var Products\NewOrderProductDTO $product
        //         */
        //        foreach($command->getProduct() as $product)
        //        {
        //            $ProductData = $this->productConstByArticle->find($product->getArticle());
        //
        //            if(!$ProductData)
        //            {
        //                $error = sprintf('Артикул товара %s не найден', $product->getArticle());
        //                throw new \InvalidArgumentException($error);
        //            }
        //
        //            $product
        //                ->setProduct($ProductData->getEvent())
        //                ->setOffer($ProductData->getOffer())
        //                ->setVariation($ProductData->getVariation())
        //                ->setModification($ProductData->getModification());
        //        }

        ///** Присваиваем информацию о покупателе */
        //$this->fillProfile($command);

        ///** Присваиваем информацию о доставке */
        //$this->fillDelivery($command);

        /** Валидация DTO  */
        $this->validatorCollection->add($command);

        $OrderUserDTO = $command->getUsr();


        /**
         * Создаем профиль пользователя
         */
        if($OrderUserDTO->getProfile() === null)
        {
            $UserProfileDTO = $OrderUserDTO->getUserProfile();
            $this->validatorCollection->add($UserProfileDTO);

            if($UserProfileDTO === null)
            {
                return $this->validatorCollection->getErrorUniqid();
            }

            /* Присваиваем новому профилю идентификатор пользователя */
            $UserProfileDTO->getInfo()->setUsr($OrderUserDTO->getUsr());
            $UserProfile = $this->profileHandler->handle($UserProfileDTO);

            if(!$UserProfile instanceof UserProfile)
            {
                return $UserProfile;
            }

            $UserProfileEvent = $UserProfile->getEvent();
            $OrderUserDTO->setProfile($UserProfileEvent);
        }


        $this->main = new Order();
        $this->main->setNumber($command->getNumber());

        $this->event = new OrderEvent();

        $this->prePersist($command);


        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        $this->entityManager->flush();

        /* Отправляем сообщение в шину */
        $this->messageDispatch->dispatch(
            message: new OrderMessage($this->main->getId(), $this->main->getEvent(), $command->getEvent()),
            transport: 'orders-order'
        );

        return $this->main;
    }


    //    public function fillProfile(MegamarketOrderDTO $command): void
    //    {
    //        if($command->getCustomer() === null)
    //        {
    //            return;
    //        }
    //
    //        /** Профиль пользователя  */
    //        $UserProfileDTO = $command->getUsr()->getUserProfile();
    //
    //        if(null === $UserProfileDTO)
    //        {
    //            return;
    //        }
    //
    //        /** Идентификатор типа профиля  */
    //        $TypeProfileUid = $UserProfileDTO?->getType();
    //
    //        if(null === $TypeProfileUid)
    //        {
    //            return;
    //        }
    //
    //
    //        $Customer = $command->getCustomer();
    //
    //        /** Определяем свойства клиента при доставке DBS */
    //        $profileFields = $this->fieldValue->get($TypeProfileUid);
    //
    //        /** @var FieldValueFormDTO $profileField */
    //        foreach($profileFields as $profileField)
    //        {
    //            if(!empty($Customer->email) && $profileField->getType()->getType() === 'account_email')
    //            {
    //                /** Не добавляем подменный mail YandexMarket */
    //
    //                $UserProfileValueDTO = new ValueDTO();
    //                $UserProfileValueDTO->setField($profileField->getField());
    //                $UserProfileValueDTO->setValue($Customer->email);
    //                $UserProfileDTO->addValue($UserProfileValueDTO);
    //                continue;
    //            }
    //
    //            if(!empty($Customer->customerFullName) && $profileField->getType()->getType() === 'contact_field')
    //            {
    //                $UserProfileValueDTO = new ValueDTO();
    //                $UserProfileValueDTO->setField($profileField->getField());
    //                $UserProfileValueDTO->setValue($Customer->customerFullName);
    //                $UserProfileDTO->addValue($UserProfileValueDTO);
    //
    //                continue;
    //            }
    //
    //            if(!empty($Customer->phone) && $profileField->getType()->getType() === 'phone_field')
    //            {
    //                $UserProfileValueDTO = new ValueDTO();
    //                $UserProfileValueDTO->setField($profileField->getField());
    //                $UserProfileValueDTO->setValue($Customer->phone);
    //                $UserProfileDTO->addValue($UserProfileValueDTO);
    //
    //                continue;
    //            }
    //
    //        }
    //    }

    //    public function fillDelivery(MegamarketOrderDTO $command): void
    //    {
    //        /* Идентификатор свойства адреса доставки */
    //        $OrderDeliveryDTO = $command->getUsr()->getDelivery();
    //
    //        /* Создаем адрес геолокации */
    //        $GeocodeAddress = $this->geocodeAddressParser
    //            ->getGeocode(
    //                $OrderDeliveryDTO->getLatitude().', '.$OrderDeliveryDTO->getLongitude()
    //            );
    //
    //        /** Если адрес не найден по геолокации - пробуем определить по адресу */
    //        if(empty($GeocodeAddress))
    //        {
    //            $GeocodeAddress = $this->geocodeAddressParser
    //                ->getGeocode(
    //                    $OrderDeliveryDTO->getAddress()
    //                );
    //        }
    //
    //        if(!empty($GeocodeAddress))
    //        {
    //            $OrderDeliveryDTO->setAddress($GeocodeAddress->getAddress());
    //            $OrderDeliveryDTO->setLatitude($GeocodeAddress->getLatitude());
    //            $OrderDeliveryDTO->setLongitude($GeocodeAddress->getLongitude());
    //        }
    //
    //
    //        /**
    //         * Определяем свойства доставки и присваиваем адрес
    //         */
    //
    //        $fields = $this->deliveryFields->fetchDeliveryFields($OrderDeliveryDTO->getDelivery());
    //
    //        $address_field = array_filter($fields, function ($v) {
    //            /** @var InputField $InputField */
    //            return $v->getType()->getType() === 'address_field';
    //        });
    //
    //        $address_field = current($address_field);
    //
    //        if($address_field)
    //        {
    //            $OrderDeliveryFieldDTO = new User\Delivery\Field\OrderDeliveryFieldDTO();
    //            $OrderDeliveryFieldDTO->setField($address_field);
    //            $OrderDeliveryFieldDTO->setValue($OrderDeliveryDTO->getAddress());
    //            $OrderDeliveryDTO->addField($OrderDeliveryFieldDTO);
    //        }
    //
    //        /**
    //         * Присваиваем активное событие доставки
    //         */
    //
    //        $DeliveryEvent = $this->currentDeliveryEvent->get($OrderDeliveryDTO->getDelivery());
    //        $OrderDeliveryDTO->setEvent($DeliveryEvent?->getId());
    //    }
}
