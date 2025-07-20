<?php

namespace App\Form;

use App\Entity\Document\DocumentType;
use App\Entity\Document\DocumentUITemplate;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocumentUITemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du modèle',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Modèle facture standard'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Description du modèle UI...'
                ]
            ])
            ->add('icon', TextType::class, [
                'label' => 'Icône (classe CSS)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: fas fa-file-invoice'
                ]
            ])
            ->add('documentType', EntityType::class, [
                'class' => DocumentType::class,
                'choice_label' => 'name',
                'label' => 'Type de document',
                'required' => false,
                'placeholder' => 'Sélectionnez un type de document',
                'attr' => ['class' => 'form-select']
            ])
            ->add('isGlobal', CheckboxType::class, [
                'label' => 'Modèle global (tous types)',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                    'role' => 'switch'
                ]
            ])
            ->add('paperSize', ChoiceType::class, [
                'label' => 'Format papier',
                'choices' => [
                    'A4' => 'A4',
                    'A3' => 'A3',
                    'Letter' => 'Letter',
                    'Legal' => 'Legal'
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('orientation', ChoiceType::class, [
                'label' => 'Orientation',
                'choices' => [
                    'Portrait' => 'portrait',
                    'Paysage' => 'landscape'
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('color', ColorType::class, [
                'label' => 'Couleur principale',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'type' => 'color'
                ]
            ])
            ->add('marginTop', NumberType::class, [
                'label' => 'Marge haut (mm)',
                'scale' => 1,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'step' => 0.1
                ]
            ])
            ->add('marginRight', NumberType::class, [
                'label' => 'Marge droite (mm)',
                'scale' => 1,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'step' => 0.1
                ]
            ])
            ->add('marginBottom', NumberType::class, [
                'label' => 'Marge bas (mm)',
                'scale' => 1,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'step' => 0.1
                ]
            ])
            ->add('marginLeft', NumberType::class, [
                'label' => 'Marge gauche (mm)',
                'scale' => 1,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'step' => 0.1
                ]
            ])
            ->add('customCss', TextareaType::class, [
                'label' => 'CSS personnalisé',
                'required' => false,
                'attr' => [
                    'class' => 'form-control font-monospace',
                    'rows' => 8,
                    'placeholder' => '/* CSS personnalisé pour ce modèle */\n.document-header {\n    background-color: #f8f9fa;\n    padding: 20px;\n}\n\n.document-title {\n    font-size: 24px;\n    font-weight: bold;\n}'
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Modèle actif',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                    'role' => 'switch'
                ]
            ])
            ->add('isDefault', CheckboxType::class, [
                'label' => 'Modèle par défaut',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                    'role' => 'switch'
                ]
            ])
            ->add('showPageNumbers', CheckboxType::class, [
                'label' => 'Afficher les numéros de page',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                    'role' => 'switch'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentUITemplate::class,
        ]);
    }
}
