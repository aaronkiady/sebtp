<?php

namespace App\Form;

use App\Entity\Liste;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
            ])
            ->add('numero', TextType::class, [
                'label' => 'Téléphone',
            ])
            ->add('email', TextType::class, [
                'label' => 'Adresse email',
            ])
            ->add('siteweb', TextType::class, [
                'label' => 'Site Web',
            ])
            ->add('activite', TextType::class, [
                'label' => 'Domaine d’activité',
            ])
            ->add('filiere', ChoiceType::class, [
                'label' => 'Filière',
                'choices' => [
                    'BTP / Construction ' => 'BTP / Construction ',
                    'Bureau d’études' => 'Bureau d’études',
                    'Fournisseur de biens et services' => 'Fournisseur de biens et services',
                ]
            ])
            ->add('nbEmployes', TextType::class, [
                'label' => 'Nombre d’employés',
            ])
            ->add('cotFMTP', ChoiceType::class, [
                'label' => 'Cotisation FMTP',
                'choices' => [
                    'Oui' => 'Oui',
                    'Non' => 'Non',
                ]
            ])
            ->add('dg', TextType::class, [
                'label' => 'Directeur général',
            ])
            ->add('adresseDg', TextType::class, [
                'label' => 'Adresse du DG',
            ])
            ->add('telephoneDg', TextType::class, [
                'label' => 'Téléphone du DG',
            ])
            ->add('statutMenmbre', ChoiceType::class, [
                'label' => 'Statut du membre',
                'choices' => [
                    'Simple Membre' => 'simple',
                    'Membre du Bureau' => 'bureau',
                ],
                'placeholder' => 'Sélectionnez un statut',
            ]);

        // Fonction pour ajouter/supprimer les champs selon le statut (satria refa membre du bureau zay vo mipotra reo option reo)
        $formModifier = function (FormInterface $form, ?string $statut) {
            if ($statut === 'bureau') {
                $form->add('fonctionSEBTP', TextType::class, [
                    'label' => 'Fonction au sein du SEBTP',
                    'required' => true,
                    'attr' => ['placeholder' => 'ex: Président, Secrétaire...']
                ]);
                $form->add('mandat', TextType::class, [
                    'label' => 'Mandat',
                    'required' => true,
                    'attr' => ['placeholder' => 'ex: 2024-2026']
                ]);
            } else {
                $form->remove('fonctionSEBTP');
                $form->remove('mandat');
            }
        };

        // 1. Écouteur pour l'affichage initial (Edition ou données pré-remplies)
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($formModifier) {
            $data = $event->getData();
            // On récupère le statut depuis l'objet Liste s'il existe
            $statut = $data ? $data->getStatutMenmbre() : null;
            $formModifier($event->getForm(), $statut);
        });

        // 2. Écouteur pour les changements (POST_SUBMIT sur le champ statutMenmbre)
        $builder->get('statutMenmbre')->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($formModifier) {
            $statut = $event->getForm()->getData();
            $formModifier($event->getForm()->getParent(), $statut);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Liste::class,
        ]);
    }
}