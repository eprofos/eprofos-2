<?php

namespace App\Form\Alternance;

use App\Entity\Alternance\CoordinationMeeting;
use App\Entity\User\Student;
use App\Entity\User\Teacher;
use App\Entity\User\Mentor;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CoordinationMeetingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('student', EntityType::class, [
                'class' => Student::class,
                'choice_label' => function(Student $student) {
                    return $student->getFirstName() . ' ' . $student->getLastName();
                },
                'label' => 'Alternant concerné',
                'attr' => ['class' => 'form-select']
            ])
            ->add('pedagogicalSupervisor', EntityType::class, [
                'class' => Teacher::class,
                'choice_label' => function(Teacher $teacher) {
                    return $teacher->getFirstName() . ' ' . $teacher->getLastName();
                },
                'label' => 'Référent pédagogique',
                'attr' => ['class' => 'form-select']
            ])
            ->add('mentor', EntityType::class, [
                'class' => Mentor::class,
                'choice_label' => function(Mentor $mentor) {
                    return $mentor->getFirstName() . ' ' . $mentor->getLastName() . ' (' . $mentor->getCompanyName() . ')';
                },
                'label' => 'Tuteur entreprise',
                'attr' => ['class' => 'form-select']
            ])
            ->add('date', DateTimeType::class, [
                'label' => 'Date et heure de la réunion',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de réunion',
                'choices' => CoordinationMeeting::TYPE_LABELS,
                'attr' => ['class' => 'form-select']
            ])
            ->add('location', ChoiceType::class, [
                'label' => 'Lieu de la réunion',
                'choices' => CoordinationMeeting::LOCATION_LABELS,
                'attr' => ['class' => 'form-select']
            ])
            ->add('agenda', CollectionType::class, [
                'label' => 'Ordre du jour',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Point à l\'ordre du jour'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter un point'
                ]
            ])
            ->add('discussionPoints', CollectionType::class, [
                'label' => 'Points abordés',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Point discuté'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'required' => false,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter un point'
                ]
            ])
            ->add('decisions', CollectionType::class, [
                'label' => 'Décisions prises',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Décision prise'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'required' => false,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter une décision'
                ]
            ])
            ->add('actionPlan', CollectionType::class, [
                'label' => 'Plan d\'actions',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Action à réaliser'
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
            ->add('nextMeetingDate', DateTimeType::class, [
                'label' => 'Prochaine réunion (optionnel)',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('meetingReport', TextareaType::class, [
                'label' => 'Compte-rendu',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 6,
                    'placeholder' => 'Synthèse de la réunion et conclusions'
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => CoordinationMeeting::STATUS_LABELS,
                'attr' => ['class' => 'form-select']
            ])
            ->add('attendees', CollectionType::class, [
                'label' => 'Participants effectifs',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => [
                        'class' => 'form-control mb-2',
                        'placeholder' => 'Nom du participant'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'required' => false,
                'attr' => [
                    'data-controller' => 'collection',
                    'data-collection-add-label' => 'Ajouter un participant'
                ]
            ])
            ->add('duration', IntegerType::class, [
                'label' => 'Durée réelle (minutes)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 15,
                    'max' => 480,
                    'placeholder' => 'Durée en minutes'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CoordinationMeeting::class,
            'attr' => [
                'novalidate' => 'novalidate'
            ]
        ]);
    }
}
