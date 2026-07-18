<?php

namespace App\Form;

use App\Entity\Campagna;
use App\Entity\Lead;
use App\Entity\Utente;
use App\Enum\FonteLead;
use App\Enum\StatoLead;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LeadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nome', TextType::class, ['label' => 'Nome'])
            ->add('cognome', TextType::class, ['label' => 'Cognome', 'required' => false])
            ->add('email', EmailType::class, ['label' => 'Email', 'required' => false])
            ->add('telefono', TextType::class, ['label' => 'Telefono', 'required' => false])
            ->add('fonte', EnumType::class, [
                'label' => 'Fonte',
                'class' => FonteLead::class,
                'choice_label' => fn (FonteLead $f) => $f->label(),
            ])
            ->add('campagna', EntityType::class, [
                'label' => 'Campagna',
                'class' => Campagna::class,
                'choice_label' => 'nome',
                'required' => false,
                'placeholder' => '— nessuna —',
                'query_builder' => fn (EntityRepository $r) => $r->createQueryBuilder('c')->orderBy('c.nome', 'ASC'),
            ])
            ->add('assegnatario', EntityType::class, [
                'label' => 'Assegnatario',
                'class' => Utente::class,
                'choice_label' => 'nomeCompleto',
                'required' => false,
                'placeholder' => '— non assegnato —',
            ])
            ->add('stato', EnumType::class, [
                'label' => 'Stato',
                'class' => StatoLead::class,
                'choice_label' => fn (StatoLead $s) => $s->label(),
            ])
            ->add('destinazione', TextType::class, ['label' => 'Destinazione', 'required' => false])
            ->add('periodo', TextType::class, ['label' => 'Periodo', 'required' => false])
            ->add('numeroPasseggeri', IntegerType::class, ['label' => 'N. passeggeri', 'required' => false])
            ->add('budgetIndicativo', TextType::class, [
                'label' => 'Budget indicativo (€)',
                'required' => false,
                'empty_data' => null,
            ])
            ->add('note', TextareaType::class, ['label' => 'Note', 'required' => false])
            ->add('consensoMarketing', CheckboxType::class, [
                'label' => 'Consenso marketing',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Lead::class]);
    }
}
