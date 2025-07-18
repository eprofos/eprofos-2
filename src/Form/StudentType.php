<?php

namespace App\Form;

use App\Entity\Student;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;

/**
 * Student Form Type for Admin Interface
 * 
 * Form for creating and editing students in the admin interface.
 * Provides comprehensive fields for student management with validation.
 */
class StudentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;
        $isAdminCreation = $options['is_admin_creation'] ?? false;

        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'email@example.com'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'L\'email est obligatoire']),
                    new Email(['message' => 'Format d\'email invalide']),
                ],
                'help' => 'L\'adresse email servira d\'identifiant de connexion'
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Prénom de l\'étudiant'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le prénom est obligatoire']),
                    new Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le prénom doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le prénom ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom de famille de l\'étudiant'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est obligatoire']),
                    new Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '01 23 45 67 89'
                ],
                'help' => 'Numéro de téléphone pour contact (optionnel)'
            ])
            ->add('birthDate', DateType::class, [
                'label' => 'Date de naissance',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                    'max' => (new \DateTime())->format('Y-m-d'),
                ],
                'help' => 'Date de naissance (optionnel)'
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Adresse complète'
                ],
                'help' => 'Adresse postale (optionnel)'
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '75000'
                ],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ville de résidence'
                ],
            ])
            ->add('country', TextType::class, [
                'label' => 'Pays',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'France'
                ],
                'data' => 'France' // Default value
            ])
            ->add('educationLevel', TextType::class, [
                'label' => 'Niveau d\'études',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Bac, Bac+2, Bac+3, Master, etc.'
                ],
                'help' => 'Dernier niveau d\'études obtenu (optionnel)'
            ])
            ->add('profession', TextType::class, [
                'label' => 'Profession',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Profession actuelle'
                ],
                'help' => 'Profession ou poste occupé (optionnel)'
            ])
            ->add('company', TextType::class, [
                'label' => 'Entreprise',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom de l\'entreprise'
                ],
                'help' => 'Nom de l\'entreprise actuelle (optionnel)'
            ])
        ;

        // Password field (only for creation or if admin wants to change password)
        if (!$isEdit || $options['include_password_field'] ?? false) {
            $builder->add('plainPassword', PasswordType::class, [
                'label' => $isEdit ? 'Nouveau mot de passe (laisser vide pour ne pas changer)' : 'Mot de passe',
                'mapped' => false,
                'required' => !$isEdit,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => $isEdit ? 'Nouveau mot de passe...' : 'Mot de passe...',
                    'autocomplete' => 'new-password'
                ],
                'constraints' => $isEdit ? [] : [
                    new NotBlank(['message' => 'Le mot de passe est obligatoire']),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères',
                        'max' => 4096,
                    ]),
                ],
                'help' => $isEdit ? 
                    'Laisser vide pour conserver le mot de passe actuel' : 
                    'Minimum 6 caractères'
            ]);
        }

        // Admin-specific fields
        if ($isAdminCreation || $isEdit) {
            $builder
                ->add('isActive', CheckboxType::class, [
                    'label' => 'Compte actif',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-check-input',
                    ],
                    'label_attr' => [
                        'class' => 'form-check-label',
                    ],
                    'help' => 'Décocher pour désactiver le compte étudiant',
                    'data' => true // Default to active
                ])
                ->add('emailVerified', CheckboxType::class, [
                    'label' => 'Email vérifié',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-check-input',
                    ],
                    'label_attr' => [
                        'class' => 'form-check-label',
                    ],
                    'help' => 'Cocher si l\'email a été vérifié manuellement'
                ])
            ;
        }

        // Additional admin creation options
        if ($isAdminCreation) {
            $builder->add('sendWelcomeEmail', CheckboxType::class, [
                'label' => 'Envoyer un email de bienvenue',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'label_attr' => [
                    'class' => 'form-check-label',
                ],
                'help' => 'Envoyer automatiquement un email de bienvenue avec les informations de connexion',
                'data' => true
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Student::class,
            'is_edit' => false,
            'is_admin_creation' => false,
            'include_password_field' => false,
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
        $resolver->setAllowedTypes('is_admin_creation', 'bool');
        $resolver->setAllowedTypes('include_password_field', 'bool');
    }
}
