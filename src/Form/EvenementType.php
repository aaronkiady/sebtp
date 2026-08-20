<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\LigneEvenement;
use App\Entity\Liste;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'événement',
                'attr' => ['class' => 'form-control custom-input'],
                'required' => true,
            ])
            ->add('periode', TextType::class, [
                'label' => 'Période',
                'attr' => ['class' => 'form-control custom-input'],
                'help' => 'Ex: 2025, 2025-2026, T1 2025...',
                'required' => false,
            ])
            ->add('date', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control custom-input'],
                'required' => false,
            ])
            ->add('typeDocument', ChoiceType::class, [
                'label' => 'Type de document',
                'choices' => [
                    'Note de débit' => 'note_debit',
                    'Facture' => 'facture',
                ],
                'attr' => ['class' => 'form-select custom-input'],
                'required' => true,
            ])
            ->add('commentaire', TextType::class, [
                'label' => 'Commentaire',
                'required' => false,
                'attr' => ['class' => 'form-control custom-input'],
            ])
            // Champ optionnel : Montant fixe (si pas de lignes)
            ->add('montantFixe', NumberType::class, [
                'label' => 'Montant fixe (MGA)',
                'required' => false,
                'attr' => ['class' => 'form-control custom-input', 'step' => '1000', 'min' => 0],
                'help' => 'Si vous ne souhaitez pas utiliser les lignes de désignation, saisissez un montant fixe',
            ])
            ->add('lignes', CollectionType::class, [
                'label' => 'Lignes de désignation',
                'entry_type' => LigneEvenementType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'required' => false,
                'attr' => [
                    'class' => 'lignes-collection',
                ],
            ])
            ->add('participants', EntityType::class, [
                'class' => Liste::class,
                'choice_label' => 'nom',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => false,  // On cache le label car on l'affiche manuellement
                'attr' => ['class' => 'participants-checkbox-list'],
                'mapped' => false,
                'query_builder' => function($repository) {
                    return $repository->createQueryBuilder('l')
                        ->where('l.statut = :statut')
                        ->setParameter('statut', 'actif')
                        ->orderBy('l.nom', 'ASC');
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
        ]);
    }
}