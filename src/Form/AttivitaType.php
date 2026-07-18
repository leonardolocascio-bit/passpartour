<?php

namespace App\Form;

use App\Entity\Attivita;
use App\Enum\TipoAttivita;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttivitaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('tipo', EnumType::class, [
                'label' => 'Tipo',
                'class' => TipoAttivita::class,
                'choice_label' => fn (TipoAttivita $t) => $t->label(),
            ])
            ->add('titolo', TextType::class, ['label' => 'Titolo'])
            ->add('descrizione', TextareaType::class, ['label' => 'Dettagli', 'required' => false])
            ->add('dataScadenza', DateTimeType::class, [
                'label' => 'Promemoria (facoltativo)',
                'required' => false,
                'input' => 'datetime_immutable',
                'widget' => 'single_text',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Attivita::class]);
    }
}
