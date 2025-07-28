<?php

declare(strict_types=1);

namespace App\Form\Analysis;

use App\Entity\Analysis\NeedsAnalysisRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for individual needs analysis.
 *
 * Comprehensive form for collecting individual training needs analysis
 * data to comply with Qualiopi 2.4 criteria.
 */
class IndividualNeedsAnalysisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Personal Information
            ->add('first_name', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre prénom',
                ],
            ])
            ->add('last_name', TextType::class, [
                'label' => 'Nom de famille',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre nom de famille',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'votre.email@exemple.com',
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '01 23 45 67 89',
                ],
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Adresse',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Votre adresse complète',
                ],
            ])

            // Professional Status
            ->add('status', ChoiceType::class, [
                'label' => 'Statut professionnel',
                'choices' => [
                    'Salarié(e)' => 'employee',
                    'Demandeur d\'emploi' => 'job_seeker',
                    'Travailleur indépendant' => 'freelancer',
                    'Chef d\'entreprise' => 'business_owner',
                    'Étudiant(e)' => 'student',
                    'Retraité(e)' => 'retired',
                    'Autre' => 'other',
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('status_other_details', TextType::class, [
                'label' => 'Précisions sur le statut',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Si "Autre", précisez votre statut',
                ],
                'help' => 'À remplir uniquement si vous avez sélectionné "Autre"',
            ])

            // Funding
            ->add('funding_type', ChoiceType::class, [
                'label' => 'Mode de financement',
                'choices' => [
                    'CPF (Compte Personnel de Formation)' => 'cpf',
                    'Pôle Emploi' => 'pole_emploi',
                    'Entreprise' => 'company',
                    'Financement personnel' => 'personal',
                    'OPCO' => 'opco',
                    'Région' => 'region',
                    'Autre organisme' => 'other_organization',
                    'Non défini' => 'undefined',
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
                'help' => 'Comment envisagez-vous de financer cette formation ?',
            ])
            ->add('funding_other_details', TextType::class, [
                'label' => 'Précisions sur le financement',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Précisez l\'organisme ou le mode de financement',
                ],
                'help' => 'Si nécessaire, précisez les détails du financement',
            ])

            // Training Objectives
            ->add('desired_training_title', TextType::class, [
                'label' => 'Intitulé de la formation souhaitée',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Titre de la formation que vous souhaitez suivre',
                ],
            ])
            ->add('professional_objective', TextareaType::class, [
                'label' => 'Objectif professionnel',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Décrivez votre projet professionnel et vos objectifs',
                ],
                'help' => 'Quel est votre projet professionnel ? Que souhaitez-vous accomplir grâce à cette formation ?',
            ])
            ->add('current_level', ChoiceType::class, [
                'label' => 'Niveau actuel dans le domaine',
                'choices' => [
                    'Débutant (aucune expérience)' => 'beginner',
                    'Initié (quelques notions)' => 'novice',
                    'Intermédiaire (expérience limitée)' => 'intermediate',
                    'Confirmé (bonne expérience)' => 'advanced',
                    'Expert (très expérimenté)' => 'expert',
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
                'help' => 'Évaluez votre niveau actuel dans le domaine de la formation',
            ])

            // Training Preferences
            ->add('desired_duration_hours', IntegerType::class, [
                'label' => 'Durée souhaitée (en heures)',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                ],
                'help' => 'Combien d\'heures de formation souhaitez-vous ?',
            ])
            ->add('preferred_start_date', DateType::class, [
                'label' => 'Date de début souhaitée',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
                'help' => 'Quand aimeriez-vous commencer la formation ?',
            ])
            ->add('preferred_end_date', DateType::class, [
                'label' => 'Date de fin souhaitée',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
                'help' => 'Quand aimeriez-vous terminer la formation ?',
            ])
            ->add('training_location_preference', ChoiceType::class, [
                'label' => 'Modalité de formation préférée',
                'choices' => [
                    'En présentiel chez EPROFOS' => 'at_training_center',
                    'À distance (visioconférence)' => 'remote',
                    'Mixte (présentiel + distanciel)' => 'hybrid',
                    'En entreprise' => 'at_company',
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
                'help' => 'Quelle modalité de formation préférez-vous ?',
            ])
            ->add('disability_accommodations', TextareaType::class, [
                'label' => 'Aménagements pour situation de handicap',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Décrivez les aménagements nécessaires pour l\'accessibilité',
                ],
                'help' => 'Avez-vous besoin d\'aménagements particuliers pour suivre la formation ?',
            ])

            // Training Expectations
            ->add('training_expectations', TextareaType::class, [
                'label' => 'Attentes et objectifs pédagogiques',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Décrivez ce que vous attendez de cette formation, les compétences que vous souhaitez acquérir',
                ],
                'help' => 'Quelles compétences souhaitez-vous développer ? Quels sont vos objectifs d\'apprentissage ?',
            ])
            ->add('specific_needs', TextareaType::class, [
                'label' => 'Besoins spécifiques et contraintes',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Contraintes horaires, besoins particuliers, contexte spécifique...',
                ],
                'help' => 'Avez-vous des contraintes particulières ou des besoins spécifiques à prendre en compte ?',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null, // We're not binding to an entity directly
            'needs_analysis_request' => null,
            'attr' => [
                'novalidate' => 'novalidate',
            ],
        ]);

        $resolver->setAllowedTypes('needs_analysis_request', ['null', NeedsAnalysisRequest::class]);
    }
}
