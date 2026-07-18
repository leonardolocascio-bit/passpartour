<?php

namespace App\Form;

use App\Entity\Destinazione;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class DestinazioneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nome', TextType::class, ['label' => 'Nome destinazione', 'attr' => ['placeholder' => 'Es. Santorini']])
            ->add('descrizione', TextareaType::class, ['label' => 'Descrizione', 'required' => false])
            ->add('immagineFile', FileType::class, [
                'label' => 'Immagine',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(maxSize: '6M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], mimeTypesMessage: 'Carica un\'immagine JPG, PNG o WebP'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Destinazione::class]);
    }
}
