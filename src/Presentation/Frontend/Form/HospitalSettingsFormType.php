<?php

declare(strict_types=1);

namespace App\Presentation\Frontend\Form;

use App\Infrastructure\Entity\HospitalSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HospitalSettingsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('reminderEnabled', CheckboxType::class, [
                'required' => false,
            ])
            ->add('reminderSmsMessage', TextareaType::class, [
                'required' => false,
            ])
            ->add('reminderEmailMessage', TextareaType::class, [
                'required' => false,
            ])
            ->add('confirmationEnabled', CheckboxType::class, [
                'required' => false,
            ])
            ->add('confirmationSmsMessage', TextareaType::class, [
                'required' => false,
            ])
            ->add('confirmationEmailMessage', TextareaType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => HospitalSettings::class,
        ]);
    }
}
