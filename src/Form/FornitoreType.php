<?php

namespace App\Form;

use App\Entity\Fornitore;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FornitoreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ragioneSociale', TextType::class, ['label' => 'Ragione sociale'])
            ->add('tipo', TextType::class, [
                'label' => 'Tipo',
                'required' => false,
                'attr' => ['list' => 'fornitore-tipi', 'placeholder' => 'es. Tour operator'],
            ])
            ->add('referente', TextType::class, ['label' => 'Referente', 'required' => false])
            ->add('email', EmailType::class, ['label' => 'Email', 'required' => false])
            ->add('telefono', TextType::class, ['label' => 'Telefono', 'required' => false])
            ->add('sitoWeb', TextType::class, ['label' => 'Sito web', 'required' => false, 'attr' => ['placeholder' => 'https://…']])
            ->add('partitaIva', TextType::class, ['label' => 'Partita IVA', 'required' => false])
            ->add('note', TextareaType::class, ['label' => 'Note', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Fornitore::class]);
    }
}
