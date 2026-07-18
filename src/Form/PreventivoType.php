<?php

namespace App\Form;

use App\Entity\Cliente;
use App\Entity\Lead;
use App\Entity\Preventivo;
use App\Enum\StatoPreventivo;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PreventivoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titolo', TextType::class, ['label' => 'Titolo', 'attr' => ['placeholder' => 'Es. Santorini · 7 notti · Luglio 2026']])
            ->add('lead', EntityType::class, [
                'class' => Lead::class,
                'choice_label' => 'nomeCompleto',
                'required' => false,
                'placeholder' => '— nessun lead —',
                'query_builder' => fn (EntityRepository $r) => $r->createQueryBuilder('l')->orderBy('l.createdAt', 'DESC'),
            ])
            ->add('cliente', EntityType::class, [
                'class' => Cliente::class,
                'choice_label' => 'nomeCompleto',
                'required' => false,
                'placeholder' => '— nessun cliente —',
            ])
            ->add('stato', EnumType::class, [
                'class' => StatoPreventivo::class,
                'choice_label' => fn (StatoPreventivo $s) => $s->label(),
            ])
            ->add('validoFino', DateType::class, [
                'label' => 'Valido fino al',
                'widget' => 'single_text',
                'required' => false,
                'input' => 'datetime_immutable',
            ])
            ->add('note', TextareaType::class, ['label' => 'Note', 'required' => false])
            ->add('scenari', CollectionType::class, [
                'entry_type' => ScenarioPreventivoType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => true,
                'label' => false,
                'prototype' => true,
                'prototype_name' => '__scenario__',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Preventivo::class]);
    }
}
