<?php

namespace App\Form;

use App\Entity\VoceCosto;
use App\Enum\CategoriaVoce;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VoceCostoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('categoria', EnumType::class, [
                'class' => CategoriaVoce::class,
                'choice_label' => fn (CategoriaVoce $c) => $c->label(),
                'label' => false,
            ])
            ->add('descrizione', TextType::class, ['label' => false, 'attr' => ['placeholder' => 'Descrizione voce']])
            ->add('quantita', IntegerType::class, ['label' => false, 'attr' => ['min' => 1]])
            ->add('costoUnitario', TextType::class, [
                'label' => false,
                'attr' => ['placeholder' => '0.00', 'inputmode' => 'decimal'],
                'empty_data' => '0',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => VoceCosto::class]);
    }
}
