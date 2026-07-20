<?php

namespace App\Form;

use App\Entity\VoceCosto;
use App\Enum\CategoriaVoce;
use App\Enum\TrattamentoHotel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VoceCostoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $testo = fn (string $label = '') => ['label' => false, 'required' => false];
        $builder
            ->add('categoria', EnumType::class, [
                'class' => CategoriaVoce::class,
                'choice_label' => fn (CategoriaVoce $c) => $c->label(),
                'label' => false,
            ])
            ->add('descrizione', TextType::class, ['label' => false, 'required' => false, 'empty_data' => ''])
            ->add('quantita', IntegerType::class, ['label' => false])
            ->add('costoUnitario', TextType::class, ['label' => false, 'empty_data' => '0'])
            // volo / transfer
            ->add('da', TextType::class, $testo())
            ->add('a', TextType::class, $testo())
            ->add('orarioPartenza', TextType::class, $testo())
            ->add('orarioArrivo', TextType::class, $testo())
            // hotel
            ->add('nomeStruttura', TextType::class, $testo())
            ->add('stelle', IntegerType::class, ['label' => false, 'required' => false])
            ->add('indirizzo', TextType::class, $testo())
            ->add('trattamento', EnumType::class, [
                'class' => TrattamentoHotel::class,
                'choice_label' => fn (TrattamentoHotel $t) => $t->label(),
                'required' => false,
                'placeholder' => '—',
                'label' => false,
            ])
            ->add('dataInizio', DateType::class, ['label' => false, 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('orarioInizio', TextType::class, $testo())
            ->add('dataFine', DateType::class, ['label' => false, 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('orarioFine', TextType::class, $testo())
            // comune
            ->add('note', TextareaType::class, $testo());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => VoceCosto::class]);
    }
}
