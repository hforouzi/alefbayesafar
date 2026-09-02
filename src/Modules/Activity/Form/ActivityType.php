<?php

namespace App\Modules\Activity\Form;

use App\Modules\Activity\Entity\Activity;
use App\Modules\Activity\Enum\ActivityCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActivityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cityId', HiddenType::class, ['mapped' => false, 'required' => true])
            ->add('name', TextType::class, [
                'label' => 'activity.activity.name',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('nameFa', TextType::class, [
                'label' => 'activity.activity.name_fa',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('slug', TextType::class, [
                'label' => 'activity.activity.slug',
                'attr' => ['class' => 'form-input'],
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'activity.activity.category',
                'choices' => ActivityCategory::choices(),
                'choice_value' => static fn (mixed $category): string => $category instanceof ActivityCategory ? $category->value : '',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('durationMinutes', IntegerType::class, [
                'label' => 'activity.activity.duration_minutes',
                'required' => false,
                'attr' => ['class' => 'form-input', 'min' => 0],
            ])
            ->add('meetingPointText', TextType::class, [
                'label' => 'activity.activity.meeting_point',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('latitude', TextType::class, [
                'label' => 'activity.activity.latitude',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('longitude', TextType::class, [
                'label' => 'activity.activity.longitude',
                'required' => false,
                'attr' => ['class' => 'form-input'],
            ])
            ->add('shortDescription', TextareaType::class, [
                'label' => 'activity.activity.short_description',
                'required' => false,
                'attr' => ['class' => 'form-textarea', 'rows' => 2],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'activity.activity.description',
                'required' => false,
                'attr' => ['class' => 'form-textarea', 'rows' => 5],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'activity.activity.active',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ])
            ->add('featured', CheckboxType::class, [
                'label' => 'activity.activity.featured',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ])
            ->add('publicVisible', CheckboxType::class, [
                'label' => 'activity.activity.public_visible',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
                $activity = $event->getData();
                if (!$activity instanceof Activity) {
                    return;
                }

                $event->getForm()->get('cityId')->setData($activity->getCity()?->getId());
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Activity::class,
            'validation_groups' => false,
        ]);
    }
}
