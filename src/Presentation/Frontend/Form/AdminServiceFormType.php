<?php

declare(strict_types=1);

namespace App\Presentation\Frontend\Form;

use App\Infrastructure\Entity\HospitalService;
use App\Infrastructure\Entity\MedicalSpecialty;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminServiceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class)
            ->add('description', TextareaType::class)
            ->add('code', TextType::class)
            ->add('price',  NumberType::class)
            ->add('duration', ChoiceType::class, [
                'choices' => [30 => 30, 45 => 45, 60 => 60]
            ])
            ->add('mode', ChoiceType::class, [
                'label'   => 'Mod',
                'choices' => [
                    'Laborator'   => HospitalService::LAB_MODE,
                    'Ambulator'   => HospitalService::AMB_MODE,
                    'Spitalizare de zi' => HospitalService::HOSPITALIZATION_MODE,
                    'Spitalizare continuă' => HospitalService::CONTINUOUS_HOSPITALIZATION_MODE,
                ],
            ])
            ->add('color', ColorType::class, [
                'label' => 'Culoare',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ],
                'help' => 'Selectați o culoare pentru acest serviciu'
            ])
            ->add('medicalSpecialty', EntityType::class, [
                'class' => MedicalSpecialty::class,
                'choice_label' => 'name',
                'placeholder' => 'Selectează o specialitate',
            ])
            ->add('isActive', CheckboxType::class, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => HospitalService::class,
        ]);
    }
}