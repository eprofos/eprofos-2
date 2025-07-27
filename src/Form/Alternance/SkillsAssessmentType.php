<?php

namespace App\Form\Alternance;

use App\Entity\Alternance\SkillsAssessment;
use App\Entity\User\Student;
use App\Entity\User\Teacher;
use App\Entity\User\Mentor;
use App\Entity\Alternance\MissionAssignment;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SkillsAssessmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('student', EntityType::class, [
                'class' => Student::class,
                'choice_label' => function(Student $student) {
                    return $student->getFirstName() . ' ' . $student->getLastName();
                },
                'label' => 'Alternant évalué',
                'attr' => ['class' => 'form-select'],
                'query_builder' => function ($repository) use ($options) {
                    $qb = $repository->createQueryBuilder('s')
                        ->where('s.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('s.lastName', 'ASC');
                    
                    // Filter by mentor's students if provided
                    if (isset($options['mentor'])) {
                        $qb->innerJoin('s.missionAssignments', 'ma')
                           ->innerJoin('ma.mission', 'm')
                           ->andWhere('m.supervisor = :mentor')
                           ->setParameter('mentor', $options['mentor']);
                    }
                    
                    return $qb;
                }
            ])
            ->add('assessmentType', ChoiceType::class, [
                'label' => 'Type d\'évaluation',
                'choices' => SkillsAssessment::ASSESSMENT_TYPES,
                'attr' => ['class' => 'form-select']
            ])
            ->add('context', ChoiceType::class, [
                'label' => 'Contexte d\'évaluation',
                'choices' => SkillsAssessment::CONTEXTS,
                'attr' => ['class' => 'form-select']
            ])
            ->add('assessmentDate', DateType::class, [
                'label' => 'Date d\'évaluation',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'data' => new \DateTime()
            ])
            ->add('centerEvaluator', EntityType::class, [
                'class' => Teacher::class,
                'choice_label' => function(Teacher $teacher) {
                    return $teacher->getFirstName() . ' ' . $teacher->getLastName();
                },
                'label' => 'Évaluateur centre de formation',
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Choisir un évaluateur'
            ])
            ->add('mentorEvaluator', EntityType::class, [
                'class' => Mentor::class,
                'choice_label' => function(Mentor $mentor) {
                    return $mentor->getFirstName() . ' ' . $mentor->getLastName() . ' (' . $mentor->getCompanyName() . ')';
                },
                'label' => 'Évaluateur entreprise',
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Choisir un évaluateur'
            ])
            ->add('skillsEvaluated', CollectionType::class, [
                'label' => 'Compétences évaluées',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Nom de la compétence'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter une compétence'
                ]
            ])
            ->add('centerScores', CollectionType::class, [
                'label' => 'Notes centre de formation',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Compétence: Note (ex: Communication: 8/10)'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'required' => false,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter une note'
                ]
            ])
            ->add('companyScores', CollectionType::class, [
                'label' => 'Notes entreprise',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Compétence: Note (ex: Autonomie: 7/10)'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'required' => false,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter une note'
                ]
            ])
            ->add('globalCompetencies', CollectionType::class, [
                'label' => 'Compétences globales',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Compétence transversale'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'required' => false,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter une compétence'
                ]
            ])
            ->add('centerComments', TextareaType::class, [
                'label' => 'Commentaires centre de formation',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Observations et recommandations du centre de formation'
                ]
            ])
            ->add('mentorComments', TextareaType::class, [
                'label' => 'Commentaires tuteur entreprise',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Observations et recommandations du tuteur'
                ]
            ])
            ->add('developmentPlan', CollectionType::class, [
                'label' => 'Plan de développement',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Action de développement recommandée'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'required' => false,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter une action'
                ]
            ])
            ->add('overallRating', ChoiceType::class, [
                'label' => 'Évaluation globale',
                'choices' => SkillsAssessment::OVERALL_RATINGS,
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Choisir une évaluation'
            ])
            ->add('relatedMission', EntityType::class, [
                'class' => MissionAssignment::class,
                'choice_label' => function(MissionAssignment $assignment) {
                    return $assignment->getMission()->getTitle() . ' (' . $assignment->getStudent()->getFirstName() . ' ' . $assignment->getStudent()->getLastName() . ')';
                },
                'label' => 'Mission liée (optionnel)',
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Choisir une mission',
                'query_builder' => function ($repository) use ($options) {
                    $qb = $repository->createQueryBuilder('ma')
                        ->innerJoin('ma.mission', 'm')
                        ->innerJoin('ma.student', 's')
                        ->orderBy('ma.startDate', 'DESC');
                    
                    // Filter by mentor if provided
                    if (isset($options['mentor'])) {
                        $qb->andWhere('m.supervisor = :mentor')
                           ->setParameter('mentor', $options['mentor']);
                    }
                    
                    return $qb;
                }
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SkillsAssessment::class,
            'mentor' => null,
            'attr' => [
                'novalidate' => 'novalidate'
            ]
        ]);
        
        $resolver->setAllowedTypes('mentor', ['null', 'App\Entity\User\Mentor']);
    }
}
