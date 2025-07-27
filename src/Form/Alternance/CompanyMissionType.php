<?php

namespace App\Form\Alternance;

use App\Entity\Alternance\CompanyMission;
use App\Entity\User\Mentor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CompanyMissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de la mission',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Titre descriptif de la mission'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Décrivez en détail la mission et ses enjeux'
                ]
            ])
            ->add('context', TextareaType::class, [
                'label' => 'Contexte entreprise',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Contexte métier et environnement de la mission'
                ]
            ])
            ->add('objectives', CollectionType::class, [
                'label' => 'Objectifs de la mission',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Objectif de la mission'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter un objectif'
                ],
                'constraints' => [
                    new Assert\Count(
                        min: 1,
                        minMessage: 'Au moins un objectif doit être défini.'
                    )
                ]
            ])
            ->add('requiredSkills', CollectionType::class, [
                'label' => 'Compétences requises',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Compétence requise'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter une compétence'
                ],
                'constraints' => [
                    new Assert\Count(
                        min: 1,
                        minMessage: 'Au moins une compétence requise doit être définie.'
                    )
                ]
            ])
            ->add('skillsToAcquire', CollectionType::class, [
                'label' => 'Compétences à acquérir',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Compétence à acquérir'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter une compétence'
                ],
                'constraints' => [
                    new Assert\Count(
                        min: 1,
                        minMessage: 'Au moins une compétence à acquérir doit être définie.'
                    )
                ]
            ])
            ->add('duration', ChoiceType::class, [
                'label' => 'Durée estimée',
                'choices' => array_combine(
                    CompanyMission::DURATION_OPTIONS,
                    CompanyMission::DURATION_OPTIONS
                ),
                'attr' => ['class' => 'form-select']
            ])
            ->add('complexity', ChoiceType::class, [
                'label' => 'Niveau de complexité',
                'choices' => CompanyMission::COMPLEXITY_LEVELS,
                'attr' => ['class' => 'form-select']
            ])
            ->add('term', ChoiceType::class, [
                'label' => 'Type de mission',
                'choices' => CompanyMission::TERMS,
                'attr' => ['class' => 'form-select']
            ])
            ->add('department', ChoiceType::class, [
                'label' => 'Service/Département',
                'choices' => CompanyMission::DEPARTMENTS,
                'attr' => ['class' => 'form-select']
            ])
            ->add('orderIndex', IntegerType::class, [
                'label' => 'Ordre dans la progression',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'placeholder' => 'Position dans la progression (auto-calculé si vide)'
                ],
                'required' => false,
                'help' => 'Laissez vide pour un calcul automatique basé sur la complexité et le type'
            ])
            ->add('prerequisites', CollectionType::class, [
                'label' => 'Prérequis pédagogiques',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Prérequis nécessaire'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'required' => false,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter un prérequis'
                ]
            ])
            ->add('evaluationCriteria', CollectionType::class, [
                'label' => 'Critères d\'évaluation',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Critère d\'évaluation'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter un critère'
                ],
                'constraints' => [
                    new Assert\Count(
                        min: 1,
                        minMessage: 'Au moins un critère d\'évaluation doit être défini.'
                    )
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CompanyMission::class,
            'attr' => [
                'novalidate' => 'novalidate'
            ]
        ]);
    }
}
