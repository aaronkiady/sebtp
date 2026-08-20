<?php

namespace App\Form;

use App\Entity\Formation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la formation',
                'attr' => ['class' => 'form-control custom-input'],
                'required' => false,
            ])
            ->add('formateurs', TextType::class, [
                'label' => 'Formateurs',
                'attr' => ['class' => 'form-control custom-input'],
                'required' => false,
                'help' => 'Nom des formateurs',
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control custom-input'],
                'required' => false,
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control custom-input'],
                'required' => false,
            ])
            ->add('organisateur', TextType::class, [
                'label' => 'Organisateur',
                'attr' => ['class' => 'form-control custom-input'],
                'required' => false,
            ])
            ->add('reference', TextType::class, [
                'label' => 'Référence',
                'attr' => ['class' => 'form-control custom-input'],
                'required' => false,
            ])
            ->add('remarque', TextType::class, [
                'label' => 'Remarque',
                'attr' => ['class' => 'form-control custom-input'],
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Formation::class,
        ]);
    }
}