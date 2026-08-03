<?php

namespace App\Form;

use App\Entity\Cotisation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CotisationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('montantPaye', NumberType::class, [
                'label' => 'Montant payé (MGA)',
                'attr' => ['class' => 'form-control custom-input', 'step' => '1000'],
                'required' => true,
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
                    'Espèces' => 'Especes',
                    'Chèque' => 'Cheque',
                    'Virement bancaire' => 'Virement',
                    //'Mobile Money' => 'Mobile Money',
                ],
                'attr' => ['class' => 'form-select custom-input'],
                'required' => true,
            ])
            ->add('reference', TextType::class, [
                'label' => 'Référence (N° chèque, ref virement)',
                'required' => false,
                'attr' => ['class' => 'form-control custom-input', 'placeholder' => 'Optionnel'],
            ])
            ->add('observation', TextType::class, [
                'label' => 'Observation',
                'required' => false,
                'attr' => ['class' => 'form-control custom-input'],
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