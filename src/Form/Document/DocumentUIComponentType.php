<?php

declare(strict_types=1);

namespace App\Form\Document;

use App\Entity\Document\DocumentUIComponent;
use App\Entity\Document\DocumentUITemplate;
use App\Form\DataTransformer\JsonToArrayTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Document UI Component Form Type.
 *
 * Form for creating and editing individual UI components within templates.
 */
class DocumentUIComponentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du composant',
                'help' => 'Nom descriptif du composant',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Logo entreprise',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom est obligatoire.'),
                    new Assert\Length(
                        min: 3,
                        max: 255,
                        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
            ])

            ->add('type', ChoiceType::class, [
                'label' => 'Type de composant',
                'help' => 'Type de contenu du composant',
                'choices' => DocumentUIComponent::TYPES,
                'attr' => [
                    'class' => 'form-select component-type-selector',
                    'data-target' => 'component-config',
                ],
            ])

            ->add('zone', ChoiceType::class, [
                'label' => 'Zone de placement',
                'help' => 'Zone où placer le composant dans le modèle',
                'choices' => DocumentUITemplate::ZONES,
                'attr' => ['class' => 'form-select'],
            ])

            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'help' => 'Contenu textuel du composant (supporte les variables {{variable}})',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Contenu du composant avec variables: {{nom}}, {{date}}, etc.',
                ],
            ])

            ->add('htmlContent', TextareaType::class, [
                'label' => 'Contenu HTML personnalisé',
                'help' => 'Code HTML personnalisé pour le composant (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control code-editor',
                    'rows' => 8,
                    'data-language' => 'html',
                    'placeholder' => '<div class="custom-component">{{content}}</div>',
                ],
            ])

            ->add('cssClass', TextType::class, [
                'label' => 'Classes CSS',
                'help' => 'Classes CSS à appliquer au composant',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: text-center font-bold',
                ],
            ])

            ->add('elementId', TextType::class, [
                'label' => 'ID de l\'élément',
                'help' => 'Identifiant unique HTML de l\'élément',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: header-logo',
                ],
            ])

            ->add('isActive', CheckboxType::class, [
                'label' => 'Actif',
                'help' => 'Le composant est inclus dans le rendu',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])

            ->add('isRequired', CheckboxType::class, [
                'label' => 'Obligatoire',
                'help' => 'Le composant est requis pour le modèle',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])

            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre de tri',
                'help' => 'Ordre d\'affichage dans la zone',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 999,
                ],
                'constraints' => [
                    new Assert\PositiveOrZero(message: 'L\'ordre de tri doit être positif ou zéro.'),
                ],
            ])

            // Advanced configuration fields (handled via JavaScript)
            ->add('styleConfig', HiddenType::class, [
                'attr' => ['class' => 'json-field style-config'],
                'required' => false,
            ])

            ->add('positionConfig', HiddenType::class, [
                'attr' => ['class' => 'json-field position-config'],
                'required' => false,
            ])

            ->add('dataBinding', HiddenType::class, [
                'attr' => ['class' => 'json-field data-binding'],
                'required' => false,
            ])

            ->add('conditionalDisplay', HiddenType::class, [
                'attr' => ['class' => 'json-field conditional-display'],
                'required' => false,
            ])
        ;

        // Add JSON data transformers for array fields
        $builder->get('styleConfig')->addModelTransformer(new JsonToArrayTransformer());
        $builder->get('positionConfig')->addModelTransformer(new JsonToArrayTransformer());
        $builder->get('dataBinding')->addModelTransformer(new JsonToArrayTransformer());
        $builder->get('conditionalDisplay')->addModelTransformer(new JsonToArrayTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentUIComponent::class,
            'attr' => ['class' => 'ui-component-form'],
        ]);
    }
}
