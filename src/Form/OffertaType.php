<?php

namespace App\Form;

use App\Entity\Destinazione;
use App\Entity\Offerta;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OffertaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titolo', TextType::class, ['label' => 'Titolo', 'attr' => ['placeholder' => 'Es. Maldive da sogno']])
            ->add('sottotitolo', TextType::class, ['label' => 'Sottotitolo', 'required' => false, 'attr' => ['placeholder' => 'Es. 7 notti in overwater']])
            ->add('destinazione', EntityType::class, [
                'label' => 'Immagine (destinazione)',
                'class' => Destinazione::class,
                'choice_label' => 'nome',
                'required' => false,
                'placeholder' => '— nessuna —',
                'query_builder' => fn (EntityRepository $r) => $r->createQueryBuilder('d')->orderBy('d.nome', 'ASC'),
            ])
            ->add('descrizione', TextareaType::class, ['label' => 'Descrizione', 'required' => false])
            ->add('prezzoDa', TextType::class, ['label' => 'Prezzo a partire da (€)', 'required' => false, 'attr' => ['inputmode' => 'decimal']])
            ->add('durata', TextType::class, ['label' => 'Durata', 'required' => false, 'attr' => ['placeholder' => 'Es. 7 notti']])
            ->add('validoDal', DateType::class, ['label' => 'Valida dal', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('validoAl', DateType::class, ['label' => 'Valida al', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('attiva', CheckboxType::class, ['label' => 'Offerta attiva', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Offerta::class]);
    }
}
