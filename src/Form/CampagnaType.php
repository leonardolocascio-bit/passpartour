<?php

namespace App\Form;

use App\Entity\Campagna;
use App\Entity\Offerta;
use App\Enum\FonteLead;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CampagnaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nome', TextType::class, ['label' => 'Nome campagna'])
            ->add('fonte', EnumType::class, [
                'label' => 'Fonte',
                'class' => FonteLead::class,
                'choice_label' => fn (FonteLead $f) => $f->label(),
            ])
            ->add('offerta', EntityType::class, [
                'label' => 'Offerta promossa (la landing pesca da qui)',
                'class' => Offerta::class,
                'choice_label' => 'titolo',
                'required' => false,
                'placeholder' => '— nessuna —',
            ])
            ->add('budget', NumberType::class, ['label' => 'Budget (€)', 'required' => false, 'input' => 'string', 'scale' => 2])
            ->add('attiva', CheckboxType::class, ['label' => 'Campagna attiva (landing raggiungibile)', 'required' => false])
            ->add('note', TextareaType::class, ['label' => 'Note', 'required' => false])
            // testi della landing (colonna JSON, riempiti dal controller)
            ->add('landingTitolo', TextType::class, ['label' => 'Titolo landing', 'mapped' => false, 'required' => false, 'attr' => ['placeholder' => 'vuoto = titolo offerta']])
            ->add('landingSottotitolo', TextType::class, ['label' => 'Sottotitolo landing', 'mapped' => false, 'required' => false, 'attr' => ['placeholder' => 'vuoto = sottotitolo/claim offerta']])
            ->add('landingCta', TextType::class, ['label' => 'Testo bottone (CTA)', 'mapped' => false, 'required' => false, 'attr' => ['placeholder' => 'es. Richiedi un preventivo gratuito']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Campagna::class]);
    }
}
