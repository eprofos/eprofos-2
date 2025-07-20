<?php

namespace App\Form\Training;

use App\Entity\Training\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Form type for Category entity
 * 
 * Provides form fields for creating and editing categories
 * with proper validation and styling for Bootstrap 5.
 */
class CategoryType extends AbstractType
{
    /**
     * Build the category form
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la catégorie',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Formations techniques'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le nom de la catégorie est obligatoire.'
                    ]),
                    new Length([
                        'min' => 2,
                        'max' => 255,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Description de la catégorie (optionnel)'
                ],
                'constraints' => [
                    new Length([
                        'max' => 1000,
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('icon', TextType::class, [
                'label' => 'Icône',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: fas fa-laptop-code'
                ],
                'help' => 'Classe CSS pour l\'icône (Font Awesome, Bootstrap Icons, etc.)',
                'constraints' => [
                    new Length([
                        'max' => 100,
                        'maxMessage' => 'L\'icône ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Catégorie active',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Une catégorie inactive ne sera pas visible sur le site public'
            ]);
    }

    /**
     * Configure form options
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
            'attr' => [
                'novalidate' => 'novalidate'
            ]
        ]);
    }
}