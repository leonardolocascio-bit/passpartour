<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

/**
 * Form generico per una voce di catalogo (destinazioni, tipologie, temi):
 * nome + descrizione + immagine (upload; l'Unsplash picker è nel template).
 * data_class e nome_label sono passati da chi crea il form.
 */
class CatalogoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nome', TextType::class, ['label' => $options['nome_label']])
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
        $resolver->setDefaults(['nome_label' => 'Nome']);
        $resolver->setRequired('data_class');
        $resolver->setAllowedTypes('nome_label', 'string');
    }
}
