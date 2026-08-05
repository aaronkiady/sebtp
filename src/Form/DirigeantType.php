<?php

namespace App\Form;

use App\Entity\Dirigeant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DirigeantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('president', TextType::class, [
                'label' => 'Nom du Président',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('secretaire', TextType::class, [
                'label' => 'Nom du Secrétaire',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('tresorier', TextType::class, [
                'label' => 'Nom du Trésorier',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('signatureFile', FileType::class, [
                'label' => 'Signature / Cachet (JPEG, PNG)',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'help' => 'Image au format JPEG ou PNG (recommandé: 200x100px)',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dirigeant::class,
        ]);
    }
}
