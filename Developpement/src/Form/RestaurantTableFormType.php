<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\RestaurantTable;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire admin : numéro, capacité, table active ou retirée du service.
 */
final class RestaurantTableFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('number', TextType::class, [
                'label' => 'Numéro de table',
                'attr' => ['maxlength' => 32],
            ])
            ->add('capacity', IntegerType::class, [
                'label' => 'Capacité (nombre de couverts)',
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'En service (décocher pour retirer du service sans supprimer l’historique)',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RestaurantTable::class,
        ]);
    }
}
