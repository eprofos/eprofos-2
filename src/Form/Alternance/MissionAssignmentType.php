<?php

declare(strict_types=1);

namespace App\Form\Alternance;

use App\Entity\Alternance\CompanyMission;
use App\Entity\Alternance\MissionAssignment;
use App\Entity\User\Mentor;
use App\Entity\User\Student;
use App\Form\DataTransformer\IntermediateObjectiveTransformer;
use DateTime;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MissionAssignmentType extends AbstractType
{
    public function __construct(
        private IntermediateObjectiveTransformer $objectiveTransformer,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mission', EntityType::class, [
                'class' => CompanyMission::class,
                'choice_label' => 'title',
                'label' => 'Mission à assigner',
                'attr' => ['class' => 'form-select'],
                'query_builder' => static function ($repository) use ($options) {
                    $qb = $repository->createQueryBuilder('m')
                        ->where('m.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('m.term', 'ASC')
                        ->addOrderBy('m.complexity', 'ASC')
                        ->addOrderBy('m.orderIndex', 'ASC')
                    ;

                    // Filter by mentor if provided
                    if (isset($options['mentor'])) {
                        $qb->andWhere('m.supervisor = :mentor')
                            ->setParameter('mentor', $options['mentor'])
                        ;
                    }

                    return $qb;
                },
                'group_by' => static fn (CompanyMission $mission) => $mission->getTermLabel() . ' - ' . $mission->getComplexityLabel(),
            ])
            ->add('student', EntityType::class, [
                'class' => Student::class,
                'choice_label' => static fn (Student $student) => $student->getFirstName() . ' ' . $student->getLastName(),
                'label' => 'Alternant',
                'attr' => ['class' => 'form-select'],
                'query_builder' => static function ($repository) use ($options) {
                    $qb = $repository->createQueryBuilder('s')
                        ->where('s.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('s.lastName', 'ASC')
                        ->addOrderBy('s.firstName', 'ASC')
                    ;

                    // Filter by mentor's students if provided
                    if (isset($options['mentor'])) {
                        $qb->innerJoin('s.missionAssignments', 'ma')
                            ->innerJoin('ma.mission', 'm')
                            ->andWhere('m.supervisor = :mentor')
                            ->setParameter('mentor', $options['mentor'])
                        ;
                    }

                    return $qb;
                },
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'min' => (new DateTime())->format('Y-m-d'),
                ],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin prévue',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Planifiée' => 'planifiee',
                    'En cours' => 'en_cours',
                    'Terminée' => 'terminee',
                    'Suspendue' => 'suspendue',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('intermediateObjectivesForForm', CollectionType::class, [
                'label' => 'Objectifs intermédiaires',
                'entry_type' => IntermediateObjectiveType::class,
                'entry_options' => [
                    'label' => false,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'required' => false,
                'by_reference' => false,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter un objectif',
                    'data-collection-item-class' => 'objective-item',
                ],
                'help' => 'Si vide, les objectifs seront générés automatiquement à partir de la mission',
            ])
        ;

        $builder
            ->add('completionRate', NumberType::class, [
                'label' => 'Taux d\'avancement (%)',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 100,
                    'step' => 0.1,
                ],
                'required' => false,
                'help' => 'Entre 0 et 100%',
            ])
            ->add('difficulties', CollectionType::class, [
                'label' => 'Difficultés rencontrées',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Difficulté identifiée',
                    ],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'required' => false,
                'empty_data' => null,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter une difficulté',
                ],
            ])
            ->add('achievements', CollectionType::class, [
                'label' => 'Réalisations concrètes',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Réalisation accomplie',
                    ],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'required' => false,
                'empty_data' => null,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter une réalisation',
                ],
            ])
            ->add('mentorFeedback', TextareaType::class, [
                'label' => 'Retour du mentor',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Observations et conseils du mentor',
                ],
            ])
            ->add('studentFeedback', TextareaType::class, [
                'label' => 'Retour de l\'alternant',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Ressenti et commentaires de l\'alternant',
                ],
            ])
            ->add('mentorRating', ChoiceType::class, [
                'label' => 'Note du mentor (1-10)',
                'choices' => array_combine(range(1, 10), range(1, 10)),
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Choisir une note',
            ])
            ->add('studentSatisfaction', ChoiceType::class, [
                'label' => 'Satisfaction de l\'alternant (1-10)',
                'choices' => array_combine(range(1, 10), range(1, 10)),
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Choisir une note',
            ])
            ->add('competenciesAcquired', CollectionType::class, [
                'label' => 'Compétences acquises',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Compétence acquise',
                    ],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'required' => false,
                'empty_data' => null,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter une compétence',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MissionAssignment::class,
            'mentor' => null,
            'attr' => [
                'novalidate' => 'novalidate',
            ],
        ]);

        $resolver->setAllowedTypes('mentor', ['null', Mentor::class]);
    }
}
