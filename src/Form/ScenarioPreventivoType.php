<?php

namespace App\Form;

use App\Entity\ScenarioPreventivo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ScenarioPreventivoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nome', TextType::class, ['label' => 'Nome scenario', 'attr' => ['placeholder' => 'Es. Comfort']])
            ->add('descrizione', TextareaType::class, ['label' => 'Descrizione', 'required' => false])
            ->add('markupPercentuale', TextType::class, [
                'label' => 'Ricarico %',
                'attr' => ['inputmode' => 'decimal'],
                'empty_data' => '0',
            ])
            ->add('consigliato', CheckboxType::class, ['label' => 'Consigliato', 'required' => false])
            ->add('ordinamento', HiddenType::class, ['empty_data' => '0'])
            ->add('voci', CollectionType::class, [
                'entry_type' => VoceCostoType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => true,
                'label' => false,
                'prototype' => true,
                'prototype_name' => '__voce__',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ScenarioPreventivo::class]);
    }
}
