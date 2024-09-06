<?php

namespace App\Controller\Gandaal\Administration\Scolarite;

use App\Entity\Inscription;
use App\Entity\Etablissement;
use App\Entity\InscriptionActivite;
use App\Form\InscriptionActiviteType;
use App\Repository\ClasseRepartitionRepository;
use App\Repository\ConfigActiviteScolaireRepository;
use App\Repository\EleveRepository;
use App\Repository\FraisInscriptionRepository;
use App\Repository\FraisScolariteRepository;
use App\Repository\InscriptionActiviteRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\InscriptionRepository;
use App\Repository\PaiementEleveRepository;
use App\Repository\TarifActiviteScolaireRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route('/gandaal/administration/scolarite/inscription/activite')]
class InscriptionActiviteController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_administration_scolarite_inscription_activite_index', methods: ['GET'])]
    public function index(Etablissement $etablissement, Request $request, InscriptionActiviteRepository $inscriptionActiviteRep, ConfigActiviteScolaireRepository $activiteRep, SessionInterface $session): Response
    {
        $search = $request->get('search', null);
        
        $search_activite = $request->get('search_activite') ? $activiteRep->find($request->get('search_activite')) : Null;

        $pageEnCours = $request->get('pageEncours', 1);
        
        $inscriptions = $inscriptionActiviteRep->listeDesElevesExterneInscritParPromoParEtablissement($session->get('promo'), $etablissement, $search, $search_activite, $pageEnCours, 35);

        $liste_activites = $activiteRep->findBy(['etablissement' => $etablissement], ['nom' => 'ASC']);

        return $this->render('gandaal/administration/scolarite/inscription_activite/index.html.twig', [
            'inscriptions' => $inscriptions,
            'etablissement' => $etablissement,
            'promo' => $session->get('promo'),
            'liste_activites' => $liste_activites,
            'search_activite' => $search_activite
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_scolarite_inscription_activite_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Etablissement $etablissement, SessionInterface $session, InscriptionActiviteRepository $inscriptionActiviteRep, TarifActiviteScolaireRepository $tarifScolariteRep, UserRepository $userRep, EleveRepository $eleveRep, EntityManagerInterface $entityManager): Response
    {

        if ($request->get("id_user_search")){
            $search = $userRep->find($request->get("id_user_search"));  
        }else{
            $search = "";
        }
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $eleves = $eleveRep->rechercheEleveParEtablissement($search, $etablissement);    
            $response = [];
            foreach ($eleves as $eleve) {
                $response[] = [
                    'nom' => ucwords($eleve->getPrenom())." ".strtoupper($eleve->getNom()),
                    'id' => $eleve->getId()
                ]; 
            }
            return new JsonResponse($response);
        }
        $inscription_activite = new InscriptionActivite();
        $form = $this->createForm(InscriptionActiviteType::class, $inscription_activite);
        $form->handleRequest($request);        

        if ($form->isSubmitted() && $form->isValid()) {
            $tarifs = $request->get('tarifs');
            foreach ($tarifs as $value) {
                $tarif = $tarifScolariteRep->find($value);
                $inscription_activite_final = new InscriptionActivite();

                $inscription_activite_final->setTarifActivite($tarif)
                    ->setPromo($session->get('promo'))
                    ->setEtablissement($etablissement)
                    ->setDateSaisie(new \DateTime("now"))
                    ->setSaisiePar($this->getUser())
                    ->setDateInscription($inscription_activite->getDateInscription())
                    ->setRemise($inscription_activite->getRemise())
                    ->setEleve($search)
                    ->setTypeEleve($search->getCategorie() ? $search->getCategorie() : 'interne')
                    ->setStatut('actif');
                $entityManager->persist($inscription_activite_final);
           }

            $entityManager->flush();
            $this->addFlash("success", "activité ajoutée avec succès :)");
            $referer = $request->headers->get('referer');
            return $this->redirect($referer);
                   
        }
        $eleve = $eleveRep->find($search);

        $inscriptions = $inscriptionActiviteRep->findBy(['eleve' => $search, 'promo' => $session->get('promo')]);
        $tarifs = $tarifScolariteRep->tarifActiviteParEtablissementParPromo($etablissement, $session->get('promo'));
        $tarif_activite_restants = [];

        foreach ($tarifs as  $tarif) {
            $inscription = $inscriptionActiviteRep->findOneBy(['eleve' => $search, 'promo' => $session->get('promo'), 'tarifActivite' => $tarif]);
            if (!$inscription) {
                $tarif_activite_restants [] = $tarif;
            }
        }
        return $this->render('gandaal/administration/scolarite/inscription_activite/new.html.twig', [
            'eleve' => $eleve,
            'form' => $form,
            'etablissement' => $etablissement,
            'search' => $search,
            'inscription_activite' => $inscription_activite,
            'dernier_promo' => $session->get('promo'),
            'tarifs' => $tarif_activite_restants,
            'inscriptions' => $inscriptions,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_administration_scolarite_inscription_activite_show', methods: ['GET'])]
    public function show(InscriptionActivite $inscription): Response
    {
        return $this->render('gandaal/administration/scolarite/inscription_activite/show.html.twig', [
            'inscription' => $inscription,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_administration_scolarite_inscription_activite_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, InscriptionActivite $inscription, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(InscriptionActiviteType::class, $inscription);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_scolarite_inscription_activite_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/scolarite/inscription_activite/edit.html.twig', [
            'inscription' => $inscription,
            'form' => $form,
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_administration_scolarite_inscription_activite_delete', methods: ['POST'])]
    public function delete(Request $request, Etablissement $etablissement, InscriptionActivite $inscription, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$inscription->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($inscription);
            $entityManager->flush();

            $this->addFlash("success", ' Inscription annulée avec succès :)');
        } 
        $referer = $request->headers->get('referer');
        return $this->redirect($referer);
    }
}
