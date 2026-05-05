<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Restaurant;
use App\Exception\NoTableAvailableException;
use App\Form\Model\ReservationBookingFormModel;
use App\Form\ReservationBookingFormType;
use App\Repository\RestaurantRepository;
use App\Service\Reservation\ReservationBookingRequest;
use App\Service\Reservation\ReservationBookingService;
use App\Service\Reservation\ReservationSlotProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Parcours public sans compte : formulaire puis page de confirmation (SPECS fonctionnelles §7).
 *
 * Sélection établissement + date + créneaux ; la date peut être préchargée (?date=) et le restaurant (?restaurant=).
 */
final class ReservationPublicController extends AbstractController
{
    private const DISPLAY_TZ = 'Europe/Paris';

    #[Route('/reserver', name: 'app_reserver', methods: ['GET', 'POST'])]
    public function book(
        Request $request,
        RestaurantRepository $restaurantRepository,
        ReservationSlotProvider $slotProvider,
        ReservationBookingService $bookingService,
    ): Response {
        $tz = new \DateTimeZone(self::DISPLAY_TZ);
        $today = (new \DateTimeImmutable('now', $tz))->setTime(0, 0, 0);

        /** @var list<Restaurant> $bookable Établissements liés à au moins un compte inscrit ({@see RestaurantRepository::findRegisteredEstablishmentsOrderedByName()}). */
        $bookable = $restaurantRepository->findRegisteredEstablishmentsOrderedByName();

        $calendarDay = $this->resolveCalendarDayForForm($request, $tz, $today);

        /** Restaurant présélectionné (GET ou corps POST). */
        $selectedRestaurant = $this->resolveSelectedRestaurantForForm($request, $bookable);
        $allowedIds = $this->restaurantIdsList($bookable);

        $slotChoices = $slotProvider->getFormChoicesForDay($calendarDay);

        $model = new ReservationBookingFormModel();
        $model->setRestaurant($selectedRestaurant);
        $model->setReservationDate($calendarDay);

        $slotsOk = [] !== $slotChoices;
        $establishmentOk = [] !== $bookable && null !== $selectedRestaurant && \in_array($selectedRestaurant->getId(), $allowedIds, true);
        $tablesOk = $establishmentOk && null !== $selectedRestaurant && $restaurantRepository->hasActiveTable($selectedRestaurant);
        $allowSubmit = $slotsOk && $tablesOk;

        $form = $this->createForm(ReservationBookingFormType::class, $model, [
            'slot_choices' => $slotChoices,
            'bookable_restaurants' => $bookable,
            'allow_submit' => $allowSubmit,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            assert($data instanceof ReservationBookingFormModel);

            $restaurantSelected = $data->getRestaurant();
            $rid = $restaurantSelected?->getId();
            if (null === $rid || !\in_array($rid, $allowedIds, true)) {
                $this->addFlash('error', 'Établissement invalide.');
            } else {
                $day = $data->getReservationDate();
                if (!$day instanceof \DateTimeImmutable) {
                    $this->addFlash('error', 'Date manquante.');
                } else {
                    $effectiveChoices = $slotProvider->getFormChoicesForDay($day);
                    /** @var list<string> $allowedTimes */
                    $allowedTimes = array_values($effectiveChoices);

                    if ([] === $allowedTimes) {
                        $this->addFlash('error', 'Aucun créneau n’est disponible pour cette date.');
                    } elseif (!\in_array((string) $data->getSlotTime(), $allowedTimes, true)) {
                        $this->addFlash('error', 'Ce créneau n’est pas valide pour cette date. Actualisez la page.');
                    } else {
                        $slotAt = $slotProvider->buildSlotAt($day, (string) $data->getSlotTime());
                        $reservationDay = \DateTimeImmutable::createFromFormat('!Y-m-d', $day->format('Y-m-d'));

                        if (null !== $slotAt && false !== $reservationDay) {
                            try {
                                $reservation = $bookingService->book(new ReservationBookingRequest(
                                    $restaurantSelected,
                                    $reservationDay,
                                    $slotAt,
                                    (int) $data->getPartySize(),
                                    (string) $data->getGuestName(),
                                    (string) $data->getGuestEmail(),
                                    (string) $data->getGuestPhone(),
                                ));

                                return $this->redirectToRoute('app_reservation_confirmation', [
                                    'id' => $reservation->getId(),
                                ]);
                            } catch (NoTableAvailableException) {
                                $this->addFlash('error', 'Il n’y a plus de table disponible pour ce créneau dans cet établissement. Essayez un autre horaire ou une autre date.');
                            }
                        } else {
                            $this->addFlash('error', 'Créneau ou date incohérent.');
                        }
                    }
                }
            }

            $calendarDayRetry = ($form->getData()?->getReservationDate()) ?? $calendarDay;
            $slotChoices = $slotProvider->getFormChoicesForDay($calendarDayRetry);

            $model = $form->getData();
            assert($model instanceof ReservationBookingFormModel);

            $restaurantRetry = $model->getRestaurant();
            $establishmentRetry = null !== $restaurantRetry
                && \in_array((int) $restaurantRetry->getId(), $allowedIds, true);
            $tablesRetry = $establishmentRetry && null !== $restaurantRetry && $restaurantRepository->hasActiveTable($restaurantRetry);

            $form = $this->createForm(ReservationBookingFormType::class, $model, [
                'slot_choices' => $slotChoices,
                'bookable_restaurants' => $bookable,
                'allow_submit' => [] !== $slotChoices && $tablesRetry,
            ]);
        }

        $displayRestaurant = ($form->getData()?->getRestaurant()) ?? $selectedRestaurant ?? ($bookable[0] ?? null);

        $establishmentChosenDisplay = null !== $displayRestaurant
            && \in_array((int) $displayRestaurant->getId(), $allowedIds, true);
        $tablesReadyDisplay = $establishmentChosenDisplay && $restaurantRepository->hasActiveTable($displayRestaurant);

        return $this->render('reservation/book.html.twig', [
            'form' => $form,
            'bookableRestaurants' => $bookable,
            'filterDateDefault' => $calendarDay->format('Y-m-d'),
            'filterRestaurantDefault' => $displayRestaurant?->getId(),
            'minDateFilter' => $today->format('Y-m-d'),
            'hasSlots' => [] !== $slotChoices,
            'hasBookableRestaurants' => [] !== $bookable,
            'noticeIncompleteTableSetup' => [] !== $bookable && $establishmentChosenDisplay && !$tablesReadyDisplay,
        ]);
    }

    /** Page de synthèse (pas d’obligation d’email de confirmation — SPECS fonctionnelles §9). */
    #[Route('/reservation/confirmation/{id}', name: 'app_reservation_confirmation', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function confirmation(Reservation $reservation): Response
    {
        return $this->render('reservation/confirmation.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    /** @param list<Restaurant> $bookable */
    private function resolveSelectedRestaurantForForm(Request $request, array $bookable): ?Restaurant
    {
        if ([] === $bookable) {
            return null;
        }

        $byId = [];
        foreach ($bookable as $r) {
            $id = $r->getId();
            if (null !== $id) {
                $byId[$id] = $r;
            }
        }

        if ($request->isMethod('POST')) {
            /** @var array<string, mixed> $allBody */
            $allBody = $request->request->all();
            $payload = $allBody['booking'] ?? [];
            $raw = null;
            if (\is_array($payload) && array_key_exists('restaurant', $payload)) {
                $raw = $payload['restaurant'];
            }

            $id = (\is_numeric($raw) ? (int) $raw : null);
            if (null !== $id && isset($byId[$id])) {
                return $byId[$id];
            }
        }

        $rawQuery = $request->query->get('restaurant');
        if (\is_string($rawQuery) && ctype_digit($rawQuery) && isset($byId[(int) $rawQuery])) {
            return $byId[(int) $rawQuery];
        }

        /** Premier établissement par ordre alphabétique ({@see RestaurantRepository::findRegisteredEstablishmentsOrderedByName()}). */
        return $bookable[0] ?? null;
    }

    /** @param list<Restaurant> $bookable */
    private function restaurantIdsList(array $bookable): array
    {
        $ids = [];
        foreach ($bookable as $r) {
            $id = $r->getId();
            if (null !== $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** Date minimale réservable : aujourd’hui (fuseau restaurant). */
    private function resolveCalendarDayForForm(Request $request, \DateTimeZone $tz, \DateTimeImmutable $today): \DateTimeImmutable
    {
        $fallback = $today->modify('+1 day');

        if ($request->isMethod('POST')) {
            /** @var array<string, mixed> $allBody */
            $allBody = $request->request->all();
            $payload = $allBody['booking'] ?? null;
            if (\is_array($payload) && isset($payload['reservationDate']) && \is_string($payload['reservationDate'])) {
                $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $payload['reservationDate'], $tz);
                if (false !== $parsed) {
                    $parsed = $parsed->setTime(0, 0, 0);
                    if ($parsed >= $today) {
                        return $parsed;
                    }

                    return $today;
                }
            }
        }

        $rawQuery = $request->query->get('date');

        if (!\is_string($rawQuery) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawQuery)) {
            return $fallback;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $rawQuery, $tz);
        if (false === $parsed) {
            return $fallback;
        }

        $parsed = $parsed->setTime(0, 0, 0);

        if ($parsed < $today) {
            return $today;
        }

        return $parsed;
    }
}
