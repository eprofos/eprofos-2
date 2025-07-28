<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CRM\ContactRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for ContactRequest entity (Admin editing).
 *
 * Provides form fields for editing contact request status and admin notes.
 * This form is used in the admin interface for managing contact requests.
 */
class ContactRequestType extends AbstractType
{
    /**
     * Build the contact request form.
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', ChoiceType::class, [
                'label' => 'Statut de la demande',
                'choices' => [
                    'En attente' => 'pending',
                    'En cours' => 'in_progress',
                    'Terminé' => 'completed',
                    'Annulé' => 'cancelled',
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
                'help' => 'Changez le statut pour suivre l\'avancement de la demande',
            ])
            ->add('adminNotes', TextareaType::class, [
                'label' => 'Notes administratives',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 6,
                    'placeholder' => 'Ajoutez vos notes internes sur cette demande...',
                ],
                'help' => 'Ces notes ne sont visibles que par les administrateurs',
            ])
        ;
    }

    /**
     * Configure form options.
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactRequest::class,
            'attr' => [
                'novalidate' => 'novalidate',
            ],
        ]);
    }
}
