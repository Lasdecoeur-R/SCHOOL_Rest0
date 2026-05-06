<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\RestaurantTable;
use App\Enum\TableSeatingZone;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire espace restaurateur : numéro, capacité, table active ou retirée du service.
 */
final class RestaurantTableFormType extends AbstractType
{
    /** Champs alignés sur l’entité {@see RestaurantTable}. */
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
            ->add('seatingZone', EnumType::class, [
                'class' => TableSeatingZone::class,
                'label' => 'Emplacement',
                'choice_label' => static fn (TableSeatingZone $z): string => $z->label(),
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'En service (décocher pour retirer du service sans supprimer l’historique)',
                'required' => false,
            ]);
    }

    /** Lie le formulaire à l’entité pour que handleRequest remplisse les propriétés. */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RestaurantTable::class,
        ]);
    }
}
