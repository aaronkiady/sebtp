<?php

namespace App\Form;

use App\Entity\Partenariats;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PartenariatsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('Partenaire')
            ->add('Contenu')
            ->add('DateDebut')
            ->add('DateFin')
            ->add('Observation')
            ->add('Fichier')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Partenariats::class,
        ]);
    }
}
