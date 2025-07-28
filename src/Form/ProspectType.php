<?php

namespace App\Form;

use App\Entity\Training\Formation;
use App\Entity\CRM\Prospect;
use App\Entity\Service\Service;
use App\Entity\User\Admin;
use App\Repository\FormationRepository;
use App\Repository\ServiceRepository;
use App\Repository\AdminRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for Prospect entity
 * 
 * Provides form fields for creating and editing prospects
 * in the EPROFOS prospect management system.
 */
class ProspectType extends AbstractType
{
    /**
     * Build the prospect form
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Prénom du prospect'
                ]
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom du prospect'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'email@exemple.com'
                ]
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '01 23 45 67 89'
                ],
                'help' => 'Format français (01 23 45 67 89 ou +33 1 23 45 67 89)'
            ])
            ->add('company', TextType::class, [
                'label' => 'Entreprise',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom de l\'entreprise'
                ]
            ])
            ->add('position', TextType::class, [
                'label' => 'Poste',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Poste occupé'
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Lead' => 'lead',
                    'Prospect' => 'prospect',
                    'Qualifié' => 'qualified',
                    'Négociation' => 'negotiation',
                    'Client' => 'customer',
                    'Perdu' => 'lost'
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
                'help' => 'Statut actuel du prospect dans le processus commercial'
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Priorité',
                'choices' => [
                    'Faible' => 'low',
                    'Moyenne' => 'medium',
                    'Élevée' => 'high',
                    'Urgente' => 'urgent'
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('source', ChoiceType::class, [
                'label' => 'Source',
                'required' => false,
                'choices' => [
                    'Site web' => 'website',
                    'Recommandation' => 'referral',
                    'Réseaux sociaux' => 'social_media',
                    'Campagne email' => 'email_campaign',
                    'Appel téléphonique' => 'phone_call',
                    'Événement' => 'event',
                    'Publicité' => 'advertising',
                    'Autre' => 'other'
                ],
                'placeholder' => 'Sélectionner une source',
                'attr' => [
                    'class' => 'form-select'
                ],
                'help' => 'Comment ce prospect nous a-t-il trouvés ?'
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Informations supplémentaires sur le prospect...'
                ],
                'help' => 'Toute information utile concernant ce prospect'
            ])
            ->add('estimatedBudget', MoneyType::class, [
                'label' => 'Budget estimé',
                'required' => false,
                'currency' => 'EUR',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0.00'
                ],
                'help' => 'Budget estimé du prospect pour nos services'
            ])
            ->add('expectedClosureDate', DateType::class, [
                'label' => 'Date de clôture prévue',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ],
                'help' => 'Date prévue pour finaliser l\'affaire'
            ])
            ->add('nextFollowUpDate', DateType::class, [
                'label' => 'Date de prochain suivi',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ],
                'help' => 'Date prévue pour le prochain contact'
            ])
            ->add('assignedTo', EntityType::class, [
                'label' => 'Assigné à',
                'class' => Admin::class,
                'choice_label' => function (Admin $admin) {
                    return $admin->getFullName();
                },
                'placeholder' => 'Sélectionner un administrateur',
                'required' => false,
                'attr' => [
                    'class' => 'form-select'
                ],
                'query_builder' => function (AdminRepository $repo) {
                    return $repo->createQueryBuilder('a')
                        ->where('a.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('a.firstName', 'ASC')
                        ->addOrderBy('a.lastName', 'ASC');
                },
                'help' => 'Administrateur responsable de ce prospect'
            ])
            ->add('interestedFormations', EntityType::class, [
                'label' => 'Formations d\'intérêt',
                'class' => Formation::class,
                'choice_label' => 'title',
                'multiple' => true,
                'required' => false,
                'attr' => [
                    'class' => 'form-select',
                    'multiple' => true,
                    'size' => 5
                ],
                'query_builder' => function (FormationRepository $repo) {
                    return $repo->createQueryBuilder('f')
                        ->where('f.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('f.title', 'ASC');
                },
                'help' => 'Formations qui intéressent ce prospect'
            ])
            ->add('interestedServices', EntityType::class, [
                'label' => 'Services d\'intérêt',
                'class' => Service::class,
                'choice_label' => 'title',
                'multiple' => true,
                'required' => false,
                'attr' => [
                    'class' => 'form-select',
                    'multiple' => true,
                    'size' => 5
                ],
                'query_builder' => function (ServiceRepository $repo) {
                    return $repo->createQueryBuilder('s')
                        ->where('s.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('s.title', 'ASC');
                },
                'help' => 'Services qui intéressent ce prospect'
            ]);
    }

    /**
     * Configure form options
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Prospect::class,
            'attr' => [
                'novalidate' => 'novalidate'
            ]
        ]);
    }
}
