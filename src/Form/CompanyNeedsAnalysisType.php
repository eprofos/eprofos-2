<?php

declare(strict_types=1);

namespace App\Form;

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
 * Form type for company needs analysis.
 *
 * Comprehensive form for collecting company training needs analysis
 * data to comply with Qualiopi 2.4 criteria.
 */
class CompanyNeedsAnalysisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Company Information
            ->add('company_name', TextType::class, [
                'label' => 'Nom de l\'entreprise',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Raison sociale de l\'entreprise',
                ],
                'help' => 'Nom officiel de votre entreprise',
            ])
            ->add('responsible_person', TextType::class, [
                'label' => 'Responsable de la formation',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom du responsable formation',
                ],
                'help' => 'Personne en charge du suivi de la formation',
            ])
            ->add('contact_email', EmailType::class, [
                'label' => 'Email de contact',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'contact@entreprise.com',
                ],
            ])
            ->add('contact_phone', TelType::class, [
                'label' => 'Téléphone de contact',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '01 23 45 67 89',
                ],
            ])
            ->add('company_address', TextareaType::class, [
                'label' => 'Adresse de l\'entreprise',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Adresse complète de l\'entreprise',
                ],
            ])

            // Company Details
            ->add('activity_sector', TextType::class, [
                'label' => 'Secteur d\'activité',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Commerce, Industrie, Services...',
                ],
            ])
            ->add('naf_code', TextType::class, [
                'label' => 'Code NAF',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 6201Z',
                ],
                'help' => 'Code d\'activité principale (optionnel)',
            ])
            ->add('siret', TextType::class, [
                'label' => 'Numéro SIRET',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '14 chiffres',
                ],
            ])
            ->add('employee_count', IntegerType::class, [
                'label' => 'Nombre d\'employés',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                ],
                'help' => 'Effectif total de l\'entreprise',
            ])
            ->add('opco', TextType::class, [
                'label' => 'OPCO de rattachement',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: AFDAS, ATLAS, CONSTRUCTYS...',
                ],
                'help' => 'Opérateur de compétences (si connu)',
            ])

            // Training Information
            ->add('training_title', TextType::class, [
                'label' => 'Intitulé de la formation souhaitée',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Titre de la formation demandée',
                ],
            ])
            ->add('training_duration_hours', IntegerType::class, [
                'label' => 'Durée souhaitée (en heures)',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                ],
                'help' => 'Nombre d\'heures de formation souhaité',
            ])
            ->add('preferred_start_date', DateType::class, [
                'label' => 'Date de début souhaitée',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
                'help' => 'Date préférentielle pour commencer la formation',
            ])
            ->add('preferred_end_date', DateType::class, [
                'label' => 'Date de fin souhaitée',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
                'help' => 'Date préférentielle pour terminer la formation',
            ])
            ->add('training_location_preference', ChoiceType::class, [
                'label' => 'Modalité de formation préférée',
                'choices' => [
                    'En présentiel dans nos locaux' => 'on_site',
                    'En présentiel chez EPROFOS' => 'at_training_center',
                    'À distance (visioconférence)' => 'remote',
                    'Mixte (présentiel + distanciel)' => 'hybrid',
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('location_appropriation_needs', TextareaType::class, [
                'label' => 'Besoins d\'aménagement des locaux',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Décrivez les aménagements nécessaires (matériel, accessibilité, etc.)',
                ],
                'help' => 'Si formation en présentiel, précisez les besoins spécifiques',
            ])
            ->add('disability_accommodations', TextareaType::class, [
                'label' => 'Aménagements pour situation de handicap',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Décrivez les aménagements nécessaires pour l\'accessibilité',
                ],
                'help' => 'Précisez si des aménagements sont nécessaires pour l\'accessibilité',
            ])

            // Training Needs Analysis
            ->add('training_expectations', TextareaType::class, [
                'label' => 'Objectifs et attentes de la formation',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Décrivez les objectifs pédagogiques et les compétences à acquérir',
                ],
                'help' => 'Quels sont les objectifs pédagogiques visés ?',
            ])
            ->add('specific_needs', TextareaType::class, [
                'label' => 'Besoins spécifiques et contexte',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Contexte professionnel, contraintes particulières, besoins spécifiques...',
                ],
                'help' => 'Précisez le contexte et les besoins particuliers de votre entreprise',
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
