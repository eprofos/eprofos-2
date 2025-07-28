<?php

declare(strict_types=1);

namespace App\Form\Alternance;

use App\Entity\Alternance\AlternanceProgram;
use App\Entity\Training\Session;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AlternanceProgramType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Relationship with Session
            ->add('session', EntityType::class, [
                'class' => Session::class,
                'choice_label' => static fn (Session $session) => sprintf(
                    '%s (%s)',
                    $session->getName(),
                    $session->getFormation()?->getTitle() ?? 'Formation',
                ),
                'label' => 'Session d\'alternance',
                'placeholder' => '-- Sélectionner une session --',
                'required' => true,
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('s')
                    ->where('s.isAlternanceSession = :alternance')
                    ->setParameter('alternance', true)
                    ->orderBy('s.startDate', 'DESC'),
                'attr' => [
                    'class' => 'form-select',
                ],
            ])

            // Program Details
            ->add('title', TextType::class, [
                'label' => 'Titre du programme',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: Programme Développeur Web en Alternance',
                ],
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description du programme',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Décrivez les objectifs et le contenu global du programme...',
                ],
            ])

            // Duration Management
            ->add('totalDurationWeeks', IntegerType::class, [
                'label' => 'Durée totale (semaines)',
                'required' => true,
                'constraints' => [
                    new Assert\Positive(message: 'La durée doit être positive'),
                    new Assert\LessThanOrEqual(104, message: 'La durée ne peut pas dépasser 104 semaines (2 ans)'),
                ],
                'attr' => [
                    'placeholder' => '52',
                    'min' => 1,
                    'max' => 104,
                ],
            ])

            ->add('centerDurationWeeks', IntegerType::class, [
                'label' => 'Durée en centre (semaines)',
                'required' => true,
                'constraints' => [
                    new Assert\Positive(message: 'La durée doit être positive'),
                ],
                'attr' => [
                    'placeholder' => '20',
                    'min' => 1,
                ],
            ])

            ->add('companyDurationWeeks', IntegerType::class, [
                'label' => 'Durée en entreprise (semaines)',
                'required' => true,
                'constraints' => [
                    new Assert\Positive(message: 'La durée doit être positive'),
                ],
                'attr' => [
                    'placeholder' => '32',
                    'min' => 1,
                ],
            ])

            // Learning Modules
            ->add('modules', CollectionType::class, [
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Ex: Module 1: Fondamentaux du développement',
                    ],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => 'Modules pédagogiques',
                'required' => false,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-prototype-name-value' => '__modules_name__',
                ],
            ])

            // Skills and Competencies
            ->add('skills', CollectionType::class, [
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Ex: Maîtrise du langage PHP',
                    ],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => 'Compétences visées',
                'required' => false,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-prototype-name-value' => '__skills_name__',
                ],
            ])

            // Coordination and Follow-up
            ->add('coordinationPoints', CollectionType::class, [
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Ex: Réunion de coordination mensuelle',
                    ],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => 'Points de coordination',
                'required' => false,
                'help' => 'Moments clés de coordination entre centre et entreprise',
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-prototype-name-value' => '__coordination_name__',
                ],
            ])

            // Assessment and Evaluation
            ->add('assessmentMethods', TextareaType::class, [
                'label' => 'Méthodes d\'évaluation',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Décrivez les méthodes d\'évaluation des compétences...',
                ],
            ])

            ->add('assessmentPeriods', CollectionType::class, [
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Ex: Évaluation trimestrielle',
                    ],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => 'Périodes d\'évaluation',
                'required' => false,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-prototype-name-value' => '__assessment_name__',
                ],
            ])

            // Success Criteria
            ->add('successCriteria', TextareaType::class, [
                'label' => 'Critères de réussite',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Définissez les critères de validation du programme...',
                ],
            ])

            // Professional Integration
            ->add('professionalObjectives', TextareaType::class, [
                'label' => 'Objectifs professionnels',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Décrivez les objectifs d\'insertion professionnelle...',
                ],
            ])

            // Additional Information
            ->add('progressTracking', TextareaType::class, [
                'label' => 'Suivi de progression',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Modalités de suivi de la progression de l\'alternant...',
                ],
            ])

            ->add('resources', TextareaType::class, [
                'label' => 'Ressources pédagogiques',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Listez les ressources et outils pédagogiques utilisés...',
                ],
            ])

            ->add('notes', TextareaType::class, [
                'label' => 'Notes complémentaires',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Informations complémentaires sur le programme...',
                ],
            ])

            // Submit button
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer le programme',
                'attr' => [
                    'class' => 'btn btn-primary',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AlternanceProgram::class,
            'attr' => [
                'novalidate' => 'novalidate', // HTML5 validation disabled for custom Bootstrap validation
            ],
        ]);
    }
}
