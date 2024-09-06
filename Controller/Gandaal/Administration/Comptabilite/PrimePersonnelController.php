<?php

namespace App\Controller\Gandaal\Administration\Comptabilite;

use App\Entity\Etablissement;
use App\Entity\PrimePersonnel;
use App\Form\PrimePersonnelType;
use App\Entity\PaiementActiviteScolaire;
use App\Repository\PersonnelActifRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\PrimePersonnelRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/administration/comptabilite/prime/personnel')]
class PrimePersonnelController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_administration_comptabilite_prime_personnel_index', methods: ['GET'])]
    public function index(PrimePersonnelRepository $primePersonnelRepository): Response
    {
        return $this->render('gandaal/administration/comptabilite/prime_personnel/index.html.twig', [
            'prime_personnels' => $primePersonnelRepository->findAll(),
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_comptabilite_prime_personnel_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Etablissement $etablissement, PrimePersonnelRepository $primePersonnelRep, SessionInterface $session, PersonnelActifRepository $personnelActifRep,  UserRepository $userRep, EntityManagerInterface $entityManager): Response
    {
        if ($request->get("id_user_search")){
            $search = $userRep->find($request->get("id_user_search"));  
        }else{
            $search = "";
        }
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $personnels = $personnelActifRep->rechercheUserParEtablissement($search, $etablissement, $session->get('promo'));    
            $response = [];
            foreach ($personnels as $personnel) {
                $response[] = [
                    'nom' => ucwords($personnel->getPersonnel()->getPrenom())." ".strtoupper($personnel->getPersonnel()->getNom()),
                    'id' => $personnel->getPersonnel()->getId()
                ]; 
            }
            return new JsonResponse($response);
        }

        $personnel = $personnelActifRep->findOneBy(['personnel' => $request->get("id_user_search")]);  
        if ($request->get('prime_personnel')) {
            $periode = $request->get('periode');
            $montantSaisie = floatval(preg_replace('/[^0-9,.]/', '', $request->get('montant')));
            $periodes = $request->get('periodes');
            foreach ($periodes as $item) {
                $periode = (new \DateTime(date("Y") . '-' . $item  . '-01'));
                // dd($periode);
                $prime_verif = $primePersonnelRep->findOneBy(['personnel' => $personnel, 'periode' => $periode, 'promo' => $session->get('promo')]);
                // dd($prime_verif);
                if ($prime_verif) {
                    $prime_verif->setMontant($montantSaisie)
                        ->setSaisiePar($this->getUser())
                        ->setDateSaisie(new \DateTime());
                    $entityManager->persist($prime_verif);

                }else{

                    $prime = new PrimePersonnel();
                    $prime->setPeriode($periode)
                        ->setPersonnel($personnel)
                        ->setMontant($montantSaisie)
                        ->setPromo($session->get('promo'))
                        ->setSaisiePar($this->getUser())
                        ->setDateSaisie(new \DateTime());
                    $entityManager->persist($prime);
                }
            }
            $entityManager->flush();
            $this->addFlash("success", "Prime enregistrée avec succés :) ");
            $referer = $request->headers->get('referer');
            return $this->redirect($referer);

        }

        $primes = $primePersonnelRep->findBy(['personnel' => $personnel, 'promo' => $session->get('promo')],);

        return $this->render('gandaal/administration/comptabilite/prime_personnel/new.html.twig', [
            'personnelActif' => $personnel,
            'etablissement' => $etablissement,
            'search' => $search,
            'dernier_promo' => $session->get('promo'),
            'primes' => $primes,
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_administration_comptabilite_prime_personnel_show', methods: ['GET'])]
    public function show(PrimePersonnel $primePersonnel): Response
    {
        return $this->render('gandaal/administration/comptabilite/prime_personnel/show.html.twig', [
            'prime_personnel' => $primePersonnel,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_administration_comptabilite_prime_personnel_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PrimePersonnel $primePersonnel, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PrimePersonnelType::class, $primePersonnel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_comptabilite_prime_personnel_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/comptabilite/prime_personnel/edit.html.twig', [
            'prime_personnel' => $primePersonnel,
            'form' => $form,
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_prime_personnel_delete', methods: ['POST'])]
    public function delete(Request $request, Etablissement $etablissement, PrimePersonnel $primePersonnel, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$primePersonnel->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($primePersonnel);
            $entityManager->flush();
            $this->addFlash("success", "Prime annulée avec succés :) ");
        }


        $referer = $request->headers->get('referer');
        return $this->redirect($referer);
    }
}
