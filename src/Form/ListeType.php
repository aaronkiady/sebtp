<?php

namespace App\Form;

use App\Entity\Liste;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class ListeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Dénomination Sociale',
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
            ])
            ->add('numero', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
            ])
            ->add('email', TextType::class, [
                'label' => 'Adresse email',
                'required' => false,
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Adhéré'             => 'actif',
                    'Inactif'            => 'inactif',
                    'Radié'              => 'radie',
                    'Demande d\'adhésion' => 'demande',
                ]
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    'ONG' => 'ong',
                    'Entreprise' => 'entreprise',
                    'Sponsor' => 'sponsor'
                ],
                'required' => false,
            ])
            // ↑ Les champs conditionnels liés à "statut" seront insérés ICI dynamiquement
            ->add('siteWeb', TextType::class, [
                'label' => 'Site Web',
                'required' => false,
            ])
            ->add('activite', TextType::class, [
                'label' => 'Domaine d\'activité',
                'required' => false,
            ])
            ->add('nif', TextType::class, [
                'label' => 'NIF',
                'required' => false,
            ])
            ->add('stat', TextType::class, [
                'label' => 'STAT',
                'required' => false,
            ])
            ->add('cnaps', TextType::class, [
                'label' => 'CNAPS',
                'required' => false,
            ])
            ->add('referentSebtp', TextType::class, [
                'label' => 'Référent SEBTP',
                'required' => false,
            ])
            ->add('numRef', TextType::class, [
                'label' => 'Numéro référent',
                'required' => false,
            ])
            ->add('mailRef', TextType::class, [
                'label' => 'Mail référent',
                'required' => false,
            ])
            ->add('filiere', ChoiceType::class, [
                'label' => 'Filière(s)',
                'choices' => [
                    'BTP / Construction' => 'BTP / Construction',
                    'Bureau d\'études' => 'Bureau d_etudes',
                    'Fournisseur de biens et services' => 'Fournisseur de biens et services',
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->add('nbEmployes', TextType::class, [
                'required' => false,
                'label' => 'Nombre d\'employés',
            ])
            ->add('cotFMTP', ChoiceType::class, [
                'label' => 'Cotisation FMFP',
                'required' => false,
                'choices' => [
                    'Oui' => 'Oui',
                    'Non' => 'Non',
                ]
            ])
            ->add('fichiers', FileType::class, [
                'label' => 'Document (PDF, DOC, DOCX)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => '.pdf,.doc,.docx',
                    'class' => 'form-control'
                ]
            ])
            ->add('dg', TextType::class, [
                'required' => false,
                'label' => 'Directeur général',
            ])
            ->add('adresseDg', TextType::class, [
                'required' => false,
                'label' => 'Adresse du DG',
            ])
            ->add('telephoneDg', TextType::class, [
                'required' => false,
                'label' => 'Téléphone du DG',
            ])
            ->add('statutMenmbre', ChoiceType::class, [
                'required' => false,
                'label' => 'Statut du membre',
                'choices' => [
                    'Simple Membre'   => 'simple',
                    'Membre du Bureau' => 'bureau',
                    'Demande d\'adhésion' => 'demande',
                ],
                'placeholder' => 'Sélectionnez un statut',
            ])
            // ↑ Les champs conditionnels liés à "statutMenmbre" seront insérés ICI dynamiquement
            ->add('observation', TextType::class, [
                'required' => false,
                'label' => 'Observation',
            ])
            ->add('contacts', CollectionType::class, [
                'label' => 'Contacts',
                'entry_type' => ContactType::class,
                'entry_options' => [
                    'label' => false,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'required' => false,
                'attr' => [
                    'class' => 'contacts-collection',
                ],
            ]);


        // --- Modifier : champs conditionnels selon statutMenmbre ---
        $formModifierMembre = function (FormInterface $form, ?string $statutMenmbre) {
            if ($statutMenmbre === 'bureau') {
                $form->add('fonctionSEBTP', TextType::class, [
                    'label'    => 'Fonction au sein du SEBTP',
                    'required' => true,
                    'attr'     => ['placeholder' => 'ex: Président, Secrétaire...']
                ]);
                $form->add('mandat', TextType::class, [
                    'label'    => 'Mandat',
                    'required' => true,
                    'attr'     => ['placeholder' => 'ex: 2024-2026']
                ]);
            } else {
                $form->remove('fonctionSEBTP');
                $form->remove('mandat');
            }
        };

        // --- Modifier : champs conditionnels selon statut principal ---
        $formModifierStatut = function (FormInterface $form, ?string $statut) {
            // Statut "radié" → raisonDepart
            if ($statut === 'radie') {
                $form->add('raisonDepart', TextareaType::class, [
                    'required' => true,
                    'label'    => 'Raison du départ',
                    'attr'     => ['rows' => 3, 'placeholder' => 'Expliquez la raison du départ...']
                ]);
            } else {
                $form->remove('raisonDepart');
            }

            // Statut "demande" → statutDemande
            if ($statut === 'demande') {
                $form->add('statutDemande', ChoiceType::class, [
                    'required' => true,
                    'label'    => 'Statut de la demande',
                    'choices'  => [
                        'En attente de validation par le bureau' => 'attente_bureau',
                        'En attente de validation par l\'AG'     => 'attente_ag',
                        'Adhéré'                                 => 'adhere',
                        'Rejeté'                                 => 'rejete',
                    ],
                    'placeholder' => 'Sélectionnez un statut',
                ]);
            } else {
                $form->remove('statutDemande');
            }

            // Statut "actif" → validationBureau + validationAG
            if ($statut === 'actif') {
                $form->add('validationBureau', DateType::class, [
                    'required' => false,
                    'label'    => 'Date de validation par le bureau',
                    'widget'   => 'single_text',
                    'attr'     => ['class' => 'form-control'],
                ]);
                $form->add('validationAG', DateType::class, [
                    'required' => false,
                    'label'    => 'Date de validation par l\'AG',
                    'widget'   => 'single_text',
                    'attr'     => ['class' => 'form-control'],
                ]);
            } else {
                $form->remove('validationBureau');
                $form->remove('validationAG');
            }
        };

        // --- PRE_SET_DATA : état initial au chargement ---
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($formModifierMembre, $formModifierStatut) {
                $data = $event->getData();
                $formModifierMembre($event->getForm(), $data?->getStatutMenmbre());
                $formModifierStatut($event->getForm(), $data?->getStatut());
            }
        );

        // --- POST_SUBMIT sur statutMenmbre : mise à jour dynamique ---
        $builder->get('statutMenmbre')->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($formModifierMembre) {
                $formModifierMembre($event->getForm()->getParent(), $event->getForm()->getData());
            }
        );

        // --- POST_SUBMIT sur statut : mise à jour dynamique ---
        $builder->get('statut')->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($formModifierStatut) {
                $formModifierStatut($event->getForm()->getParent(), $event->getForm()->getData());
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Liste::class,
        ]);
    }
}