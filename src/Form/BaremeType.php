<?php

namespace App\Form;

use App\Entity\Bareme;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BaremeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('categorie', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    'Entreprise' => 'entreprise',
                    'ONG / Association' => 'ong',
                    'Sponsor' => 'sponsor',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('sousCategorie', ChoiceType::class, [
                'label' => 'Tranche (pour entreprise)',
                'choices' => [
                    '1 à 10 employés' => '1-10',
                    '11 à 50 employés' => '11-50',
                    'Plus de 50 employés' => '51+',
                    'Non applicable' => null,
                ],
                'required' => false,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('montant', NumberType::class, [
                'label' => 'Montant (MGA)',
                'attr' => ['class' => 'form-control', 'step' => '1000'],
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Date de début d\'application',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'data' => new \DateTime(),
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Date de fin (optionnel)',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('description', TextType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Optionnel'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Bareme::class,
        ]);
    }
}