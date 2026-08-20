<?php

namespace App\Form;

use App\Entity\LigneEvenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LigneEvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('designation', TextType::class, [
                'label' => 'Désignation',
                'attr' => ['class' => 'form-control form-control-sm'],
                'required' => false,
            ])
            ->add('montantUnitaire', NumberType::class, [
                'label' => 'Montant unitaire (MGA)',
                'attr' => ['class' => 'form-control form-control-sm'],
                'required' => false,
                'scale' => 0,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LigneEvenement::class,
        ]);
    }
}