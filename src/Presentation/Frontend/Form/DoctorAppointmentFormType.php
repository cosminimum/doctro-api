<?php

declare(strict_types=1);

namespace App\Presentation\Frontend\Form;

use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Entity\MedicalSpecialty;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

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
                'format' => 'dd-MM-yyyy HH:mm',
                'widget' => 'single_text',
                'html5' => false, // Set to false to prevent native HTML5 date picker
                'attr' => [
                    'class' => 'flatpickr-datetime',
                    'placeholder' => 'Selectează dată'
                ]
            ])
            ->add('firstName', TextType::class, [
                'attr' => [
                    'placeholder' => 'Prenume pacient'
                ]
            ])
            ->add('lastName', TextType::class, [
                'attr' => [
                    'placeholder' => 'Nume pacient'
                ]
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Email (opțional)'
                ]
            ])
            ->add('phone', TelType::class, [
                'attr' => [
                    'placeholder' => 'Telefon'
                ]
            ])
            ->add('cnp', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Length([
                        'min' => 13,
                        'max' => 13,
                        'exactMessage' => 'Câmpul trebuie să aibă exact 13 caractere.'
                    ])
                ],
                'attr' => [
                    'placeholder' => 'CNP (opțional)'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
