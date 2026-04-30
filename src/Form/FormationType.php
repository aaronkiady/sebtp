<?php

namespace App\Form;

use App\Entity\Formation;
use App\Entity\Liste;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la formation',
                'attr' => ['class' => 'form-control']
            ])
            ->add('reference', TextType::class, [
                'label' => 'Référence',
                'attr' => ['class' => 'form-control'],
                'required' => false,
            ])
            ->add('remarque', TextType::class, [
                'label' => 'Remarque',
                'attr' => ['class' => 'form-control'],
                'required' => false,
            ])
            ->add('type', TextType::class, [
                'label' => 'Type de formation',
                'attr' => ['class' => 'form-control']
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('organisateur', TextType::class, [
                'label' => 'Organisateur',
                'attr' => ['class' => 'form-control']
            ])
            ->add('participants', EntityType::class, [
                'class' => Liste::class,
                'choice_label' => 'nom',
                'multiple' => true,
                'expanded' => false,
                'label' => 'Sélectionner les participants',
                'attr' => ['class' => 'form-select', 'data-participants-select' => 'true']
            ])
            ->add('participantsDetails', CollectionType::class, [
                'label' => 'Détails des participants',
                'entry_type' => TextType::class,
                'entry_options' => [
                    'attr' => ['class' => 'form-control participant-detail-input', 'placeholder' => 'Noms des agents / représentants...']
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'mapped' => false,
                'required' => false,
                'label' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Formation::class,
        ]);
    }
}