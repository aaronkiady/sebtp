<?php

namespace App\Form;

use App\Entity\Contact;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'attr' => ['placeholder' => 'Nom et Prénom']
            ])
            ->add('fonction', ChoiceType::class, [
                'label' => 'Fonction',
                'choices' => [
                    'Compta' => 'Compta',
                    'RH' => 'RH',
                ]
            ])
            ->add('email', EmailType::class, [
                'attr' => ['placeholder' => 'email@entreprise.mg']
            ])
            ->add('telephone', TelType::class, [
                'required' => false,
                'attr' => ['placeholder' => 'Numéro de téléphone']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contact::class,
        ]);
    }
}