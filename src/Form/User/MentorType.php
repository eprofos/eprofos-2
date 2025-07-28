<?php

declare(strict_types=1);

namespace App\Form\User;

use App\Entity\User\Mentor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class MentorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email *',
                'constraints' => [
                    new NotBlank(['message' => 'L\'email est obligatoire']),
                    new Email(['message' => 'L\'email n\'est pas valide']),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'mentor@entreprise.com',
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom *',
                'constraints' => [
                    new NotBlank(['message' => 'Le prénom est obligatoire']),
                    new Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le prénom doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le prénom ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Jean',
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom *',
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est obligatoire']),
                    new Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Dupont',
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'constraints' => [
                    new Length([
                        'max' => 20,
                        'maxMessage' => 'Le numéro de téléphone ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '06 12 34 56 78',
                ],
            ])
            ->add('position', TextType::class, [
                'label' => 'Poste *',
                'constraints' => [
                    new NotBlank(['message' => 'Le poste est obligatoire']),
                    new Length([
                        'min' => 2,
                        'max' => 150,
                        'minMessage' => 'Le poste doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le poste ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Responsable RH',
                ],
            ])
            ->add('companyName', TextType::class, [
                'label' => 'Nom de l\'entreprise *',
                'constraints' => [
                    new NotBlank(['message' => 'Le nom de l\'entreprise est obligatoire']),
                    new Length([
                        'min' => 2,
                        'max' => 200,
                        'minMessage' => 'Le nom de l\'entreprise doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le nom de l\'entreprise ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ACME Corporation',
                ],
            ])
            ->add('companySiret', TextType::class, [
                'label' => 'SIRET *',
                'constraints' => [
                    new NotBlank(['message' => 'Le SIRET est obligatoire']),
                    new Length([
                        'min' => 14,
                        'max' => 14,
                        'minMessage' => 'Le SIRET doit contenir exactement 14 chiffres',
                        'maxMessage' => 'Le SIRET doit contenir exactement 14 chiffres',
                    ]),
                    new Regex([
                        'pattern' => '/^\d{14}$/',
                        'message' => 'Le SIRET doit contenir uniquement des chiffres',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '12345678901234',
                    'maxlength' => 14,
                ],
            ])
            ->add('expertiseDomains', ChoiceType::class, [
                'label' => 'Domaines d\'expertise *',
                'choices' => Mentor::EXPERTISE_DOMAINS,
                'multiple' => true,
                'expanded' => false,
                'constraints' => [
                    new NotBlank(['message' => 'Au moins un domaine d\'expertise doit être sélectionné']),
                ],
                'attr' => [
                    'class' => 'form-select',
                    'multiple' => true,
                    'size' => 6,
                ],
                'help' => 'Maintenez Ctrl (Cmd sur Mac) pour sélectionner plusieurs domaines',
            ])
            ->add('experienceYears', IntegerType::class, [
                'label' => 'Années d\'expérience *',
                'constraints' => [
                    new NotBlank(['message' => 'L\'expérience est obligatoire']),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 60,
                    'placeholder' => '5',
                ],
            ])
            ->add('educationLevel', ChoiceType::class, [
                'label' => 'Niveau de formation *',
                'choices' => Mentor::EDUCATION_LEVELS,
                'constraints' => [
                    new NotBlank(['message' => 'Le niveau de formation est obligatoire']),
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Compte actif',
                'required' => false,
                'data' => true,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'help' => 'Décochez pour désactiver le compte du mentor',
            ])
            ->add('emailVerified', CheckboxType::class, [
                'label' => 'Email vérifié',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'help' => 'Cochez si l\'email a été vérifié manuellement',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Mentor::class,
            'attr' => ['novalidate' => 'novalidate'], // Disable HTML5 validation to use Symfony validation
        ]);
    }
}
