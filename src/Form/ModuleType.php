<?php

namespace App\Form;

use App\Entity\Module;
use App\Entity\Formation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ModuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du module',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 255])
                ]
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug (URL)',
                'required' => true,
                'help' => 'URL friendly du module (ex: module-introduction)',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 255]),
                    new Assert\Regex([
                        'pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                        'message' => 'Le slug ne peut contenir que des lettres minuscules, des chiffres et des tirets'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'attr' => [
                    'rows' => 5
                ],
                'constraints' => [
                    new Assert\NotBlank()
                ]
            ])
            ->add('learningObjectives', CollectionType::class, [
                'label' => 'Objectifs pédagogiques',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Objectif pédagogique'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'required' => false,
                'help' => 'Objectifs concrets et mesurables (requis par Qualiopi)'
            ])
            ->add('prerequisites', TextareaType::class, [
                'label' => 'Prérequis',
                'required' => false,
                'attr' => [
                    'rows' => 3
                ],
                'help' => 'Connaissances ou compétences nécessaires avant ce module'
            ])
            ->add('durationHours', IntegerType::class, [
                'label' => 'Durée (en heures)',
                'required' => true,
                'attr' => [
                    'min' => 1,
                    'max' => 200
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Range(['min' => 1, 'max' => 200])
                ]
            ])
            ->add('orderIndex', IntegerType::class, [
                'label' => 'Ordre',
                'required' => true,
                'attr' => [
                    'min' => 1
                ],
                'help' => 'Position du module dans la formation',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\GreaterThan(0)
                ]
            ])
            ->add('evaluationMethods', TextareaType::class, [
                'label' => 'Méthodes d\'évaluation',
                'required' => false,
                'attr' => [
                    'rows' => 3
                ],
                'help' => 'Comment les acquis sont évalués (requis par Qualiopi)'
            ])
            ->add('teachingMethods', TextareaType::class, [
                'label' => 'Méthodes pédagogiques',
                'required' => false,
                'attr' => [
                    'rows' => 3
                ],
                'help' => 'Approches et techniques pédagogiques utilisées'
            ])
            ->add('resources', CollectionType::class, [
                'label' => 'Ressources et supports',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Ressource pédagogique'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'required' => false,
                'help' => 'Documents, outils et matériel pédagogique'
            ])
            ->add('successCriteria', CollectionType::class, [
                'label' => 'Critères de réussite',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Critère de réussite'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'required' => false,
                'help' => 'Indicateurs mesurables de réussite du module'
            ])
            ->add('formation', EntityType::class, [
                'class' => Formation::class,
                'choice_label' => 'title',
                'label' => 'Formation',
                'required' => true,
                'placeholder' => 'Choisir une formation',
                'constraints' => [
                    new Assert\NotBlank()
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
                'help' => 'Module visible et accessible'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Module::class,
        ]);
    }
}
