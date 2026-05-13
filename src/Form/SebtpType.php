<?php

namespace App\Form;

use App\Entity\Sebtp;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SebtpType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('instance')
            ->add('nomOrganisme')
            ->add('mandat')
            ->add('nomRepresentant')
            ->add('fichiers', FileType::class, [
                'label' => 'Document (PDF, DOC, DOCX)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => '.pdf,.doc,.docx',
                    'class' => 'form-control'
                ]
            ])
            ->add('observation')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Sebtp::class,
        ]);
    }
}
