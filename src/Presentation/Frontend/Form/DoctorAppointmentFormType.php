<?php

declare(strict_types=1);

namespace App\Presentation\Frontend\Form;

use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Entity\MedicalSpecialty;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DoctorAppointmentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('specialty', EntityType::class, [
                'class' => MedicalSpecialty::class,
                'choice_label' => 'name',
                'placeholder' => 'Selectează o specialitate',
            ])
            ->add('service', EntityType::class, [
                'class' => HospitalService::class,
                'choice_label' => 'name',
                'placeholder' => 'Selectează un serviciu',
            ])
            ->add('appointmentStart', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Data și ora de start a programării',
                'minutes' => [0, 15, 30, 45],
                'attr' => [
                    'step' => 900
                ]
            ])
            ->add('firstName', TextType::class)
            ->add('lastName', TextType::class)
            ->add('email', EmailType::class)
            ->add('phone', TextType::class)
            ->add('cnp', TextType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
