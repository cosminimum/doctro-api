<?php

declare(strict_types=1);

namespace App\Presentation\Frontend\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class AppointmentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('doctorId', IntegerType::class)
            ->add('specialtyId', IntegerType::class)
            ->add('serviceId', IntegerType::class)
            ->add('slotId', IntegerType::class)
            ->add('firstName', TextType::class)
            ->add('lastName', TextType::class)
            ->add('email', EmailType::class)
            ->add('phone', TextType::class)
            ->add('cnp', TextType::class, [
                'constraints' => [
                    new Length([
                        'min' => 13,
                        'max' => 13,
                        'exactMessage' => 'Câmpul trebuie să aibă exact 13 caractere.'
                    ])
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
