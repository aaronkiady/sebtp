<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\Liste;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
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
                'attr' => ['class' => 'custom-input', 'placeholder' => 'Ex: Séminaire annuel']
            ])
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'Date',
                'attr' => ['class' => 'custom-input']
            ])
            ->add('montant', NumberType::class, [
                'required' => false,
                'label' => 'Montant unitaire (MGA)',
                'attr' => ['class' => 'custom-input', 'placeholder' => '0']
            ])
            ->add('commentaire', TextareaType::class, [
                'required' => false,
                'label' => 'Commentaire',
                'attr' => ['class' => 'custom-input', 'rows' => 3]
            ])
            ->add('participantsTemp', EntityType::class, [
                'class' => Liste::class,
                'choice_label' => 'nom',
                'multiple' => true,
                'expanded' => true,
                'mapped' => false,
                'required' => false,
                'label' => 'Participants',
                'attr' => ['class' => 'participants-checkbox-list']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
        ]);
    }
}