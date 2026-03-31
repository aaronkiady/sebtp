<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\Liste;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EvenementType extends AbstractType
{
    /**
     * Construit le formulaire de gestion d'un événement.
     *
     * @param FormBuilderInterface $builder
     * @param array $options
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'événement',
                'attr' => [
                    'placeholder' => 'Ex: Assemblée Générale, Cocktail annuel...',
                    'class' => 'form-control'
                ],
            ])
            ->add('date', DateType::class, [
                'label' => 'Date de l\'événement',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('montant', TextType::class, [
                'label' => 'Montant / Frais de participation (MGA)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: 50 000',
                    'class' => 'form-control'
                ],
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Commentaires ou Observations',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Détails supplémentaires sur l\'événement...',
                    'class' => 'form-control'
                ],
            ])
            /*->add('participants', EntityType::class, [
                'class' => Liste::class,
                'choice_label' => 'nom',
                'multiple' => true,
                'expanded' => false,
                'by_reference' => false,
                'attr' => [
                    'class' => 'form-control select2',
                ],
            ]);*/
            ->add('participants', EntityType::class, [
                'class' => Liste::class,
                'choice_label' => 'nom',
                'multiple' => true,
                'expanded' => true,
                'label' => 'Sélectionner les participants',
            ]);
    }

    /**
     * Configure les options par défaut pour ce formulaire.
     *
     * @param OptionsResolver $resolver
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
        ]);
    }
}