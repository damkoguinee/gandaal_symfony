<?php
namespace App\Controller\Gandaal\Administration\Pedagogie\Admin;

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
use App\Repository\HeureTravailleRepository;
use App\Repository\PaiementSalairePersonnelRepository;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/administration/pedagogie/admin/calendar')]
class CalendarController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_calendar')]
    public function index(Etablissement $etablissement, PersonnelActifRepository $personnelActifRep, ClasseRepartitionRepository $classeRep, Request $request): Response
    {
        $classe = $request->query->get('classe') ? $classeRep->find($request->query->get('classe')) : null;
        $enseignant = $request->query->get('enseignant') ? $personnelActifRep->findOneBy(['personnel' => $request->query->get('enseignant')]) : null;


        return $this->render('gandaal/administration/pedagogie/admin/calendar/index.html.twig', [
            'etablissement' => $etablissement,
            'enseignants' => $personnelActifRep->listeDesEnseignantsActifParEtablissementParPromo($etablissement, $request->getSession()->get('promo')),
            'classes' => $classeRep->listeDesClassesParEtablissementParPromo($etablissement, $request->getSession()->get('promo')),
            'classe' => $classe,
            'enseignant' => $enseignant
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_calendar_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UserRepository $userRep, PersonnelActifRepository $personnelActifRep, ClasseRepartitionRepository $classeRep, MatiereRepository $matiereRep, SessionInterface $session, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {

        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $personnels = $personnelActifRep->rechercheUserParEtablissement($search, $etablissement, $session->get('promo'));    
            $response = [];
            foreach ($personnels as $personnel) {
                $response[] = [
                    'nom' => ucwords($personnel->getPersonnel()->getPrenom())." ".strtoupper($personnel->getPersonnel()->getNom()),
                    'id' => $personnel->getPersonnel()->getId(),

                ]; 
            }
            return new JsonResponse($response);
        }
        $classe = $request->get('classe') ? $classeRep->find($request->get('classe')) : null;
        // dd($classe);
        $personnel = $request->get('enseignant') ? $personnelActifRep->find($request->get('enseignant')) : null;
        $matiere = $request->get('matiere') ? $matiereRep->find($request->get('matiere')) : null;
        if ($request->get('id_user_search')) {
            $personnel = $personnelActifRep->findOneBy(['personnel' => $request->get('id_user_search')]); 
           
        } 
        // dd($classe);

        $matieres = $matiereRep->listeMatiereParFormation($classe ? $classe->getFormation(): null);

        if ($request->get('ajout_cours')) {
            $matiere = $matiereRep->find($request->get('matiere'));
            $titre = $request->get('titre');
            $frequence = $request->get('frequence');
            $commentaire = $request->get('commentaire');

            $date = $request->get('date');
            $heure_debut = $request->get('heure_debut');
            $heure_fin = $request->get('heure_fin');
            // Combinaison de la date et de l'heure de début et de fin
            $start = new \DateTime($date . ' ' . $heure_debut);
            $end = new \DateTime($date . ' ' . $heure_fin);
            // Calcul de la différence
            $interval = $start->diff($end);            
            // Calcul du nombre total d'heures
            $totalHeures = $interval->h + ($interval->days * 24); // On prend en compte les jours pour calculer les heures totales

            // Date limite pour la répétition (20 juin de l'année en cours)
            $limitDate = new \DateTime($start->format('Y') . '-06-20');
            // dd($limitDate, $frequence);
           
            if ($frequence === 'periodique') {
                $currentYear = $start->format('Y');

                // Définir la date limite pour le 20 juin de l'année en cours ou de l'année suivante
                $limitDate = new \DateTime("$currentYear-06-20");

                // Si la date de début est après la date limite, utiliser l'année suivante
                if ($start > $limitDate) {
                    $limitDate->modify('+1 year');
                }
                while ($start <= $limitDate) {
                    // Créer un nouvel événement pour chaque occurrence
                    $event = new Event();
                    $event->setEnseignant($personnel)
                        ->setClasse($classe)
                        ->setMatiere($matiere)
                        ->setTitle($titre)
                        ->setStart(clone $start)  // Cloner pour éviter de modifier l'original
                        ->setEnd(clone $end)      // Cloner pour éviter de modifier l'original
                        ->setPromo($session->get('promo'))
                        ->setDuree($totalHeures)
                        ->setDescription($commentaire)
                        // ->setUrl('edit/'.$etablissement->getId())
                        ->setEtablissement($etablissement)
                        ->setSaisiePar($this->getUser())
                        ->setDateSaisie(new \DateTime("now"));
    
                    // Enregistrer l'événement dans la base de données
                    $entityManager->persist($event);
    
                    // Ajouter une semaine à la date de début et de fin pour la prochaine occurrence
                    $start->modify('+1 week');
                    $end->modify('+1 week');
    
                    // Appeler flush() à chaque itération pour s'assurer que l'événement est inséré
                }
                $entityManager->flush();
            }else{
                $event = new Event();
                $event->setEnseignant($personnel)
                    ->setClasse($classe)
                    ->setMatiere($matiere)
                    ->setTitle($titre)
                    ->setStart(clone $start)  // Cloner pour éviter de modifier l'original
                    ->setEnd(clone $end)      // Cloner pour éviter de modifier l'original
                    ->setPromo($session->get('promo'))
                    ->setDuree($totalHeures)
                    ->setDescription($commentaire)
                    // ->setUrl('edit/'.$etablissement->getId())
                    ->setEtablissement($etablissement)
                    ->setSaisiePar($this->getUser())
                    ->setDateSaisie(new \DateTime("now"));

                // Enregistrer l'événement dans la base de données
                $entityManager->persist($event);
                $entityManager->flush();
            }

            $this->addFlash("success", "Cours enregistrés avec succés :) ");
            // $referer = $request->headers->get('referer');
            // return $this->redirect($referer);
            // Redirection après traitement pour éviter la soumission multiple
            return $this->redirectToRoute('app_gandaal_administration_pedagogie_admin_calendar_new', [
                'etablissement' => $etablissement->getId(),
                'classe' => $classe->getId(),
                'id_user_search' => $personnel->getPersonnel()->getId(),
                'matiere' => $matiere->getId()                
            ]);

        }

        return $this->render('gandaal/administration/pedagogie/admin/calendar/new.html.twig', [
            'etablissement' => $etablissement,
            'dernier_promo' => $session->get('promo'),
            'personnelActif' => $personnel,
            'classe' => $classe,
            'enseignants' => $personnelActifRep->listeDesEnseignantsActifParEtablissementParPromo($etablissement, $request->getSession()->get('promo')),
            'classes' => $classeRep->listeDesClassesParEtablissementParPromo($etablissement, $request->getSession()->get('promo')),
            'matieres' => $matieres,
            'matiere' => $matiere,
            'event' => Null
        ]);
    }

    #[Route('/edit/{event}', name: 'app_gandaal_administration_pedagogie_admin_calendar_event', methods: ['GET', 'POST'])]
    public function event(Request $request, Event $event, PersonnelActifRepository $personnelActifRep, ClasseRepartitionRepository $classeRep, MatiereRepository $matiereRep, SessionInterface $session, EtablissementRepository $etablissementRep, EventRepository $eventRep, EntityManagerInterface $entityManager): Response
    {
        $etablissement = $event->getEtablissement() ;

        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $personnels = $personnelActifRep->rechercheEnseignantActifParEtablissement($search, $etablissement, $session->get('promo'));  
              
            $response = [];
            foreach ($personnels as $personnel) {
                $response[] = [
                    'nom' => ucwords($personnel->getPersonnel()->getPrenom())." ".strtoupper($personnel->getPersonnel()->getNom()),
                    'id' => $personnel->getPersonnel()->getId(),

                ]; 
            }
            return new JsonResponse($response);
        }
        $classe = $request->get('classe') ? $classeRep->find($request->get('classe')) : $event->getClasse();
        $personnel = $request->get('enseignant') ? $personnelActifRep->find($request->get('enseignant')) : $event->getEnseignant();
        $matiere = $request->get('matiere') ? $matiereRep->find($request->get('matiere')) : $event->getMatiere();
        if ($request->get('id_user_search')) {
            $personnel = $personnelActifRep->findOneBy(['personnel' => $request->get('id_user_search')]); 
           
        } 

        $matieres = $matiereRep->listeMatiereParFormation($classe ? $classe->getFormation(): null);

        if ($request->get('ajout_cours') and $request->get('event_id')) {
            $matiere = $matiereRep->find($request->get('matiere'));
            $titre = $request->get('titre');
            $frequence = $request->get('frequence');
            $commentaire = $request->get('commentaire');

            $date = $request->get('date');
            $heure_debut = $request->get('heure_debut');
            $heure_fin = $request->get('heure_fin');
            // Combinaison de la date et de l'heure de début et de fin
            $start = new \DateTime($date . ' ' . $heure_debut);
            $end = new \DateTime($date . ' ' . $heure_fin);
            // Calcul de la différence
            $interval = $start->diff($end);            
            // Calcul du nombre total d'heures
            $totalHeures = $interval->h + ($interval->days * 24); // On prend en compte les jours pour calculer les heures totales

            // Date limite pour la répétition (20 juin de l'année en cours)
            $limitDate = new \DateTime($start->format('Y') . '-06-20');
           
            // gestion de la suppression avant de mettre à jour           
            if ($frequence === 'periodique') {
                $etablissement = $event->getEtablissement();
                $classe_update = $event->getClasse();
                $personnel_update = $event->getEnseignant();
                $matiere_update = $event->getMatiere();
                $promo = $session->get('promo');
                // Obtenir le jour de la semaine et l'heure de début de l'événement actuel
                $currentDayOfWeek = $event->getStart()->format('N'); // 'N' retourne le jour de la semaine (1 pour lundi, 7 pour dimanche)
                $currentStartHour = $event->getStart()->format('H:i:s'); // Heure de début formatée

                $events = $eventRep->evenementSimulaire($personnel_update, $classe_update, $matiere_update, $promo, $currentDayOfWeek, $currentStartHour);
                foreach ($events as $value) {
                    $entityManager->remove($value);
                    $entityManager->flush();
                }
            }else{
                $entityManager->remove($event);
                $entityManager->flush();
            }
            if ($frequence === 'periodique') {
                $firstEvent = $events[0];
                $currentYear = $start->format('Y');
                $limitDate = new \DateTime("$currentYear-06-20");
                
                // Si la date de début est après la date limite, utiliser l'année suivante
                if ($start > $limitDate) {
                    $limitDate->modify('+1 year');
                }
                while ($start <= $limitDate) {
                    // Créer un nouvel événement pour chaque occurrence
                    $event = new Event();
                    $event->setEnseignant($personnel)
                        ->setClasse($classe)
                        ->setMatiere($matiere)
                        ->setTitle($titre)
                        ->setStart(clone $start)  // Cloner pour éviter de modifier l'original
                        ->setEnd(clone $end)      // Cloner pour éviter de modifier l'original
                        ->setPromo($session->get('promo'))
                        ->setDuree($totalHeures)
                        // ->setUrl('edit/'.$etablissement->getId())
                        ->setEtablissement($etablissement)
                        ->setSaisiePar($this->getUser())
                        ->setDateSaisie(new \DateTime("now"));
    
                    // Enregistrer l'événement dans la base de données
                    $entityManager->persist($event);
    
                    // Ajouter une semaine à la date de début et de fin pour la prochaine occurrence
                    $start->modify('+1 week');
                    $end->modify('+1 week');
    
                    // Appeler flush() à chaque itération pour s'assurer que l'événement est inséré
                }
                $entityManager->flush();
            }else{
                $event = new Event();
                $event->setEnseignant($personnel)
                    ->setClasse($classe)
                    ->setMatiere($matiere)
                    ->setTitle($titre)
                    ->setStart(clone $start)  // Cloner pour éviter de modifier l'original
                    ->setEnd(clone $end)      // Cloner pour éviter de modifier l'original
                    ->setPromo($session->get('promo'))
                    ->setDuree($totalHeures)
                    // ->setUrl('edit/'.$etablissement->getId())
                    ->setEtablissement($etablissement)
                    ->setSaisiePar($this->getUser())
                    ->setDateSaisie(new \DateTime("now"));

                // Enregistrer l'événement dans la base de données
                $entityManager->persist($event);
                $entityManager->flush();
            }

            $this->addFlash("success", "Cours enregistrés avec succés :) ");
            // $referer = $request->headers->get('referer');
            // return $this->redirect($referer);
            // Redirection après traitement pour éviter la soumission multiple
            return $this->redirectToRoute('app_gandaal_administration_pedagogie_admin_calendar', [
                'etablissement' => $etablissement->getId(),
                'enseignant' => $personnel->getId(),               
            ]);

        }

        return $this->render('gandaal/administration/pedagogie/admin/calendar/edit.html.twig', [
            'etablissement' => $etablissement,
            'dernier_promo' => $session->get('promo'),
            'personnelActif' => $personnel,
            'classe' => $classe,
            'enseignants' => $personnelActifRep->listeDesEnseignantsActifParEtablissementParPromo($etablissement, $request->getSession()->get('promo')),
            'classes' => $classeRep->listeDesClassesParEtablissementParPromo($etablissement, $request->getSession()->get('promo')),
            'matieres' => $matieres,
            'matiere' => $matiere,
            'event' => $event
        ]);
    }

    

    #[Route('/api/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_calendar_api')]
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
                'url' => 'edit/'.$event->getId(),
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

    #[Route('/confirm/delete/{id}/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_calendar_confirm_delete', methods: ['GET', 'POST'])]
    public function confirmDelete(Event $event, Request $request, Etablissement $etablissement): Response
    {
        $param = $request->request->get('param'); // Récupération du paramètre

        if ($param === 'simple') {
            // Code spécifique pour le paramètre "simple"
            $route_suppression = $this->generateUrl('app_gandaal_administration_pedagogie_admin_calendar_delete', [
                'id' => $event->getId(),
                'etablissement' => $etablissement->getId()
            ]);
        }elseif ($param === 'general') {
            $route_suppression = $this->generateUrl('app_gandaal_administration_pedagogie_admin_calendar_delete_liaison', [
                'id' => $event->getId(),
                'etablissement' => $etablissement->getId()
            ]);
        }

        return $this->render('gandaal/administration/pedagogie/admin/calendar/confirm_delete.html.twig', [
            'event' => $event,
            'etablissement' => $etablissement,
            'route_suppression' => $route_suppression,
            'param' => $param,
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_calendar_delete', methods: ['POST'])]
    public function delete(Request $request, Etablissement $etablissement, SessionInterface $session, Event $event, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrfTokenManager): Response
    {        
        // Vérification du token CSRF
        if ($this->isCsrfTokenValid('delete'.$event->getId(), $request->request->get('_token'))) {
            // Récupérer le motif de suppression
            $deleteReason = $request->request->get('delete_reason');
            // Configurer le formateur pour obtenir le jour en français
            $formatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE);
            $formatter->setPattern('EEEE'); // 'EEEE' pour le nom complet du jour de la semaine

            // Obtenir le nom du jour en français
            $jourEnFrancais = $formatter->format($event->getStart());

            // Construire la chaîne d'information
            $information = 'enseignant concerné ' . $event->getEnseignant()->getPersonnel()->getNomComplet() . 
                        ' Classe concernée ' . $event->getClasse()->getNom() . 
                        ' période ' . ucfirst($jourEnFrancais) . ' ' . $event->getStart()->format('H:i');

           
            $historique = new HistoriqueSuppression();
            $historique->setType('emploi du temps') // ou un type plus spécifique
                ->setMotif($deleteReason)
                ->setOrigine('pédagogie')
                ->setDateOperation(new \DateTime())
                ->setInformation($information)
                ->setSaisiePar($this->getUser())
                ->setPromo($session->get('promo'))
                ->setUser($event->getEnseignant()->getPersonnel());
                
            $entityManager->persist($historique);
            
            // Suppression de l'entité
            $entityManager->remove($event);
            $entityManager->flush();
        }

        // Redirection après suppression
        return $this->redirectToRoute('app_gandaal_administration_pedagogie_admin_calendar', [
            'etablissement' => $etablissement->getId(),
            'enseignant' => $event->getEnseignant()->getPersonnel()->getId()
        ], Response::HTTP_SEE_OTHER);
    }


    #[Route('/delete/liaison/{id}/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_calendar_delete_liaison', methods: ['POST'])]
    public function deleteLiaison(Request $request, Etablissement $etablissement, Event $event, eventRepository $eventRep, SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $deleteReason = $request->request->get('delete_reason');

            $etablissement = $event->getEtablissement();
            $classe_update = $event->getClasse();
            $personnel_update = $event->getEnseignant();
            $matiere_update = $event->getMatiere();
            $promo = $session->get('promo');
            // Obtenir le jour de la semaine et l'heure de début de l'événement actuel
            $currentDayOfWeek = $event->getStart()->format('N'); // 'N' retourne le jour de la semaine (1 pour lundi, 7 pour dimanche)
            $currentStartHour = $event->getStart()->format('H:i:s'); // Heure de début formatée

            $events = $eventRep->evenementSimulaire($personnel_update, $classe_update, $matiere_update, $promo, $currentDayOfWeek, $currentStartHour);
            
            foreach ($events as $event_sup) {

                $formatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE);
                $formatter->setPattern('EEEE'); 
                $jourEnFrancais = $formatter->format($event_sup->getStart());
                $information = 'enseignant concerné ' . $event_sup->getEnseignant()->getPersonnel()->getNomComplet() . 
                            ' Classe concernée ' . $event_sup->getClasse()->getNom() . 
                            ' période ' . ucfirst($jourEnFrancais) . ' ' . $event_sup->getStart()->format('H:i');

           
                $historique = new HistoriqueSuppression();
                $historique->setType('emploi du temps') // ou un type plus spécifique
                    ->setMotif($deleteReason)
                    ->setOrigine('pédagogie')
                    ->setDateOperation(new \DateTime())
                    ->setInformation($information)
                    ->setSaisiePar($this->getUser())
                    ->setPromo($session->get('promo'))
                    ->setUser($event_sup->getEnseignant()->getPersonnel());
                $entityManager->persist($historique);
                $entityManager->remove($event_sup);                

            }
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_pedagogie_admin_calendar', [
            'etablissement' => $etablissement->getId(), 
            'enseignant' => $event->getEnseignant()->getPersonnel()->getId()
        ], Response::HTTP_SEE_OTHER);
    }


    #[Route('/horaire/planifiee/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_horaire_planifiee')]
    public function horairePlanifiee(Etablissement $etablissement, SessionInterface $session, EventRepository $eventRep, PaiementSalairePersonnelRepository $paiementRep, HeureTravailleRepository $heureTravailleRep, Request $request): Response
    {
        $jour = $request->query->get('jour') ?:date('Y-m-d');
        $search = $request->query->get('search') ?:null;
        $events= $eventRep->listeEvenementNonTransmiseParPeriodeParParPromoEtablissement($jour, $session->get('promo'), $etablissement, $search);

        // dd($events);


        return $this->render('gandaal/administration/pedagogie/admin/calendar/horaire_planifiee.html.twig', [
            'etablissement' => $etablissement,
            'jour' => $jour,
            'events' => $events

        ]);
    }


    


}