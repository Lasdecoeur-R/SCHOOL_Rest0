<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Restaurant;
use App\Enum\TableSeatingZone;
use App\Exception\NoTableAvailableException;
use App\Form\Model\ReservationFinishFormModel;
use App\Form\ReservationFinishFormType;
use App\Repository\RestaurantRepository;
use App\Service\Reservation\ReservationBookingRequest;
use App\Service\Reservation\ReservationBookingService;
use App\Service\Reservation\ReservationPlanningAvailability;
use App\Service\Reservation\ReservationSlotProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Parcours public type « Zenchef » : effectif → dates → zone → créneau → table → coordonnées.
 */
final class ReservationPublicController extends AbstractController
{
    private const DISPLAY_TZ = 'Europe/Paris';

    private const DAYS_PER_PAGE = 14;

    private const SESSION_GUEST_DRAFT = 'app_reservation_guest_draft';

    /** Bornes alignées sur {@see ReservationSlotProvider} (midi / soir). */
    private const MEAL_MIDI_FIRST = '11:00';

    private const MEAL_MIDI_LAST = '13:00';

    private const MEAL_SOIR_FIRST = '18:30';

    private const MEAL_SOIR_LAST = '22:30';

    #[Route('/reserver', name: 'app_reserver', methods: ['GET', 'POST'])]
    public function book(
        Request $request,
        RestaurantRepository $restaurantRepository,
        ReservationSlotProvider $slotProvider,
        ReservationPlanningAvailability $planningAvailability,
        ReservationBookingService $bookingService,
    ): Response {
        $tz = new \DateTimeZone(self::DISPLAY_TZ);
        $today = (new \DateTimeImmutable('now', $tz))->setTime(0, 0, 0);

        $bookable = $restaurantRepository->findRegisteredEstablishmentsOrderedByName();
        $allowedIds = $this->restaurantIdsList($bookable);

        $selectedRestaurant = $this->resolveRestaurantFromQuery($request, $bookable);
        $partySize = $this->resolvePartyFromQuery($request);
        $selectedDate = $this->resolveDateFromQuery($request, $tz, $today);
        $selectedZone = $this->resolveZoneFromQuery($request);
        $selectedSlotHi = $this->resolveSlotHiFromQuery($request);
        $datePage = max(0, $request->query->getInt('p'));

        $finishFormView = null;
        /** @var 'midi'|'soir'|null Valeur envoyée avec le POST final pour réafficher l’étape (créneau / service). */
        $flowMealFromPost = null;

        if ($request->isMethod('POST')) {
            $flowRestaurantId = $request->request->getInt('flow_restaurant');
            $flowParty = $request->request->getInt('flow_party');
            $flowDateRaw = $request->request->getString('flow_date');
            $flowZoneRaw = $request->request->getString('flow_zone');
            $flowSlotHi = $this->canonicalizeSlotHi($request->request->getString('flow_slot'));
            $flowMealRaw = $request->request->getString('flow_meal');
            $flowMealFromPost = \in_array($flowMealRaw, ['midi', 'soir'], true) ? $flowMealRaw : null;

            $restaurantFlow = $this->findRestaurantByIdAmong($flowRestaurantId, $bookable);
            $zoneFlow = TableSeatingZone::tryFrom($flowZoneRaw);
            $dayFlow = $this->normalizeFlowBookingDate($flowDateRaw, $tz, $today);

            $slotAtFlow = null;
            if ($restaurantFlow && $zoneFlow && $dayFlow && null !== $flowSlotHi && $flowParty >= 1
                && \in_array((int) $restaurantFlow->getId(), $allowedIds, true)
                && $restaurantRepository->hasActiveTable($restaurantFlow)) {
                $slotAtFlow = $slotProvider->buildSlotAt($dayFlow, $flowSlotHi);
            }

            $freeForPost = [];
            if (null !== $restaurantFlow && null !== $zoneFlow && null !== $dayFlow && null !== $slotAtFlow && $flowParty >= 1) {
                $freeForPost = $planningAvailability->findFreeTablesForSlot(
                    $restaurantFlow,
                    $flowParty,
                    $zoneFlow,
                    $dayFlow->setTime(0, 0, 0),
                    $slotAtFlow,
                );
            }

            if ([] === $freeForPost) {
                $this->addFlash(
                    'error',
                    null === $slotAtFlow
                        ? 'Créneau ou informations de réservation incomplètes (rechargez la page et vérifiez date, lieu, midi/soir puis l’horaire).'
                        : 'Aucune table libre pour ce créneau et cet emplacement. Essayez autre zone (intérieur / extérieur) ou changement d’heure.',
                );
            } else {
                $finishModel = new ReservationFinishFormModel();
                $session = $request->getSession();
                $this->hydrateGuestDraftFromSession($finishModel, $session);
                $finishForm = $this->createForm(ReservationFinishFormType::class, $finishModel, [
                    'allow_submit' => true,
                ]);
                $finishForm->handleRequest($request);
                $finishFormView = $finishForm->createView();

                if ($finishForm->isSubmitted() && !$finishForm->isValid()) {
                    $this->persistGuestDraftToSession($finishModel, $session);
                }

                if ($finishForm->isSubmitted() && $finishForm->isValid()) {
                    assert(null !== $restaurantFlow && null !== $zoneFlow && null !== $dayFlow && null !== $slotAtFlow);

                    $reservationDay = \DateTimeImmutable::createFromFormat('!Y-m-d', $dayFlow->format('Y-m-d'));
                    if (false === $reservationDay) {
                        $this->addFlash('error', 'Date incohérente.');
                    } else {
                        try {
                            $reservation = $bookingService->book(new ReservationBookingRequest(
                                $restaurantFlow,
                                $reservationDay,
                                $slotAtFlow,
                                $flowParty,
                                (string) $finishModel->getGuestName(),
                                (string) $finishModel->getGuestEmail(),
                                (string) $finishModel->getGuestPhone(),
                                $zoneFlow,
                            ));
                            $session->remove(self::SESSION_GUEST_DRAFT);

                            return $this->redirectToRoute('app_reservation_confirmation', [
                                'id' => $reservation->getId(),
                            ]);
                        } catch (NoTableAvailableException) {
                            $this->addFlash('error', 'Plus de table disponible pour ce créneau. Actualisez la page ou choisissez un autre horaire.');
                        }
                    }
                }
            }
        }

        $hasRestaurantAndParty = null !== $selectedRestaurant && $partySize >= 1
            && \in_array((int) $selectedRestaurant->getId(), $allowedIds, true);

        /** @var list<array{date: \DateTimeImmutable, available: bool}> $calendarWindow */
        $calendarWindow = [];
        /** @var list<list<array{date: \DateTimeImmutable, available: bool}>> $calendarWeeks */
        $calendarWeeks = [];
        $calendarHasAvailability = false;
        if ($hasRestaurantAndParty && $restaurantRepository->hasActiveTable($selectedRestaurant)) {
            $rangeStart = $today->modify('+'.($datePage * self::DAYS_PER_PAGE).' days');
            for ($i = 0; $i < self::DAYS_PER_PAGE; ++$i) {
                $d = $rangeStart->modify('+'.$i.' days');
                $available = $d >= $today
                    && $planningAvailability->dayHasAvailableSlotIgnoringZone($selectedRestaurant, $partySize, $d);
                if ($available) {
                    $calendarHasAvailability = true;
                }
                $calendarWindow[] = ['date' => $d, 'available' => $available];
            }
            $calendarWeeks = array_chunk($calendarWindow, 7);
        }

        $mealFromRequest = $this->resolveMealFromQuery($request, $flowMealFromPost);

        /** @var list<array{hi: string, label: string, freeTables: list<\App\Entity\RestaurantTable>}> $slotAvailabilityRowsAll */
        $slotAvailabilityRowsAll = [];
        if ($hasRestaurantAndParty && null !== $selectedDate && null !== $selectedZone
            && $restaurantRepository->hasActiveTable($selectedRestaurant)) {
            $dayStartForSlots = $selectedDate->setTime(0, 0, 0);
            foreach ($slotProvider->getFormChoicesForDay($selectedDate) as $label => $hi) {
                $tablesForSlot = [];
                $slotAtItem = $slotProvider->buildSlotAt($selectedDate, $hi);
                if (null !== $slotAtItem) {
                    $tablesForSlot = $planningAvailability->findFreeTablesForSlot(
                        $selectedRestaurant,
                        $partySize,
                        $selectedZone,
                        $dayStartForSlots,
                        $slotAtItem,
                    );
                }
                $slotAvailabilityRowsAll[] = [
                    'hi' => $hi,
                    'label' => $label,
                    'freeTables' => $tablesForSlot,
                ];
            }
        }

        $selectedMeal = $mealFromRequest;
        if (null === $selectedMeal && null !== $selectedSlotHi) {
            $selectedMeal = $this->inferMealFromSlotHi($selectedSlotHi);
        }

        /** @var list<array{hi: string, label: string, freeTables: list<\App\Entity\RestaurantTable>}> $slotAvailabilityRows */
        $slotAvailabilityRows = [];
        if (null !== $selectedMeal) {
            foreach ($slotAvailabilityRowsAll as $slotRow) {
                if ($this->slotHiBelongsToMeal($slotRow['hi'], $selectedMeal)) {
                    $slotAvailabilityRows[] = $slotRow;
                }
            }
        }

        $slotHiForBooking = null;
        if (null !== $selectedSlotHi
            && (null === $mealFromRequest || $this->slotMatchesRequestedMeal($selectedSlotHi, $mealFromRequest))) {
            $slotHiForBooking = $selectedSlotHi;
        }

        $highlightedSlotHi = $slotHiForBooking;

        $mealForLinks = $mealFromRequest;
        if (null === $mealForLinks && null !== $selectedSlotHi) {
            $mealForLinks = $this->inferMealFromSlotHi($selectedSlotHi);
        }

        $freeTablesPreview = [];
        $showFinalStep = false;

        if ($hasRestaurantAndParty && null !== $selectedDate && null !== $selectedZone && null !== $slotHiForBooking
            && $restaurantRepository->hasActiveTable($selectedRestaurant)) {
            $slotAt = $slotProvider->buildSlotAt($selectedDate, $slotHiForBooking);
            if (null !== $slotAt) {
                $freeTablesPreview = $planningAvailability->findFreeTablesForSlot(
                    $selectedRestaurant,
                    $partySize,
                    $selectedZone,
                    $selectedDate->setTime(0, 0, 0),
                    $slotAt,
                );
                $allowedHi = $slotProvider->getAllowedTimeValues($selectedDate);
                $showFinalStep = [] !== $freeTablesPreview
                    && \in_array($slotHiForBooking, $allowedHi, true);
            }
        }

        $tablesReady = null !== $selectedRestaurant && $hasRestaurantAndParty
            && $restaurantRepository->hasActiveTable($selectedRestaurant);

        $noticeIncompleteTableSetup = [] !== $bookable && null !== $selectedRestaurant && $hasRestaurantAndParty && !$restaurantRepository->hasActiveTable($selectedRestaurant);

        if (null === $finishFormView && $showFinalStep && [] !== $freeTablesPreview) {
            $finishModelGet = new ReservationFinishFormModel();
            $this->hydrateGuestDraftFromSession($finishModelGet, $request->getSession());
            $finishFormGet = $this->createForm(ReservationFinishFormType::class, $finishModelGet, [
                'allow_submit' => true,
            ]);
            $finishFormView = $finishFormGet->createView();
        }

        return $this->render('reservation/book.html.twig', [
            'bookableRestaurants' => $bookable,
            'minDateFilter' => $today->format('Y-m-d'),
            'selectedRestaurant' => $selectedRestaurant,
            'partySize' => $partySize,
            'hasRestaurantAndParty' => $hasRestaurantAndParty,
            'calendarWindow' => $calendarWindow,
            'calendarWeeks' => $calendarWeeks,
            'calendarHasAvailability' => $calendarHasAvailability,
            'datePage' => $datePage,
            'daysPerPage' => self::DAYS_PER_PAGE,
            'selectedDate' => $selectedDate,
            'selectedZone' => $selectedZone,
            'selectedSlotHi' => $selectedSlotHi,
            'highlightedSlotHi' => $highlightedSlotHi,
            'mealFromRequest' => $mealFromRequest,
            'mealForLinks' => $mealForLinks,
            'bookingMeal' => $selectedMeal,
            'slotAvailabilityRows' => $slotAvailabilityRows,
            'freeTablesPreview' => $freeTablesPreview,
            'showFinalStep' => $showFinalStep,
            'finishForm' => $finishFormView,
            'hasBookableRestaurants' => [] !== $bookable,
            'tablesReady' => $tablesReady,
            'noticeIncompleteTableSetup' => $noticeIncompleteTableSetup,
        ]);
    }

    #[Route('/reservation/confirmation/{id}', name: 'app_reservation_confirmation', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function confirmation(Reservation $reservation): Response
    {
        return $this->render('reservation/confirmation.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    /** @param list<Restaurant> $bookable */
    private function resolveRestaurantFromQuery(Request $request, array $bookable): ?Restaurant
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

        $raw = $request->query->get('restaurant');
        if (!\is_string($raw) || !ctype_digit($raw)) {
            $raw = null;
            if ($request->isMethod('POST')) {
                $fr = $request->request->get('flow_restaurant');
                if (\is_string($fr) && ctype_digit($fr) && (int) $fr > 0) {
                    $raw = $fr;
                }
            }
        }
        if (\is_string($raw) && ctype_digit($raw) && isset($byId[(int) $raw])) {
            return $byId[(int) $raw];
        }

        return $bookable[0] ?? null;
    }

    private function resolvePartyFromQuery(Request $request): int
    {
        $p = $request->query->getInt('party');
        if ($p < 1 || $p > 99) {
            $p = $request->isMethod('POST') ? $request->request->getInt('flow_party') : 0;
        }

        return $p >= 1 && $p <= 99 ? $p : 0;
    }

    private function resolveZoneFromQuery(Request $request): ?TableSeatingZone
    {
        $z = $request->query->get('zone');
        if (!\is_string($z) || '' === $z) {
            $z = $request->isMethod('POST') ? $request->request->getString('flow_zone') : '';
        }

        return \is_string($z) && '' !== $z ? TableSeatingZone::tryFrom($z) : null;
    }

    private function resolveSlotHiFromQuery(Request $request): ?string
    {
        $s = $request->query->get('slot');
        if (!\is_string($s) || '' === trim($s)) {
            $s = $request->isMethod('POST') ? $request->request->getString('flow_slot') : '';
        }

        return $this->canonicalizeSlotHi(\is_string($s) ? $s : '');
    }

    /** H:i canonique HH:mm (fuseau DISPLAY géré après par {@see ReservationSlotProvider}). */
    private function canonicalizeSlotHi(string $raw): ?string
    {
        $raw = trim($raw);
        if ('' === $raw) {
            return null;
        }

        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $raw, $m)) {
            return null;
        }

        $h = (int) $m[1];
        $i = (int) $m[2];
        if ($h < 0 || $h > 23 || $i < 0 || $i > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $h, $i);
    }

    private function resolveDateFromQuery(Request $request, \DateTimeZone $tz, \DateTimeImmutable $today): ?\DateTimeImmutable
    {
        $raw = $request->query->get('date');
        if (!\is_string($raw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            $raw = $request->isMethod('POST') ? $request->request->getString('flow_date') : '';
        }
        if (!\is_string($raw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $raw, $tz);
        if (false === $parsed) {
            return null;
        }

        $parsed = $parsed->setTime(0, 0, 0);

        return $parsed < $today ? $today : $parsed;
    }

    /**
     * Même logique que {@see resolveDateFromQuery()} : date passée ramenée au jour « aujourd’hui »
     * (fuseau DISPLAY_TZ), pour que POST final et créneaux {@see ReservationSlotProvider} restent alignés.
     */
    private function normalizeFlowBookingDate(string $raw, \DateTimeZone $tz, \DateTimeImmutable $today): ?\DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $raw, $tz);
        if (false === $parsed) {
            return null;
        }

        $parsed = $parsed->setTime(0, 0, 0);

        return $parsed < $today ? $today : $parsed;
    }

    /** @param list<Restaurant> $bookable */
    private function findRestaurantByIdAmong(int $id, array $bookable): ?Restaurant
    {
        foreach ($bookable as $r) {
            if ($r->getId() === $id) {
                return $r;
            }
        }

        return null;
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

    private function resolveMealFromQuery(Request $request, ?string $flowMealFromPost): ?string
    {
        if ($request->isMethod('POST') && null !== $flowMealFromPost) {
            return $flowMealFromPost;
        }

        $q = $request->query->get('meal');

        return \is_string($q) && \in_array($q, ['midi', 'soir'], true) ? $q : null;
    }

    private function inferMealFromSlotHi(string $slotHi): ?string
    {
        if ($slotHi >= self::MEAL_MIDI_FIRST && $slotHi <= self::MEAL_MIDI_LAST) {
            return 'midi';
        }
        if ($slotHi >= self::MEAL_SOIR_FIRST && $slotHi <= self::MEAL_SOIR_LAST) {
            return 'soir';
        }

        return null;
    }

    private function slotMatchesRequestedMeal(string $slotHi, string $requestedMeal): bool
    {
        $inferred = $this->inferMealFromSlotHi($slotHi);

        return null !== $inferred && $inferred === $requestedMeal;
    }

    /** @param 'midi'|'soir' $meal */
    private function slotHiBelongsToMeal(string $slotHi, string $meal): bool
    {
        return match ($meal) {
            'midi' => $slotHi >= self::MEAL_MIDI_FIRST && $slotHi <= self::MEAL_MIDI_LAST,
            'soir' => $slotHi >= self::MEAL_SOIR_FIRST && $slotHi <= self::MEAL_SOIR_LAST,
            default => false,
        };
    }

    private function hydrateGuestDraftFromSession(ReservationFinishFormModel $model, SessionInterface $session): void
    {
        $draft = $session->get(self::SESSION_GUEST_DRAFT);
        if (!\is_array($draft)) {
            return;
        }
        $name = $draft['guestName'] ?? null;
        $email = $draft['guestEmail'] ?? null;
        $phone = $draft['guestPhone'] ?? null;
        if ((null === $model->getGuestName() || '' === trim((string) $model->getGuestName()))
            && \is_string($name) && '' !== trim($name)) {
            $model->setGuestName($name);
        }
        if ((null === $model->getGuestEmail() || '' === trim((string) $model->getGuestEmail()))
            && \is_string($email) && '' !== trim($email)) {
            $model->setGuestEmail($email);
        }
        if ((null === $model->getGuestPhone() || '' === trim((string) $model->getGuestPhone()))
            && \is_string($phone) && '' !== trim($phone)) {
            $model->setGuestPhone($phone);
        }
    }

    private function persistGuestDraftToSession(ReservationFinishFormModel $model, SessionInterface $session): void
    {
        $session->set(self::SESSION_GUEST_DRAFT, [
            'guestName' => $model->getGuestName(),
            'guestEmail' => $model->getGuestEmail(),
            'guestPhone' => $model->getGuestPhone(),
        ]);
    }
}
