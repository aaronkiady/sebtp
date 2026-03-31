<?php

namespace App\Form;

use App\Entity\Cotisation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CotisationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('montant', MoneyType::class, [
                'currency' => 'MGA',
                'data' => '500000.00', // Montant par défaut
                'attr' => ['class' => 'form-control']
            ])
            ->add('datePaiement', DateType::class, [
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('modePaiement', ChoiceType::class, [
                'choices' => [
                    'Chèque' => 'Chèque',
                    'Virement' => 'Virement',
                    'Espèces' => 'Espèces',
                    'CB' => 'CB',
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('reference', TextType::class, [
                'label' => 'Référence du règlement',
                'attr' => ['class' => 'form-control', 'placeholder' => 'N° Chèque, ref virement...']
            ])
            ->add('statut', ChoiceType::class, [
                'choices' => [
                    'Payé' => 'payé',
                    'En attente' => 'impayer',
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('periode', TextType::class, [
                'label' => 'Période (Année)',
                'data' => date('Y'),
                'attr' => ['class' => 'form-control']
            ])
            ->add('observation', TextType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Cotisation::class,
        ]);
    }
}