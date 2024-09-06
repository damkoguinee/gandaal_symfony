<?php

namespace App\Controller\Gandaal\Administration\Pedagogie\Admin;

use DateTime;
use App\Entity\Event;
use App\Entity\Etablissement;
use App\Entity\HeureTravaille;
use App\Form\HeureTravailleType;
use App\Repository\EventRepository;
use App\Entity\HistoriqueSuppression;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\HeureTravailleRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\PaiementSalairePersonnelRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/administration/pedagogie/admin/heure/travaille')]
class HeureTravailleController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_heure_travaillee')]
    public function horaireTravaillee(Etablissement $etablissement, SessionInterface $session, HeureTravailleRepository $heureTravailleRep, Request $request): Response
    {
        $periode_select = $request->get("periode") ?:date('m');
        $periode = date("Y") . '-' . $periode_select  . '-01';
        // dd($periode, $periode_select);
        $search = $request->query->get('search') ?:null;
        $heureTravaillees = $heureTravailleRep->listeEvenementTransmiseParPeriodeParParPromoEtablissement($periode, $session->get('promo'), $etablissement, $search);

        // dd($heureTravaillees);


        return $this->render('gandaal/administration/pedagogie/admin/heure_travaille/index.html.twig', [
            'etablissement' => $etablissement,
            'periode_select' => $periode_select,
            'periode' => $periode,
            'heureTravaillees' => $heureTravaillees

        ]);
    }

    #[Route('/new/{event}/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_heure_travaille_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Event $event, Etablissement $etablissement, SessionInterface $session, PaiementSalairePersonnelRepository $paiementRep, EntityManagerInterface $entityManager): Response
    {
        $periode = (new \DateTime($request->get('periode')));
        $heurePrev =$request->get('heurePrev');
        $heureReal =$request->get('heureReal');
        $verif_paie = $paiementRep->paiementPersonnelParPeriodeParParPromoEtablissement($event->getEnseignant(), $request->get('periode'), $session->get('promo'), $etablissement);
        
        if (!$verif_paie) {
            // s'il n' y a pas eu de paiement pour le mois concerné, on transmis sinon on rejete
            $heureTravaille = new HeureTravaille();
            $heureTravaille->setEvent($event)
                    ->setPeriode($periode)
                    ->setHeurePrev($heurePrev)
                    ->setHeureReel($heureReal)
                    ->setPromo($session->get('promo'))
                    ->setSaisiePar($this->getUser())
                    ->setDateSaisie(new \DateTime())
                    ->setEtablissement($etablissement);        
            $entityManager->persist($heureTravaille);
            $entityManager->flush();
            $this->addFlash("success", "Heure(s) transmise(s) avec succès :)");

        }else{
            $this->addFlash("warning", "Heure(s) non transmise(s) car il y a eu un paiement pour la période concernée ");
        }

        $referer = $request->headers->get('referer');
        return $this->redirect($referer);
        // return $this->redirectToRoute('app_gandaal_administration_pedagogie_admin_heure_travaille_index', [], Response::HTTP_SEE_OTHER);
        

        return $this->render('gandaal/administration/pedagogie/admin/heure_travaille/new.html.twig', [
            'heure_travaille' => $heureTravaille,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_heure_travaille_show', methods: ['GET'])]
    public function show(HeureTravaille $heureTravaille, Etablissement $etablissement): Response
    {
        return $this->render('gandaal/administration/pedagogie/admin/heure_travaille/show.html.twig', [
            'heure_travaille' => $heureTravaille,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/edit/{id}/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_heure_travaille_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, HeureTravaille $heureTravaille, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(HeureTravailleType::class, $heureTravaille);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_pedagogie_admin_heure_travaille_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/pedagogie/admin/heure_travaille/edit.html.twig', [
            'heure_travaille' => $heureTravaille,
            'form' => $form,
        ]);
    }

    #[Route('/confirm/delete/{id}/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_heure_travaille_confirm_delete', methods: ['GET', 'POST'])]
    public function confirmDelete(HeureTravaille $heureTravaille, Request $request, Etablissement $etablissement): Response
    {
        $param = $request->request->get('param'); // Récupération du paramètre
        // Code spécifique pour le paramètre "simple"
        $route_suppression = $this->generateUrl('app_gandaal_administration_pedagogie_admin_heure_travaille_delete', [
            'id' => $heureTravaille->getId(),
            'etablissement' => $etablissement->getId()
        ]);
        

        return $this->render('gandaal/administration/pedagogie/admin/heure_travaille/confirm_delete.html.twig', [
            'heure_travaille' => $heureTravaille,
            'etablissement' => $etablissement,
            'route_suppression' => $route_suppression,
            'param' => $param,
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_heure_travaille_delete', methods: ['POST'])]
    public function delete(Request $request, SessionInterface $session, HeureTravaille $heureTravaille, Etablissement $etablissement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$heureTravaille->getId(), $request->getPayload()->getString('_token'))) {
            $deleteReason = $request->request->get('delete_reason');
            $information = 'créneau '.($heureTravaille->getEvent()->getStart())->format('d/m/Y à H:i').' '.($heureTravaille->getEvent()->getStart())->format('d/m/Y à H:i');
            // dd($information);
            $historique = new HistoriqueSuppression();
            $historique->setType('horaire') // ou un type plus spécifique
                ->setMotif($deleteReason)
                ->setOrigine('pedagogie')
                ->setDateOperation(new \DateTime())
                ->setInformation($information)
                ->setSaisiePar($this->getUser())
                ->setPromo($session->get('promo'))
                ->setUser($heureTravaille->getEvent()->getEnseignant()->getPersonnel());
                
            $entityManager->persist($historique);
            $entityManager->remove($heureTravaille);
            $entityManager->flush();
        }

        $this->addFlash('success', 'Créneau supprimé avec succès :)');
        return $this->redirectToRoute('app_gandaal_administration_pedagogie_admin_heure_travaillee', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
