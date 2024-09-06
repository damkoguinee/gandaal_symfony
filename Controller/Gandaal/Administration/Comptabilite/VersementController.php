<?php

namespace App\Controller\Gandaal\Administration\Comptabilite;

use App\Entity\Versement;
use App\Entity\Etablissement;
use App\Form\VersementType;
use App\Entity\Modification;
use App\Entity\MouvementCaisse;
use App\Entity\DeleteDecaissement;
use App\Repository\UserRepository;
use App\Repository\ConfigDeviseRepository;
use App\Entity\MouvementCollaborateur;
use App\Repository\VersementRepository;
use App\Repository\EntrepriseRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\etablissementRepository;
use App\Repository\ModificationRepository;
use App\Repository\ConfigCompteOperationRepository;
use App\Repository\MouvementCaisseRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ConfigCategorieOperationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\MouvementCollaborateurRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route('\gandaal/administration/comptabilite/versement')]
class VersementController extends AbstractController
{
    #[Route('/accueil/{etablissement}', name: 'app_gandaal_administration_comptabilite_versement_index', methods: ['GET'])]
    public function index(VersementRepository $versementRep, SessionInterface $session, UserRepository $userRep, Request $request, etablissement $etablissement): Response
    {
        if ($request->get("id_user_search")){
            $search = $request->get("id_user_search");
        }else{
            $search = "";
        }

        $firstOp = $versementRep->findOneBy(['promo' => $session->get('promo')], ['dateOperation' => 'ASC']);
        $date1 = $request->get("date1") ? $request->get("date1") : ($firstOp ? $firstOp->getDateOperation()->format('Y-m-d') : $request->get("date1"));
        $date2 = $request->get("date2") ? $request->get("date2") : date("Y-m-d");  

        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $users = $userRep->rechercheUserParTypeParEtablissement($search, 'personnel', $etablissement);    
            $response = [];
            foreach ($users as $user) {
                $response[] = [
                    'nom' => ucwords($user->getPrenom())." ".strtoupper($user->getNom()),
                    'id' => $user->getId()
                ]; 
            }
            return new JsonResponse($response);
        }
        $pageEncours = $request->get('pageEncours', 1);
        if ($request->get("id_user_search")){
            $versements = $versementRep->findVersementByEtablissementBySearchPaginated($etablissement, $search, $session->get('promo'), $date1, $date2, $pageEncours, 25);
        }else{
            $versements = $versementRep->findVersementByEtablissementPaginated($etablissement, $session->get('promo'), $date1, $date2, $pageEncours, 25);
        }

        return $this->render('gandaal/administration/comptabilite/versement/index.html.twig', [
            'versements' => $versements,
            'search' => $search,
            
            'etablissement' => $etablissement,
            'date1' => $date1,
            'date2' => $date2,
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_comptabilite_versement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, etablissement $etablissement, VersementRepository $versementRep, ConfigCompteOperationRepository $compteOpRep, ConfigCategorieOperationRepository $catetgorieOpRep, ConfigDeviseRepository $deviseRep, MouvementCollaborateurRepository $mouvementCollabRep, SessionInterface $session, UserRepository $userRep): Response
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $users = $userRep->rechercheUserParTypeParEtablissement($search, 'personnel', $etablissement);    
            $response = [];
            foreach ($users as $user) {
                $response[] = [
                    'nom' => ucwords($user->getPrenom())." ".strtoupper($user->getNom()),
                    'id' => $user->getId()
                ]; 
            }
            return new JsonResponse($response);
        }

        $versement = new Versement();
        $form = $this->createForm(VersementType::class, $versement, ['etablissement' => $etablissement]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $montantString = $form->get('montant')->getData();
            $montantString = preg_replace('/[^0-9,.]/', '', $montantString);
            $montant = floatval($montantString);
            $dateDuJour = new \DateTime();
            $referenceDate = $dateDuJour->format('ymd');
            $idSuivant =($versementRep->findMaxId() + 1);
            $reference = "vers".$referenceDate . sprintf('%04d', $idSuivant);
            $collaborateur = $request->get('id_collaborateur');
            $collaborateur = $userRep->find($collaborateur);

            $categorie_op = $catetgorieOpRep->find(4);
            $compte_op = $compteOpRep->find(4);

            $versement->setEtablissement($etablissement)
                        ->setCollaborateur($collaborateur)
                        ->setSaisiePar($this->getUser())
                        ->setReference($reference)
                        ->setMontant($montant)
                        ->setDateSaisie(new \DateTime("now"))
                        ->setCategorieOperation($categorie_op)
                        ->setCompteOperation($compte_op)
                        ->setTypeMouvement('versement')
                        ->setEtatOperation('clos')
                        ->setPromo($session->get('promo'))
                       ;     

            $taux = $form->getViewData()->getTaux();
            if ($taux == 1) {
                $montant = $montant;
                $devise = $form->getViewData()->getDevise();
            }else{
                $montant = $montant * $taux;
                $devise = $deviseRep->find(1);
            }
            $mouvement_collab = new MouvementCollaborateur();

            $mouvement_collab->setCollaborateur($collaborateur)
                    ->setOrigine("versement")
                    ->setMontant($montant)
                    ->setDevise($devise)
                    ->setEtablissement($etablissement)
                    ->setDateOperation($form->getViewData()->getdateOperation())
                    ->setDateSaisie(new \DateTime("now"));
            $versement->addMouvementCollaborateur($mouvement_collab);
            
            $entityManager->persist($versement);
            $entityManager->flush();

            $this->addFlash("success", "versement enregistré avec succès :)");
            return $this->redirectToRoute('app_gandaal_administration_comptabilite_versement_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        if ($request->get("id_user_search")){
            $client_find = $userRep->find($request->get("id_user_search"));
            $soldes_collaborateur = $mouvementCollabRep->findSoldeCollaborateur($client_find);
        }else{
            $client_find = array();
            $soldes_collaborateur = array();
        }

        return $this->render('gandaal/administration/comptabilite/versement/new.html.twig', [
            'versement' => $versement,
            'form' => $form,
            
            'etablissement' => $etablissement,
            'client_find' => $client_find,
            'soldes_collaborateur' => $soldes_collaborateur,
        ]);
    }

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_versement_show', methods: ['GET'])]
    public function show(Versement $versement, etablissement $etablissement): Response
    {
        $versements_modif = [];
        return $this->render('gandaal/administration/comptabilite/versement/show.html.twig', [
            'versement' => $versement,
            
            'etablissement' => $etablissement,
            'versements_modif' => $versements_modif,
        ]);
    }

    #[Route('/edit/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_versement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Versement $versement, VersementRepository $versementRep, EntityManagerInterface $entityManager, UserRepository $userRep, MouvementCollaborateurRepository $mouvementCollabRep, MouvementCaisseRepository $mouvementCaisseRep, etablissement $etablissement): Response
    {
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $clients = $userRep->findUserSearchByEtablissement($search, $etablissement);    
            $response = [];
            foreach ($clients as $client) {
                $response[] = [
                    'nom' => ucwords($client->getPrenom())." ".strtoupper($client->getNom()),
                    'id' => $client->getId()
                ]; // Mettez à jour avec le nom réel de votre propriété
            }
            return new JsonResponse($response);
        }

        $form = $this->createForm(VersementType::class, $versement, ['etablissement' => $etablissement]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $montantString = $form->get('montant')->getData();
            $montantString = preg_replace('/[^0-9,.]/', '', $montantString);
            $montant = floatval($montantString);
            $collaborateur = $request->get('id_collaborateur');
            $collaborateur = $userRep->find($collaborateur);
            $versement->setMontant($montant)
                        ->setCollaborateur($collaborateur)
                        ->setSaisiePar($this->getUser())
                        ->setDateSaisie(new \DateTime("now"));

            $mouvement_collab = $mouvementCollabRep->findOneBy(['versement' => $versement]); 
            $mouvement_collab->setCollaborateur($collaborateur)
                    ->setMontant($montant)
                    ->setDevise($form->getViewData()->getDevise())
                    ->setEtablissement($etablissement)
                    ->setDateOperation($form->getViewData()->getdateOperation())
                    ->setDateSaisie(new \DateTime("now"));
            $entityManager->persist($versement);
            $entityManager->flush();
            $this->addFlash("success", "Versement modifié avec succès :)");
            return $this->redirectToRoute('app_gandaal_administration_comptabilite_versement_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
            
        }

        if ($request->get("id_user_search")){
            $client_find = $userRep->find($request->get("id_user_search"));
            $soldes_collaborateur = $mouvementCollabRep->findSoldeCollaborateur($client_find);
        }else{
            $client_find = $versement->getCollaborateur();
            $soldes_collaborateur = $mouvementCollabRep->findSoldeCollaborateur($client_find);
        }

        return $this->render('gandaal/administration/comptabilite/versement/edit.html.twig', [
            'versement' => $versement,
            'form' => $form,
            
            'etablissement' => $etablissement,
            'client_find' => $client_find,
            'soldes_collaborateur' => $soldes_collaborateur
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_versement_delete', methods: ['POST'])]
    public function delete(Request $request, Versement $versement, EntityManagerInterface $entityManager, etablissement $etablissement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$versement->getId(), $request->request->get('_token'))) {
            
            $entityManager->remove($versement);
            $entityManager->flush();

            $this->addFlash("success", "versement supprimé avec succès :)");
        }

        return $this->redirectToRoute('app_gandaal_administration_comptabilite_versement_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/pdf/reçu/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_versement_recu_pdf', methods: ['GET'])]
    public function recuPdf(Versement $versement, etablissement $etablissement, MouvementCollaborateurRepository $mouvementCollabRep)
    {
        $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/config/'.$etablissement->getEntreprise()->getLogo();
        $logoBase64 = base64_encode(file_get_contents($logoPath));
        $symbolePath = $this->getParameter('kernel.project_dir') . '/public/images/config/symbole.png';
        $symboleBase64 = base64_encode(file_get_contents($symbolePath));
        $ministerePath = $this->getParameter('kernel.project_dir') . '/public/images/config/ministere.jpg';
        $ministereBase64 = base64_encode(file_get_contents($ministerePath));

        $soleCollaborateur = $mouvementCollabRep->findSoldeCollaborateur($versement->getCollaborateur());

        $collaborateur = $versement->getCollaborateur();
        $dateOp = $versement->getdateOperation();

        $ancienSoleCollaborateur = $mouvementCollabRep->findAncienSoldeCollaborateur($collaborateur, $dateOp);

        $html = $this->renderView('gandaal/administration/comptabilite/versement/recu_pdf.html.twig', [
            'versement' => $versement,
            'solde_collaborateur' => $soleCollaborateur,
            'ancien_solde' => $ancienSoleCollaborateur,
            'etablissement' => $etablissement,
            'logoPath' => $logoBase64,
            'symbolePath' => $symboleBase64,
            'ministerePath' => $ministereBase64,
            // 'qrCode'    => $qrCode,
        ]);

        // Configurez Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set("isPhpEnabled", true);
        $options->set("isHtml5ParserEnabled", true);

        // Instancier Dompdf
        $dompdf = new Dompdf($options);

        // Charger le contenu HTML
        $dompdf->loadHtml($html);

        // Définir la taille du papier (A4 par défaut)
        $dompdf->setPaper('A4', 'portrait');

        // Rendre le PDF (stream le PDF au navigateur)
        $dompdf->render();

        // Renvoyer une réponse avec le contenu du PDF
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="réçu_versement.pdf"',
        ]);
    }
}
