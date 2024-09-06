<?php

namespace App\Controller\Gandaal\Administration\Comptabilite;

use App\Entity\Recette;
use App\Form\RecetteType;
use App\Entity\LieuxVentes;
use App\Entity\Modification;
use App\Entity\Etablissement;
use App\Entity\MouvementCaisse;
use App\Repository\UserRepository;
use App\Repository\DeviseRepository;
use App\Repository\RecetteRepository;
use App\Repository\EntrepriseRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\InscriptionRepository;
use App\Repository\ComptesDepotRepository;
use App\Repository\ConfigDeviseRepository;
use Symfony\Component\Filesystem\Filesystem;
use App\Repository\CompteOperationRepository;
use App\Repository\MouvementCaisseRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\CategorieRecetteRepository;
use App\Repository\ServicesGenerauxRepository;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\ModifDecaissementRepository;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\CategorieOperationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\String\Slugger\AsciiSlugger;
use App\Repository\ConfigCompteOperationRepository;
use App\Repository\ConfigCategorieOperationRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/administration/comptabilite/recette')]
class RecetteController extends AbstractController
{
    #[Route('/accueil/{etablissement}', name: 'app_gandaal_administration_comptabilite_recette_index', methods: ['GET'])]
    public function index(Request $request, RecetteRepository $recetteRep,CategorieRecetteRepository $categorieRecetteRep, SessionInterface $session,  Etablissement $etablissement, EntrepriseRepository $entrepriseRep): Response
    {
        if ($request->get("categorie")){
            $search = $categorieRecetteRep->find($request->get("categorie"));
        }else{
            $search = "";
        }

        $firstOp = $recetteRep->findOneBy(['promo' => $session->get('promo')], ['dateOperation' => 'ASC']);
        $date1 = $request->get("date1") ? $request->get("date1") : ($firstOp ? $firstOp->getDateOperation()->format('Y-m-d') : $request->get("date1"));
        $date2 = $request->get("date2") ? $request->get("date2") : date("Y-m-d");

        $pageEncours = $request->get('pageEncours', 1);
        if ($request->get("categorie")){
            $recette = $recetteRep->findRecetteByLieuBySearchPaginated($etablissement, $search, $date1, $date2, $pageEncours, 25);

            $cumulRecette = $recetteRep->totalRecetteParPeriodeParLieuParCategorie($etablissement, $search, $date1, $date2);
        }else{
            $recette = $recetteRep->findRecetteByLieuPaginated($etablissement, $date1, $date2, $pageEncours, 25);
            $cumulRecette = $recetteRep->totalRecetteParPeriodeParLieu($etablissement, $date1, $date2);

        }
        return $this->render('gandaal/administration/comptabilite/recette/index.html.twig', [
            'recette' => $recette,
            'entreprise' => $entrepriseRep->find(1),
            'etablissement' => $etablissement,
            'categories' => $categorieRecetteRep->findBy([], ['nom' => 'ASC']),
            'search' => $search,
            'cumulrecettes' => $cumulRecette,
            'date1' => $date1,
            'date2' => $date2,
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_comptabilite_recette_new', methods: ['GET', 'POST'])]
    public function new(Request $request, MouvementCaisseRepository $mouvCaisseRep, SessionInterface $session, EntityManagerInterface $entityManager, Etablissement $etablissement, ConfigCompteOperationRepository $compteOpRep, ConfigCategorieOperationRepository $categorieOpRep, UserRepository $userRep, InscriptionRepository $inscriptionRep, RecetteRepository $recetteRep, EntrepriseRepository $entrepriseRep): Response
    {
        if ($request->get("id_user_search")){
            $search = $userRep->find($request->get("id_user_search"));            
        }else{
            $search = "";
        }


        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $inscriptions = $inscriptionRep->rechercheEleveParEtablissementParPromo($search, $session->get('promo'), $etablissement);    
            $response = [];
            foreach ($inscriptions as $inscription) {
                $response[] = [
                    'nom' => ucwords($inscription->getEleve()->getPrenom())." ".strtoupper($inscription->getEleve()->getNom()),
                    'id' => $inscription->getEleve()->getId()
                ]; 
            }
            return new JsonResponse($response);
        }

        $recette = new Recette();
        $form = $this->createForm(RecetteType::class, $recette, ['etablissement' => $etablissement] );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $montantString = $form->get('montant')->getData();
            $montantString = preg_replace('/[^0-9,.]/', '', $montantString);
            $montant = floatval($montantString);

            $montantString = $form->get('tva')->getData();
            $montantString = preg_replace('/[^0-9,.]/', '', $montantString);
            $montant_tva = floatval($montantString);
            
            $dateDuJour = new \DateTime();
            $referenceDate = $dateDuJour->format('ymd');
            $idSuivant =($recetteRep->findCountId($etablissement, $session->get('promo')) + 1);
            $reference = "rec".$referenceDate . sprintf('%04d', $idSuivant);
            $eleve = $userRep->find($request->get('id_eleve'));
            if ($eleve) {
                $inscriptions = $eleve->getInscriptions();
                foreach ($inscriptions as $value) {
                    if ($value->getPromo() == $session->get('promo')) {
                        $inscription = $value;
                    }
                }
            }
            
            $recette->setSaisiePar($this->getUser())
                    ->setMontant($montant)
                    ->setTva($montant_tva)
                    ->setTaux(1)
                    ->setTypeMouvement('recette')
                    ->setEtablissement($etablissement)
                    ->setEtatOperation('clos')
                    ->setPromo($session->get('promo'))
                    ->setEtablissement($etablissement)
                    ->setReference($reference)
                    ->setDateSaisie(new \DateTime("now"))
                    ->setCategorieOperation($categorieOpRep->find(6))
                    ->setCompteOperation($compteOpRep->find(5))
                    ->setInscription($eleve ? $inscription : null);

            $entityManager->persist($recette);
            $entityManager->flush();
            $this->addFlash("success", "recette ajoutée avec succès :)");
            
            return $this->redirectToRoute('app_gandaal_administration_comptabilite_recette_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
            
            
        }

        return $this->render('gandaal/administration/comptabilite/recette/new.html.twig', [
            'recette' => $recette,
            'form' => $form,
            'entreprise' => $entrepriseRep->find(1),
            'etablissement' => $etablissement,
            'search' => $search
        ]);
    }

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_recette_show', methods: ['GET'])]
    public function show(Recette $recette, Etablissement $etablissement, EntrepriseRepository $entrepriseRep): Response
    {
        // $recette_modif = $modifDecRep->findBy(['recette' => $recette]);
        return $this->render('gandaal/administration/comptabilite/recette/show.html.twig', [
            'recette' => $recette,
            'entreprise' => $entrepriseRep->find(1),
            'etablissement' => $etablissement,
            'recette_modif' =>  [],

        ]);
    }

    #[Route('/edit/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_recette_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Recette $recette, MouvementCaisseRepository $mouvCaisseRep, RecetteRepository $recetteRep, EntityManagerInterface $entityManager, Etablissement $etablissement, EntrepriseRepository $entrepriseRep): Response
    {
        $form = $this->createForm(RecetteType::class, $recette, ['etablissement' => $etablissement] );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $montantString = $form->get('montant')->getData();
            $montantString = preg_replace('/[^0-9,.]/', '', $montantString);
            $montant = floatval($montantString);

            $montantString = $form->get('tva')->getData();
            $montantString = preg_replace('/[^0-9,.]/', '', $montantString);
            $montant_tva = floatval($montantString);

            $caisse = $form->getViewData()->getCaisse();
            $devise = $form->getViewData()->getDevise();
            $solde_caisse = $mouvCaisseRep->findSoldeCaisse($caisse, $devise);
            if ($solde_caisse >= $montant) {
                $recette->setMontant($montant)
                        ->setTva($montant_tva)
                        ->setSaisiePar($this->getUser())
                        ->setDateSaisie(new \DateTime("now"));

                $entityManager->persist($recette);
                $entityManager->flush();
                $this->addFlash("success", "Dépense enregistrée avec succès :)");
                return $this->redirectToRoute('app_gandaal_administration_comptabilite_recette_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
            }else{
                $this->addFlash("warning", "Le montant disponible en caisse est insuffisant");
                // Récupérer l'URL de la page précédente
                $referer = $request->headers->get('referer');
                if ($referer) {
                    $formView = $form->createView();
                    return $this->render('gandaal/administration/comptabilite/recette/edit.html.twig', [
                        'entreprise' => $entrepriseRep->find(1),
                        'etablissement' => $etablissement,
                        'form' => $formView,
                        'recette' => $recette,
                        'referer' => $referer,
                    ]);
                }
            }

        }

        return $this->render('gandaal/administration/comptabilite/recette/edit.html.twig', [
            'recette' => $recette,
            'form' => $form,
            'entreprise' => $entrepriseRep->find(1),
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_recette_delete', methods: ['POST'])]
    public function delete(Request $request, Recette $recette, Filesystem $filesystem, EntityManagerInterface $entityManager, Etablissement $etablissement, EntrepriseRepository $entrepriseRep): Response
    {
        if ($this->isCsrfTokenValid('delete'.$recette->getId(), $request->request->get('_token'))) {
            
            $entityManager->remove($recette);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_comptabilite_recette_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
