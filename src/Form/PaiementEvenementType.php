<?php

namespace App\Form;

use App\Entity\PaiementEvenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaiementEvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('montant', NumberType::class, [
                'label' => 'Montant (MGA)',
                'attr' => ['class' => 'form-control custom-input'],
                'scale' => 0,
            ])
            ->add('datePaiement', DateType::class, [
                'label' => 'Date de paiement',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control custom-input'],
                'required' => true,
            ])
            ->add('modePaiement', ChoiceType::class, [
                'label' => 'Mode de paiement',
                'choices' => [
                    'Espèces' => 'especes',
                    'Chèque' => 'cheque',
                    'Virement bancaire' => 'virement',
                    //'Mobile Money' => 'mobile_money',
                    'Autre' => 'autre',
                ],
                'attr' => ['class' => 'form-select custom-input'],
                'required' => false,
                'placeholder' => 'Sélectionnez un mode',
            ])
            ->add('reference', TextType::class, [
                'label' => 'Référence',
                'attr' => ['class' => 'form-control custom-input'],
                'required' => false,
            ])
            ->add('commentaire', TextType::class, [
                'label' => 'Commentaire',
                'attr' => ['class' => 'form-control custom-input'],
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PaiementEvenement::class,
        ]);
    }
}