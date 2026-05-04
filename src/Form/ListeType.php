<?php

namespace App\Form;

use App\Entity\Liste;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;

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
                'label' => 'Type',
                'choices' => [
                    'ONG' => 'ong',
                    'Simple entreprise' => 'entreprise',
                    'Sponsor' => 'sponsor'
                ]
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
            ->add('filiere', ChoiceType::class, [
                'label' => 'Filière',
                'choices' => [
                    'BTP / Construction'             => 'BTP / Construction ',
                    'Bureau d\'études'                => 'Bureau d\'études',
                    'Fournisseur de biens et services' => 'Fournisseur de biens et services',
                ]
            ])
            ->add('nbEmployes', TextType::class, [
                'required' => false,
                'label' => 'Nombre d\'employés',
            ])
            ->add('cotFMTP', ChoiceType::class, [
                'label' => 'Cotisation FMFP',
                'choices' => [
                    'Oui' => 'Oui',
                    'Non' => 'Non',
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
                ],
                'placeholder' => 'Sélectionnez un statut',
            ])
            // ↑ Les champs conditionnels liés à "statutMenmbre" seront insérés ICI dynamiquement
            ->add('observation', TextType::class, [
                'required' => false,
                'label' => 'Observation',
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