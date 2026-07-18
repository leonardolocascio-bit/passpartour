<?php

namespace App\Form;

use App\Entity\Destinazione;
use App\Entity\TappaViaggio;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TappaViaggioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('giorno', IntegerType::class, ['required' => false, 'label' => false])
            ->add('titolo', TextType::class, ['label' => false])
            ->add('descrizione', TextareaType::class, ['label' => false, 'required' => false])
            ->add('destinazione', EntityType::class, [
                'class' => Destinazione::class,
                'choice_label' => 'nome',
                'required' => false,
                'placeholder' => '— nessuna immagine —',
                'label' => false,
                'query_builder' => fn (EntityRepository $r) => $r->createQueryBuilder('d')->orderBy('d.nome', 'ASC'),
            ])
            ->add('ordinamento', HiddenType::class, ['empty_data' => '0']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TappaViaggio::class]);
    }
}
