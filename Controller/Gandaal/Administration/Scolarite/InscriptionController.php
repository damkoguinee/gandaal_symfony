<?php

namespace App\Controller\Gandaal\Administration\Scolarite;

use App\Entity\Inscription;
use App\Entity\Etablissement;
use App\Form\InscriptionType;
use App\Repository\UserRepository;
use App\Repository\EleveRepository;
use App\Repository\CursusRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\InscriptionRepository;
use App\Repository\PaiementEleveRepository;
use App\Repository\FraisScolariteRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\FraisInscriptionRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ClasseRepartitionRepository;
use App\Repository\FormationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/administration/scolarite/inscription')]
class InscriptionController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_administration_scolarite_inscription_index', methods: ['GET'])]
    public function index(InscriptionRepository $inscriptionRepository, UserRepository $userRep, EntityManagerInterface $em): Response
    {
        return $this->render('gandaal/administration/scolarite/inscription/index.html.twig', [
            'inscriptions' => $inscriptionRepository->findAll(),
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_scolarite_inscription_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Etablissement $etablissement, ClasseRepartitionRepository $classeRepartitionRep, SessionInterface $session, FraisScolariteRepository $fraisScolRep, FraisInscriptionRepository $fraisInsRep, PaiementEleveRepository $paiementRep, InscriptionRepository $inscriptionRep, EleveRepository $eleveRep, EntityManagerInterface $entityManager): Response
    {
        if ($request->get("id_user_search")){
            $search = $eleveRep->find($request->get("id_user_search"));
            $verif_inscription = $inscriptionRep->findOneBy(['eleve' => $search, 'promo' => $session->get('promo')]);

            if ($verif_inscription) {
                $etat = 'inscrit';
            }else{
                $etat = 'non inscrit';
            }         
        }else{
            $search = "";
            $etat = 'non inscrit';
        }
        // $inscriptions = $inscriptionRep->rechercheInscriptionAncienEleveParEtablissementParPromo('gshs21487', $session->get('promo'), $etablissement);    

        // dd($inscriptions);
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $inscriptions = $inscriptionRep->rechercheInscriptionAncienEleveParEtablissementParPromo($search, $session->get('promo'), $etablissement);    
            $response = [];
            foreach ($inscriptions as $inscription) {
                $response[] = [
                    'nom' => ucwords($inscription->getEleve()->getPrenom())." ".strtoupper($inscription->getEleve()->getNom()),
                    'id' => $inscription->getEleve()->getId()
                ]; 
            }
            return new JsonResponse($response);
        }
        $inscription = new Inscription();
        $classes = $classeRepartitionRep->listeDesClassesParEtablissementParPromo($etablissement, $session->get('promo'));
        $form = $this->createForm(InscriptionType::class, $inscription, ['classes' => $classes]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $inscription->setSaisiePar($this->getUser())
                    ->setEleve($eleveRep->find($request->get('id_eleve')))
                    ->setPromo($session->get('promo'))
                    ->setEtablissement($etablissement)
                    ->setType('réinscription')
                    ->setDateInscription(new \DateTime("now"))
                    ->setStatut("actif");
            $entityManager->persist($inscription);
            $entityManager->flush();
            $this->addFlash("success", "Elève reinscrit avec succès :)");
            $id = $inscriptionRep->findMaxId($etablissement);
            return $this->redirectToRoute('app_gandaal_administration_scolarite_eleve_show', ['etablissement' => $etablissement->getId(), 'id' => $id], Response::HTTP_SEE_OTHER);            
        }

        // $inscription = $inscriptionRep->findOneBy(['eleve' => $search, 'promo' => $session->get('promo')]);
        $inscription = $inscriptionRep->derniereInscriptionEleveParEtablissementParPromo($search, $session->get('promo'), $etablissement);
        if ($inscription) {
            $inscription = $inscriptionRep->derniereInscriptionEleveParEtablissementParPromo($search, $session->get('promo'), $etablissement)[0];
        }

        // dd($inscription);
        
        // $tranches = $tranchePaieRep->findBy(['etablissement' => $etablissement, 'promo' => $session->get('promo')]);

        if ($inscription) {
            $remiseIns = $inscription->getRemiseInscription() ? $inscription->getRemiseInscription() : 0;
            $remiseScolarite = $inscription->getRemiseScolarite() ? $inscription->getRemiseScolarite() : 0;
            $classe = $inscription->getClasse();
    
            $frais = $fraisScolRep->findBy(['formation' => $classe->getFormation(), 'promo' => $inscription->getPromo()]);

            $cursus = $classe->getFormation()->getCursus();

            $fraisIns = $fraisInsRep->findOneBy(['cursus' => $cursus, 'description' => $inscription->getType(), 'promo' => $inscription->getPromo()]);

            // $paiements = $paiementRep->findBy(['inscription' => $inscription, 'promo' => $inscription->getPromo()]);
            
            $totalScolarite = $fraisScolRep->montantTotalFraisScolariteParFormation($classe->getFormation(), $inscription->getPromo());

            $totalScolarite = $totalScolarite * (1 - ($remiseScolarite / 100));
            $fraisInscription = $fraisIns ? ($fraisIns->getMontant() * (1 - ($remiseIns / 100))) : 0;
            
            $scolarite_annuel = $totalScolarite + $fraisInscription;

            $reste_scolarite = $paiementRep->resteScolariteEleve($inscription, $inscription->getPromo(), $frais, $remiseScolarite/100);

            $reste_inscription = $fraisInscription - $paiementRep->paiementInscription($inscription, $inscription->getPromo());

            $historiques = $paiementRep->findBy(['inscription' => $inscription, 'promo' => $inscription->getPromo()], ['id' => 'DESC']);
            $cumulPaiements = $paiementRep->cumulPaiementEleveGroupeParType($inscription, $inscription->getPromo());
            // dd($cumulPaiements, $inscription->getPromo());

            $paie_annuel = 0;
            foreach ($cumulPaiements as $key => $value) {
                $paie_annuel += $value['solde'];
            }
            $reste_annuel = $scolarite_annuel - $paie_annuel;
            $dernier_promo = $inscription->getPromo();
            
        }else{
            $frais = [];
            $fraisIns = [];
            $paiements = [];
            $scolarite_annuel = 0;
            $reste_scolarite = [];
            $reste_inscription = 0;
            $historiques = [];
            $cumulPaiements = [];
            $reste_annuel = 0;
            $dernier_promo = [];
        }

        
        
        return $this->render('gandaal/administration/scolarite/inscription/new.html.twig', [
            'inscription' => $inscription,
            'form' => $form,
            'etablissement' => $etablissement,
            'search' => $search,
            'etat' => $etat,
            'inscription' => $inscription,
            'scolarite_annuel' => $scolarite_annuel,
            'reste_scolarite' => $reste_scolarite,
            'reste_inscription' => $reste_inscription,
            'historiques' => $historiques,
            'cumulPaiements' => $cumulPaiements,
            'reste_annuel' => $reste_annuel,
            'dernier_promo' => $dernier_promo,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_administration_scolarite_inscription_show', methods: ['GET'])]
    public function show(Inscription $inscription): Response
    {
        return $this->render('gandaal/administration/scolarite/inscription/show.html.twig', [
            'inscription' => $inscription,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_administration_scolarite_inscription_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Inscription $inscription, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(InscriptionType::class, $inscription);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_scolarite_inscription_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/scolarite/inscription/edit.html.twig', [
            'inscription' => $inscription,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_administration_scolarite_inscription_delete', methods: ['POST'])]
    public function delete(Request $request, Inscription $inscription, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$inscription->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($inscription);
            $entityManager->flush();

            $this->addFlash("success", $inscription->getType().' annulée avec succèes :)');
        }

        if ($request->get('origine') and $request->get('origine') == 'eleve') {
            return $this->redirectToRoute('app_gandaal_administration_scolarite_eleve_index', ['etablissement' => $inscription->getEtablissement()->getId()], Response::HTTP_SEE_OTHER);
            
        }
        $referer = $request->headers->get('referer');
        return $this->redirect($referer);
    }


    
}
