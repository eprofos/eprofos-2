<?php

namespace App\Form;

use App\Entity\LegalDocument;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for LegalDocument entity
 */
class LegalDocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Type de document',
                'choices' => array_flip(LegalDocument::TYPES),
                'attr' => [
                    'class' => 'form-select'
                ],
                'help' => 'Sélectionnez le type de document légal'
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Règlement intérieur stagiaires 2025'
                ]
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 15,
                    'placeholder' => 'Saisissez le contenu du document...'
                ],
                'help' => 'Le contenu peut contenir du HTML pour la mise en forme'
            ])
            ->add('version', TextType::class, [
                'label' => 'Version',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 1.0, 2.1, etc.'
                ],
                'help' => 'Numéro de version du document'
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => array_flip(LegalDocument::STATUSES),
                'attr' => [
                    'class' => 'form-select'
                ],
                'help' => 'Statut du document (brouillon, publié, archivé)'
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Document actif',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Les documents inactifs ne sont pas visibles publiquement'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LegalDocument::class,
        ]);
    }
}
