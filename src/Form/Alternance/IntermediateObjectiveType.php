<?php

declare(strict_types=1);

namespace App\Form\Alternance;

use App\DTO\Alternance\IntermediateObjectiveDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IntermediateObjectiveType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de l\'objectif',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Analyser l\'existant et définir la cible',
                ],
                'help' => 'Titre court et précis de l\'objectif à atteindre',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 2,
                    'placeholder' => 'Description optionnelle de l\'objectif',
                ],
                'help' => 'Description optionnelle pour préciser l\'objectif',
            ])
            ->add('completed', CheckboxType::class, [
                'label' => 'Objectif atteint',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                    'data-controller' => 'objective-completion',
                    'data-action' => 'change->objective-completion#toggle',
                ],
            ])
            ->add('completionDate', DateType::class, [
                'label' => 'Date d\'achèvement',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'data-objective-completion-target' => 'dateField',
                ],
                'help' => 'Date à laquelle l\'objectif a été atteint',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => IntermediateObjectiveDTO::class,
        ]);
    }
}
