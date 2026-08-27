<?php

namespace App\Modules\Hotel\Form;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelRate;
use App\Modules\Hotel\Entity\HotelRoomType;
use App\Modules\Hotel\Enum\HotelBoardType;
use App\Modules\Hotel\Repository\HotelRoomTypeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HotelRateType extends AbstractType
{
    public function __construct(private readonly HotelRoomTypeRepository $roomTypeRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $hotel = $options['hotel'];
        \assert($hotel instanceof Hotel);

        $builder
            ->add('roomType', EntityType::class, [
                'label' => 'hotel.rate.room_type',
                'class' => HotelRoomType::class,
                'choices' => $this->roomTypeRepository->findActiveForHotel($hotel),
                'choice_label' => 'name',
                'placeholder' => 'hotel.rate.select_room_type',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('validFrom', HiddenType::class, [
                'label' => 'hotel.rate.valid_from',
                'error_bubbling' => false,
                'attr' => ['data-locale-date-target' => 'value'],
            ])
            ->add('validTo', HiddenType::class, [
                'label' => 'hotel.rate.valid_to',
                'error_bubbling' => false,
                'attr' => ['data-locale-date-target' => 'value'],
            ])
            ->add('adults', IntegerType::class, [
                'label' => 'hotel.rate.adults',
                'attr' => ['class' => 'form-input', 'min' => 1],
            ])
            ->add('children', IntegerType::class, [
                'label' => 'hotel.rate.children',
                'attr' => [
                    'class' => 'form-input',
                    'min' => 0,
                    'data-child-ages-target' => 'count',
                    'data-action' => 'input->child-ages#sync change->child-ages#sync',
                ],
            ])
            ->add('childrenAges', TextType::class, [
                'label' => 'hotel.rate.children_ages',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => '4,8',
                    'data-child-ages-target' => 'field',
                ],
                'help' => 'hotel.offer.children_ages_help',
            ])
            ->add('boardType', ChoiceType::class, [
                'label' => 'hotel.rate.board',
                'required' => false,
                'choices' => HotelBoardType::formChoices(),
                'placeholder' => 'hotel.rate.select_board',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('currency', TextType::class, [
                'label' => 'hotel.rate.currency',
                'attr' => ['class' => 'form-input', 'maxlength' => 3],
            ])
            ->add('pricePerNight', TextType::class, [
                'label' => 'hotel.rate.price_per_night',
                'attr' => ['class' => 'form-input', 'inputmode' => 'decimal'],
            ])
            ->add('priority', IntegerType::class, [
                'label' => 'hotel.rate.priority',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'hotel.common.active',
                'required' => false,
                'label_attr' => ['class' => 'inline-flex items-center gap-2'],
                'attr' => ['class' => 'form-checkbox'],
            ]);

        foreach (['validFrom', 'validTo'] as $field) {
            $builder->get($field)->addModelTransformer(new CallbackTransformer(
                static fn (?\DateTimeImmutable $date): string => $date?->format('Y-m-d') ?? '',
                static function (mixed $value): ?\DateTimeImmutable {
                    if (!\is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                        return null;
                    }

                    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                    $errors = \DateTimeImmutable::getLastErrors();

                    return $date instanceof \DateTimeImmutable && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                        ? $date
                        : null;
                },
            ));
        }

        $builder->get('childrenAges')->addModelTransformer(new CallbackTransformer(
            static fn (array $ages): string => implode(',', $ages),
            static function (mixed $value): array {
                $value = trim((string) $value);
                if ($value === '') {
                    return [];
                }

                $ages = [];
                foreach (explode(',', $value) as $part) {
                    $part = trim($part);
                    if (preg_match('/^\d+$/', $part) !== 1) {
                        return [];
                    }

                    $ages[] = (int) $part;
                }

                sort($ages, SORT_NUMERIC);

                return $ages;
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HotelRate::class,
            'hotel' => null,
        ]);
        $resolver->setAllowedTypes('hotel', Hotel::class);
    }
}
