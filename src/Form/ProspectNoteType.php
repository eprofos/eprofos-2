<?php

namespace App\Form;

use App\Entity\Prospect;
use App\Entity\ProspectNote;
use App\Entity\User\Admin;
use App\Repository\AdminRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for ProspectNote entity
 * 
 * Provides form fields for creating and editing prospect notes
 * in the EPROFOS prospect management system.
 */
class ProspectNoteType extends AbstractType
{
    /**
     * Build the prospect note form
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Titre de la note ou de la tâche'
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Appel téléphonique' => 'call',
                    'Email' => 'email',
                    'Réunion' => 'meeting',
                    'Démonstration' => 'demo',
                    'Proposition' => 'proposal',
                    'Suivi' => 'follow_up',
                    'Note générale' => 'general',
                    'Tâche' => 'task',
                    'Rappel' => 'reminder'
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
                'help' => 'Type d\'interaction ou de note'
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 6,
                    'placeholder' => 'Détails de l\'interaction ou de la note...'
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'pending',
                    'Terminé' => 'completed',
                    'Annulé' => 'cancelled'
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
                'help' => 'Pour les tâches et rappels, indiquez si c\'est terminé ou en attente'
            ])
            ->add('scheduledAt', DateTimeType::class, [
                'label' => 'Planifié le',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ],
                'help' => 'Date et heure prévues pour cette tâche ou ce rappel'
            ])
            ->add('isImportant', CheckboxType::class, [
                'label' => 'Important',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Marquer cette note comme importante'
            ])
            ->add('isPrivate', CheckboxType::class, [
                'label' => 'Privé',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Note visible uniquement par son créateur'
            ]);

        // Add prospect field only if we're not in a prospect context
        if (!$options['prospect_context']) {
            $builder->add('prospect', EntityType::class, [
                'label' => 'Prospect',
                'class' => Prospect::class,
                'choice_label' => function (Prospect $prospect) {
                    return $prospect->getFullName() . ($prospect->getCompany() ? ' (' . $prospect->getCompany() . ')' : '');
                },
                'attr' => [
                    'class' => 'form-select'
                ],
                'help' => 'Prospect concerné par cette note'
            ]);
        }

        // Add createdBy field only for admins or when specified
        if ($options['show_created_by']) {
            $builder->add('createdBy', EntityType::class, [
                'label' => 'Créé par',
                'class' => Admin::class,
                'choice_label' => function (Admin $admin) {
                    return $admin->getFullName();
                },
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
                'help' => 'Administrateur qui a créé cette note'
            ]);
        }
    }

    /**
     * Configure form options
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProspectNote::class,
            'prospect_context' => false,
            'show_created_by' => false,
            'attr' => [
                'novalidate' => 'novalidate'
            ]
        ]);

        $resolver->setAllowedTypes('prospect_context', 'bool');
        $resolver->setAllowedTypes('show_created_by', 'bool');
    }
}
