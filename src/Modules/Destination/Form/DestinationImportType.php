<?php

namespace App\Modules\Destination\Form;

use App\Modules\Destination\Provider\DestinationProviderRegistry;
use App\Modules\Destination\ValueObject\DestinationEntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DestinationImportType extends AbstractType
{
    public function __construct(
        private readonly DestinationProviderRegistry $providerRegistry,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $providers = [];
        foreach ($this->providerRegistry->all() as $provider) {
            $providers[$provider->getLabel()] = $provider->getCode();
        }

        $builder
            ->add('targetType', ChoiceType::class, [
                'label' => 'destination.import.target_type',
                'choices' => [
                    'destination.import.target_country' => DestinationEntityType::COUNTRY,
                    'destination.import.target_state' => DestinationEntityType::STATE,
                    'destination.import.target_city' => DestinationEntityType::CITY,
                    'destination.import.target_airport' => DestinationEntityType::AIRPORT,
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('countryName', TextType::class, [
                'label' => 'destination.import.country_name',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'destination.import.country_placeholder',
                ],
            ])
            ->add('cityName', TextType::class, [
                'label' => 'destination.import.city_name',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'destination.import.city_placeholder',
                ],
            ])
            ->add('providerCodes', ChoiceType::class, [
                'label' => 'destination.import.providers',
                'choices' => $providers,
                'multiple' => true,
                'expanded' => true,
                'attr' => ['class' => 'space-y-2'],
                'choice_attr' => static fn (): array => ['class' => 'form-checkbox'],
            ])
            ->add('refresh', CheckboxType::class, [
                'label' => 'destination.import.refresh_existing',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'messages',
            'empty_data' => [
                'targetType' => DestinationEntityType::CITY,
                'countryName' => '',
                'cityName' => '',
                'providerCodes' => ['booking', 'tripadvisor', 'wikivoyage'],
                'refresh' => false,
            ],
        ]);
    }
}
