<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Restaurant;
use App\Form\Model\ReservationBookingFormModel;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Réservation client : jour, créneau (liste dynamique), effectif et coordonnées.
 */
final class ReservationBookingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $choiceLabelsToValues Symfony : libellé => valeur stockée « H:i » */
        $choiceLabelsToValues = $options['slot_choices'];

        /** @var list<Restaurant> $establishments Au moins un pour afficher la liste déroulante. */
        $establishments = $options['bookable_restaurants'];

        $builder->add('restaurant', EntityType::class, [
            'label' => 'Restaurant',
            'class' => Restaurant::class,
            'choices' => $establishments,
            'choice_label' => static fn (?Restaurant $r): string => (string) $r?->getName(),
            'placeholder' => '— Choisir un restaurant —',
            'disabled' => [] === $establishments,
        ]);

        $builder
            ->add('reservationDate', DateType::class, [
                'label' => 'Date du repas',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'html5' => true,
            ]);

        if ([] !== $choiceLabelsToValues) {
            $builder->add('slotTime', ChoiceType::class, [
                'label' => 'Créneau horaire',
                'choices' => $choiceLabelsToValues,
                'placeholder' => '— Choisir un créneau —',
            ]);
        } else {
            $builder->add('slotTime', ChoiceType::class, [
                'label' => 'Créneau horaire',
                'choices' => [],
                'disabled' => true,
                'attr' => ['data-empty-slots' => '1'],
            ]);
        }

        $builder
            ->add('partySize', IntegerType::class, [
                'label' => 'Nombre de personnes',
                'attr' => ['min' => 1, 'max' => 99],
            ])
            ->add('guestName', TextType::class, [
                'label' => 'Nom',
                'attr' => ['maxlength' => 120],
            ])
            ->add('guestEmail', EmailType::class, [
                'label' => 'Email',
                'attr' => ['maxlength' => 180],
            ])
            ->add('guestPhone', TelType::class, [
                'label' => 'Téléphone',
                'attr' => ['maxlength' => 32],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Confirmer la réservation',
                /** Désactivé si aucune place horaire encore bookable pour le jour sélectionné. */
                'disabled' => !$options['allow_submit'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReservationBookingFormModel::class,
            /** @see ReservationSlotProvider::getFormChoicesForDay() — libellé => « H:i » */
            'slot_choices' => [],
            /** @see RestaurantRepository::findRegisteredEstablishmentsOrderedByName() */
            'bookable_restaurants' => [],
            'allow_submit' => true,
        ]);
        $resolver->setAllowedTypes('slot_choices', ['array']);
        $resolver->setAllowedTypes('bookable_restaurants', ['array']);
        $resolver->setAllowedTypes('allow_submit', ['bool']);
    }

    /** Préfixe du nom des champs HTML : booking[...]. */
    public function getBlockPrefix(): string
    {
        return 'booking';
    }
}
