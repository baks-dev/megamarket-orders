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

use BaksDev\Auth\Email\Repository\AccountEventActiveByEmail\AccountEventActiveByEmailInterface;
use BaksDev\Auth\Email\UseCase\User\Registration\RegistrationHandler;
use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Core\Type\Field\InputField;
use BaksDev\Core\Validator\ValidatorCollectionInterface;
use BaksDev\Delivery\Repository\CurrentDeliveryEvent\CurrentDeliveryEventInterface;
use BaksDev\Files\Resources\Upload\File\FileUploadInterface;
use BaksDev\Files\Resources\Upload\Image\ImageUploadInterface;
use BaksDev\Megamarket\Orders\UseCase\New\User\Delivery\Field\OrderDeliveryFieldDTO;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\FieldByDeliveryChoice\FieldByDeliveryChoiceInterface;
use BaksDev\Products\Product\Repository\ProductByArticle\ProductEventByArticleInterface;
use BaksDev\Users\Address\Services\GeocodeAddressParser;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\CurrentUserProfileEvent\CurrentUserProfileEventInterface;
use BaksDev\Users\Profile\UserProfile\UseCase\User\NewEdit\UserProfileHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class MegamarketOrderHandler extends AbstractHandler
{
    private TokenStorageInterface $tokenStorage;
    private RegistrationHandler $registrationHandler;
    private AccountEventActiveByEmailInterface $accountEventActiveByEmail;
    private UserPasswordHasherInterface $passwordHasher;

    private CurrentUserProfileEventInterface $currentUserProfileEvent;
    private UserProfileHandler $profileHandler;
    private ProductEventByArticleInterface $currentProductEventByArticle;
    private FieldByDeliveryChoiceInterface $deliveryFields;
    private CurrentDeliveryEventInterface $currentDeliveryEvent;
    private GeocodeAddressParser $geocodeAddressParser;

    public function __construct(
        EntityManagerInterface $entityManager,
        MessageDispatchInterface $messageDispatch,
        ValidatorCollectionInterface $validatorCollection,
        ImageUploadInterface $imageUpload,
        FileUploadInterface $fileUpload,
        RegistrationHandler $registrationHandler,
        UserProfileHandler $profileHandler,
        AccountEventActiveByEmailInterface $accountEventActiveByEmail,
        UserPasswordHasherInterface $passwordHasher,
        CurrentUserProfileEventInterface $currentUserProfileEvent,
        TokenStorageInterface $tokenStorage,


        ProductEventByArticleInterface $currentProductEventByArticle,
        FieldByDeliveryChoiceInterface $deliveryFields,
        CurrentDeliveryEventInterface $currentDeliveryEvent,
        GeocodeAddressParser $geocodeAddressParser,


    )
    {
        parent::__construct($entityManager, $messageDispatch, $validatorCollection, $imageUpload, $fileUpload);

        $this->registrationHandler = $registrationHandler;
        $this->profileHandler = $profileHandler;
        $this->accountEventActiveByEmail = $accountEventActiveByEmail;
        $this->passwordHasher = $passwordHasher;
        $this->currentUserProfileEvent = $currentUserProfileEvent;
        $this->tokenStorage = $tokenStorage;


        $this->currentProductEventByArticle = $currentProductEventByArticle;
        $this->deliveryFields = $deliveryFields;
        $this->currentDeliveryEvent = $currentDeliveryEvent;
        $this->geocodeAddressParser = $geocodeAddressParser;
    }

    public function handle(MegamarketOrderDTO $command): string|Order
    {
        dd($command);


        /**
         * Получаем события продукции
         * @var Products\NewOrderProductDTO $product
         */
        foreach($command->getProduct() as $product)
        {
            $ProductData = $this->currentProductEventByArticle->findProductEventByArticle($product->getArticle());

            if(!$ProductData)
            {
                return 'Артикул товара не найден';
            }

            $product->setProduct($ProductData['product_event_uid']);
            $product->setOffer($ProductData['product_offer_uid']);
            $product->setVariation($ProductData['product_variation_uid']);
            $product->setModification($ProductData['product_modification_uid']);

        }


        /** Идентификатор свойства адреса доставки */
        $OrderDeliveryDTO = $command->getUsr()->getDelivery();

        /** Создаем адрес геолокации */
        $GeocodeAddress = $this->geocodeAddressParser->getGeocode($OrderDeliveryDTO->getLatitude().', '.$OrderDeliveryDTO->getLongitude());

        if($GeocodeAddress)
        {
            $OrderDeliveryDTO->setAddress($GeocodeAddress->getAddress());
            $OrderDeliveryDTO->setGeocode($GeocodeAddress->getId());
        }


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

        $DeliveryEvent = $this->currentDeliveryEvent->get($OrderDeliveryDTO->getDelivery());
        $OrderDeliveryDTO->setEvent($DeliveryEvent?->getId());





        /** Валидация DTO  */
        $this->validatorCollection->add($command);

        $OrderUserDTO = $command->getUsr();

        /**
         * Создаем профиль пользователя если отсутствует
         */
        if($OrderUserDTO->getProfile() === null)
        {

            $UserProfileDTO = $OrderUserDTO->getUserProfile();

            //dd($UserProfileDTO);

            $this->validatorCollection->add($UserProfileDTO);

            if($UserProfileDTO === null)
            {
                return $this->validatorCollection->getErrorUniqid();
            }

            /** Пробуем найти активный профиль пользователя */
            $UserProfileEvent = $this->currentUserProfileEvent
                ->findByUser($OrderUserDTO->getUsr())?->getId();

            if(!$UserProfileEvent)
            {
                /* Присваиваем новому профилю идентификатор пользователя (либо нового, либо уже созданного) */

                $UserProfileDTO->getInfo()->setUsr($OrderUserDTO->getUsr());

                $UserProfile = $this->profileHandler->handle($UserProfileDTO);

                if(!$UserProfile instanceof UserProfile)
                {
                    return $UserProfile;
                }

                $UserProfileEvent = $UserProfile->getEvent();
            }

            $OrderUserDTO->setProfile($UserProfileEvent);
        }

        $this->main = new Order();
        $this->event = new OrderEvent();


        $this->prePersist($command);
        $this->main->setNumber($command->getNumber());

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
}
