<?php

namespace App\Controller\Gandaal\Administration\Comptabilite\Admin;

use App\Entity\Etablissement;
use App\Entity\TranchePaiement;
use App\Form\TranchePaiementType;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\TranchePaiementRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route('/gandaal/administration/comptabilite/admin/tranche/paiement')]
class TranchePaiementController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_tranche_paiement_index', methods: ['GET'])]
    public function index(TranchePaiementRepository $tranchePaiementRepository, SessionInterface $session, Etablissement $etablissement): Response
    {
        return $this->render('gandaal/administration/comptabilite/admin/tranche_paiement/index.html.twig', [
            'tranche_paiements' => $tranchePaiementRepository->findBy(['promo' => $session->get('promo')]),
            'etablissement' => $etablissement
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_tranche_paiement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, Etablissement $etablissement, SessionInterface $session): Response
    {
        $promo = $session->get('promo');
        $tranchePaiement = new TranchePaiement();
        $form = $this->createForm(TranchePaiementType::class, $tranchePaiement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tranchePaiement->setPromo($promo)
                    ->setEtablissement($etablissement);
            $entityManager->persist($tranchePaiement);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_tranche_paiement_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/comptabilite/admin/tranche_paiement/new.html.twig', [
            'tranche_paiement' => $tranchePaiement,
            'form' => $form,
            'etablissement' => $etablissement
        ]);
    }

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_tranche_paiement_show', methods: ['GET'])]
    public function show(TranchePaiement $tranchePaiement, Etablissement $etablissement): Response
    {
        return $this->render('gandaal/administration/comptabilite/admin/tranche_paiement/show.html.twig', [
            'tranche_paiement' => $tranchePaiement,
            'etablissement' => $etablissement
        ]);
    }

    #[Route('/edit/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_tranche_paiement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TranchePaiement $tranchePaiement, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        $form = $this->createForm(TranchePaiementType::class, $tranchePaiement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_tranche_paiement_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/comptabilite/admin/tranche_paiement/edit.html.twig', [
            'tranche_paiement' => $tranchePaiement,
            'form' => $form,
            'etablissement' => $etablissement
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_tranche_paiement_delete', methods: ['POST'])]
    public function delete(Request $request, TranchePaiement $tranchePaiement, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tranchePaiement->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($tranchePaiement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_tranche_paiement_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
