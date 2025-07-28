<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Service\Service;
use App\Entity\Service\ServiceCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Form type for Service entity.
 *
 * Provides form fields for creating and editing services
 * with proper validation and styling for Bootstrap 5.
 */
class ServiceType extends AbstractType
{
    /**
     * Build the service form.
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du service',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Conseil en stratégie d\'entreprise',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le titre du service est obligatoire.',
                    ]),
                    new Length([
                        'min' => 2,
                        'max' => 255,
                        'minMessage' => 'Le titre doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le titre ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 6,
                    'placeholder' => 'Description détaillée du service',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'La description du service est obligatoire.',
                    ]),
                    new Length([
                        'min' => 10,
                        'max' => 5000,
                        'minMessage' => 'La description doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('benefits', TextareaType::class, [
                'label' => 'Avantages',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Listez les avantages de ce service (un par ligne)',
                ],
                'help' => 'Saisissez un avantage par ligne. Ces avantages seront affichés sous forme de liste.',
                'constraints' => [
                    new Length([
                        'max' => 2000,
                        'maxMessage' => 'Les avantages ne peuvent pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('icon', TextType::class, [
                'label' => 'Icône',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: fas fa-chart-line',
                ],
                'help' => 'Classe CSS de l\'icône (Font Awesome, Bootstrap Icons, etc.)',
                'constraints' => [
                    new Length([
                        'max' => 100,
                        'maxMessage' => 'L\'icône ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('image', TextType::class, [
                'label' => 'Image',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: service-conseil.jpg',
                ],
                'help' => 'Nom du fichier image (doit être uploadé dans public/images/)',
                'constraints' => [
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'Le nom de l\'image ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('serviceCategory', EntityType::class, [
                'class' => ServiceCategory::class,
                'choice_label' => 'name',
                'label' => 'Catégorie de service',
                'attr' => [
                    'class' => 'form-select',
                ],
                'placeholder' => 'Sélectionnez une catégorie',
                'constraints' => [
                    new NotBlank([
                        'message' => 'La catégorie de service est obligatoire.',
                    ]),
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Service actif',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'help' => 'Décochez pour désactiver temporairement ce service',
            ])
        ;
    }

    /**
     * Configure form options.
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Service::class,
            'attr' => [
                'novalidate' => 'novalidate',
            ],
        ]);
    }
}
