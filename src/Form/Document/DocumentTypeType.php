<?php

namespace App\Form\Document;

use App\Entity\Document\DocumentType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Form type for DocumentType entity
 * 
 * Provides form fields for creating and editing document types
 * with proper validation and styling for Bootstrap 5.
 */
class DocumentTypeType extends AbstractType
{
    /**
     * Build the document type form
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code du type',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: policy_document'
                ],
                'help' => 'Code unique pour identifier le type de document (lettres minuscules, chiffres et underscores uniquement)',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le code est obligatoire.'
                    ]),
                    new Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le code doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le code ne peut pas dépasser {{ limit }} caractères.'
                    ]),
                    new Regex([
                        'pattern' => '/^[a-z0-9_]+$/',
                        'message' => 'Le code ne peut contenir que des lettres minuscules, chiffres et underscores.'
                    ])
                ]
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom du type',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Document de politique'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le nom est obligatoire.'
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
                    'placeholder' => 'Description du type de document'
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
                    'placeholder' => 'Ex: fas fa-file-alt'
                ],
                'help' => 'Classe CSS pour l\'icône (Font Awesome, Bootstrap Icons, etc.)',
                'constraints' => [
                    new Length([
                        'max' => 100,
                        'maxMessage' => 'L\'icône ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('color', TextType::class, [
                'label' => 'Couleur',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'type' => 'color',
                    'placeholder' => '#007bff'
                ],
                'help' => 'Couleur associée au type de document',
                'constraints' => [
                    new Length([
                        'max' => 50,
                        'maxMessage' => 'La couleur ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('requiresApproval', CheckboxType::class, [
                'label' => 'Nécessite une approbation',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Les documents de ce type doivent être approuvés avant publication'
            ])
            ->add('allowMultiplePublished', CheckboxType::class, [
                'label' => 'Autoriser plusieurs documents publiés',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Permet d\'avoir plusieurs documents de ce type publiés en même temps'
            ])
            ->add('hasExpiration', CheckboxType::class, [
                'label' => 'A une date d\'expiration',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Les documents de ce type ont une date d\'expiration'
            ])
            ->add('generatesPdf', CheckboxType::class, [
                'label' => 'Génère des PDF',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Les documents de ce type peuvent être générés en PDF'
            ])
            ->add('allowedStatuses', ChoiceType::class, [
                'label' => 'Statuts autorisés',
                'multiple' => true,
                'expanded' => true,
                'choices' => [
                    'Brouillon' => 'draft',
                    'En révision' => 'under_review',
                    'Publié' => 'published',
                    'Archivé' => 'archived',
                    'Expiré' => 'expired'
                ],
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Statuts que peuvent avoir les documents de ce type'
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre de tri',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0'
                ],
                'help' => 'Ordre d\'affichage (0 = automatique)',
                'constraints' => [
                    new Range([
                        'min' => 0,
                        'max' => 999,
                        'notInRangeMessage' => 'L\'ordre doit être compris entre {{ min }} et {{ max }}.'
                    ])
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Type actif',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Un type inactif ne peut pas être utilisé pour créer de nouveaux documents'
            ]);
    }

    /**
     * Configure form options
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentType::class,
            'attr' => [
                'novalidate' => 'novalidate'
            ]
        ]);
    }
}
