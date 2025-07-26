<?php

namespace App\Form\Alternance;

use App\Entity\Alternance\AlternanceContract;
use App\Entity\Training\Session;
use App\Entity\Training\Formation;
use App\Entity\User\Student;
use App\Entity\User\Mentor;
use App\Entity\User\Teacher;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AlternanceContractType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Basic Information
            ->add('contractNumber', TextType::class, [
                'label' => 'Numéro de contrat',
                'required' => false,
                'help' => 'Généré automatiquement si non spécifié',
                'attr' => [
                    'placeholder' => 'Ex: ALT-2025-001'
                ]
            ])
            
            // Relationships
            ->add('student', EntityType::class, [
                'class' => Student::class,
                'choice_label' => function (Student $student) {
                    return sprintf('%s %s (%s)', 
                        $student->getFirstName(), 
                        $student->getLastName(), 
                        $student->getEmail()
                    );
                },
                'label' => 'Étudiant',
                'placeholder' => '-- Sélectionner un étudiant --',
                'required' => true,
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            
            ->add('session', EntityType::class, [
                'class' => Session::class,
                'choice_label' => function (Session $session) {
                    return sprintf('%s (%s)', 
                        $session->getName(), 
                        $session->getFormation()?->getTitle() ?? 'Formation'
                    );
                },
                'label' => 'Session d\'alternance',
                'placeholder' => '-- Sélectionner une session --',
                'required' => true,
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('s')
                        ->where('s.isAlternanceSession = :alternance')
                        ->setParameter('alternance', true)
                        ->orderBy('s.startDate', 'DESC');
                },
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            
            ->add('mentor', EntityType::class, [
                'class' => Mentor::class,
                'choice_label' => function (Mentor $mentor) {
                    return sprintf('%s %s (%s)', 
                        $mentor->getFirstName(), 
                        $mentor->getLastName(), 
                        $mentor->getCompanyName()
                    );
                },
                'label' => 'Tuteur entreprise',
                'placeholder' => '-- Sélectionner un tuteur --',
                'required' => false,
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            
            ->add('teacher', EntityType::class, [
                'class' => Teacher::class,
                'choice_label' => function (Teacher $teacher) {
                    return sprintf('%s %s', 
                        $teacher->getFirstName(), 
                        $teacher->getLastName()
                    );
                },
                'label' => 'Référent pédagogique',
                'placeholder' => '-- Sélectionner un référent --',
                'required' => false,
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            
            // Company Information
            ->add('companyName', TextType::class, [
                'label' => 'Nom de l\'entreprise',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: ACME Corporation'
                ]
            ])
            
            ->add('companySiret', TextType::class, [
                'label' => 'SIRET',
                'required' => false,
                'constraints' => [
                    new Assert\Length(exactly: 14, exactMessage: 'Le SIRET doit contenir exactement 14 chiffres'),
                    new Assert\Regex('/^\d{14}$/', message: 'Le SIRET ne doit contenir que des chiffres')
                ],
                'attr' => [
                    'placeholder' => '12345678901234'
                ]
            ])
            
            ->add('companyAddress', TextareaType::class, [
                'label' => 'Adresse de l\'entreprise',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Adresse complète de l\'entreprise'
                ]
            ])
            
            ->add('companyContactPerson', TextType::class, [
                'label' => 'Personne de contact',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Nom du responsable'
                ]
            ])
            
            ->add('companyContactEmail', EmailType::class, [
                'label' => 'Email de contact',
                'required' => false,
                'attr' => [
                    'placeholder' => 'contact@entreprise.com'
                ]
            ])
            
            ->add('companyContactPhone', TextType::class, [
                'label' => 'Téléphone de contact',
                'required' => false,
                'attr' => [
                    'placeholder' => '01 23 45 67 89'
                ]
            ])
            
            // Contract Details
            ->add('contractType', ChoiceType::class, [
                'label' => 'Type de contrat',
                'choices' => [
                    'Contrat d\'apprentissage' => 'apprentissage',
                    'Contrat de professionnalisation' => 'professionnalisation',
                    'Stage alterné' => 'stage_alterné'
                ],
                'required' => true,
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'required' => true,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'required' => true,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            
            ->add('weeklyHours', IntegerType::class, [
                'label' => 'Heures hebdomadaires',
                'required' => false,
                'constraints' => [
                    new Assert\Positive(message: 'Le nombre d\'heures doit être positif'),
                    new Assert\LessThanOrEqual(35, message: 'Le nombre d\'heures ne peut pas dépasser 35h')
                ],
                'attr' => [
                    'placeholder' => '35',
                    'min' => 1,
                    'max' => 35
                ]
            ])
            
            ->add('compensation', IntegerType::class, [
                'label' => 'Rémunération (€/mois)',
                'required' => false,
                'constraints' => [
                    new Assert\PositiveOrZero(message: 'La rémunération doit être positive ou nulle')
                ],
                'attr' => [
                    'placeholder' => '800',
                    'min' => 0
                ]
            ])
            
            // Status and Management
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Brouillon' => 'draft',
                    'En attente de signature' => 'pending_signature',
                    'Signé' => 'signed',
                    'En cours' => 'active',
                    'Terminé' => 'completed',
                    'Annulé' => 'cancelled',
                    'Suspendu' => 'suspended'
                ],
                'required' => true,
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            
            // Additional Information
            ->add('objectives', TextareaType::class, [
                'label' => 'Objectifs de l\'alternance',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Décrivez les objectifs pédagogiques et professionnels...'
                ]
            ])
            
            ->add('tasks', TextareaType::class, [
                'label' => 'Missions en entreprise',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Détaillez les tâches et missions confiées...'
                ]
            ])
            
            ->add('evaluationCriteria', TextareaType::class, [
                'label' => 'Critères d\'évaluation',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Définissez les critères d\'évaluation...'
                ]
            ])
            
            ->add('notes', TextareaType::class, [
                'label' => 'Notes complémentaires',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Informations complémentaires...'
                ]
            ])
            
            // Submit button
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer le contrat',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AlternanceContract::class,
            'attr' => [
                'novalidate' => 'novalidate', // HTML5 validation disabled for custom Bootstrap validation
            ]
        ]);
    }
}
