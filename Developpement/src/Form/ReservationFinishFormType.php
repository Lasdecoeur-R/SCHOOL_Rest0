<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Model\ReservationFinishFormModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Étape finale : coordonnées (attribution automatique de la plus petite table adaptée).
 */
final class ReservationFinishFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
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
                'disabled' => !$options['allow_submit'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReservationFinishFormModel::class,
            'allow_submit' => true,
        ]);
        $resolver->setAllowedTypes('allow_submit', ['bool']);
    }

    public function getBlockPrefix(): string
    {
        return 'finish';
    }
}
