<?php

namespace App\Form\Training;

use App\Entity\Training\Session;
use App\Entity\Training\Formation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for Session entity
 */
class SessionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la session',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Session de janvier 2025'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Description optionnelle de la session'
                ]
            ])
            ->add('formation', EntityType::class, [
                'class' => Formation::class,
                'choice_label' => 'title',
                'label' => 'Formation',
                'required' => true,
                'attr' => [
                    'class' => 'form-select'
                ],
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('f')
                        ->where('f.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('f.title', 'ASC');
                }
            ])
            ->add('startDate', DateTimeType::class, [
                'label' => 'Date et heure de début',
                'required' => true,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('endDate', DateTimeType::class, [
                'label' => 'Date et heure de fin',
                'required' => true,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('registrationDeadline', DateType::class, [
                'label' => 'Date limite d\'inscription',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('location', TextType::class, [
                'label' => 'Lieu',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Paris, Lyon, En ligne'
                ]
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse complète',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Adresse complète du lieu de formation'
                ]
            ])
            ->add('maxCapacity', IntegerType::class, [
                'label' => 'Capacité maximale',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'placeholder' => 'Nombre maximum de participants'
                ]
            ])
            ->add('minCapacity', IntegerType::class, [
                'label' => 'Capacité minimale',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'placeholder' => 'Nombre minimum pour maintenir la session'
                ]
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Prix de la session',
                'required' => false,
                'currency' => 'EUR',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Laisser vide pour utiliser le prix de la formation'
                ],
                'help' => 'Si vide, le prix de la formation sera utilisé'
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'required' => true,
                'choices' => [
                    'Planifiée' => 'planned',
                    'Inscriptions ouvertes' => 'open',
                    'Confirmée' => 'confirmed',
                    'Annulée' => 'cancelled',
                    'Terminée' => 'completed'
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('instructor', TextType::class, [
                'label' => 'Formateur',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom du formateur'
                ]
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes administratives',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Notes internes pour cette session'
                ]
            ])
            
            // ============== ALTERNANCE FIELDS ==============
            ->add('isAlternanceSession', CheckboxType::class, [
                'label' => 'Session en alternance',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                    'data-controller' => 'alternance-form',
                    'data-action' => 'change->alternance-form#toggle'
                ],
                'help' => 'Cochez si cette session propose un parcours en alternance'
            ])
            ->add('alternanceType', ChoiceType::class, [
                'label' => 'Type d\'alternance',
                'required' => false,
                'choices' => [
                    'Choisir un type...' => '',
                    'Contrat d\'apprentissage' => 'apprentissage',
                    'Contrat de professionnalisation' => 'professionnalisation',
                    'Stage alterné' => 'stage_alterne'
                ],
                'attr' => [
                    'class' => 'form-select alternance-field',
                    'data-alternance-form-target' => 'field'
                ],
                'help' => 'Type de contrat d\'alternance proposé'
            ])
            ->add('minimumAlternanceDuration', IntegerType::class, [
                'label' => 'Durée minimale (en semaines)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control alternance-field',
                    'data-alternance-form-target' => 'field',
                    'min' => 1,
                    'max' => 156, // 3 ans maximum
                    'placeholder' => 'Ex: 52 pour 1 an'
                ],
                'help' => 'Durée minimale du contrat d\'alternance en semaines'
            ])
            ->add('centerPercentage', IntegerType::class, [
                'label' => 'Pourcentage temps centre (%)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control alternance-field',
                    'data-alternance-form-target' => 'field centerPercentage',
                    'data-action' => 'input->alternance-form#updateCompanyPercentage',
                    'min' => 0,
                    'max' => 100,
                    'placeholder' => 'Ex: 30'
                ],
                'help' => 'Pourcentage du temps passé en centre de formation'
            ])
            ->add('companyPercentage', IntegerType::class, [
                'label' => 'Pourcentage temps entreprise (%)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control alternance-field',
                    'data-alternance-form-target' => 'field companyPercentage',
                    'data-action' => 'input->alternance-form#updateCenterPercentage',
                    'min' => 0,
                    'max' => 100,
                    'placeholder' => 'Ex: 70'
                ],
                'help' => 'Pourcentage du temps passé en entreprise'
            ])
            ->add('alternanceRhythm', ChoiceType::class, [
                'label' => 'Rythme d\'alternance',
                'required' => false,
                'choices' => [
                    'Choisir un rythme...' => '',
                    '1 semaine centre / 1 semaine entreprise' => '1-1',
                    '2 semaines centre / 2 semaines entreprise' => '2-2',
                    '3 semaines centre / 1 semaine entreprise' => '3-1',
                    '1 semaine centre / 3 semaines entreprise' => '1-3',
                    '2 semaines centre / 3 semaines entreprise' => '2-3',
                    '3 semaines centre / 2 semaines entreprise' => '3-2',
                    '4 semaines centre / 4 semaines entreprise' => '4-4',
                    '2 jours centre / 3 jours entreprise par semaine' => '2j-3j',
                    '3 jours centre / 2 jours entreprise par semaine' => '3j-2j',
                    'Rythme personnalisé' => 'custom'
                ],
                'attr' => [
                    'class' => 'form-select alternance-field',
                    'data-alternance-form-target' => 'field'
                ],
                'help' => 'Répartition temporelle entre centre et entreprise'
            ])
            ->add('alternancePrerequisites', TextareaType::class, [
                'label' => 'Prérequis spécifiques à l\'alternance',
                'required' => false,
                'attr' => [
                    'class' => 'form-control alternance-field',
                    'data-alternance-form-target' => 'field',
                    'rows' => 3,
                    'placeholder' => 'Prérequis particuliers pour le parcours en alternance...'
                ],
                'help' => 'Conditions spécifiques requises pour suivre cette formation en alternance'
            ])
            
            ->add('isActive', CheckboxType::class, [
                'label' => 'Session active',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Session::class,
        ]);
    }
}
