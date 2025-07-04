<?php

namespace App\Form;

use App\Entity\Formation;
use App\Entity\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Form type for Formation entity
 * 
 * Provides comprehensive form fields for creating and editing formations
 * with proper validation, styling for Bootstrap 5, and Qualiopi compliance.
 */
class FormationType extends AbstractType
{
    /**
     * Build the formation form
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Basic Information
            ->add('title', TextType::class, [
                'label' => 'Titre de la formation',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Formation développement web'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le titre de la formation est obligatoire.'
                    ]),
                    new Length([
                        'min' => 5,
                        'max' => 255,
                        'minMessage' => 'Le titre doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le titre ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Description détaillée de la formation'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'La description est obligatoire.'
                    ]),
                    new Length([
                        'min' => 50,
                        'max' => 2000,
                        'minMessage' => 'La description doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'label' => 'Catégorie',
                'placeholder' => 'Sélectionnez une catégorie',
                'attr' => [
                    'class' => 'form-select'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'La catégorie est obligatoire.'
                    ])
                ]
            ])
            
            // Training Details
            ->add('objectives', TextareaType::class, [
                'label' => 'Objectifs pédagogiques',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Objectifs que les participants atteindront'
                ],
                'help' => 'Décrivez les compétences et connaissances acquises',
                'constraints' => [
                    new Length([
                        'max' => 2000,
                        'maxMessage' => 'Les objectifs ne peuvent pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('prerequisites', TextareaType::class, [
                'label' => 'Prérequis',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Connaissances ou expériences requises'
                ],
                'constraints' => [
                    new Length([
                        'max' => 1000,
                        'maxMessage' => 'Les prérequis ne peuvent pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('program', TextareaType::class, [
                'label' => 'Programme de formation',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 6,
                    'placeholder' => 'Détail du programme et des modules'
                ],
                'constraints' => [
                    new Length([
                        'max' => 5000,
                        'maxMessage' => 'Le programme ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            
            // Duration and Pricing
            ->add('durationHours', IntegerType::class, [
                'label' => 'Durée (en heures)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 35',
                    'min' => 1
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'La durée est obligatoire.'
                    ]),
                    new Positive([
                        'message' => 'La durée doit être positive.'
                    ]),
                    new Range([
                        'min' => 1,
                        'max' => 1000,
                        'notInRangeMessage' => 'La durée doit être comprise entre {{ min }} et {{ max }} heures.'
                    ])
                ]
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Prix (€)',
                'currency' => 'EUR',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0.00'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le prix est obligatoire.'
                    ]),
                    new Positive([
                        'message' => 'Le prix doit être positif.'
                    ])
                ]
            ])
            
            // Level and Format
            ->add('level', ChoiceType::class, [
                'label' => 'Niveau',
                'choices' => [
                    'Débutant' => 'Débutant',
                    'Intermédiaire' => 'Intermédiaire',
                    'Avancé' => 'Avancé',
                    'Expert' => 'Expert'
                ],
                'placeholder' => 'Sélectionnez un niveau',
                'attr' => [
                    'class' => 'form-select'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le niveau est obligatoire.'
                    ])
                ]
            ])
            ->add('format', ChoiceType::class, [
                'label' => 'Format',
                'choices' => [
                    'Présentiel' => 'Présentiel',
                    'Distanciel' => 'Distanciel',
                    'Hybride' => 'Hybride',
                    'E-learning' => 'E-learning'
                ],
                'placeholder' => 'Sélectionnez un format',
                'attr' => [
                    'class' => 'form-select'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le format est obligatoire.'
                    ])
                ]
            ])
            
            // Image Upload
            ->add('imageFile', FileType::class, [
                'label' => 'Image de la formation',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ],
                'help' => 'Formats acceptés: JPG, PNG, WebP (max 2MB)',
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp'
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPG, PNG, WebP).',
                        'maxSizeMessage' => 'L\'image ne peut pas dépasser 2MB.'
                    ])
                ]
            ])
            
            // Qualiopi Required Fields
            ->add('targetAudience', TextareaType::class, [
                'label' => 'Public cible',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Salariés, demandeurs d\'emploi, étudiants...'
                ],
                'help' => 'Requis par Qualiopi',
                'constraints' => [
                    new Length([
                        'max' => 1000,
                        'maxMessage' => 'Le public cible ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('accessModalities', TextareaType::class, [
                'label' => 'Modalités d\'accès',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Délais d\'inscription, validation des prérequis...'
                ],
                'help' => 'Requis par Qualiopi',
                'constraints' => [
                    new Length([
                        'max' => 1000,
                        'maxMessage' => 'Les modalités d\'accès ne peuvent pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('handicapAccessibility', TextareaType::class, [
                'label' => 'Accessibilité handicap',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Aménagements possibles pour les personnes en situation de handicap'
                ],
                'help' => 'Requis par Qualiopi',
                'constraints' => [
                    new Length([
                        'max' => 1000,
                        'maxMessage' => 'L\'accessibilité handicap ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('teachingMethods', TextareaType::class, [
                'label' => 'Méthodes pédagogiques',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Cours magistraux, ateliers pratiques, études de cas...'
                ],
                'help' => 'Requis par Qualiopi',
                'constraints' => [
                    new Length([
                        'max' => 1000,
                        'maxMessage' => 'Les méthodes pédagogiques ne peuvent pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('evaluationMethods', TextareaType::class, [
                'label' => 'Méthodes d\'évaluation',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'QCM, projets pratiques, évaluations continues...'
                ],
                'help' => 'Requis par Qualiopi',
                'constraints' => [
                    new Length([
                        'max' => 1000,
                        'maxMessage' => 'Les méthodes d\'évaluation ne peuvent pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('contactInfo', TextareaType::class, [
                'label' => 'Contact pédagogique',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 2,
                    'placeholder' => 'Coordonnées du référent pédagogique'
                ],
                'help' => 'Requis par Qualiopi',
                'constraints' => [
                    new Length([
                        'max' => 500,
                        'maxMessage' => 'Le contact ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('trainingLocation', TextareaType::class, [
                'label' => 'Lieu de formation',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 2,
                    'placeholder' => 'Adresse physique ou plateforme en ligne'
                ],
                'help' => 'Requis par Qualiopi',
                'constraints' => [
                    new Length([
                        'max' => 500,
                        'maxMessage' => 'Le lieu ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('fundingModalities', TextareaType::class, [
                'label' => 'Modalités de financement',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'CPF, OPCO, financement entreprise, paiement personnel...'
                ],
                'help' => 'Requis par Qualiopi',
                'constraints' => [
                    new Length([
                        'max' => 1000,
                        'maxMessage' => 'Les modalités de financement ne peuvent pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            
            // Status Fields
            ->add('isActive', CheckboxType::class, [
                'label' => 'Formation active',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Une formation inactive ne sera pas visible sur le site public'
            ])
            ->add('isFeatured', CheckboxType::class, [
                'label' => 'Formation mise en avant',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Les formations mises en avant apparaissent sur la page d\'accueil'
            ]);
    }

    /**
     * Configure form options
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Formation::class,
            'attr' => [
                'novalidate' => 'novalidate'
            ]
        ]);
    }
}