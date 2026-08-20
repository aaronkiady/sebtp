<?php

namespace App\Form;

use App\Entity\LigneEvenement;
use App\Entity\ParticipationLigne;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParticipationLigneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ligne', EntityType::class, [
                'class' => LigneEvenement::class,
                'choice_label' => 'designation',
                'label' => 'Désignation',
                'attr' => ['class' => 'form-control form-control-sm'],
                'required' => true,
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantité',
                'attr' => ['class' => 'form-control form-control-sm', 'min' => 1],
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ParticipationLigne::class,
        ]);
    }
}