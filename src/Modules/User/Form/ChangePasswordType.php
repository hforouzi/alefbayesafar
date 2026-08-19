<?php

namespace App\Modules\User\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Aktuelles Passwort',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Bitte geben Sie Ihr aktuelles Passwort ein',
                    ]),
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Die Passwörter stimmen nicht überein',
                'options' => ['attr' => ['class' => 'password-field']],
                'required' => true,
                'first_options'  => ['label' => 'Neues Passwort'],
                'second_options' => ['label' => 'Neues Passwort wiederholen'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Bitte geben Sie ein Passwort ein',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Das Passwort muss mindestens {{ limit }} Zeichen lang sein',
                        'max' => 4096,
                    ]),
                ],
            ])
        ;
    }
    
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
