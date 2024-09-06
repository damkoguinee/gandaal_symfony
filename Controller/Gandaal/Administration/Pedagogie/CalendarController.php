<?php
namespace App\Controller\Gandaal\Administration\Pedagogie;

use App\Entity\ControlEleve;
use App\Entity\Event;
use App\Entity\Etablissement;
use App\Repository\UserRepository;
use App\Repository\EventRepository;
use App\Entity\HistoriqueSuppression;
use App\Repository\MatiereRepository;
use Doctrine\ORM\EntityManagerInterface;
use phpDocumentor\Reflection\Types\Null_;
use App\Repository\EtablissementRepository;
use App\Repository\PersonnelActifRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\ClasseRepartitionRepository;
use App\Repository\ControlEleveRepository;
use App\Repository\HeureTravailleRepository;
use App\Repository\InscriptionRepository;
use App\Repository\PaiementSalairePersonnelRepository;
use App\Service\TrieService;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/administration/pedagogie/calendar/jour')]
class CalendarController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_administration_pedagogie_calendar_jour')]
    public function index(Etablissement $etablissement, PersonnelActifRepository $personnelActifRep, ClasseRepartitionRepository $classeRep, EventRepository $eventRep, SessionInterface $session, InscriptionRepository $inscriptionRep, ControlEleveRepository $controlEleveRep, TrieService $trieService, Request $request, EntityManagerInterface $em): Response
    {
        $classe = $request->query->get('classe') ? $classeRep->find($request->query->get('classe')) : null;
        $event = $request->query->get('event') ? $eventRep->find($request->query->get('event')) : null;
    
        // Gestion des absences, absences globales, retards et exclusions
        $absence = $request->get('absence');  // Case à cocher d'absence individuelle
        $absenceGlobal = $request->get('absenceGlobal');  // Sélection de l'absence globale (journée, matinée, soirée)
        $retard = $request->get('retard');
        $exclusion = $request->get('exclusion');
        $inscription = $request->get('inscription') ? $inscriptionRep->find($request->get('inscription')) : null;
    
        // Déterminer le type de contrôle en fonction des entrées
        if ($absence) {
            $type = 'absence';  // Absence individuelle via checkbox
        } elseif ($absenceGlobal) {
            $type = 'absence global';  // Absence globale via select
        } elseif ($retard && $retard > 0) {
            $type = 'retard';
        } elseif ($exclusion) {
            $type = 'exclusion';
        } else {
            $type = null;
        }
        
    
        // Suppression du contrôle si aucune valeur n'est définie
        if (!$absence && !$absenceGlobal && (!$retard || $retard == 0) && !$exclusion) {
            $verif_control = $controlEleveRep->findOneBy([
                'inscription' => $inscription,
                'event' => $event,
                'dateControl' => ($event ? $event->getStart() : new \DateTime("now")),
            ]);
    
            if ($verif_control) {
                $em->remove($verif_control);  // Supprimer le contrôle existant
                $em->flush();
            }
        } elseif ($type) {
            // Supprimer le contrôle existant pour cet élève à cet événement
            $verif_control = $controlEleveRep->findOneBy([
                'inscription' => $inscription,
                'event' => $event,
                'dateControl' => ($event ? $event->getStart() : new \DateTime("now")),
            ]);
    
            if ($verif_control) {
                $em->remove($verif_control);
                $em->flush();
            }

            /* gestion des absences globales */

            if ($type == 'absence global') {  
                $verif_control = $controlEleveRep->findBy([
                    'inscription' => $inscription,
                    'type' => $type,
                    'dateControl' => ($event ? $event->getStart() : new \DateTime("now")),
                ]);

                if ($verif_control) {

                    foreach ($verif_control as $verif) {                    
                        $em->remove($verif);
                        $em->flush();
                    }
                }
        

                $dateJour = ($event->getStart())->format('Y-m-d');
                /* recupération des évenements de la matinée entre 7h00 et 13h00*/
                $events = $eventRep->listeEvenementParClasseParTypeParPromoParEtablissementCompriseEntre($classe, $absenceGlobal, $dateJour, $dateJour, $session->get('promo'), $etablissement);

                foreach ($events as $event) {
                    // Créer un nouveau contrôle pour cet élève
                    $controlEleve = new ControlEleve();
                    $controlEleve->setEvent($event)
                        ->setEtablissement($etablissement)
                        ->setPromo($session->get('promo'))
                        ->setSaisiePar($this->getUser())
                        ->setDateSaisie(new \DateTime("now"))
                        ->setDateControl($event ? $event->getStart() : new \DateTime("now"))
                        ->setType($type)
                        ->setInscription($inscription)
                        ->setCommentaire($exclusion ?: ($absenceGlobal ?: $absence))
                        ->setDuree($event ? $event->getDuree() : '');
                    $em->persist($controlEleve);  
                }
                $em->flush();
                $referer = $request->headers->get('referer');
                return $this->redirect($referer);
            }
    
            // Créer un nouveau contrôle pour cet élève
            $controlEleve = new ControlEleve();
            $controlEleve->setEvent($event)
                ->setEtablissement($etablissement)
                ->setPromo($session->get('promo'))
                ->setSaisiePar($this->getUser())
                ->setDateSaisie(new \DateTime("now"))
                ->setDateControl($event ? $event->getStart() : new \DateTime("now"))
                ->setType($type)
                ->setInscription($inscription)
                ->setCommentaire($exclusion ?: ($absenceGlobal ?: $absence));
    
            // Si le type est "retard", on définit la durée
            if ($type == 'retard') {
                $controlEleve->setDuree($retard);
            } else {
                // Pour les autres types, la durée est celle de l'événement
                $controlEleve->setDuree($event ? $event->getDuree() : '');
            }
    
            $em->persist($controlEleve);  // Persister la nouvelle entité
            $em->flush();
    
            $referer = $request->headers->get('referer');
            return $this->redirect($referer);
        }
   
        $inscriptions = $classe ? $classe->getInscriptions()->toArray() : [];
        $sortedInscriptions = $trieService->trieInscriptions($inscriptions);

        // Récupérer les contrôles pour l'événement
        $controls = $controlEleveRep->findBy(['event' => $event]);

        // Créer un tableau associatif pour faciliter l'accès aux contrôles par inscription
        $controlsByInscription = [];
        foreach ($controls as $control) {
            $controlsByInscription[$control->getInscription()->getId()] = $control;
        }

        // Variables pour les totaux
        $totalAbsences = 0;
        $totalRetards = 0;
        $totalMinutesRetard = 0;
        $totalExclusions = 0;

        // Parcourir les inscriptions pour compter les types d'actions
        foreach ($inscriptions as $inscription) {
            $control = $controlsByInscription[$inscription->getId()] ?? null;

            if ($control) {
                switch ($control->getType()) {
                    case 'absence':
                    case 'absence global':  // Traiter les absences globales comme des absences
                        $totalAbsences++;
                        break;
                    case 'retard':
                        $totalRetards++;
                        $totalMinutesRetard += $control->getDuree();
                        break;
                    case 'exclusion':
                        $totalExclusions++;
                        break;
                }
            }
        }

        // Passer ces données à la vue
        return $this->render('gandaal/administration/pedagogie/general/presence_cours/index.html.twig', [
            'etablissement' => $etablissement,
            'enseignants' => $personnelActifRep->listeDesEnseignantsActifParEtablissementParPromo($etablissement, $request->getSession()->get('promo')),
            'classes' => $classeRep->listeDesClassesParEtablissementParPromo($etablissement, $request->getSession()->get('promo')),
            'classe' => $classe,
            'event' => $event,
            'inscriptions' => $sortedInscriptions,
            'controlsByInscription' => $controlsByInscription, // Nouveau tableau avec les contrôles par inscription
            'totalAbsences' => $totalAbsences,
            'totalRetards' => $totalRetards,
            'totalMinutesRetard' => $totalMinutesRetard,
            'totalExclusions' => $totalExclusions,
        ]);
    }

    #[Route('/new/{etablissement}/{event}', name: 'app_gandaal_administration_pedagogie_calendar_jour_new')]
    public function new(Etablissement $etablissement, Event $event, PersonnelActifRepository $personnelActifRep, ClasseRepartitionRepository $classeRep, TrieService $trieService, Request $request): Response
    {
        // dd($event);
        return $this->redirectToRoute('app_gandaal_administration_pedagogie_calendar_jour', ['etablissement' => $etablissement->getId(), 'classe' => $event->getClasse()->getId(), 'event' => $event->getId()], Response::HTTP_SEE_OTHER);

        $classe = $request->query->get('classe') ? $classeRep->find($request->query->get('classe')) : null;
        $enseignant = $request->query->get('enseignant') ? $personnelActifRep->findOneBy(['personnel' => $request->query->get('enseignant')]) : null;

        $inscriptions = $classe ? $classe->getInscriptions()->toArray() : []; // Convertir la collection en tableau
        $sortedInscriptions = $trieService->trieInscriptions($inscriptions);

        return $this->render('gandaal/administration/pedagogie/general/presence_cours/index.html.twig', [
            'etablissement' => $etablissement,
            'enseignants' => $personnelActifRep->listeDesEnseignantsActifParEtablissementParPromo($etablissement, $request->getSession()->get('promo')),
            'classes' => $classeRep->listeDesClassesParEtablissementParPromo($etablissement, $request->getSession()->get('promo')),
            'classe' => $classe,
            'enseignant' => $enseignant,
            'inscriptions' => $sortedInscriptions,
            'event' => $event,
        ]);
    }

    

    #[Route('/api/{etablissement}', name: 'app_gandaal_administration_pedagogie_calendar_jour_api')]
    public function events(Etablissement $etablissement, ClasseRepartitionRepository $classeRep, UserRepository $userRep, EventRepository $eventRepository, SessionInterface $session, PersonnelActifRepository $personnelActifRep, Request $request): JsonResponse
    {
        $classe = $request->query->get('classe') ? $classeRep->find($request->query->get('classe')) : null;
        $enseignant = $request->query->get('enseignant') ? $personnelActifRep->findOneBy(['personnel' => $request->query->get('enseignant')]) : null;

        $criteria = ['etablissement' => $etablissement];

        if ($classe) {
            $criteria['classe'] = $classe;
        }

        if ($enseignant) {
            $criteria['enseignant'] = $enseignant;
        }

        // dd($enseignant);

        // Si une classe ou un enseignant est sélectionné
        if ($classe || $enseignant) {
            $events = $eventRepository->findBy($criteria);
        } else {
            // Sinon, récupérer un ensemble par défaut d'événements
            $defaultClasse = $classeRep->findOneBy(['promo' => $session->get('promo')]);
            $criteria['classe'] = $defaultClasse;
            $events = $eventRepository->findBy($criteria);
        }

        // Formatage des événements pour l'API
        $formattedEvents = array_map(function ($event) {
            return [
                'id' => $event->getId(),
                'title' => ucwords($event->getTitle()),
                'start' => $event->getStart()->format('Y-m-d\TH:i:s'),
                'end' => $event->getEnd() ? $event->getEnd()->format('Y-m-d\TH:i:s') : null,
                'allDay' => $event->isAllDay(),
                'url' => 'new/'.$event->getEtablissement()->getId().'/'.$event->getId(),
                'className' => $event->getClassName(),
                'backgroundColor' => $event->getBackgroundColor(),
                'borderColor' => $event->getBorderColor(),
                'textColor' => $event->getTextColor(),
                'classe' => $event->getClasse() ? $event->getClasse()->getNom() : null,
                'enseignant' => $event->getEnseignant() ? $event->getEnseignant()->getPersonnel()->getNomComplet() : null,
                'matiere' => $event->getMatiere() ? ucwords($event->getMatiere()->getNom()) : null,
            ];
        }, $events);

        return new JsonResponse($formattedEvents);
    }

    


   


    


}