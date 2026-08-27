<?php

namespace App\Modules\Hotel\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class HotelOfferSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('checkIn', HiddenType::class, [
                'label' => 'hotel.offer.check_in',
                'error_bubbling' => false,
                'attr' => ['data-locale-date-target' => 'value'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Enter a valid date.'),
                    new Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'Enter a valid date.'),
                ],
            ])
            ->add('checkOut', HiddenType::class, [
                'label' => 'hotel.offer.check_out',
                'error_bubbling' => false,
                'attr' => ['data-locale-date-target' => 'value'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Enter a valid date.'),
                    new Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'Enter a valid date.'),
                ],
            ])
            ->add('adults', IntegerType::class, [
                'label' => 'hotel.offer.adults',
                'attr' => ['class' => 'form-input', 'min' => 1],
                'constraints' => [new Assert\NotNull(), new Assert\GreaterThanOrEqual(1)],
            ])
            ->add('children', IntegerType::class, [
                'label' => 'hotel.offer.children',
                'attr' => [
                    'class' => 'form-input',
                    'min' => 0,
                    'data-child-ages-target' => 'count',
                    'data-action' => 'input->child-ages#sync change->child-ages#sync',
                ],
                'constraints' => [new Assert\NotNull(), new Assert\GreaterThanOrEqual(0)],
            ])
            ->add('childrenAges', TextType::class, [
                'label' => 'hotel.offer.children_ages',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => '4,8',
                    'data-child-ages-target' => 'field',
                ],
                'help' => 'hotel.offer.children_ages_help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $today = new \DateTimeImmutable('today');

        $resolver->setDefaults([
            'data' => [
                'checkIn' => $today->modify('+1 day')->format('Y-m-d'),
                'checkOut' => $today->modify('+6 days')->format('Y-m-d'),
                'adults' => 2,
                'children' => 0,
                'childrenAges' => '',
            ],
        ]);
    }
}
