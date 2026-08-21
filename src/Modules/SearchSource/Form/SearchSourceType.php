<?php

namespace App\Modules\SearchSource\Form;

use App\Modules\Destination\Repository\CountryRepository;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\SearchSource\Enum\SearchSourceProviderType;
use App\Modules\SearchSource\Provider\TravelDataProviderRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchSourceType extends AbstractType
{
    public function __construct(
        private readonly CountryRepository $countryRepository,
        private readonly TravelDataProviderRegistry $providerRegistry,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('countryId', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'search_source.source.country',
            ])
            ->add('configJson', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'search_source.source.config',
                'attr' => ['class' => 'form-textarea font-mono text-sm', 'rows' => 8],
                'help' => 'search_source.source.config_help',
            ])
            ->add('name', TextType::class, [
                'label' => 'search_source.source.name',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('domain', TextType::class, [
                'label' => 'search_source.source.domain',
                'attr' => ['class' => 'form-input', 'placeholder' => 'example.com'],
            ])
            ->add('provider', ChoiceType::class, [
                'label' => 'search_source.source.provider',
                'choices' => $this->providerRegistry->choices(),
                'attr' => ['class' => 'form-select'],
            ])
            ->add('providerType', ChoiceType::class, [
                'label' => 'search_source.source.provider_type',
                'choices' => SearchSourceProviderType::choices(),
                'choice_value' => static fn (mixed $type): string => $type instanceof SearchSourceProviderType ? $type->value : '',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('language', TextType::class, [
                'label' => 'search_source.source.language',
                'required' => false,
                'attr' => ['class' => 'form-input', 'placeholder' => 'fa'],
            ])
            ->add('priority', IntegerType::class, [
                'label' => 'search_source.source.priority',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('capabilities', ChoiceType::class, [
                'label' => 'search_source.source.capabilities',
                'choices' => [
                    'search_source.capability.hotel' => SearchSource::CAPABILITY_HOTEL,
                    'search_source.capability.flight' => SearchSource::CAPABILITY_FLIGHT,
                    'search_source.capability.airline' => SearchSource::CAPABILITY_AIRLINE,
                    'search_source.capability.review' => SearchSource::CAPABILITY_REVIEW,
                    'search_source.capability.activity' => SearchSource::CAPABILITY_ACTIVITY,
                    'search_source.capability.tour' => SearchSource::CAPABILITY_TOUR,
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'search_source.common.enabled',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
                $source = $event->getData();
                if (!$source instanceof SearchSource) {
                    return;
                }

                $form = $event->getForm();
                $form->get('countryId')->setData($source->getCountry()?->getId());
                $form->get('configJson')->setData($source->getConfig() !== [] ? json_encode($source->getConfig(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '');
            })
            ->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
                $source = $event->getData();
                if (!$source instanceof SearchSource) {
                    return;
                }

                $form = $event->getForm();
                $countryId = $form->get('countryId')->getData();
                $country = $countryId !== null && $countryId !== '' ? $this->countryRepository->find((int) $countryId) : null;
                $source->setCountry($country);

                $configJson = trim((string) $form->get('configJson')->getData());
                if ($configJson === '') {
                    $source->setConfig([]);

                    return;
                }

                try {
                    $config = json_decode($configJson, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $form->get('configJson')->addError(new FormError('search_source.validation.config_json'));

                    return;
                }

                if (!\is_array($config) || array_is_list($config)) {
                    $form->get('configJson')->addError(new FormError('search_source.validation.config_object'));

                    return;
                }

                /** @var array<string, mixed> $config */
                $source->setConfig($config);
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SearchSource::class,
        ]);
    }
}
