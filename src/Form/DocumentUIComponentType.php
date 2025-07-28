<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Document\DocumentUIComponent;
use App\Entity\Document\DocumentUITemplate;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocumentUIComponentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du composant',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: En-tête de facture',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de composant',
                'choices' => DocumentUIComponent::TYPES,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('zone', ChoiceType::class, [
                'label' => 'Zone du document',
                'choices' => DocumentUITemplate::ZONES,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Contenu du composant (peut contenir des variables)...',
                ],
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                ],
            ])
            ->add('cssClass', TextType::class, [
                'label' => 'Classes CSS',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: text-center font-bold',
                ],
            ])
            ->add('isRequired', CheckboxType::class, [
                'label' => 'Composant requis',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                    'role' => 'switch',
                ],
            ])
            ->add('isConditional', CheckboxType::class, [
                'label' => 'Affichage conditionnel',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-check-input',
                    'role' => 'switch',
                ],
            ])
        ;

        // Add template field only if not provided in options
        if (!$options['template']) {
            $builder->add('uiTemplate', EntityType::class, [
                'class' => DocumentUITemplate::class,
                'choice_label' => 'name',
                'label' => 'Modèle UI',
                'attr' => ['class' => 'form-select'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentUIComponent::class,
            'template' => null, // Template can be passed as option
        ]);
    }
}
