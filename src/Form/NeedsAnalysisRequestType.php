<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Training\Formation;
use App\Entity\Analysis\NeedsAnalysisRequest;
use App\Repository\FormationRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for creating and editing needs analysis requests
 * 
 * Provides form fields for all necessary information to create
 * a needs analysis request including recipient details, type selection,
 * and optional formation association.
 */
class NeedsAnalysisRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Type de demande',
                'choices' => [
                    'Entreprise' => NeedsAnalysisRequest::TYPE_COMPANY,
                    'Particulier' => NeedsAnalysisRequest::TYPE_INDIVIDUAL,
                ],
                'placeholder' => 'Sélectionnez le type de demande',
                'attr' => [
                    'class' => 'form-select',
                ],
                'help' => 'Choisissez si cette analyse concerne une entreprise ou un particulier.',
            ])
            ->add('recipientName', TextType::class, [
                'label' => 'Nom du destinataire',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom complet du destinataire',
                ],
                'help' => 'Nom de la personne qui recevra le formulaire d\'analyse.',
            ])
            ->add('recipientEmail', EmailType::class, [
                'label' => 'Email du destinataire',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'email@exemple.com',
                ],
                'help' => 'Adresse email où sera envoyé le lien du formulaire.',
            ])
            ->add('companyName', TextType::class, [
                'label' => 'Nom de l\'entreprise',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom de l\'entreprise (optionnel)',
                ],
                'help' => 'Nom de l\'entreprise concernée par la formation (si applicable).',
            ])
            ->add('formation', EntityType::class, [
                'class' => Formation::class,
                'choice_label' => 'title',
                'label' => 'Formation concernée',
                'required' => false,
                'placeholder' => 'Sélectionnez une formation (optionnel)',
                'attr' => [
                    'class' => 'form-select',
                ],
                'help' => 'Formation pour laquelle l\'analyse des besoins est demandée.',
                'query_builder' => function (FormationRepository $repository) {
                    return $repository->createQueryBuilder('f')
                        ->where('f.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('f.title', 'ASC');
                },
            ])
            ->add('adminNotes', TextareaType::class, [
                'label' => 'Notes administratives',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Notes internes pour cette demande...',
                ],
                'help' => 'Notes internes visibles uniquement par les administrateurs.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NeedsAnalysisRequest::class,
            'attr' => [
                'novalidate' => 'novalidate', // Disable HTML5 validation to use Symfony validation
            ],
            'validation_groups' => ['admin_form'], // Use specific validation group
        ]);
    }
}